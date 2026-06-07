<?php
declare(strict_types=1);

namespace DiceGoblins\Services;

use DiceGoblins\Core\Env;
use DiceGoblins\Repositories\DiceRepository;
use DiceGoblins\Repositories\PlayerStateRepository;
use DiceGoblins\Repositories\RegionRepository;
use DiceGoblins\Repositories\UnitRepository;
use PDO;
use RuntimeException;
use Throwable;

final class DevToolsService
{
  private ?UnitLoadoutService $unitLoadoutService = null;
  private ?UnitNameGenerator $unitNameGenerator = null;

  public function __construct(
    private readonly PDO $pdo,
    private readonly PlayerBootstrapper $bootstrapper,
    private readonly PlayerStateRepository $playerStateRepo,
    private readonly UnitRepository $unitRepo,
    private readonly DiceRepository $diceRepo,
    private readonly RegionRepository $regionRepo,
    private readonly DiceAffixService $diceAffixService,
  ) {}

  public function isEnabled(): bool
  {
    $flag = strtolower(trim((string) Env::get('ENABLE_DEBUG_ENDPOINTS', '')));
    if (in_array($flag, ['1', 'true', 'yes', 'on'], true)) {
      return true;
    }
    if (in_array($flag, ['0', 'false', 'no', 'off'], true)) {
      return false;
    }

    return strtolower((string) Env::get('APP_ENV', 'dev')) !== 'prod';
  }

