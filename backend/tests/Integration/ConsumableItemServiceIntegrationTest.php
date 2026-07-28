<?php
declare(strict_types=1);

namespace DiceGoblins\Tests\Integration;

use DiceGoblins\Controllers\GameplayController;
use DiceGoblins\Services\ConsumableItemService;
use DiceGoblins\Services\ItemInventoryService;
use DiceGoblins\Tests\Support\BattleFlowIntegrationCase;

final class ConsumableItemServiceIntegrationTest extends BattleFlowIntegrationCase
{
  protected function integrationSkipMessage(): string
  {
    return 'Set TEST_DB_DSN to run consumable item integration tests.';
  }

  public function testHealingConsumableSpendsItemAndCapsRunUnitHp(): void
  {
    $userId = $this->insertUser('heal_item_success', 'Heal Item Success');
    $regionId = $this->insertRegion();
    $teamId = $this->insertTeam($userId);
    $runId = $this->insertRun($userId, $regionId);
    [$unitTypeId] = $this->pickUnitTypeForProgressTest();
    $unitId = $this->insertUnit($userId, $unitTypeId, 1, 0);
    $this->insertTeamUnit($teamId, $unitId);
    $this->insertRunUnitState($runId, $unitId, 5, false);
    $this->grantItem($userId, 'field_poultice', 2);

    $result = $this->service()->healRunUnit($userId, $runId, $unitId, 'field_poultice');

    $this->assertSame((string)$runId, $result['run_id']);
    $this->assertSame((string)$unitId, $result['unit_instance_id']);
    $this->assertSame(1, (int)$result['item']['spent_quantity']);
    $this->assertSame(1, (int)$result['item']['quantity']);
    $this->assertSame(5, (int)$result['healing']['hp_before']);
    $this->assertSame(15, (int)$result['healing']['hp_after']);
    $this->assertSame('15', (string)$this->scalar(
      'SELECT `current_hp` FROM `run_unit_state` WHERE `run_id` = ? AND `unit_instance_id` = ?',
      [$runId, $unitId]
    ));
    $this->assertSame('0', (string)$this->scalar(
      'SELECT `is_defeated` FROM `run_unit_state` WHERE `run_id` = ? AND `unit_instance_id` = ?',
      [$runId, $unitId]
    ));
  }

  public function testControllerHealingEndpointUsesJsonItemSlug(): void
  {
    $userId = $this->insertUser('heal_item_endpoint', 'Heal Item Endpoint');
    $regionId = $this->insertRegion();
    $teamId = $this->insertTeam($userId);
    $runId = $this->insertRun($userId, $regionId);
    [$unitTypeId] = $this->pickUnitTypeForProgressTest();
    $unitId = $this->insertUnit($userId, $unitTypeId, 1, 0);
    $this->insertTeamUnit($teamId, $unitId);
    $this->insertRunUnitState($runId, $unitId, 3, false);
    $this->grantItem($userId, 'field_poultice', 1);

    $_SESSION['user_id'] = $userId;
    $_SESSION['csrf_token'] = 'valid_csrf';
    $_SERVER['HTTP_X_CSRF_TOKEN'] = 'valid_csrf';
    $this->setJsonBody(['item_slug' => 'field_poultice']);

    $response = $this->invoke(fn() => (new GameplayController())->healRunUnitWithItem((string)$runId, (string)$unitId));

    $this->assertSame(200, $response['status']);
    $this->assertTrue((bool)($response['body']['ok'] ?? false));
    $this->assertSame('field_poultice', (string)($response['body']['data']['item']['item_slug'] ?? ''));
    $this->assertSame(13, (int)($response['body']['data']['healing']['hp_after'] ?? 0));
  }

  public function testEnergyConsumableSpendsItemAndRestoresUpToCap(): void
  {
    $userId = $this->insertUser('energy_item_cap', 'Energy Item Cap');
    $this->setEnergy($userId, 45, 50);
    $this->grantItem($userId, 'sparkroot_tonic', 2);

    $result = $this->service()->restoreEnergy($userId, 'sparkroot_tonic');

    $this->assertSame(1, (int)$result['item']['quantity']);
    $this->assertSame(5, (int)$result['energy']['amount']);
    $this->assertSame(45, (int)$result['energy']['current_before']);
    $this->assertSame(50, (int)$result['energy']['current_after']);
    $this->assertSame(50, (int)$result['energy']['max']);
    $this->assertSame('50', (string)$this->scalar('SELECT `energy_current` FROM `energy_state` WHERE `user_id` = ?', [$userId]));
  }

  public function testEnergyConsumableUsesUnlockedEnergyCap(): void
  {
    $userId = $this->insertUser('energy_item_unlock_cap', 'Energy Item Unlock Cap');
    $this->grantUnlock($userId, 'feature', 'energy_cap_75');
    $this->setEnergy($userId, 60, 50);
    $this->grantItem($userId, 'sparkroot_tonic', 1);

    $result = $this->service()->restoreEnergy($userId, 'sparkroot_tonic');

    $this->assertSame(15, (int)$result['energy']['amount']);
    $this->assertSame(75, (int)$result['energy']['current_after']);
    $this->assertSame(75, (int)$result['energy']['max']);
    $this->assertSame('75', (string)$this->scalar('SELECT `energy_max` FROM `energy_state` WHERE `user_id` = ?', [$userId]));
  }

