<?php
declare(strict_types=1);

/**
 * File: C:\xampp\htdocs\dice-goblin\backend\src\Services\ProfileService.php
 * Purpose: Project PHP module.
 */

namespace DiceGoblins\Services;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use DiceGoblins\Core\Db;
use DiceGoblins\Repositories\DiceRepository;
use DiceGoblins\Repositories\PlayerStateRepository;
use DiceGoblins\Repositories\RegionRepository;
use DiceGoblins\Repositories\RunRepository;
use DiceGoblins\Repositories\TeamRepository;
use DiceGoblins\Repositories\UnitRepository;

final class ProfileService
{
  private PDO $pdo;

  public function __construct(
    private readonly EnergyService $energyService,
    private readonly ProfileDtoMapper $profileDtoMapper,
    private readonly ObjectiveService $objectiveService,

    private readonly PlayerStateRepository $playerStateRepo,
    private readonly TeamRepository $teamRepo,
    private readonly UnitRepository $unitRepo,
    private readonly DiceRepository $diceRepo,
    private readonly RegionRepository $regionRepo,
    private readonly RunRepository $runRepo,

    ?PDO $pdo = null,
  ) {
    // ProfileService is read-heavy; a direct PDO handle is useful for any
    // small join queries we haven’t formalized into repositories yet.
    $this->pdo = $pdo ?? Db::pdo();
  }

  /**
   * Hydrates the player profile for GET /api/v1/profile.
   *
   * @return array<string,mixed>
   */
  public function getProfile(int $userId): array
  {

    // Apply regen as part of profile hydration so the client always sees “fresh” energy.
    $energy = $this->energyService->regenIfNeeded($userId);

    // Currency
    $currency = $this->playerStateRepo->getCurrency($userId);
    $featureUnlocks = (new UserUnlockService($this->pdo))
      ->listUnlockedKeys($userId, UserUnlockService::NAMESPACE_FEATURE);
    $unitTypeUnlocks = (new UserUnlockService($this->pdo))
      ->listUnlockedKeys($userId, UserUnlockService::NAMESPACE_UNIT_TYPE);
    $seenDialogues = (new UserUnlockService($this->pdo))
      ->listUnlockedKeys($userId, UserUnlockService::NAMESPACE_DIALOGUE);
    $squadUnitCap = SquadCapacityService::resolveCapFromFeatureUnlocks($featureUnlocks);

    // Squads/Teams (membership + formation)
    $teams = $this->teamRepo->getTeamsWithMembershipAndFormationForUser($userId);


    // Units (with equipped dice)
    $units = $this->unitRepo->getUnitsWithEquippedDiceForUser($userId);

    // Dice inventory (with affixes + base definition data)
    $dice = $this->diceRepo->getDiceWithAffixesForUser($userId);
    $dice = $this->applyEconomyModifiersToDice($dice, $featureUnlocks);

    // Region unlocks
    $regions = $this->regionRepo->listRegionsWithUserState($userId);
    $regionUnlocks = $this->regionRepo->getUnlocksForUser($userId);

    // Region items (small join; you did not create RegionItemRepository, so we keep this here for now)
    $regionItems = $this->getRegionItemsForUser($userId);

    // Active run (if any)
    $activeRun = $this->runRepo->getActiveRunForUser($userId);
    if ($activeRun !== null) {
      $activeRun = $this->decorateActiveRun($activeRun);
      $units = $this->applyActiveRunUnitHealth($units, (int)$activeRun['run_id'], $userId);
    }
    $objectives = $this->objectiveService->listProfileObjectives($teams, $units, $regions, $squadUnitCap, $activeRun);

    return $this->profileDtoMapper->mapProfilePayload(
      $this->nowIsoUtc(),
      $teams,
      $units,
      $dice,
      $currency,
      $energy,
      $squadUnitCap,
      $featureUnlocks,
      $unitTypeUnlocks,
      $seenDialogues,
      $regions,
      $regionUnlocks,
      $regionItems,
      $activeRun,
      $objectives
    );
  }

