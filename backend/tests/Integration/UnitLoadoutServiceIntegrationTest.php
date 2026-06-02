<?php
declare(strict_types=1);

namespace DiceGoblins\Tests\Integration;

use DiceGoblins\Services\GrantService;
use DiceGoblins\Services\UnitLoadoutService;
use DiceGoblins\Tests\Support\IntegrationTestCase;
use RuntimeException;

final class UnitLoadoutServiceIntegrationTest extends IntegrationTestCase
{
  public function testReplaceEquippedAbilitiesRejectsOverBudgetList(): void
  {
    $userId = $this->seedStarterUser();
    $unitId = $this->loadStarterUnitId($userId, 'frontline_bruiser_t1');

    $service = new UnitLoadoutService($this->pdo);

    $this->expectException(RuntimeException::class);
    $this->expectExceptionMessage('20-point speed budget');
    $service->replaceEquippedAbilities($unitId, array_fill(0, 6, 'basic_attack_melee'));
  }

  public function testReplaceEquippedAbilitiesRejectsLockedAbility(): void
  {
    $userId = $this->seedStarterUser();
    $unitId = $this->loadStarterUnitId($userId, 'frontline_bruiser_t1');

    $service = new UnitLoadoutService($this->pdo);

    $this->expectException(RuntimeException::class);
    $this->expectExceptionMessage('not unlocked');
    $service->replaceEquippedAbilities($unitId, ['sleep_dart']);
  }

  public function testReplaceEquippedAbilitiesAllowsDuplicateAbilitiesWithinBudget(): void
  {
    $userId = $this->seedStarterUser();
    $unitId = $this->loadStarterUnitId($userId, 'frontline_bruiser_t1');

    $service = new UnitLoadoutService($this->pdo);
    $service->replaceEquippedAbilities($unitId, ['basic_attack_melee', 'basic_attack_melee', 'basic_attack_melee']);

    $count = (int)$this->scalar(
      'SELECT COUNT(*) FROM `unit_instance_equipped_abilities` WHERE `unit_instance_id` = ? AND `ability_id` = ?',
      [$unitId, 'basic_attack_melee']
    );
    $budget = (int)$this->scalar(
      'SELECT COALESCE(SUM(`speed_cost`), 0) FROM `unit_instance_equipped_abilities` WHERE `unit_instance_id` = ?',
      [$unitId]
    );

    $this->assertSame(3, $count);
    $this->assertSame(12, $budget);
  }

  public function testAssignDieToAbilitySlotRejectsInvalidSlotIndex(): void
  {
    $userId = $this->seedStarterUser();
    $unitId = $this->loadStarterUnitId($userId, 'frontline_bruiser_t1');
    $diceId = $this->loadOwnedUnboundDiceId($userId);

    $service = new UnitLoadoutService($this->pdo);

    $this->expectException(RuntimeException::class);
    $this->expectExceptionMessage('exceeds the configured slot count');
    $service->assignDieToAbilitySlot($unitId, 'heavy_strike', 1, $diceId);
  }

  public function testAssignDieToAbilitySlotRejectsDiceOwnedByDifferentUser(): void
  {
    $firstUserId = $this->seedStarterUser('loadout_a');
    $secondUserId = $this->seedStarterUser('loadout_b');
    $unitId = $this->loadStarterUnitId($firstUserId, 'frontline_bruiser_t1');
    $foreignDiceId = $this->loadOwnedUnboundDiceId($secondUserId);

    $service = new UnitLoadoutService($this->pdo);

    $this->expectException(RuntimeException::class);
    $this->expectExceptionMessage('same user');
    $service->assignDieToAbilitySlot($unitId, 'heavy_strike', 0, $foreignDiceId);
  }

  public function testAssignDieToAbilitySlotAllowsUnlockedActiveAbilityOutsideCurrentLoadout(): void
  {
    $userId = $this->seedStarterUser();
    $unitId = $this->loadStarterUnitId($userId, 'frontline_bruiser_t1');
    $diceId = $this->insertDiceInstance($userId, $this->pickAnyDiceDefinitionId());

    $service = new UnitLoadoutService($this->pdo);
    $service->replaceEquippedAbilities($unitId, ['basic_attack_melee', 'basic_attack_melee']);
    $service->assignDieToAbilitySlot($unitId, 'heavy_strike', 0, $diceId);

    $equippedAbilityIds = array_map(
      static fn(array $row): string => (string)$row['ability_id'],
      $this->rows(
        'SELECT `ability_id` FROM `unit_instance_equipped_abilities` WHERE `unit_instance_id` = ? ORDER BY `equip_order` ASC, `id` ASC',
        [$unitId]
      )
    );

    $binding = $this->rows(
      'SELECT `ability_id`, `slot_index`, `dice_instance_id` FROM `unit_ability_dice` WHERE `unit_instance_id` = ? AND `ability_id` = ?',
      [$unitId, 'heavy_strike']
    );

    $this->assertSame(['basic_attack_melee', 'basic_attack_melee'], $equippedAbilityIds);
    $this->assertCount(1, $binding);
    $this->assertSame('heavy_strike', (string)$binding[0]['ability_id']);
    $this->assertSame(0, (int)$binding[0]['slot_index']);
    $this->assertSame($diceId, (int)$binding[0]['dice_instance_id']);
  }

  private function seedStarterUser(string $prefix = 'loadout_user'): int
  {
    $userId = $this->insertUser($prefix, 'Loadout User');
    (new GrantService())->ensureStarterPackGranted($userId);
    return $userId;
  }

  private function loadStarterUnitId(int $userId, string $unitTypeSlug): int
  {
    $stmt = $this->pdo->prepare('
      SELECT ui.`id`
      FROM `unit_instances` ui
      JOIN `unit_types` ut ON ut.`id` = ui.`unit_type_id`
      WHERE ui.`user_id` = ? AND ut.`slug` = ?
      LIMIT 1
    ');
    $stmt->execute([$userId, $unitTypeSlug]);
    return (int)$stmt->fetchColumn();
  }

  private function loadOwnedUnboundDiceId(int $userId): int
  {
    $stmt = $this->pdo->prepare('
      SELECT di.`id`
      FROM `dice_instances` di
      LEFT JOIN `unit_ability_dice` uad ON uad.`dice_instance_id` = di.`id`
      WHERE di.`user_id` = ? AND uad.`dice_instance_id` IS NULL
      ORDER BY di.`id` ASC
      LIMIT 1
    ');
    $stmt->execute([$userId]);
    return (int)$stmt->fetchColumn();
  }

  private function pickAnyDiceDefinitionId(): int
  {
    $stmt = $this->pdo->query('SELECT `id` FROM `dice_definitions` ORDER BY `id` ASC LIMIT 1');
    return (int)$stmt->fetchColumn();
  }

  private function insertDiceInstance(int $userId, int $diceDefinitionId): int
  {
    $stmt = $this->pdo->prepare('
      INSERT INTO `dice_instances` (`user_id`, `dice_definition_id`, `display_name`)
      VALUES (?, ?, NULL)
    ');
    $stmt->execute([$userId, $diceDefinitionId]);
    return (int)$this->pdo->lastInsertId();
  }

  /**
   * @param array<int,int|string> $params
   * @return array<int,array<string,mixed>>
   */
  private function rows(string $sql, array $params): array
  {
    $stmt = $this->pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
    return is_array($rows) ? $rows : [];
  }
}
