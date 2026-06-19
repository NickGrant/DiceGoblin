<?php
declare(strict_types=1);

namespace DiceGoblins\Services;

use PDO;
use RuntimeException;

final class RunGraphGenerator
{
  /** @var array<string,array<string,int|bool>> */
  private const REGION_CONFIG = [
    'mountains' => [
      'spine_middle_nodes' => 5,
      'dead_ends_min' => 1,
      'dead_ends_max' => 1,
      'reconnect_min' => 0,
      'reconnect_max' => 1,
      'long_reconnect_min' => 1,
      'long_reconnect_max' => 2,
      'wide_fork_min' => 0,
      'wide_fork_max' => 0,
      'rest_weight' => 1,
      'loot_weight' => 2,
      'combat_weight' => 5,
    ],
    'swamps' => [
      'spine_middle_nodes' => 5,
      'dead_ends_min' => 3,
      'dead_ends_max' => 4,
      'reconnect_min' => 0,
      'reconnect_max' => 1,
      'long_reconnect_min' => 0,
      'long_reconnect_max' => 0,
      'wide_fork_min' => 1,
      'wide_fork_max' => 2,
      'rest_weight' => 2,
      'loot_weight' => 2,
      'combat_weight' => 3,
    ],
  ];

  private int $rngCounter = 0;

  public function __construct(
    private readonly PDO $pdo,
  ) {}

  /**
   * @return array{nodes: array<int,array<string,mixed>>, edges: array<int,array{from:int,to:int}>}
   */
  public function generate(int $regionId, string $regionSlug, string $seed): array
  {
    if ($regionSlug === 'the_farm') {
      return $this->generateFarm($regionId);
    }

    return $this->generateProcedural($regionId, $regionSlug, $seed);
  }

  /**
   * @return array{nodes: array<int,array<string,mixed>>, edges: array<int,array{from:int,to:int}>}
   */
  public function generateFarm(int $regionId): array
  {
    $templateIds = $this->loadEncounterTemplateIdsBySlug($regionId, [
      'the_farm_mud_combat_1',
      'the_farm_loot_1',
      'the_farm_rest_1',
      'the_farm_mud_boss_1',
    ]);

    $graph = [
      'nodes' => [
        ['node_index' => 0, 'node_type' => 'combat', 'status' => 'available', 'encounter_template_id' => $templateIds['the_farm_mud_combat_1'] ?? null, 'meta' => ['col' => 0, 'row' => 1]],
        ['node_index' => 1, 'node_type' => 'loot', 'status' => 'locked', 'encounter_template_id' => $templateIds['the_farm_loot_1'] ?? null, 'meta' => ['col' => 1, 'row' => 1]],
        ['node_index' => 2, 'node_type' => 'rest', 'status' => 'locked', 'encounter_template_id' => $templateIds['the_farm_rest_1'] ?? null, 'meta' => ['col' => 2, 'row' => 1]],
        ['node_index' => 3, 'node_type' => 'boss', 'status' => 'locked', 'encounter_template_id' => $templateIds['the_farm_mud_boss_1'] ?? null, 'meta' => ['col' => 3, 'row' => 1]],
        ['node_index' => 4, 'node_type' => 'exit', 'status' => 'locked', 'meta' => ['col' => 4, 'row' => 1]],
      ],
      'edges' => [
        ['from' => 0, 'to' => 1],
        ['from' => 1, 'to' => 2],
        ['from' => 2, 'to' => 3],
        ['from' => 3, 'to' => 4],
      ],
    ];

    $this->validateGraph($graph['nodes'], $graph['edges']);

    return $graph;
  }

