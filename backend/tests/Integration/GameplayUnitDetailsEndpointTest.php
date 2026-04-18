<?php
declare(strict_types=1);

namespace DiceGoblins\Tests\Integration;

use DiceGoblins\Controllers\GameplayController;
use DiceGoblins\Services\UnitLoadoutService;
use DiceGoblins\Tests\Support\BattleFlowIntegrationCase;

final class GameplayUnitDetailsEndpointTest extends BattleFlowIntegrationCase
{
  public function testRenameUnitEndpointUpdatesDisplayName(): void
  {
    $userId = $this->insertUser('rename_case', 'Rename User');
    [$unitTypeId, ] = $this->loadUnitType('frontline_bruiser_t1');
    $unitId = $this->insertUnit($userId, $unitTypeId, 1, 0);
    (new UnitLoadoutService($this->pdo))->initializeUnit($unitId, $unitTypeId);

    $_SESSION['user_id'] = $userId;
    $_SESSION['csrf_token'] = 'valid_csrf';
    $_SERVER['HTTP_X_CSRF_TOKEN'] = 'valid_csrf';
    $_POST = ['display_name' => 'Mudjaw'];

    $controller = new GameplayController();
    $response = $this->invoke(fn() => $controller->renameUnit((string)$unitId));

    $this->assertSame(200, $response['status'], json_encode($response['body']));
    $this->assertSame((string)$unitId, (string)($response['body']['data']['unit_id'] ?? ''));
    $this->assertSame('Mudjaw', (string)($response['body']['data']['display_name'] ?? ''));
    $this->assertSame('Mudjaw', (string)$this->scalar('SELECT `display_name` FROM `unit_instances` WHERE `id` = ?', [$unitId]));
  }

  public function testReplaceEquippedAbilitiesEndpointReturnsUpdatedOrderedLoadout(): void
  {
    $userId = $this->insertUser('loadout_case', 'Loadout User');
    [$unitTypeId, ] = $this->loadUnitType('frontline_bruiser_t1');
    $unitId = $this->insertUnit($userId, $unitTypeId, 1, 0);
    (new UnitLoadoutService($this->pdo))->initializeUnit($unitId, $unitTypeId);

    $_SESSION['user_id'] = $userId;
    $_SESSION['csrf_token'] = 'valid_csrf';
    $_SERVER['HTTP_X_CSRF_TOKEN'] = 'valid_csrf';
    $_POST = [
      'ability_ids' => ['basic_attack_melee', 'basic_attack_melee', 'heavy_strike'],
    ];

    $controller = new GameplayController();
    $response = $this->invoke(fn() => $controller->replaceEquippedAbilities((string)$unitId));

    $this->assertSame(200, $response['status'], json_encode($response['body']));
    $equipped = $response['body']['data']['equipped_abilities'] ?? null;
    $this->assertIsArray($equipped);
    $this->assertSame(
      ['basic_attack_melee', 'basic_attack_melee', 'heavy_strike'],
      array_map(static fn(array $row): string => (string)$row['ability_id'], $equipped)
    );
    $this->assertSame(
      [0, 1, 2],
      array_map(static fn(array $row): int => (int)$row['equip_order'], $equipped)
    );

    $stored = $this->rows(
      'SELECT `ability_id`, `equip_order`, `speed_cost` FROM `unit_instance_equipped_abilities` WHERE `unit_instance_id` = ? ORDER BY `equip_order` ASC, `id` ASC',
      [$unitId]
    );
    $this->assertCount(3, $stored);
    $this->assertSame('heavy_strike', (string)$stored[2]['ability_id']);
    $this->assertGreaterThan(0, (int)$stored[0]['speed_cost']);
  }

  public function testReplaceEquippedAbilitiesEndpointRequiresRestContextForActiveRunUnits(): void
  {
    $userId = $this->insertUser('loadout_rest_case', 'Loadout Rest User');
    [$unitTypeId, ] = $this->loadUnitType('frontline_bruiser_t1');
    $unitId = $this->insertUnit($userId, $unitTypeId, 1, 0);
    (new UnitLoadoutService($this->pdo))->initializeUnit($unitId, $unitTypeId);
    $regionId = $this->insertRegion();
    $teamId = $this->insertTeam($userId);
    $runId = $this->insertRun($userId, $regionId, 646464);
    $restNodeId = $this->insertRunNode($runId, 'rest', 'available');

    $this->insertTeamUnit($teamId, $unitId);
    $this->insertRunUnitState($runId, $unitId, 10, false);

    $_SESSION['user_id'] = $userId;
    $_SESSION['csrf_token'] = 'valid_csrf';
    $_SERVER['HTTP_X_CSRF_TOKEN'] = 'valid_csrf';

    $controller = new GameplayController();

    $_POST = ['ability_ids' => ['basic_attack_melee', 'heavy_strike']];
    $blocked = $this->invoke(fn() => $controller->replaceEquippedAbilities((string)$unitId));
    $this->assertSame(409, $blocked['status']);
    $this->assertSame('run_rest_context_required', (string)($blocked['body']['error']['code'] ?? ''));

    $_POST = [
      'ability_ids' => ['basic_attack_melee', 'heavy_strike'],
      'run_id' => (string)$runId,
      'node_id' => (string)$restNodeId,
    ];
    $allowed = $this->invoke(fn() => $controller->replaceEquippedAbilities((string)$unitId));
    $this->assertSame(200, $allowed['status'], json_encode($allowed['body']));
  }