  public function testEnergyConsumableDoesNotSpendWhenEnergyIsFull(): void
  {
    $userId = $this->insertUser('energy_item_full', 'Energy Item Full');
    $this->setEnergy($userId, 50, 50);
    $this->grantItem($userId, 'travel_ration', 1);

    $this->expectExceptionMessage('energy_full');

    try {
      $this->service()->restoreEnergy($userId, 'travel_ration');
    } finally {
      $this->assertSame('1', (string)$this->ownedItemQuantity($userId, 'travel_ration'));
    }
  }

  public function testControllerEnergyRestoreEndpointUsesJsonItemSlug(): void
  {
    $userId = $this->insertUser('energy_item_endpoint', 'Energy Item Endpoint');
    $this->setEnergy($userId, 40, 50);
    $this->grantItem($userId, 'travel_ration', 1);

    $_SESSION['user_id'] = $userId;
    $_SESSION['csrf_token'] = 'valid_csrf';
    $_SERVER['HTTP_X_CSRF_TOKEN'] = 'valid_csrf';
    $this->setJsonBody(['item_slug' => 'travel_ration']);

    $response = $this->invoke(fn() => (new GameplayController())->restoreEnergyWithItem());

    $this->assertSame(200, $response['status']);
    $this->assertTrue((bool)($response['body']['ok'] ?? false));
    $this->assertSame(50, (int)($response['body']['data']['energy']['current_after'] ?? 0));
    $this->assertSame('travel_ration', (string)($response['body']['data']['item']['item_slug'] ?? ''));
  }

  public function testHealingConsumableRevivesDefeatedRunUnitWithoutExceedingMaxHp(): void
  {
    $userId = $this->insertUser('heal_item_revive', 'Heal Item Revive');
    $regionId = $this->insertRegion();
    $teamId = $this->insertTeam($userId);
    $runId = $this->insertRun($userId, $regionId);
    [$unitTypeId] = $this->pickUnitTypeForProgressTest();
    $unitId = $this->insertUnit($userId, $unitTypeId, 1, 0);
    $this->insertTeamUnit($teamId, $unitId);
    $this->insertRunUnitState($runId, $unitId, 0, true);
    $this->grantItem($userId, 'hearty_bone_broth', 1);

    $result = $this->service()->healRunUnit($userId, $runId, $unitId, 'hearty_bone_broth');

    $this->assertGreaterThan(0, (int)$result['healing']['hp_after']);
    $this->assertLessThanOrEqual((int)$result['healing']['max_hp'], (int)$result['healing']['hp_after']);
    $this->assertFalse((bool)$result['healing']['is_defeated']);
    $this->assertSame('0', (string)$this->scalar(
      'SELECT `is_defeated` FROM `run_unit_state` WHERE `run_id` = ? AND `unit_instance_id` = ?',
      [$runId, $unitId]
    ));
  }

  public function testHealingConsumableDoesNotSpendWhenUnitIsFullHealth(): void
  {
    $userId = $this->insertUser('heal_item_full', 'Heal Item Full');
    $regionId = $this->insertRegion();
    $teamId = $this->insertTeam($userId);
    $runId = $this->insertRun($userId, $regionId);
    [$unitTypeId] = $this->pickUnitTypeForProgressTest();
    $unitId = $this->insertUnit($userId, $unitTypeId, 1, 0);
    $this->insertTeamUnit($teamId, $unitId);
    $maxHp = $this->maxHpForUnitType($unitTypeId);
    $this->insertRunUnitState($runId, $unitId, $maxHp, false);
    $this->grantItem($userId, 'field_poultice', 1);

    $this->expectExceptionMessage('unit_not_wounded');

    try {
      $this->service()->healRunUnit($userId, $runId, $unitId, 'field_poultice');
    } finally {
      $this->assertSame('1', (string)$this->ownedItemQuantity($userId, 'field_poultice'));
    }
  }

  private function service(): ConsumableItemService
  {
    return new ConsumableItemService($this->pdo, new ItemInventoryService($this->pdo));
  }

  private function grantItem(int $userId, string $slug, int $quantity): void
  {
    $itemId = (int)$this->scalar('SELECT `id` FROM `items` WHERE `slug` = ? LIMIT 1', [$slug]);
    $stmt = $this->pdo?->prepare('
      INSERT INTO `user_items` (`user_id`, `item_id`, `quantity`)
      VALUES (?, ?, ?)
      ON DUPLICATE KEY UPDATE `quantity` = VALUES(`quantity`)
    ');
    $stmt?->execute([$userId, $itemId, $quantity]);
  }

  private function ownedItemQuantity(int $userId, string $slug): int
  {
    return (int)$this->scalar(
      'SELECT ui.`quantity`
       FROM `user_items` ui
       JOIN `items` i ON i.`id` = ui.`item_id`
       WHERE ui.`user_id` = ? AND i.`slug` = ?
       LIMIT 1',
      [$userId, $slug]
    );
  }

  private function maxHpForUnitType(int $unitTypeId): int
  {
    return (int)$this->scalar(
      "SELECT JSON_UNQUOTE(JSON_EXTRACT(`base_stats_json`, '$.max_hp')) FROM `unit_types` WHERE `id` = ? LIMIT 1",
      [$unitTypeId]
    );
  }
}