  /**
   * @return array{nodes: array<int,array<string,mixed>>, edges: array<int,array{from:int,to:int}>}
   */
  public function generateProcedural(int $regionId, string $regionSlug, string $seed): array
  {
    $config = self::REGION_CONFIG[$regionSlug] ?? [
      'spine_middle_nodes' => 5,
      'dead_ends_min' => 1,
      'dead_ends_max' => 2,
      'reconnect_min' => 1,
      'reconnect_max' => 1,
      'long_reconnect_min' => 0,
      'long_reconnect_max' => 0,
      'wide_fork_min' => 0,
      'wide_fork_max' => 1,
      'rest_weight' => 2,
      'loot_weight' => 2,
      'combat_weight' => 3,
    ];

    $this->rngCounter = 0;
    $seedKey = sprintf('%s|%d|%s', $regionSlug, $regionId, $seed);

    $spineMiddleNodes = (int)$config['spine_middle_nodes'];
    $nodes = [];
    $edges = [];
    /** @var array<string,bool> $occupied */
    $occupied = [];
    /** @var array<int,int> $spineNodeIndexes */
    $spineNodeIndexes = [];

    $startIndex = $this->appendNode($nodes, $occupied, 'combat', 'available', 0, 1);
    $spineNodeIndexes[] = $startIndex;

    for ($column = 1; $column <= $spineMiddleNodes; $column++) {
      $nodeType = $this->pickSpineNodeType($seedKey, $column, $spineMiddleNodes, $config);
      $spineNodeIndexes[] = $this->appendNode($nodes, $occupied, $nodeType, 'locked', $column, 1);
    }

    $bossColumn = $spineMiddleNodes + 1;
    $exitColumn = $spineMiddleNodes + 2;
    $bossIndex = $this->appendNode($nodes, $occupied, 'boss', 'locked', $bossColumn, 1);
    $exitIndex = $this->appendNode($nodes, $occupied, 'exit', 'locked', $exitColumn, 1);
    $spineNodeIndexes[] = $bossIndex;
    $spineNodeIndexes[] = $exitIndex;

    for ($index = 0; $index < count($spineNodeIndexes) - 1; $index++) {
      $edges[] = ['from' => $spineNodeIndexes[$index], 'to' => $spineNodeIndexes[$index + 1]];
    }

    $deadEndsTarget = $this->randBetween($seedKey, (int)$config['dead_ends_min'], (int)$config['dead_ends_max']);
    $reconnectTarget = $this->randBetween($seedKey, (int)$config['reconnect_min'], (int)$config['reconnect_max']);
    $longReconnectTarget = $this->randBetween($seedKey, (int)$config['long_reconnect_min'], (int)$config['long_reconnect_max']);
    $wideForkTarget = $this->randBetween($seedKey, (int)$config['wide_fork_min'], (int)$config['wide_fork_max']);

    $deadEndParents = $this->pickDistinctSpineParents($seedKey, $spineMiddleNodes, $deadEndsTarget);
    foreach ($deadEndParents as $parentOffset) {
      $parentIndex = $spineNodeIndexes[$parentOffset];
      $parentCol = (int)$nodes[$parentIndex]['meta']['col'];
      $childCol = $parentCol + 1;
      $row = $this->pickDeadEndRow($seedKey, $nodes, $edges, $occupied, $parentIndex, $childCol);
      if ($row === null) {
        continue;
      }

      $deadEndType = $this->pickDeadEndType($seedKey);
      $childIndex = $this->appendNode($nodes, $occupied, $deadEndType, 'locked', $childCol, $row);
      $edges[] = ['from' => $parentIndex, 'to' => $childIndex];
    }

    $reconnectParents = $this->pickDistinctSpineParents($seedKey . '|reconnect', max(0, $spineMiddleNodes - 1), $reconnectTarget);
    foreach ($reconnectParents as $parentOffset) {
      $this->addShortReconnectBranch($seedKey, $config, $nodes, $edges, $occupied, $spineNodeIndexes, $parentOffset);
    }

    $longReconnectParents = $this->pickDistinctSpineParents($seedKey . '|long-reconnect', max(0, $spineMiddleNodes - 2), $longReconnectTarget);
    foreach ($longReconnectParents as $parentOffset) {
      $this->addLongReconnectBranch($seedKey, $config, $nodes, $edges, $occupied, $spineNodeIndexes, $parentOffset);
    }

    $wideForkParents = $this->pickDistinctSpineParents($seedKey . '|wide-fork', $spineMiddleNodes, $wideForkTarget);
    foreach ($wideForkParents as $parentOffset) {
      $this->addWideForkDeadEnd($seedKey, $nodes, $edges, $occupied, $spineNodeIndexes, $parentOffset);
    }

    $this->ensureAtLeastOneRestNode($nodes, $seedKey);
    $nodes = $this->assignEncounterTemplates($regionId, $nodes, $seedKey);
    $this->validateGraph($nodes, $edges);

    return ['nodes' => $nodes, 'edges' => $edges];
  }

  /**
   * @param array{nodes:array<int,array<string,mixed>>,edges:array<int,array{from:int,to:int}>} $graph
   * @return array{nodes:array<int,array<string,mixed>>,edges:array<int,array{from:int,to:int}>}
   */
  public function applyTreasureSenseReveal(int $regionId, array $graph, string $seed, float $revealChance): array
  {
    if ($revealChance <= 0.0) {
      return $graph;
    }

    $roll = $this->randBetween($seed . '|treasure-sense-roll', 1, 10000) / 10000;
    if ($roll > $revealChance) {
      return $graph;
    }

    $nodes = $graph['nodes'];
    $edges = $graph['edges'];
    $occupied = [];
    $exitCol = -1;
    foreach ($nodes as $node) {
      $meta = is_array($node['meta'] ?? null) ? $node['meta'] : [];
      $col = (int)($meta['col'] ?? -1);
      $row = (int)($meta['row'] ?? -1);
      if ($col >= 0 && $row >= 0) {
        $occupied[$col . ':' . $row] = true;
      }
      if ((string)($node['node_type'] ?? '') === 'exit') {
        $exitCol = max($exitCol, $col);
      }
    }

    $candidates = [];
    foreach ($nodes as $node) {
      $nodeIndex = (int)($node['node_index'] ?? -1);
      $nodeType = (string)($node['node_type'] ?? '');
      $meta = is_array($node['meta'] ?? null) ? $node['meta'] : [];
      $col = (int)($meta['col'] ?? -1);
      if ($nodeIndex < 0 || $col < 0 || in_array($nodeType, ['boss', 'exit'], true) || ($col + 1) >= $exitCol) {
        continue;
      }

      $row = $this->pickDeadEndRow($seed . '|treasure-sense|' . $nodeIndex, $nodes, $edges, $occupied, $nodeIndex, $col + 1);
      if ($row === null) {
        continue;
      }

      $candidates[] = [
        'parent_index' => $nodeIndex,
        'child_col' => $col + 1,
        'child_row' => $row,
      ];
    }

    if ($candidates === []) {
      return $graph;
    }

    $lootPool = $this->loadEncounterTemplatePools($regionId)['loot'] ?? [];
    if ($lootPool === []) {
      return $graph;
    }

    $pickIndex = $this->randBetween($seed . '|treasure-sense-parent', 0, count($candidates) - 1);
    $picked = $candidates[$pickIndex];
    $newNodeIndex = $this->appendNode($nodes, $occupied, 'loot', 'locked', (int)$picked['child_col'], (int)$picked['child_row']);
    $nodes[$newNodeIndex]['meta']['revealed_by_treasure_sense'] = true;
    $nodes[$newNodeIndex]['meta']['hidden_treasure'] = true;
    $nodes[$newNodeIndex]['meta']['treasure_sense_chance'] = round($revealChance, 4);
    $templateIndex = $this->randBetween($seed . '|treasure-sense-template', 0, count($lootPool) - 1);
    $nodes[$newNodeIndex]['encounter_template_id'] = $lootPool[$templateIndex];
    $edges[] = ['from' => (int)$picked['parent_index'], 'to' => $newNodeIndex];

    $this->validateGraph($nodes, $edges);

    return [
      'nodes' => $nodes,
      'edges' => $edges,
    ];
  }