  public function testAbilitySlotDiceEndpointsAssignAndClearBinding(): void
  {
    $userId = $this->insertUser('slot_dice_case', 'Slot Dice User');
    [$unitTypeId, ] = $this->loadUnitType('frontline_bruiser_t1');
    $unitId = $this->insertUnit($userId, $unitTypeId, 1, 0);
    (new UnitLoadoutService($this->pdo))->initializeUnit($unitId, $unitTypeId);
    $diceId = $this->insertDiceInstance($userId, $this->pickAnyDiceDefinitionId());

    $_SESSION['user_id'] = $userId;
    $_SESSION['csrf_token'] = 'valid_csrf';
    $_SERVER['HTTP_X_CSRF_TOKEN'] = 'valid_csrf';

    $controller = new GameplayController();

    $_POST = ['dice_instance_id' => (string)$diceId];
    $assign = $this->invoke(fn() => $controller->assignAbilitySlotDie((string)$unitId, 'heavy_strike', '0'));
    $this->assertSame(200, $assign['status'], json_encode($assign['body']));
    $abilityDice = $assign['body']['data']['ability_dice'] ?? null;
    $this->assertIsArray($abilityDice);
    $this->assertTrue(
      count(array_filter($abilityDice, static fn(array $row): bool => (string)$row['ability_id'] === 'heavy_strike' && (int)$row['slot_index'] === 0 && (string)$row['dice_instance_id'] === (string)$diceId)) === 1
    );

    $_POST = [];
    $clear = $this->invoke(fn() => $controller->clearAbilitySlotDie((string)$unitId, 'heavy_strike', '0'));
    $this->assertSame(200, $clear['status'], json_encode($clear['body']));
    $this->assertSame(
      '0',
      (string)$this->scalar(
        'SELECT COUNT(*) FROM `unit_ability_dice` WHERE `unit_instance_id` = ? AND `ability_id` = ? AND `slot_index` = 0',
        [$unitId, 'heavy_strike']
      )
    );
  }

  public function testAbilitySlotDiceAssignRequiresRestContextForActiveRunUnits(): void
  {
    $userId = $this->insertUser('slot_dice_rest', 'Slot Dice Rest User');
    [$unitTypeId, ] = $this->loadUnitType('frontline_bruiser_t1');
    $unitId = $this->insertUnit($userId, $unitTypeId, 1, 0);
    (new UnitLoadoutService($this->pdo))->initializeUnit($unitId, $unitTypeId);
    $diceId = $this->insertDiceInstance($userId, $this->pickAnyDiceDefinitionId());
    $regionId = $this->insertRegion();
    $teamId = $this->insertTeam($userId);
    $runId = $this->insertRun($userId, $regionId, 757575);
    $restNodeId = $this->insertRunNode($runId, 'rest', 'available');

    $this->insertTeamUnit($teamId, $unitId);
    $this->insertRunUnitState($runId, $unitId, 10, false);

    $_SESSION['user_id'] = $userId;
    $_SESSION['csrf_token'] = 'valid_csrf';
    $_SERVER['HTTP_X_CSRF_TOKEN'] = 'valid_csrf';

    $controller = new GameplayController();

    $_POST = ['dice_instance_id' => (string)$diceId];
    $blocked = $this->invoke(fn() => $controller->assignAbilitySlotDie((string)$unitId, 'heavy_strike', '0'));
    $this->assertSame(409, $blocked['status']);
    $this->assertSame('run_rest_context_required', (string)($blocked['body']['error']['code'] ?? ''));

    $_POST = [
      'dice_instance_id' => (string)$diceId,
      'run_id' => (string)$runId,
      'node_id' => (string)$restNodeId,
    ];
    $allowed = $this->invoke(fn() => $controller->assignAbilitySlotDie((string)$unitId, 'heavy_strike', '0'));
    $this->assertSame(200, $allowed['status'], json_encode($allowed['body']));
  }

  /**
   * @return array{0:int,1:string}
   */
  private function loadUnitType(string $slug): array
  {
    $rows = $this->rows(
      'SELECT `id`, `slug`
       FROM `unit_types`
       WHERE `slug` = ?
       LIMIT 1',
      [$slug]
    );
    $this->assertCount(1, $rows, 'Expected seeded unit type to exist.');
    return [(int)$rows[0]['id'], (string)$rows[0]['slug']];
  }
}
