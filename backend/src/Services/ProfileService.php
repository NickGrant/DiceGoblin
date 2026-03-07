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
    private readonly PlayerBootstrapper $bootstrapper,
    private readonly EnergyService $energyService,
    private readonly ProfileDtoMapper $profileDtoMapper,

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
    // Ensure the minimum state exists for a logged-in user.
    $this->bootstrapper->ensureBaseline($userId);

    // Apply regen as part of profile hydration so the client always sees “fresh” energy.
    $energy = $this->energyService->regenIfNeeded($userId);

    // Currency
    $currency = $this->playerStateRepo->getCurrency($userId);

    // Squads/Teams (membership + formation)
    $teams = $this->teamRepo->getTeamsWithMembershipAndFormationForUser($userId);

    // Units (with equipped dice)
    $units = $this->unitRepo->getUnitsWithEquippedDiceForUser($userId);

    // Dice inventory (with affixes + base definition data)
    $dice = $this->diceRepo->getDiceWithAffixesForUser($userId);

    // Region unlocks
    //$regionUnlocks = $this->regionRepo->getUnlocksForUser($userId);
    $regionUnlocks = [];

    // Region items (small join; you did not create RegionItemRepository, so we keep this here for now)
    $regionItems = $this->getRegionItemsForUser($userId);

    // Active run (if any)
    $activeRun = $this->runRepo->getActiveRunForUser($userId);

    return $this->profileDtoMapper->mapProfilePayload(
      $this->nowIsoUtc(),
      $teams,
      $units,
      $dice,
      $currency,
      $energy,
      $regionUnlocks,
      $regionItems,
      $activeRun
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
}
