<?php
declare(strict_types=1);

namespace DiceGoblins\Domain\Battles;

use DiceGoblins\Combat\Vnext\CombatInput;
use InvalidArgumentException;

/** Durable identity/presentation facts kept outside the deterministic kernel. */
final class BattleParticipantManifest
{
  /** @var list<array<string,mixed>> */
  private array $entries;

  /** @param list<array<string,mixed>> $entries */
  public function __construct(array $entries, CombatInput $input)
  {
    if (!array_is_list($entries)) throw new InvalidArgumentException('Participant manifest must be a list.');
    $byKey = [];
    $playerUnitIds = [];
    foreach ($entries as $entry) {
      CombatInput::keys($entry, [
        'combatant_key', 'side', 'unit_id', 'unit_type_id', 'enemy_unit_type_id', 'display_name', 'art_key',
      ]);
      $key = $entry['combatant_key'];
      $side = $entry['side'];
      if (!is_string($key) || !isset($input->combatants[$key]) || isset($byKey[$key])) {
        throw new InvalidArgumentException('Manifest combatant keys must uniquely match the input.');
      }
      if (!is_string($side) || $side !== $input->combatants[$key]['side']) {
        throw new InvalidArgumentException('Manifest side must match the input combatant.');
      }
      self::boundedIdentity($entry['display_name'], 128, 'display name');
      self::boundedIdentity($entry['art_key'], 128, 'art key');
      if ($side === 'player') {
        if (!is_int($entry['unit_id']) || $entry['unit_id'] < 1 || isset($playerUnitIds[$entry['unit_id']])) {
          throw new InvalidArgumentException('Player manifest unit IDs must be positive and unique.');
        }
        if (!is_string($entry['unit_type_id']) || preg_match('/^unit_type\.[a-z0-9][a-z0-9_.-]*$/', $entry['unit_type_id']) !== 1
          || $entry['enemy_unit_type_id'] !== null) {
          throw new InvalidArgumentException('Player manifest type identity is invalid.');
        }
        $playerUnitIds[$entry['unit_id']] = true;
      } elseif ($side === 'enemy') {
        if ($entry['unit_id'] !== null || $entry['unit_type_id'] !== null
          || !is_string($entry['enemy_unit_type_id'])
          || preg_match('/^enemy_unit_type\.[a-z0-9][a-z0-9_.-]*$/', $entry['enemy_unit_type_id']) !== 1) {
          throw new InvalidArgumentException('Enemy manifest identity is invalid.');
        }
      } else {
        throw new InvalidArgumentException('Manifest side is invalid.');
      }
      $byKey[$key] = $entry;
    }
    if (array_diff_key($input->combatants, $byKey) !== [] || array_diff_key($byKey, $input->combatants) !== []) {
      throw new InvalidArgumentException('Participant manifest must cover every input combatant exactly once.');
    }
    $this->entries = $entries;
  }

  /** @return list<array<string,mixed>> */
  public function toArray(): array { return $this->entries; }

  private static function boundedIdentity(mixed $value, int $max, string $name): void
  {
    if (!is_string($value) || trim($value) === '' || strlen($value) > $max) {
      throw new InvalidArgumentException("Manifest {$name} must be non-empty and at most {$max} bytes.");
    }
  }
}