  // -----------------------------
  // Internals
  // -----------------------------

  private function nowIsoUtc(): string
  {
    $dt = new DateTimeImmutable('now', new DateTimeZone('UTC'));
    return $dt->format('Y-m-d\TH:i:s.v\Z');
  }

  /**
   * @param array<string,mixed> $activeRun
   * @return array<string,mixed>
   */
  private function decorateActiveRun(array $activeRun): array
  {
    $regionId = (int)($activeRun['region_id'] ?? 0);
    if ($regionId <= 0) {
      return $activeRun;
    }

    $region = $this->regionRepo->getRegionById($regionId);
    if ($region === null) {
      return $activeRun;
    }

    $activeRun['region_slug'] = (string)$region['slug'];
    $activeRun['region_name'] = (string)$region['name'];
    $activeRun['region_theme'] = (string)$region['theme'];
    $activeRun['recommended_level'] = (int)$region['recommended_level'];
    $activeRun['energy_cost'] = (int)$region['energy_cost'];

    return $activeRun;
  }

  /**
   * @return array<int, array{region_item_id:string,quantity:int}>
   */
  private function getRegionItemsForUser(int $userId): array
  {
    // Schema tables:
    // - user_region_items (user_id, region_item_id, quantity)
    // - region_items (id, slug, name, region_id)
    $stmt = $this->pdo->prepare('
      SELECT ri.`slug` AS `region_item_slug`, uri.`quantity`
      FROM `user_region_items` uri
      JOIN `region_items` ri ON ri.`id` = uri.`region_item_id`
      WHERE uri.`user_id` = ?
        AND uri.`quantity` > 0
      ORDER BY ri.`slug` ASC
    ');
    $stmt->execute([$userId]);

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return array_map(static fn(array $r): array => [
      'region_item_id' => (string)$r['region_item_slug'], // slug is the client-facing id
      'quantity' => (int)$r['quantity'],
    ], $rows);
  }

  /**
   * @param array<int,array<string,mixed>> $units
   * @return array<int,array<string,mixed>>
   */
  private function applyActiveRunUnitHealth(array $units, int $runId, int $userId): array
  {
    if (count($units) === 0) {
      return $units;
    }

    $stmt = $this->pdo->prepare(' 
      SELECT rus.`unit_instance_id`, rus.`current_hp`, rus.`is_defeated`
      FROM `run_unit_state` rus
      JOIN `unit_instances` ui ON ui.`id` = rus.`unit_instance_id`
      WHERE rus.`run_id` = ? AND ui.`user_id` = ?
    ');
    $stmt->execute([$runId, $userId]);

    $hpByUnitId = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
      $id = (string)$row['unit_instance_id'];
      $hpByUnitId[$id] = [
        'hp' => max(0, (int)$row['current_hp']),
        'is_defeated' => ((int)$row['is_defeated']) === 1,
      ];
    }

    if (count($hpByUnitId) === 0) {
      return $units;
    }

    foreach ($units as &$unit) {
      $unitId = (string)($unit['id'] ?? '');
      if ($unitId === '' || !isset($hpByUnitId[$unitId])) {
        continue;
      }
      $snapshot = $hpByUnitId[$unitId];
      $maxHp = max(1, (int)($unit['max_hp'] ?? 1));
      $currentHp = $snapshot['is_defeated'] ? 0 : min($maxHp, (int)$snapshot['hp']);
      $unit['current_hp'] = $currentHp;
      $unit['locked'] = true;
    }
    unset($unit);

    return $units;
  }

  /**
   * @param array<int,array<string,mixed>> $dice
   * @param array<int,string> $featureUnlocks
   * @return array<int,array<string,mixed>>
   */
  private function applyEconomyModifiersToDice(array $dice, array $featureUnlocks): array
  {
    foreach ($dice as &$die) {
      $die['sell_value'] = EconomyModifierService::adjustedSellValue(
        max(1, (int)($die['sell_value'] ?? 0)),
        $featureUnlocks
      );
    }
    unset($die);

    return $dice;
  }
}
