<?php
declare(strict_types=1);

namespace DiceGoblins\Application\Queries;

use DateTimeImmutable;
use DateTimeZone;
use DiceGoblins\Content\ContentRegistry;
use DiceGoblins\Content\ContentValidationException;
use DiceGoblins\Repositories\PlayerStateRepository;
use DiceGoblins\Repositories\RunPersistenceRepository;
use DiceGoblins\Repositories\RunModifierRepository;
use DiceGoblins\Repositories\UserItemRepository;
use JsonException;

final class CurrentRunQuery
{
  private const NODE_STATUSES = ['locked', 'available', 'completed'];

  public function __construct(
    private readonly RunPersistenceRepository $runs,
    private readonly PlayerStateRepository $playerState,
    private readonly ContentRegistry $content,
    private readonly RunModifierRepository $modifiers,
    private readonly ?UserItemRepository $items = null,
  ) {}

  /** @return array{run:?array<string,mixed>,player_revision:int} */
  public function execute(int $userId): array
  {
    $state = $this->playerState->getPlayerState($userId);
    if ($state === null) throw new CurrentRunIntegrityException('Required player state is missing.');
    $root = $this->runs->findActiveRunForUser($userId);
    if ($root === null) return ['run' => null, 'player_revision' => (int)$state['player_revision']];
    $this->validateRoot($root, $userId);
    $runId = (int)$root['id'];
    $nodes = $this->mapNodes($this->runs->listNodes($runId), $runId, (string)$root['region_id'], $userId);
    $edges = $this->mapEdges($this->runs->listEdges($runId), $runId, $nodes);
    $units = $this->mapUnits($this->runs->listParticipatingUnits($runId), $runId, $userId);
    $modifiers = [];
    foreach ($this->modifiers->listActive($runId) as $row) {
      $source = array_values(array_filter($nodes, static fn(array $node): bool => $node['id'] === (string)$row['source_node_id']));
      if ((int)$row['id'] < 1 || (int)$row['run_id'] !== $runId || (int)$row['remaining_combats'] !== 1
        || count($source) !== 1 || $source[0]['status'] !== 'completed') {
        throw new CurrentRunIntegrityException('Active run modifier state is invalid.');
      }
      try {
        $definition = $this->content->runModifier((string)$row['modifier_id']);
      } catch (ContentValidationException) {
        throw new CurrentRunIntegrityException('Active run modifier content is invalid.');
      }
      if ($definition['kind'] !== 'next_combat_attack_bonus') {
        throw new CurrentRunIntegrityException('Active run modifier kind is invalid.');
      }
      $modifiers[] = ['id' => (string)$row['id'], 'modifier_id' => $definition['id'],
        'display_name' => $definition['display_name'], 'description' => $definition['description'],
        'remaining_combats' => 1];
    }

    return [
      'run' => [
        'id' => (string)$runId,
        'region_id' => (string)$root['region_id'],
        'squad_id' => (string)$root['squad_id'],
        'status' => 'active',
        'created_at' => $this->utcTimestamp((string)$root['created_at']),
        'nodes' => $nodes,
        'edges' => $edges,
        'available_node_ids' => array_values(array_map(static fn(array $node): string => $node['id'],
          array_filter($nodes, static fn(array $node): bool => $node['status'] === 'available'))),
        'units' => $units,
        ...($modifiers !== [] ? ['modifiers' => $modifiers] : []),
      ],
      'player_revision' => (int)$state['player_revision'],
    ];
  }

  /** @param array<string,mixed> $root */
  private function validateRoot(array $root, int $userId): void
  {
    if ((int)$root['id'] <= 0 || (int)$root['user_id'] !== $userId || (string)$root['status'] !== 'active'
      || $root['squad_id'] === null || (int)$root['squad_id'] <= 0
      || $root['squad_user_id'] === null || (int)$root['squad_user_id'] !== $userId || $root['ended_at'] !== null) {
      throw new CurrentRunIntegrityException('Active run root is invalid.');
    }
    try {
      $this->content->region((string)$root['region_id']);
    } catch (ContentValidationException) {
      throw new CurrentRunIntegrityException('Active run region is invalid.');
    }
  }

