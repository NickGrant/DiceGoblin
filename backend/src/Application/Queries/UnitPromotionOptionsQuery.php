<?php
declare(strict_types=1);

namespace DiceGoblins\Application\Queries;

use DiceGoblins\Application\Commands\ActiveRunConfigurationPolicy;
use DiceGoblins\Application\UnitPromotionPolicy;
use DiceGoblins\Application\WarbandIntegrityException;
use DiceGoblins\Repositories\PlayerStateRepository;
use DiceGoblins\Repositories\WarbandUnitRepository;
use DiceGoblins\Support\ClientSafeInteger;

final class UnitPromotionOptionsQuery
{
  public function __construct(
    private readonly WarbandUnitRepository $units,
    private readonly PlayerStateRepository $players,
    private readonly UnitPromotionPolicy $promotions,
    private readonly ActiveRunConfigurationPolicy $activeRuns,
  ) {}

  /** @return array<string,mixed> */
  public function execute(int $userId, int $unitId): array
  {
    $unit = $this->units->getActiveForUser($userId, $unitId);
    if ($unit === null) throw new UnitNotFoundException('Unit is unavailable.');
    $state = $this->players->getPlayerState($userId);
    if ($state === null) throw new WarbandIntegrityException('Required player state is unavailable.');
    $level = (int)$unit['level']; $xp = (int)$unit['xp'];
    $rawChaos = $state['raw_chaos']; $revision = $state['player_revision'];
    if ($rawChaos < 0 || $rawChaos > ClientSafeInteger::MAXIMUM || $revision < 0 || $revision > ClientSafeInteger::MAXIMUM) {
      throw new WarbandIntegrityException('Player progression state is incoherent.');
    }
    $threshold = $this->promotions->xpThreshold($level, $xp);
    if ($threshold > ClientSafeInteger::MAXIMUM) throw new WarbandIntegrityException('Unit XP threshold is unsafe.');
    $unitTypeId = (string)$unit['unit_type_id'];
    $this->promotions->validateHistory($unitTypeId, $this->units->listPromotions($unitId));
    $ownedAbilities = [];
    foreach ($this->units->listOwnedAbilities($unitId) as $row) $ownedAbilities[] = (string)$row['ability_id'];
    $locked = $this->activeRuns->isUnitConfigurationLocked($userId, $unitId);
    $options = [];
    foreach ($this->promotions->outgoing($unitTypeId) as $promotion) {
      $price = $promotion['price']['amount'];
      $levelMet = $this->promotions->levelMet($promotion['id'], $level);
      $options[] = ['promotion_id' => $promotion['id'], 'target_unit_type_id' => $promotion['to_unit_type_id'],
        'required_level' => $promotion['required_level'],
        'price' => ['currency_id' => 'raw_chaos', 'amount' => $price],
        'level_met' => $levelMet, 'can_afford' => $rawChaos >= $price,
        'available' => $levelMet && !$locked,
        'new_ability_ids' => $this->promotions->targetAbilityDelta($promotion['id'], $ownedAbilities)];
    }
    return ['unit_id' => (string)$unit['id'], 'unit_type_id' => $unitTypeId, 'level' => $level, 'xp' => $xp,
      'xp_to_next_level' => $threshold, 'raw_chaos' => $rawChaos, 'player_revision' => $revision,
      'configuration_locked' => $locked, 'options' => $options];
  }
}
