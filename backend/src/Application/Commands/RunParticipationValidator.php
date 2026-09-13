<?php
declare(strict_types=1);

namespace DiceGoblins\Application\Commands;

use DiceGoblins\Application\Queries\UnitNotFoundException;
use DiceGoblins\Application\WarbandIntegrityException;
use DiceGoblins\Content\ContentRegistry;
use DiceGoblins\Content\ContentValidationException;

final class RunParticipationValidator
{
  public function __construct(
    private readonly ContentRegistry $content,
    private readonly UnitConfigurationSupport $unitConfiguration,
  ) {}

  /** @param array<int,array<string,mixed>> $formationRows @return list<int> */
  public function validate(int $userId, array $formationRows): array
  {
    if ($formationRows === []) {
      throw new RunStartException('active_squad_empty', 'The active squad must contain at least one unit.', 422);
    }

    $unitIds = [];
    $seen = [];
    foreach ($formationRows as $row) {
      $unitId = (int)($row['unit_id'] ?? 0);
      $position = $row['position'] ?? null;
      if (!is_int($position) && !is_string($position)) $position = -1;
      $position = (int)$position;
      if (
        $unitId <= 0 || $position < 0 || $position > 8 || isset($seen[$unitId])
        || (int)($row['unit_user_id'] ?? 0) !== $userId
        || ($row['lifecycle_status'] ?? null) !== 'active'
      ) {
        throw new RunStartIntegrityException('Persisted active squad formation is invalid.');
      }

      try {
        $this->content->unitType((string)($row['unit_type_id'] ?? ''));
        $this->content->kin((string)($row['kin_id'] ?? ''));
      } catch (ContentValidationException $e) {
        throw new RunStartIntegrityException('Participating unit references invalid authored content.', 0, $e);
      }

      try {
        $unit = $this->unitConfiguration->lockValidUnit($userId, $unitId);
      } catch (UnitNotFoundException $e) {
        throw new RunStartIntegrityException('Participating unit is unavailable.', 0, $e);
      } catch (WarbandIntegrityException $e) {
        throw new RunStartException('run_configuration_invalid', 'A participating unit configuration is invalid.', 422);
      }
      if (($unit['ability_loadout'] ?? []) === []) {
        throw new RunStartException('run_configuration_invalid', 'A participating unit configuration is invalid.', 422);
      }

      $seen[$unitId] = true;
      $unitIds[] = $unitId;
    }
    return $unitIds;
  }
}
