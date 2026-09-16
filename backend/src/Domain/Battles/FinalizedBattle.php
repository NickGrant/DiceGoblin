<?php
declare(strict_types=1);

namespace DiceGoblins\Domain\Battles;

use DiceGoblins\Combat\Vnext\CombatEngine;
use DiceGoblins\Combat\Vnext\CombatInput;
use DiceGoblins\Combat\Vnext\CombatResult;
use DiceGoblins\Combat\Vnext\CombatRules;
use DiceGoblins\Combat\Vnext\PlaybackRecorder;
use InvalidArgumentException;

/** Strict immutable value boundary for one finalized deterministic battle. */
final class FinalizedBattle
{
  private const PROHIBITED_KEYS = [
    'timestamp', 'timestamps', 'created_at', 'updated_at', 'resolved_at', 'reward', 'rewards', 'reward_id',
    'reward_ids', 'xp', 'experience', 'teeth', 'raw_chaos',
  ];

  public readonly CombatInput $combatInput;
  public readonly BattleParticipantManifest $participantManifest;

  /** @param array<string,mixed> $inputSnapshot @param list<array<string,mixed>> $manifest @param array<string,mixed> $result */
  public function __construct(
    public readonly int $engineVersion,
    public readonly int $playbackVersion,
    public readonly array $inputSnapshot,
    array $manifest,
    public readonly array $result,
  ) {
    if ($engineVersion !== CombatResult::ENGINE_VERSION || $playbackVersion !== CombatResult::PLAYBACK_VERSION) {
      throw new InvalidArgumentException('Battle persistence version is unsupported.');
    }
    self::rejectProhibitedFields($inputSnapshot);
    self::rejectProhibitedFields($result);
    $this->combatInput = new CombatInput($inputSnapshot);
    $this->participantManifest = new BattleParticipantManifest($manifest, $this->combatInput);
    $this->validateResult();
  }

  /** @return list<array<string,mixed>> */
  public function manifestArray(): array { return $this->participantManifest->toArray(); }

  private function validateResult(): void
  {
    CombatInput::keys($this->result, [
      'engine_version', 'playback_version', 'outcome', 'ending_round', 'ending_tick', 'combatants', 'events',
    ]);
    if ($this->result['engine_version'] !== $this->engineVersion || $this->result['playback_version'] !== $this->playbackVersion) {
      throw new InvalidArgumentException('Stored battle versions must match the result payload.');
    }
    if (!in_array($this->result['outcome'], ['victory', 'defeat', 'stalemate'], true)) {
      throw new InvalidArgumentException('Stored battle outcome is invalid.');
    }
    CombatInput::integer($this->result['ending_round'], 0, 200, 'ending_round');
    CombatInput::integer($this->result['ending_tick'], 0, CombatEngine::MAX_TICKS, 'ending_tick');
    self::validateRoundTick($this->result['ending_round'], $this->result['ending_tick']);
    $this->validateTerminalCombatants();
    $this->validateEvents();
  }

  private function validateTerminalCombatants(): void
  {
    $terminal = $this->result['combatants'];
    if (!is_array($terminal) || !array_is_list($terminal)) throw new InvalidArgumentException('Terminal combatants must be a list.');
    $byKey = [];
    $alive = ['player' => false, 'enemy' => false];
    foreach ($terminal as $combatant) {
      CombatInput::keys($combatant, ['key', 'side', 'current_hp', 'max_hp', 'is_defeated', 'statuses']);
      $key = $combatant['key'];
      if (!is_string($key) || !isset($this->combatInput->combatants[$key]) || isset($byKey[$key])) {
        throw new InvalidArgumentException('Terminal combatant keys must uniquely match the input.');
      }
      $input = $this->combatInput->combatants[$key];
      if ($combatant['side'] !== $input['side'] || $combatant['max_hp'] !== $input['max_hp']) {
        throw new InvalidArgumentException('Terminal combatant side and max HP must match the input.');
      }
      CombatInput::integer($combatant['current_hp'], 0, $input['max_hp'], 'terminal current_hp');
      if (!is_bool($combatant['is_defeated']) || $combatant['is_defeated'] !== ($combatant['current_hp'] === 0)) {
        throw new InvalidArgumentException('Terminal defeated state must match current HP.');
      }
      if (!is_array($combatant['statuses']) || !array_is_list($combatant['statuses'])) {
        throw new InvalidArgumentException('Terminal statuses must be a list.');
      }
      $statusIds = [];
      foreach ($combatant['statuses'] as $status) {
        if (!is_array($status)) throw new InvalidArgumentException('Terminal status must be an object.');
        CombatRules::validateStatus($status);
        if (!isset($this->combatInput->combatants[$status['source_key']])
          || ($status['forced_target_key'] !== null && !isset($this->combatInput->combatants[$status['forced_target_key']]))
          || isset($statusIds[$status['id']])) {
          throw new InvalidArgumentException('Terminal status references or identity are invalid.');
        }
        $statusIds[$status['id']] = true;
      }
      if ($combatant['current_hp'] > 0) $alive[$combatant['side']] = true;
      $byKey[$key] = true;
    }
    if (array_diff_key($this->combatInput->combatants, $byKey) !== [] || array_diff_key($byKey, $this->combatInput->combatants) !== []) {
      throw new InvalidArgumentException('Terminal combatants must exactly cover the input.');
    }
    $coherent = match ($this->result['outcome']) {
      'victory' => $alive['player'] && !$alive['enemy'],
      'defeat' => !$alive['player'] && $alive['enemy'],
      'stalemate' => $alive['player'] && $alive['enemy'],
    };
    if (!$coherent) throw new InvalidArgumentException('Outcome does not match terminal combatant state.');
  }

