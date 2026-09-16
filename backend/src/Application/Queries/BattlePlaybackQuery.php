<?php
declare(strict_types=1);

namespace DiceGoblins\Application\Queries;

use DiceGoblins\Domain\Battles\PersistedBattle;
use DiceGoblins\Repositories\BattlePersistenceRepository;
use DiceGoblins\Repositories\PlayerStateRepository;
use Throwable;

final class BattlePlaybackQuery
{
  public function __construct(
    private readonly BattlePersistenceRepository $battles,
    private readonly PlayerStateRepository $playerState,
  ) {}

  /** @return array{battle:array<string,mixed>,player_revision:int} */
  public function execute(int $userId, int $battleId): array
  {
    try {
      $battle = $this->battles->findOwnedById($userId, $battleId);
    } catch (Throwable $e) {
      throw new BattlePlaybackIntegrityException('Persisted battle failed validation.', 0, $e);
    }
    if ($battle === null) throw new BattlePlaybackNotFoundException('Battle is unavailable.');

    try {
      $state = $this->playerState->getPlayerState($userId);
    } catch (Throwable $e) {
      throw new BattlePlaybackIntegrityException('Battle owner state failed validation.', 0, $e);
    }
    if ($state === null) throw new BattlePlaybackIntegrityException('Battle owner state is unavailable.');

    return [
      'battle' => $this->project($battle),
      'player_revision' => (int)$state['player_revision'],
    ];
  }

  /** @return array<string,mixed> */
  private function project(PersistedBattle $persisted): array
  {
    $battle = $persisted->battle;
    $terminalByKey = [];
    foreach ($battle->result['combatants'] as $terminal) $terminalByKey[$terminal['key']] = $terminal;

    $participants = [];
    foreach ($battle->manifestArray() as $manifest) {
      $key = $manifest['combatant_key'];
      $input = $battle->combatInput->combatants[$key] ?? null;
      $terminal = $terminalByKey[$key] ?? null;
      if (!is_array($input) || !is_array($terminal)) {
        throw new BattlePlaybackIntegrityException('Battle participant correspondence is invalid.');
      }
      $terminalStatuses = $terminal['statuses'];
      foreach ($terminalStatuses as &$status) {
        if ($status['params'] === []) $status['params'] = (object)[];
      }
      unset($status);
      $participants[] = [
        'combatant_key' => $key,
        'side' => $manifest['side'],
        'unit_id' => $manifest['unit_id'] === null ? null : (string)$manifest['unit_id'],
        'unit_type_id' => $manifest['unit_type_id'],
        'enemy_unit_type_id' => $manifest['enemy_unit_type_id'],
        'display_name' => $manifest['display_name'],
        'art_key' => $manifest['art_key'],
        'position' => ['x' => $input['position']['x'], 'y' => $input['position']['y']],
        'initial_hp' => $input['current_hp'],
        'max_hp' => $input['max_hp'],
        'terminal_hp' => $terminal['current_hp'],
        'is_defeated' => $terminal['is_defeated'],
        'terminal_statuses' => $terminalStatuses,
      ];
    }

    $events = $battle->result['events'];
    foreach ($events as &$event) {
      if ($event['facts'] === []) {
        $event['facts'] = (object)[];
      } elseif (array_key_exists('params', $event['facts']) && $event['facts']['params'] === []) {
        $event['facts']['params'] = (object)[];
      }
    }
    unset($event);

    return [
      'id' => (string)$persisted->id,
      'run_id' => (string)$persisted->runId,
      'run_node_id' => (string)$persisted->runNodeId,
      'engine_version' => $battle->engineVersion,
      'playback_version' => $battle->playbackVersion,
      'outcome' => $battle->result['outcome'],
      'ending_round' => $battle->result['ending_round'],
      'ending_tick' => $battle->result['ending_tick'],
      'participants' => $participants,
      'events' => $events,
    ];
  }
}