  /**
   * @return array{combat:array<int,int>,boss:array<int,int>,loot:array<int,int>,rest:array<int,int>}
   */
  public function loadEncounterTemplatePools(int $regionId): array
  {
    $stmt = $this->pdo->prepare('SELECT `id`, `slug`, `reward_profile_json` FROM `encounter_templates` WHERE `region_id` = ? ORDER BY `id` ASC');
    $stmt->execute([$regionId]);

    $pools = [
      'combat' => [],
      'boss' => [],
      'loot' => [],
      'rest' => [],
    ];

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
      $id = (int)$row['id'];
      $slug = (string)$row['slug'];

      $rewardType = null;
      $rewardProfile = json_decode((string)($row['reward_profile_json'] ?? ''), true);
      if (is_array($rewardProfile) && isset($rewardProfile['type'])) {
        $rewardType = (string)$rewardProfile['type'];
      }

      if (str_contains($slug, '_boss_')) {
        $pools['boss'][] = $id;
        continue;
      }
      if (str_contains($slug, '_combat_')) {
        $pools['combat'][] = $id;
        continue;
      }
      if ($rewardType === 'loot' || str_contains($slug, '_loot_')) {
        $pools['loot'][] = $id;
        continue;
      }
      if ($rewardType === 'rest' || str_contains($slug, '_rest_')) {
        $pools['rest'][] = $id;
      }
    }