  private function validateEvents(): void
  {
    $events = $this->result['events'];
    if (!is_array($events) || !array_is_list($events) || count($events) < 2) {
      throw new InvalidArgumentException('Playback events must be a non-empty ordered list.');
    }
    $previousTick = -1;
    $startedCount = 0;
    $endedCount = 0;
    foreach ($events as $index => $event) {
      if (!is_array($event)) throw new InvalidArgumentException('Playback event must be an object.');
      PlaybackRecorder::validateEvent($event);
      if ($event['sequence'] !== $index) throw new InvalidArgumentException('Playback event sequence must be contiguous from zero.');
      self::validateRoundTick($event['round'], $event['tick']);
      if ($event['tick'] < $previousTick || $event['tick'] > $this->result['ending_tick']) {
        throw new InvalidArgumentException('Playback event ticks must be ordered within the battle ending tick.');
      }
      $this->validateEventReferences($event);
      if ($event['type'] === 'battle_started') $startedCount++;
      if ($event['type'] === 'battle_ended') $endedCount++;
      $previousTick = $event['tick'];
    }
    $first = $events[0];
    $expectedKeys = array_keys($this->combatInput->combatants);
    $startedKeys = $first['facts']['combatant_keys'] ?? null;
    if ($startedCount !== 1 || $endedCount !== 1 || $first['type'] !== 'battle_started' || $first['round'] !== 0 || $first['tick'] !== 0
      || !is_array($startedKeys) || count($startedKeys) !== count(array_unique($startedKeys))
      || array_diff($expectedKeys, $startedKeys) !== [] || array_diff($startedKeys, $expectedKeys) !== []) {
      throw new InvalidArgumentException('Playback must start with the exact input combatants.');
    }
    $last = $events[count($events) - 1];
    if ($last['type'] !== 'battle_ended' || $last['round'] !== $this->result['ending_round']
      || $last['tick'] !== $this->result['ending_tick'] || $last['facts']['outcome'] !== $this->result['outcome']) {
      throw new InvalidArgumentException('Playback battle end must match the stored result ending facts.');
    }
  }

  /** @param array<string,mixed> $event */
  private function validateEventReferences(array $event): void
  {
    foreach (['actor_key', 'target_key', 'source_key', 'combatant_key', 'forced_target_key'] as $field) {
      $value = $event['facts'][$field] ?? null;
      if ($value !== null && !isset($this->combatInput->combatants[$value])) {
        throw new InvalidArgumentException('Playback event references an unknown combatant.');
      }
    }
  }

  private static function validateRoundTick(int $round, int $tick): void
  {
    $expected = $tick === 0 ? 0 : intdiv($tick - 1, CombatEngine::TICKS_PER_ROUND) + 1;
    if ($round !== $expected) throw new InvalidArgumentException('Battle round and tick are incoherent.');
  }

  private static function rejectProhibitedFields(mixed $value): void
  {
    if (!is_array($value)) return;
    foreach ($value as $key => $child) {
      if (is_string($key) && in_array(strtolower($key), self::PROHIBITED_KEYS, true)) {
        throw new InvalidArgumentException('Deterministic battle payload contains a prohibited field.');
      }
      self::rejectProhibitedFields($child);
    }
  }
}