  /** @param list<array<string,mixed>> $rows @return list<array<string,mixed>> */
  private function mapNodes(array $rows, int $runId, string $regionId, int $userId): array
  {
    if ($rows === []) throw new CurrentRunIntegrityException('Active run has no nodes.');
    $mapped = [];
    $ids = [];
    foreach ($rows as $expectedIndex => $row) {
      $id = (int)$row['id'];
      $index = (int)$row['node_index'];
      $status = (string)$row['status'];
      if ((int)$row['run_id'] !== $runId || $id <= 0 || isset($ids[$id]) || $index !== $expectedIndex
        || !in_array($status, self::NODE_STATUSES, true)
        || ($status === 'completed') !== ($row['completed_at'] !== null)) {
        throw new CurrentRunIntegrityException('Active run node state is invalid.');
      }
      try {
        $nodeType = $this->content->runNodeType((string)$row['node_type_id']);
        $metadata = json_decode((string)$row['generated_metadata'], true, 16, JSON_THROW_ON_ERROR);
      } catch (ContentValidationException|JsonException) {
        throw new CurrentRunIntegrityException('Active run node content is invalid.');
      }
      if (!is_array($metadata) || !$this->hasExactKeys($metadata, ['position']) || !is_array($metadata['position'])
        || !$this->hasExactKeys($metadata['position'], ['column', 'row'])
        || !is_int($metadata['position']['column']) || !is_int($metadata['position']['row'])
        || abs($metadata['position']['column']) > 9007199254740991 || abs($metadata['position']['row']) > 9007199254740991) {
        throw new CurrentRunIntegrityException('Active run node position is invalid.');
      }
      $battleId = $row['battle_id'] === null ? null : (int)$row['battle_id'];
      $isBattleBearing = in_array((string)$nodeType['id'], ['run_node_type.combat', 'run_node_type.boss'], true);
      if (($battleId !== null && ($battleId <= 0 || $status !== 'completed' || !$isBattleBearing))
        || ($isBattleBearing && $status === 'completed' && $battleId === null)) {
        throw new CurrentRunIntegrityException('Active run battle correspondence is invalid.');
      }
      $ids[$id] = true;
      $projected = [
        'id' => (string)$id, 'node_index' => $index, 'node_type_id' => (string)$row['node_type_id'],
        'status' => $status,
        'completed_at' => $row['completed_at'] !== null ? $this->utcTimestamp((string)$row['completed_at']) : null,
        'battle_id' => $battleId === null ? null : (string)$battleId,
        'position' => ['column' => $metadata['position']['column'], 'row' => $metadata['position']['row']],
      ];
      $expectedEncounterKind = $nodeType['encounter_kind'] ?? null;
      $encounterId = $row['encounter_id'] ?? null;
      $stepId = $row['encounter_step_id'] ?? null;
      if (($expectedEncounterKind === null) !== ($encounterId === null)) {
        throw new CurrentRunIntegrityException('Run node encounter association is invalid.');
      }
      if ($encounterId !== null) {
        try {
          $encounter = $this->content->encounter((string)$encounterId);
        } catch (ContentValidationException) {
          throw new CurrentRunIntegrityException('Run node encounter is invalid.');
        }
        if ($encounter['kind'] !== $expectedEncounterKind || $encounter['region_id'] !== $regionId
          || (isset($nodeType['encounter_family']) && ($encounter['family'] ?? null) !== $nodeType['encounter_family'])) {
          throw new CurrentRunIntegrityException('Run node encounter does not match its run.');
        }
        if ($stepId !== null && ($status !== 'available' || $encounter['kind'] !== 'non_combat'
          || !isset($encounter['steps'][0]) || $encounter['steps'][0]['id'] !== $stepId)) {
          throw new CurrentRunIntegrityException('Run node encounter step is invalid.');
        }
        if ($status === 'available' && $encounter['kind'] === 'non_combat') {
          $stage = $stepId === null ? $encounter : $encounter['steps'][0];
          $actions = $stage['actions'];
          if ($stepId === null && isset($encounter['steps'])) {
            if ($this->items === null) throw new CurrentRunIntegrityException('Encounter inventory authority is unavailable.');
            $owned = array_column($this->items->listPositiveForUser($userId), 'quantity', 'item_id');
            $actions = array_values(array_filter($actions, static fn(array $action): bool =>
              ($action['effect']['type'] ?? null) !== 'advance_step'
              || ($owned[$action['effect']['item_id']] ?? 0) >= $action['effect']['quantity']));
          }
          $projected['interaction'] = [
            'encounter_id' => $encounter['id'], 'title' => $encounter['display_name'],
            'body' => $stage['body'] ?? $encounter['description'], 'prompt' => $stage['prompt'],
            ...($stepId !== null ? ['step_id' => $stepId] : []),
            'actions' => array_map(static fn(array $action): array =>
              ['id' => $action['id'], 'label' => $action['label']], $actions),
          ];
        }
      } elseif ($stepId !== null) {
        throw new CurrentRunIntegrityException('Run node encounter step has no encounter.');
      }
      $mapped[] = $projected;
    }
    return $mapped;
  }