    return $pools;
  }

  /**
   * @param array<int,array<string,mixed>> $nodes
   * @return array<int,array<string,mixed>>
   */
  public function assignEncounterTemplates(int $regionId, array $nodes, string $seed): array
  {
    $pools = $this->loadEncounterTemplatePools($regionId);

    foreach ($nodes as $index => $node) {
      $nodeType = (string)($node['node_type'] ?? '');
      if ($nodeType === 'exit') {
        continue;
      }

      $pool = $pools[$nodeType] ?? [];
      if ($pool === []) {
        $nodes[$index]['encounter_template_id'] = null;
        continue;
      }

      $poolIndex = $this->randBetween($seed . '|template|' . $index . '|' . $nodeType, 0, count($pool) - 1);
      $nodes[$index]['encounter_template_id'] = $pool[$poolIndex];
    }

    return $nodes;
  }

  /**
   * @param array<int,array<string,mixed>> $nodes
   * @param array<int,array{from:int,to:int}> $edges
   */
  public function validateGraph(array $nodes, array $edges): void
  {
    if ($nodes === []) {
      throw new RuntimeException('Run graph must contain nodes.');
    }

    $nodeIndexes = [];
    $incoming = [];
    $outgoing = [];
    $availableStarts = [];
    $bossIndexes = [];
    $exitIndexes = [];
    $maxCol = -1;

    foreach ($nodes as $node) {
      $nodeIndex = (int)($node['node_index'] ?? -1);
      if ($nodeIndex < 0) {
        throw new RuntimeException('Run graph node indexes must be non-negative.');
      }
      if (isset($nodeIndexes[$nodeIndex])) {
        throw new RuntimeException('Run graph node indexes must be unique.');
      }

      $meta = is_array($node['meta'] ?? null) ? $node['meta'] : null;
      $col = is_array($meta) ? (int)($meta['col'] ?? -1) : -1;
      $row = is_array($meta) ? (int)($meta['row'] ?? -1) : -1;
      if ($col < 0 || $row < 0 || $row > 2) {
        throw new RuntimeException('Run graph nodes must include valid col/row metadata.');
      }
      $maxCol = max($maxCol, $col);

      $nodeIndexes[$nodeIndex] = $node;
      $incoming[$nodeIndex] = [];
      $outgoing[$nodeIndex] = [];

      if ((string)($node['status'] ?? '') === 'available') {
        $availableStarts[] = $nodeIndex;
      }
      if ((string)($node['node_type'] ?? '') === 'boss') {
        $bossIndexes[] = $nodeIndex;
      }
      if ((string)($node['node_type'] ?? '') === 'exit') {
        $exitIndexes[] = $nodeIndex;
      }
    }

    if (count($availableStarts) !== 1) {
      throw new RuntimeException('Run graph must contain exactly one available starting node.');
    }
    if (count($bossIndexes) !== 1) {
      throw new RuntimeException('Run graph must contain exactly one boss node.');
    }
    if (count($exitIndexes) !== 1) {
      throw new RuntimeException('Run graph must contain exactly one exit node.');
    }

    $edgeKeys = [];
    foreach ($edges as $edge) {
      $from = (int)($edge['from'] ?? -1);
      $to = (int)($edge['to'] ?? -1);
      if (!isset($nodeIndexes[$from], $nodeIndexes[$to])) {
        throw new RuntimeException('Run graph edges must point to valid nodes.');
      }
      if ($from === $to) {
        throw new RuntimeException('Run graph may not contain self edges.');
      }

      $fromCol = (int)$nodeIndexes[$from]['meta']['col'];
      $toCol = (int)$nodeIndexes[$to]['meta']['col'];
      if ($toCol <= $fromCol) {
        throw new RuntimeException('Run graph edges must always point forward.');
      }

      $edgeKey = $from . '->' . $to;
      if (isset($edgeKeys[$edgeKey])) {
        throw new RuntimeException('Run graph may not contain duplicate edges.');
      }
      $edgeKeys[$edgeKey] = true;

      $outgoing[$from][] = $to;
      $incoming[$to][] = $from;
    }

    $startIndex = $availableStarts[0];
    foreach (array_keys($nodeIndexes) as $nodeIndex) {
      if ($nodeIndex === $startIndex) {
        continue;
      }
      if ($incoming[$nodeIndex] === []) {
        throw new RuntimeException('Every non-start node must have at least one incoming edge.');
      }
    }

    $reachableFromStart = $this->reachableNodeIndexes($startIndex, $outgoing);
    $bossIndex = $bossIndexes[0];
    $exitIndex = $exitIndexes[0];
    $bossCol = (int)$nodeIndexes[$bossIndex]['meta']['col'];
    $exitCol = (int)$nodeIndexes[$exitIndex]['meta']['col'];

    if ($exitCol !== $maxCol) {
      throw new RuntimeException('Exit must be the right-most node in the run graph.');
    }
    if ($bossCol !== $exitCol - 1) {
      throw new RuntimeException('Boss must appear immediately before the exit column.');
    }

    foreach ($nodeIndexes as $nodeIndex => $node) {
      if ($nodeIndex === $exitIndex) {
        continue;
      }
      if ((int)$node['meta']['col'] > $bossCol) {
        throw new RuntimeException('No non-exit nodes may appear to the right of the boss.');
      }
    }

    if (!isset($reachableFromStart[$bossIndex])) {
      throw new RuntimeException('Boss must be reachable from the start node.');
    }
    if (!isset($reachableFromStart[$exitIndex])) {
      throw new RuntimeException('Exit must be reachable from the start node.');
    }

    $reachableFromBoss = $this->reachableNodeIndexes($bossIndex, $outgoing);
    if (!isset($reachableFromBoss[$exitIndex])) {
      throw new RuntimeException('Exit must be reachable from the boss node.');
    }

    foreach (array_keys($nodeIndexes) as $nodeIndex) {
      if (!isset($reachableFromStart[$nodeIndex])) {
        throw new RuntimeException('All run graph nodes must be reachable from the start node.');
      }
    }

    foreach ($nodes as $node) {
      $nodeIndex = (int)$node['node_index'];
      if (($outgoing[$nodeIndex] ?? []) !== []) {
        continue;
      }

      $nodeType = (string)$node['node_type'];
      if ($nodeType === 'exit') {
        continue;
      }

      if ($nodeType === 'boss') {
        throw new RuntimeException('Boss nodes may not be dead ends.');
      }

      foreach ($incoming[$nodeIndex] ?? [] as $parentIndex) {
        $alternateChildren = array_values(array_filter(
          $outgoing[$parentIndex] ?? [],
          static fn(int $childIndex): bool => $childIndex !== $nodeIndex,
        ));
        if ($alternateChildren === []) {
          throw new RuntimeException('Dead ends must be optional.');
        }

        $hasBossRoute = false;
        foreach ($alternateChildren as $alternateChild) {
          $reachable = $this->reachableNodeIndexes($alternateChild, $outgoing);
          if (isset($reachable[$bossIndex])) {
            $hasBossRoute = true;
            break;
          }
        }

        if (!$hasBossRoute) {
          throw new RuntimeException('Dead ends must not block access to the boss path.');
        }
      }
    }

    $this->assertNoCrossingEdges($nodes, $edges);
  }

  /**
   * @param array<int,string> $slugs
   * @return array<string,int>
   */
  private function loadEncounterTemplateIdsBySlug(int $regionId, array $slugs): array
  {
    if ($slugs === []) {
      return [];
    }

    $placeholders = implode(',', array_fill(0, count($slugs), '?'));
    $params = array_merge([$regionId], $slugs);
    $stmt = $this->pdo->prepare("
      SELECT `id`, `slug`
      FROM `encounter_templates`
      WHERE `region_id` = ? AND `slug` IN ($placeholders)
      ORDER BY `id` ASC
    ");
    $stmt->execute($params);

    $map = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
      $map[(string)$row['slug']] = (int)$row['id'];
    }

    return $map;
  }

  /**
   * @param array<int,array<string,mixed>> $nodes
   * @param array<string,bool> $occupied
   */
  private function appendNode(array &$nodes, array &$occupied, string $nodeType, string $status, int $col, int $row): int
  {
    $positionKey = $col . ':' . $row;
    if (isset($occupied[$positionKey])) {
      throw new RuntimeException('Run graph attempted to reuse a map position.');
    }

    $nodeIndex = count($nodes);
    $nodes[] = [
      'node_index' => $nodeIndex,
      'node_type' => $nodeType,
      'status' => $status,
      'meta' => ['col' => $col, 'row' => $row],
    ];
    $occupied[$positionKey] = true;

    return $nodeIndex;
  }

  private function pickSpineNodeType(string $seedKey, int $column, int $spineMiddleNodes, array $config): string
  {
    if ($column === 1 || $column === $spineMiddleNodes) {
      return 'combat';
    }

    $weights = [
      'combat' => (int)$config['combat_weight'],
      'loot' => (int)$config['loot_weight'],
      'rest' => (int)$config['rest_weight'],
    ];

    return $this->pickWeightedType($seedKey . '|spine-node|' . $column, $weights);
  }

  private function pickDeadEndType(string $seedKey): string
  {
    return $this->pickWeightedType($seedKey . '|dead-end', [
      'loot' => 60,
      'rest' => 25,
      'combat' => 15,
    ]);
  }

  /**
   * @param array<string,int|bool> $config
   */
  private function pickReconnectType(string $seedKey, array $config): string
  {
    return $this->pickWeightedType($seedKey . '|reconnect', [
      'combat' => max(1, (int)$config['combat_weight']),
      'loot' => max(1, (int)$config['loot_weight']),
      'rest' => max(1, (int)$config['rest_weight']),
    ]);
  }

  /**
   * @param array<string,int> $weights
   */
  private function pickWeightedType(string $seedKey, array $weights): string
  {
    $total = array_sum($weights);
    $roll = $this->randBetween($seedKey, 1, $total);
    $running = 0;

    foreach ($weights as $type => $weight) {
      $running += $weight;
      if ($roll <= $running) {
        return $type;
      }
    }

    return array_key_first($weights) ?? 'combat';
  }

  /**
   * @param array<string,int|bool> $config
   * @param array<int,array<string,mixed>> $nodes
   * @param array<int,array{from:int,to:int}> $edges
   * @param array<string,bool> $occupied
   * @param array<int,int> $spineNodeIndexes
   */
  private function addShortReconnectBranch(
    string $seedKey,
    array $config,
    array &$nodes,
    array &$edges,
    array &$occupied,
    array $spineNodeIndexes,
    int $parentOffset,
  ): void {
    $parentIndex = $spineNodeIndexes[$parentOffset];
    $parentCol = (int)$nodes[$parentIndex]['meta']['col'];
    $childCol = $parentCol + 1;
    $reconnectTargetIndex = $spineNodeIndexes[$parentOffset + 2];
    $row = $this->pickShortReconnectRow($seedKey, $nodes, $edges, $occupied, $parentIndex, $childCol, $reconnectTargetIndex);
    if ($row === null) {
      return;
    }

    $branchType = $this->pickReconnectType($seedKey . '|short-type', $config);
    $branchIndex = $this->appendNode($nodes, $occupied, $branchType, 'locked', $childCol, $row);
    $edges[] = ['from' => $parentIndex, 'to' => $branchIndex];
    $edges[] = ['from' => $branchIndex, 'to' => $reconnectTargetIndex];
  }

  /**
   * @param array<string,int|bool> $config
   * @param array<int,array<string,mixed>> $nodes
   * @param array<int,array{from:int,to:int}> $edges
   * @param array<string,bool> $occupied
   * @param array<int,int> $spineNodeIndexes
   */
  private function addLongReconnectBranch(
    string $seedKey,
    array $config,
    array &$nodes,
    array &$edges,
    array &$occupied,
    array $spineNodeIndexes,
    int $parentOffset,
  ): void {
    $parentIndex = $spineNodeIndexes[$parentOffset];
    $parentCol = (int)$nodes[$parentIndex]['meta']['col'];
    $firstCol = $parentCol + 1;
    $secondCol = $parentCol + 2;

    $reconnectTargetIndex = $spineNodeIndexes[$parentOffset + 3];
    $branchRow = $this->pickLongReconnectRow($seedKey, $nodes, $edges, $occupied, $parentIndex, $firstCol, $secondCol, $reconnectTargetIndex);
    if ($branchRow === null) {
      return;
    }

    $firstType = $this->pickReconnectType($seedKey . '|long-type-a', $config);
    $secondType = $this->pickReconnectType($seedKey . '|long-type-b', $config);
    $firstIndex = $this->appendNode($nodes, $occupied, $firstType, 'locked', $firstCol, $branchRow);
    $secondIndex = $this->appendNode($nodes, $occupied, $secondType, 'locked', $secondCol, $branchRow);

    $edges[] = ['from' => $parentIndex, 'to' => $firstIndex];
    $edges[] = ['from' => $firstIndex, 'to' => $secondIndex];
    $edges[] = ['from' => $secondIndex, 'to' => $reconnectTargetIndex];
  }

  /**
   * @param array<int,array<string,mixed>> $nodes
   * @param array<int,array{from:int,to:int}> $edges
   * @param array<string,bool> $occupied
   * @param array<int,int> $spineNodeIndexes
   */
  private function addWideForkDeadEnd(
    string $seedKey,
    array &$nodes,
    array &$edges,
    array &$occupied,
    array $spineNodeIndexes,
    int $parentOffset,
  ): void {
    $parentIndex = $spineNodeIndexes[$parentOffset];
    $parentCol = (int)$nodes[$parentIndex]['meta']['col'];
    $childCol = $parentCol + 1;
    $rows = $this->pickWideForkRows($seedKey, $nodes, $edges, $occupied, $parentIndex, $childCol);
    if ($rows === null) {
      return;
    }
    [$firstRow, $secondRow] = $rows;

    $firstIndex = $this->appendNode($nodes, $occupied, $this->pickDeadEndType($seedKey . '|wide-type-a'), 'locked', $childCol, $firstRow);
    $secondIndex = $this->appendNode($nodes, $occupied, $this->pickDeadEndType($seedKey . '|wide-type-b'), 'locked', $childCol, $secondRow);

    $edges[] = ['from' => $parentIndex, 'to' => $firstIndex];
    $edges[] = ['from' => $parentIndex, 'to' => $secondIndex];
  }

  /**
   * @param array<string,bool> $occupied
   */
  private function isBranchLaneOpen(array $occupied, array $columns, int $row): bool
  {
    foreach ($columns as $col) {
      if (isset($occupied[$col . ':' . $row])) {
        return false;
      }
    }

    return true;
  }

  /**
   * @return array<int,int>
   */
  private function orderedBranchLanes(string $seedKey): array
  {
    $rows = [0, 2];
    if ($this->randBetween($seedKey . '|lane-order', 0, 1) === 1) {
      $rows = array_reverse($rows);
    }

    return $rows;
  }

  /**
   * @param array<int,array<string,mixed>> $nodes
   * @param array<int,array{from:int,to:int}> $edges
   * @param array<string,bool> $occupied
   */
  private function pickDeadEndRow(
    string $seedKey,
    array $nodes,
    array $edges,
    array $occupied,
    int $parentIndex,
    int $childCol,
  ): ?int {
    $parentMeta = $nodes[$parentIndex]['meta'];
    foreach ($this->orderedBranchLanes($seedKey . '|dead-end-row') as $row) {
      if (!$this->isBranchLaneOpen($occupied, [$childCol], $row)) {
        continue;
      }

      if ($this->proposedSegmentsCrossExisting($nodes, $edges, [[
        'from' => ['col' => (int)$parentMeta['col'], 'row' => (int)$parentMeta['row']],
        'to' => ['col' => $childCol, 'row' => $row],
      ]])) {
        continue;
      }

      return $row;
    }

    return null;
  }

  /**
   * @param array<int,array<string,mixed>> $nodes
   * @param array<int,array{from:int,to:int}> $edges
   * @param array<string,bool> $occupied
   */
  private function pickShortReconnectRow(
    string $seedKey,
    array $nodes,
    array $edges,
    array $occupied,
    int $parentIndex,
    int $childCol,
    int $reconnectTargetIndex,
  ): ?int {
    $parentMeta = $nodes[$parentIndex]['meta'];
    $targetMeta = $nodes[$reconnectTargetIndex]['meta'];
    foreach ($this->orderedBranchLanes($seedKey . '|short-row') as $row) {
      if (!$this->isBranchLaneOpen($occupied, [$childCol], $row)) {
        continue;
      }

      if ($this->proposedSegmentsCrossExisting($nodes, $edges, [
        [
          'from' => ['col' => (int)$parentMeta['col'], 'row' => (int)$parentMeta['row']],
          'to' => ['col' => $childCol, 'row' => $row],
        ],
        [
          'from' => ['col' => $childCol, 'row' => $row],
          'to' => ['col' => (int)$targetMeta['col'], 'row' => (int)$targetMeta['row']],
        ],
      ])) {
        continue;
      }

      return $row;
    }

    return null;
  }

  /**
   * @param array<int,array<string,mixed>> $nodes
   * @param array<int,array{from:int,to:int}> $edges
   * @param array<string,bool> $occupied
   */
  private function pickLongReconnectRow(
    string $seedKey,
    array $nodes,
    array $edges,
    array $occupied,
    int $parentIndex,
    int $firstCol,
    int $secondCol,
    int $reconnectTargetIndex,
  ): ?int {
    $parentMeta = $nodes[$parentIndex]['meta'];
    $targetMeta = $nodes[$reconnectTargetIndex]['meta'];
    foreach ($this->orderedBranchLanes($seedKey . '|long-row') as $row) {
      if (!$this->isBranchLaneOpen($occupied, [$firstCol, $secondCol], $row)) {
        continue;
      }

      if ($this->proposedSegmentsCrossExisting($nodes, $edges, [
        [
          'from' => ['col' => (int)$parentMeta['col'], 'row' => (int)$parentMeta['row']],
          'to' => ['col' => $firstCol, 'row' => $row],
        ],
        [
          'from' => ['col' => $firstCol, 'row' => $row],
          'to' => ['col' => $secondCol, 'row' => $row],
        ],
        [
          'from' => ['col' => $secondCol, 'row' => $row],
          'to' => ['col' => (int)$targetMeta['col'], 'row' => (int)$targetMeta['row']],
        ],
      ])) {
        continue;
      }

      return $row;
    }

    return null;
  }

  /**
   * @param array<int,array<string,mixed>> $nodes
   * @param array<int,array{from:int,to:int}> $edges
   * @param array<string,bool> $occupied
   * @return array{0:int,1:int}|null
   */
  private function pickWideForkRows(
    string $seedKey,
    array $nodes,
    array $edges,
    array $occupied,
    int $parentIndex,
    int $col,
  ): ?array
  {
    if (!$this->isBranchLaneOpen($occupied, [$col], 0) || !$this->isBranchLaneOpen($occupied, [$col], 2)) {
      return null;
    }

    $rows = [0, 2];
    if ($this->randBetween($seedKey . '|wide-order', 0, 1) === 1) {
      $rows = array_reverse($rows);
    }

    $parentMeta = $nodes[$parentIndex]['meta'];
    if ($this->proposedSegmentsCrossExisting($nodes, $edges, [
      [
        'from' => ['col' => (int)$parentMeta['col'], 'row' => (int)$parentMeta['row']],
        'to' => ['col' => $col, 'row' => $rows[0]],
      ],
      [
        'from' => ['col' => (int)$parentMeta['col'], 'row' => (int)$parentMeta['row']],
        'to' => ['col' => $col, 'row' => $rows[1]],
      ],
    ])) {
      return null;
    }

    return $rows;
  }

  /**
   * @return array<int,int>
   */
  private function pickDistinctSpineParents(string $seedKey, int $spineMiddleNodes, int $count): array
  {
    $candidates = range(0, max(0, $spineMiddleNodes - 1));
    $picked = [];

    while ($count > 0 && $candidates !== []) {
      $pickIndex = $this->randBetween($seedKey . '|parent-pick|' . $count, 0, count($candidates) - 1);
      $picked[] = $candidates[$pickIndex];
      array_splice($candidates, $pickIndex, 1);
      $count--;
    }

    sort($picked);
    return $picked;
  }

  /**
   * @param array<int,array<string,mixed>> $nodes
   */
  private function ensureAtLeastOneRestNode(array &$nodes, string $seedKey): void
  {
    foreach ($nodes as $node) {
      if ((string)($node['node_type'] ?? '') === 'rest') {
        return;
      }
    }

    $candidates = [];
    foreach ($nodes as $index => $node) {
      $nodeType = (string)($node['node_type'] ?? '');
      $status = (string)($node['status'] ?? '');
      if (!in_array($nodeType, ['combat', 'loot'], true) || $status === 'available') {
        continue;
      }

      $meta = is_array($node['meta'] ?? null) ? $node['meta'] : [];
      $col = (int)($meta['col'] ?? -1);
      if ($col <= 0) {
        continue;
      }

      $candidates[] = $index;
    }

    if ($candidates === []) {
      return;
    }

    usort($candidates, function (int $leftIndex, int $rightIndex) use ($nodes): int {
      $leftMeta = is_array($nodes[$leftIndex]['meta'] ?? null) ? $nodes[$leftIndex]['meta'] : [];
      $rightMeta = is_array($nodes[$rightIndex]['meta'] ?? null) ? $nodes[$rightIndex]['meta'] : [];
      $leftCol = (int)($leftMeta['col'] ?? 0);
      $rightCol = (int)($rightMeta['col'] ?? 0);

      if ($leftCol !== $rightCol) {
        return $leftCol <=> $rightCol;
      }

      return ((int)($leftMeta['row'] ?? 1)) <=> ((int)($rightMeta['row'] ?? 1));
    });

    $pickIndex = $this->randBetween($seedKey . '|force-rest', 0, count($candidates) - 1);
    $nodes[$candidates[$pickIndex]]['node_type'] = 'rest';
  }

  /**
   * @param array<int,array<int,int>> $outgoing
   * @return array<int,bool>
   */
  private function reachableNodeIndexes(int $startIndex, array $outgoing): array
  {
    $queue = [$startIndex];
    $visited = [];

    while ($queue !== []) {
      $current = array_shift($queue);
      if ($current === null || isset($visited[$current])) {
        continue;
      }

      $visited[$current] = true;
      foreach ($outgoing[$current] ?? [] as $next) {
        if (!isset($visited[$next])) {
          $queue[] = $next;
        }
      }
    }

    return $visited;
  }

  private function randBetween(string $seedKey, int $min, int $max): int
  {
    if ($min >= $max) {
      return $min;
    }

    $material = hash('sha256', $seedKey . '|rng|' . $this->rngCounter++);
    $value = hexdec(substr($material, 0, 8));
    return $min + ($value % (($max - $min) + 1));
  }

  /**
   * @param array<int,array<string,mixed>> $nodes
   * @param array<int,array{from:int,to:int}> $edges
   * @param array<int,array{from:array{col:int,row:int},to:array{col:int,row:int}}> $proposedSegments
   */
  private function proposedSegmentsCrossExisting(array $nodes, array $edges, array $proposedSegments): bool
  {
    $nodeByIndex = [];
    foreach ($nodes as $node) {
      $nodeByIndex[(int)$node['node_index']] = $node;
    }

    foreach ($proposedSegments as $segment) {
      $from = $segment['from'];
      $to = $segment['to'];
      foreach ($edges as $edge) {
        $existingFrom = $nodeByIndex[(int)$edge['from']]['meta'];
        $existingTo = $nodeByIndex[(int)$edge['to']]['meta'];
        if ($this->segmentsShareEndpoint(
          (int)$from['col'],
          (int)$from['row'],
          (int)$to['col'],
          (int)$to['row'],
          (int)$existingFrom['col'],
          (int)$existingFrom['row'],
          (int)$existingTo['col'],
          (int)$existingTo['row'],
        )) {
          continue;
        }

        if ($this->segmentsIntersect(
          (int)$from['col'],
          (int)$from['row'],
          (int)$to['col'],
          (int)$to['row'],
          (int)$existingFrom['col'],
          (int)$existingFrom['row'],
          (int)$existingTo['col'],
          (int)$existingTo['row'],
        )) {
          return true;
        }
      }
    }

    $segmentCount = count($proposedSegments);
    for ($leftIndex = 0; $leftIndex < $segmentCount; $leftIndex++) {
      $left = $proposedSegments[$leftIndex];
      for ($rightIndex = $leftIndex + 1; $rightIndex < $segmentCount; $rightIndex++) {
        $right = $proposedSegments[$rightIndex];
        if ($this->segmentsShareEndpoint(
          (int)$left['from']['col'],
          (int)$left['from']['row'],
          (int)$left['to']['col'],
          (int)$left['to']['row'],
          (int)$right['from']['col'],
          (int)$right['from']['row'],
          (int)$right['to']['col'],
          (int)$right['to']['row'],
        )) {
          continue;
        }

        if ($this->segmentsIntersect(
          (int)$left['from']['col'],
          (int)$left['from']['row'],
          (int)$left['to']['col'],
          (int)$left['to']['row'],
          (int)$right['from']['col'],
          (int)$right['from']['row'],
          (int)$right['to']['col'],
          (int)$right['to']['row'],
        )) {
          return true;
        }
      }
    }

    return false;
  }

  /**
   * @param array<int,array<string,mixed>> $nodes
   * @param array<int,array{from:int,to:int}> $edges
   */
  private function assertNoCrossingEdges(array $nodes, array $edges): void
  {
    $nodeByIndex = [];
    foreach ($nodes as $node) {
      $nodeByIndex[(int)$node['node_index']] = $node;
    }

    $edgeCount = count($edges);
    for ($leftIndex = 0; $leftIndex < $edgeCount; $leftIndex++) {
      $leftEdge = $edges[$leftIndex];
      for ($rightIndex = $leftIndex + 1; $rightIndex < $edgeCount; $rightIndex++) {
        $rightEdge = $edges[$rightIndex];
        if (
          $leftEdge['from'] === $rightEdge['from']
          || $leftEdge['from'] === $rightEdge['to']
          || $leftEdge['to'] === $rightEdge['from']
          || $leftEdge['to'] === $rightEdge['to']
        ) {
          continue;
        }

        $a = $nodeByIndex[$leftEdge['from']]['meta'];
        $b = $nodeByIndex[$leftEdge['to']]['meta'];
        $c = $nodeByIndex[$rightEdge['from']]['meta'];
        $d = $nodeByIndex[$rightEdge['to']]['meta'];

        if ($this->segmentsIntersect(
          (int)$a['col'],
          (int)$a['row'],
          (int)$b['col'],
          (int)$b['row'],
          (int)$c['col'],
          (int)$c['row'],
          (int)$d['col'],
          (int)$d['row'],
        )) {
          throw new RuntimeException(sprintf(
            'Run graph edges may not visually cross (%d->%d [%d,%d]->[%d,%d] x %d->%d [%d,%d]->[%d,%d]).',
            (int)$leftEdge['from'],
            (int)$leftEdge['to'],
            (int)$a['col'],
            (int)$a['row'],
            (int)$b['col'],
            (int)$b['row'],
            (int)$rightEdge['from'],
            (int)$rightEdge['to'],
            (int)$c['col'],
            (int)$c['row'],
            (int)$d['col'],
            (int)$d['row'],
          ));
        }
      }
    }
  }

  private function segmentsIntersect(
    int $ax,
    int $ay,
    int $bx,
    int $by,
    int $cx,
    int $cy,
    int $dx,
    int $dy,
  ): bool {
    $o1 = $this->orientation($ax, $ay, $bx, $by, $cx, $cy);
    $o2 = $this->orientation($ax, $ay, $bx, $by, $dx, $dy);
    $o3 = $this->orientation($cx, $cy, $dx, $dy, $ax, $ay);
    $o4 = $this->orientation($cx, $cy, $dx, $dy, $bx, $by);

    if ($o1 !== $o2 && $o3 !== $o4) {
      return true;
    }

    if ($o1 === 0 && $this->onSegment($ax, $ay, $bx, $by, $cx, $cy)) {
      return true;
    }
    if ($o2 === 0 && $this->onSegment($ax, $ay, $bx, $by, $dx, $dy)) {
      return true;
    }
    if ($o3 === 0 && $this->onSegment($cx, $cy, $dx, $dy, $ax, $ay)) {
      return true;
    }
    if ($o4 === 0 && $this->onSegment($cx, $cy, $dx, $dy, $bx, $by)) {
      return true;
    }

    return false;
  }

  private function segmentsShareEndpoint(
    int $ax,
    int $ay,
    int $bx,
    int $by,
    int $cx,
    int $cy,
    int $dx,
    int $dy,
  ): bool {
    return ($ax === $cx && $ay === $cy)
      || ($ax === $dx && $ay === $dy)
      || ($bx === $cx && $by === $cy)
      || ($bx === $dx && $by === $dy);
  }

  private function orientation(int $ax, int $ay, int $bx, int $by, int $cx, int $cy): int
  {
    $value = (($by - $ay) * ($cx - $bx)) - (($bx - $ax) * ($cy - $by));
    if ($value === 0) {
      return 0;
    }

    return $value > 0 ? 1 : 2;
  }

  private function onSegment(int $ax, int $ay, int $bx, int $by, int $px, int $py): bool
  {
    return $px >= min($ax, $bx)
      && $px <= max($ax, $bx)
      && $py >= min($ay, $by)
      && $py <= max($ay, $by);
  }
}