  /**
   * @return array{
   *   unit_types: array<int, array{id:string,slug:string,name:string,role:string}>,
   *   dice_definitions: array<int, array{id:string,sides:int,rarity:string,slot_capacity:int}>,
   *   region_items: array<int, array{id:string,slug:string,name:string,region_slug:string,region_name:string}>,
   *   owned_units: array<int, array{id:string,name:string,unit_type_slug:string,level:int,max_level:int}>
   * }
   */
  public function getCatalog(int $userId): array
  {
    $stmt = $this->pdo->query('
      SELECT
        ri.`id`,
        ri.`slug`,
        ri.`name`,
        r.`slug` AS `region_slug`,
        r.`name` AS `region_name`
      FROM `region_items` ri
      JOIN `regions` r ON r.`id` = ri.`region_id`
      ORDER BY r.`id` ASC, ri.`id` ASC
    ');

    $regionItems = array_map(static fn(array $row): array => [
      'id' => (string) $row['id'],
      'slug' => (string) $row['slug'],
      'name' => (string) $row['name'],
      'region_slug' => (string) $row['region_slug'],
      'region_name' => (string) $row['region_name'],
    ], $stmt->fetchAll(PDO::FETCH_ASSOC));

    $ownedUnitsStmt = $this->pdo->prepare('
      SELECT
        ui.`id`,
        ui.`display_name`,
        ui.`level`,
        ut.`slug` AS `unit_type_slug`,
        ut.`name` AS `unit_type_name`,
        ut.`max_level`
      FROM `unit_instances` ui
      JOIN `unit_types` ut ON ut.`id` = ui.`unit_type_id`
      WHERE ui.`user_id` = ?
      ORDER BY ui.`id` ASC
    ');
    $ownedUnitsStmt->execute([$userId]);
    $ownedUnits = array_map(static fn(array $row): array => [
      'id' => (string)$row['id'],
      'name' => trim((string)($row['display_name'] ?? '')) !== ''
        ? (string)$row['display_name']
        : (string)$row['unit_type_name'],
      'unit_type_slug' => (string)$row['unit_type_slug'],
      'level' => (int)$row['level'],
      'max_level' => (int)$row['max_level'],
    ], $ownedUnitsStmt->fetchAll(PDO::FETCH_ASSOC));

    return [
      'unit_types' => $this->unitRepo->listUnitTypes(),
      'dice_definitions' => $this->diceRepo->listDiceDefinitions(),
      'region_items' => $regionItems,
      'owned_units' => $ownedUnits,
    ];
  }

  /**
   * @return array{soft:int,hard:int}
   */
  public function grantCurrency(int $userId, int $soft, int $hard = 0): array
  {
    $this->bootstrapper->ensureBaseline($userId);
    return $this->playerStateRepo->addCurrencyClamped($userId, max(0, $soft), max(0, $hard));
  }

  /**
   * @return array<int, array{id:string,unit_type_slug:string}>
   */
  public function grantUnits(int $userId, string $unitTypeSlug, int $count = 1): array
  {
    $count = max(1, min(25, $count));
    $this->bootstrapper->ensureBaseline($userId);

    $unitTypeId = $this->lookupUnitTypeId($unitTypeSlug);
    if ($unitTypeId === null) {
      throw new RuntimeException('Unknown unit_type_slug.');
    }

    $granted = [];
    for ($i = 0; $i < $count; $i += 1) {
      $unitId = $this->unitRepo->createUnitInstance(
        $userId,
        $unitTypeId,
        1,
        1,
        0,
        false,
        $this->getUnitNameGenerator()->generate()
      );
      $this->getUnitLoadoutService()->initializeUnit($unitId, $unitTypeId);
      $granted[] = [
        'id' => (string) $unitId,
        'unit_type_slug' => $unitTypeSlug,
      ];
    }

    return $granted;
  }

  /**
   * @return array<int, array{id:string,sides:int,rarity:string}>
   */
  public function grantDice(int $userId, int $sides, string $rarity, int $count = 1): array
  {
    $count = max(1, min(25, $count));
    $this->bootstrapper->ensureBaseline($userId);

    $definitionId = $this->lookupDiceDefinitionId($sides, $rarity);
    if ($definitionId === null) {
      throw new RuntimeException('Unknown dice definition for requested rarity and size.');
    }

    $insert = $this->pdo->prepare('
      INSERT INTO `dice_instances` (`user_id`, `dice_definition_id`, `display_name`)
      VALUES (?, ?, NULL)
    ');

    $granted = [];
    for ($i = 0; $i < $count; $i += 1) {
      $insert->execute([$userId, $definitionId]);
      $diceId = (int) $this->pdo->lastInsertId();
      $this->diceAffixService->assignAffixesToDiceInstance($diceId);
      $granted[] = [
        'id' => (string) $diceId,
        'sides' => $sides,
        'rarity' => $rarity,
      ];
    }

    return $granted;
  }

  /**
   * @return array{region_item_slug:string,quantity:int}
   */
  public function grantRegionItem(int $userId, string $regionItemSlug, int $quantity = 1): array
  {
    $quantity = max(1, min(99, $quantity));
    $this->bootstrapper->ensureBaseline($userId);

    $regionItemId = $this->lookupRegionItemId($regionItemSlug);
    if ($regionItemId === null) {
      throw new RuntimeException('Unknown region_item_slug.');
    }

    $stmt = $this->pdo->prepare('
      INSERT INTO `user_region_items` (`user_id`, `region_item_id`, `quantity`)
      VALUES (?, ?, ?)
      ON DUPLICATE KEY UPDATE `quantity` = `quantity` + VALUES(`quantity`)
    ');
    $stmt->execute([$userId, $regionItemId, $quantity]);

    $qtyStmt = $this->pdo->prepare('
      SELECT `quantity`
      FROM `user_region_items`
      WHERE `user_id` = ? AND `region_item_id` = ?
      LIMIT 1
    ');
    $qtyStmt->execute([$userId, $regionItemId]);
    $currentQuantity = (int) ($qtyStmt->fetchColumn() ?: 0);

    return [
      'region_item_slug' => $regionItemSlug,
      'quantity' => $currentQuantity,
    ];
  }

  /**
   * @return array{
   *   user_id:string,
   *   squads:int,
   *   units:int,
   *   dice:int,
   *   region_unlocks:int,
   *   active_run:bool
   * }
   */
  public function resetAccount(int $userId): array
  {
    try {
      $this->pdo->beginTransaction();

      $this->deleteUserOwnedData($userId);

      $this->pdo->commit();
    } catch (Throwable $e) {
      if ($this->pdo->inTransaction()) {
        $this->pdo->rollBack();
      }
      throw $e;
    }

    $this->bootstrapper->ensureBaseline($userId);

    return [
      'user_id' => (string) $userId,
      'squads' => $this->countRows('teams', 'user_id', $userId),
      'units' => $this->countRows('unit_instances', 'user_id', $userId),
      'dice' => $this->countRows('dice_instances', 'user_id', $userId),
      'region_unlocks' => $this->countRows('region_unlocks', 'user_id', $userId),
      'active_run' => $this->hasActiveRun($userId),
    ];
  }

  /**
   * @return array{id:string,level:int,max_level:int}
   */
  public function setUnitLevel(int $userId, int $unitId, int $level): array
  {
    $stmt = $this->pdo->prepare('
      SELECT ut.`max_level`
      FROM `unit_instances` ui
      JOIN `unit_types` ut ON ut.`id` = ui.`unit_type_id`
      WHERE ui.`id` = ? AND ui.`user_id` = ?
      LIMIT 1
    ');
    $stmt->execute([$unitId, $userId]);
    $maxLevel = $stmt->fetchColumn();
    if ($maxLevel === false) {
      throw new RuntimeException('Unit not found or not owned by user.');
    }

    $normalizedLevel = max(1, min((int)$maxLevel, $level));
    $this->unitRepo->setUnitLevel($userId, $unitId, $normalizedLevel);

    return [
      'id' => (string)$unitId,
      'level' => $normalizedLevel,
      'max_level' => (int)$maxLevel,
    ];
  }

  private function deleteUserOwnedData(int $userId): void
  {
    $this->execDelete(
      'DELETE br FROM `battle_rewards` br JOIN `battles` b ON b.`id` = br.`battle_id` WHERE b.`user_id` = ?',
      [$userId]
    );
    $this->execDelete(
      'DELETE bl FROM `battle_logs` bl JOIN `battles` b ON b.`id` = bl.`battle_id` WHERE b.`user_id` = ?',
      [$userId]
    );
    $this->execDelete('DELETE FROM `battles` WHERE `user_id` = ?', [$userId]);

    $this->execDelete(
      'DELETE re FROM `run_edges` re JOIN `region_runs` rr ON rr.`id` = re.`run_id` WHERE rr.`user_id` = ?',
      [$userId]
    );
    $this->execDelete(
      'DELETE rus FROM `run_unit_state` rus JOIN `region_runs` rr ON rr.`id` = rus.`run_id` WHERE rr.`user_id` = ?',
      [$userId]
    );
    $this->execDelete(
      'DELETE rn FROM `run_nodes` rn JOIN `region_runs` rr ON rr.`id` = rn.`run_id` WHERE rr.`user_id` = ?',
      [$userId]
    );
    $this->execDelete('DELETE FROM `region_runs` WHERE `user_id` = ?', [$userId]);

    $this->execDelete(
      'DELETE tf FROM `team_formation` tf JOIN `teams` t ON t.`id` = tf.`team_id` WHERE t.`user_id` = ?',
      [$userId]
    );
    $this->execDelete(
      'DELETE tu FROM `team_units` tu JOIN `teams` t ON t.`id` = tu.`team_id` WHERE t.`user_id` = ?',
      [$userId]
    );
    $this->execDelete(
      'DELETE ud FROM `unit_dice` ud JOIN `unit_instances` ui ON ui.`id` = ud.`unit_instance_id` WHERE ui.`user_id` = ?',
      [$userId]
    );
    $this->execDelete(
      'DELETE dia FROM `dice_instance_affixes` dia JOIN `dice_instances` di ON di.`id` = dia.`dice_instance_id` WHERE di.`user_id` = ?',
      [$userId]
    );
    $this->execDelete('DELETE FROM `shop_daily_deals` WHERE `user_id` = ?', [$userId]);

    $this->execDelete('DELETE FROM `unit_promotions` WHERE `user_id` = ?', [$userId]);
    $this->execDelete('DELETE FROM `user_region_items` WHERE `user_id` = ?', [$userId]);
    $this->execDelete('DELETE FROM `region_unlocks` WHERE `user_id` = ?', [$userId]);
    $this->execDelete('DELETE FROM `user_grants` WHERE `user_id` = ?', [$userId]);
    $this->execDelete('DELETE FROM `dice_instances` WHERE `user_id` = ?', [$userId]);
    $this->execDelete('DELETE FROM `unit_instances` WHERE `user_id` = ?', [$userId]);
    $this->execDelete('DELETE FROM `teams` WHERE `user_id` = ?', [$userId]);
    $this->execDelete('DELETE FROM `energy_state` WHERE `user_id` = ?', [$userId]);
    $this->execDelete('DELETE FROM `player_state` WHERE `user_id` = ?', [$userId]);
  }

  /**
   * @param list<int|string> $params
   */
  private function execDelete(string $sql, array $params): void
  {
    $stmt = $this->pdo->prepare($sql);
    $stmt->execute($params);
  }

  private function countRows(string $table, string $column, int $userId): int
  {
    $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM `$table` WHERE `$column` = ?");
    $stmt->execute([$userId]);
    return (int) ($stmt->fetchColumn() ?: 0);
  }

  private function hasActiveRun(int $userId): bool
  {
    $stmt = $this->pdo->prepare('
      SELECT 1
      FROM `region_runs`
      WHERE `user_id` = ? AND `status` = \'active\'
      LIMIT 1
    ');
    $stmt->execute([$userId]);
    return (bool) $stmt->fetchColumn();
  }

  private function lookupUnitTypeId(string $slug): ?int
  {
    $stmt = $this->pdo->prepare('SELECT `id` FROM `unit_types` WHERE `slug` = ? LIMIT 1');
    $stmt->execute([$slug]);
    $value = $stmt->fetchColumn();
    return $value === false ? null : (int) $value;
  }

  private function lookupDiceDefinitionId(int $sides, string $rarity): ?int
  {
    $stmt = $this->pdo->prepare('
      SELECT `id`
      FROM `dice_definitions`
      WHERE `sides` = ? AND `rarity` = ?
      LIMIT 1
    ');
    $stmt->execute([$sides, $rarity]);
    $value = $stmt->fetchColumn();
    return $value === false ? null : (int) $value;
  }

  private function lookupRegionItemId(string $slug): ?int
  {
    $stmt = $this->pdo->prepare('SELECT `id` FROM `region_items` WHERE `slug` = ? LIMIT 1');
    $stmt->execute([$slug]);
    $value = $stmt->fetchColumn();
    return $value === false ? null : (int) $value;
  }

  private function getUnitLoadoutService(): UnitLoadoutService
  {
    $this->unitLoadoutService ??= new UnitLoadoutService($this->pdo);
    return $this->unitLoadoutService;
  }

  private function getUnitNameGenerator(): UnitNameGenerator
  {
    $this->unitNameGenerator ??= new UnitNameGenerator();
    return $this->unitNameGenerator;
  }
}