  /** @param list<array<string,mixed>> $rows @param list<array<string,mixed>> $nodes @return list<array<string,string>> */
  private function mapEdges(array $rows, int $runId, array $nodes): array
  {
    $nodeIds = [];
    foreach ($nodes as $node) $nodeIds[(int)$node['id']] = true;
    $seen = [];
    $reachable = [(int)$nodes[0]['id'] => true];
    $mapped = [];
    foreach ($rows as $row) {
      $from = (int)$row['from_node_id'];
      $to = (int)$row['to_node_id'];
      $key = $from . ':' . $to;
      if ((int)$row['run_id'] !== $runId || !isset($nodeIds[$from], $nodeIds[$to]) || $from === $to || isset($seen[$key])) {
        throw new CurrentRunIntegrityException('Active run edge state is invalid.');
      }
      $seen[$key] = true;
      $mapped[] = ['from_node_id' => (string)$from, 'to_node_id' => (string)$to];
    }
    do {
      $changed = false;
      foreach ($mapped as $edge) {
        $from = (int)$edge['from_node_id']; $to = (int)$edge['to_node_id'];
        if (isset($reachable[$from]) && !isset($reachable[$to])) { $reachable[$to] = true; $changed = true; }
      }
    } while ($changed);
    if (count($reachable) !== count($nodeIds)) throw new CurrentRunIntegrityException('Active run graph is disconnected.');
    return $mapped;
  }

  /** @param list<array<string,mixed>> $rows @return list<array<string,mixed>> */
  private function mapUnits(array $rows, int $runId, int $userId): array
  {
    if ($rows === []) throw new CurrentRunIntegrityException('Active run has no participating units.');
    $seen = []; $mapped = [];
    foreach ($rows as $row) {
      $unitId = (int)$row['unit_id'];
      $hp = $row['current_hp'];
      if ((int)$row['run_id'] !== $runId || $unitId <= 0 || isset($seen[$unitId])
        || $row['unit_user_id'] === null || (int)$row['unit_user_id'] !== $userId
        || $hp === null || (int)$hp < 0) {
        throw new CurrentRunIntegrityException('Active run participation is invalid.');
      }
      $seen[$unitId] = true;
      $mapped[] = ['unit_id' => (string)$unitId, 'current_hp' => (int)$hp];
    }
    return $mapped;
  }

  private function utcTimestamp(string $value): string
  {
    return (new DateTimeImmutable($value, new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
  }

  /** @param array<mixed> $value @param list<string> $keys */
  private function hasExactKeys(array $value, array $keys): bool
  {
    $actual = array_keys($value); sort($actual, SORT_STRING); sort($keys, SORT_STRING);
    return $actual === $keys;
  }
}
