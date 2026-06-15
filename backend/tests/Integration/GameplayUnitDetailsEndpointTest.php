<?php
declare(strict_types=1);

namespace DiceGoblins\Tests\Integration;

use DiceGoblins\Controllers\GameplayController;
use DiceGoblins\Services\UnitLoadoutService;
use DiceGoblins\Services\UserUnlockService;
use DiceGoblins\Tests\Support\BattleFlowIntegrationCase;

final class GameplayUnitDetailsEndpointTest extends BattleFlowIntegrationCase
{
  public function testRenameUnitEndpointUpdatesDisplayName(): void
  {
    $userId = $this->insertUser('rename', 'Rename User');
    [$unitTypeId, ] = $this->loadUnitType('frontline_bruiser_t1');
    $unitId = $this->insertUnit($userId, $unitTypeId, 1, 0);
    (new UnitLoadoutService($this->pdo))->initializeUnit($unitId, $unitTypeId);

    $_SESSION['user_id'] = $userId;
    $_SESSION['csrf_token'] = 'valid_csrf';
    $_SERVER['HTTP_X_CSRF_TOKEN'] = 'valid_csrf';
    $this->setJsonBody(['display_name' => 'Mudjaw']);

    $controller = new GameplayController();
    $response = $this->invoke(fn() => $controller->renameUnit((string)$unitId));

    $this->assertSame(200, $response['status'], json_encode($response['body']));
    $this->assertSame((string)$unitId, (string)($response['body']['data']['unit_id'] ?? ''));
    $this->assertSame('Mudjaw', (string)($response['body']['data']['display_name'] ?? ''));
    $this->assertSame('Mudjaw', (string)$this->scalar('SELECT `display_name` FROM `unit_instances` WHERE `id` = ?', [$unitId]));
  }

  public function testPromotionOptionsEndpointReturnsOnlyUnlockedSidewaysBranches(): void
  {
    $userId = $this->insertUser('promo_opts', 'Promotion Options User');
    [$bruiserTypeId, ] = $this->loadUnitType('frontline_bruiser_t1');
    [$marksmanTypeId, ] = $this->loadUnitType('backline_marksman_t1');
    $unitId = $this->insertUnit($userId, $bruiserTypeId, 6, 0);
    (new UnitLoadoutService($this->pdo))->initializeUnit($unitId, $bruiserTypeId);
    $unlockService = new UserUnlockService($this->pdo);
    $unlockService->grant($userId, UserUnlockService::NAMESPACE_UNIT_TYPE, 'frontline_bruiser_t1');
    $unlockService->grant($userId, UserUnlockService::NAMESPACE_UNIT_TYPE, 'backline_marksman_t1');

    $_SESSION['user_id'] = $userId;

    $controller = new GameplayController();
    $response = $this->invoke(fn() => $controller->getPromotionOptions((string)$unitId));

    $this->assertSame(200, $response['status'], json_encode($response['body']));
    $options = $response['body']['data']['options'] ?? null;
    $this->assertIsArray($options);
    $this->assertCount(2, $options);
    $this->assertSame('chain', (string)($options[0]['mode'] ?? ''));
    $this->assertSame('Bruiser', (string)($options[0]['branch_unit_type_name'] ?? ''));
    $this->assertSame('Enforcer', (string)($options[0]['target_unit_type_name'] ?? ''));

    $branchNames = array_map(static fn(array $row): string => (string)($row['branch_unit_type_name'] ?? ''), $options);
    $targetNames = array_map(static fn(array $row): string => (string)($row['target_unit_type_name'] ?? ''), $options);
    $this->assertContains('Marksman', $branchNames);
    $this->assertContains('Marksman', $targetNames);
    $this->assertNotContains('Bannerbearer', $branchNames);
    $this->assertNotContains('Warcaller', $targetNames);
    $this->assertNotSame($marksmanTypeId, 0);
  }

  public function testReplaceEquippedAbilitiesEndpointReturnsUpdatedOrderedLoadout(): void
  {
    $userId = $this->insertUser('loadout', 'Loadout User');
    [$unitTypeId, ] = $this->loadUnitType('frontline_bruiser_t1');
    $unitId = $this->insertUnit($userId, $unitTypeId, 1, 0);
    (new UnitLoadoutService($this->pdo))->initializeUnit($unitId, $unitTypeId);

    $_SESSION['user_id'] = $userId;
    $_SESSION['csrf_token'] = 'valid_csrf';
    $_SERVER['HTTP_X_CSRF_TOKEN'] = 'valid_csrf';
    $this->setJsonBody([
      'ability_ids' => ['basic_attack_melee', 'basic_attack_melee', 'heavy_strike'],
    ]);

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

  public function testPromoteUnitEndpointHonorsSelectedDestinationAndUnlocksNewBranchAbilities(): void
  {
    $userId = $this->insertUser('promo_dest', 'Promotion Destination User');
    [$bruiserTypeId, ] = $this->loadUnitType('frontline_bruiser_t1');
    [$bannerTargetTypeId, ] = $this->loadUnitType('support_banner_t1');
    $primaryId = $this->insertUnit($userId, $bruiserTypeId, 6, 0);
    $secondaryA = $this->insertUnit($userId, $bruiserTypeId, 6, 0);
    $secondaryB = $this->insertUnit($userId, $bruiserTypeId, 6, 0);

    $loadout = new UnitLoadoutService($this->pdo);
    $loadout->initializeUnit($primaryId, $bruiserTypeId);
    $loadout->initializeUnit($secondaryA, $bruiserTypeId);
    $loadout->initializeUnit($secondaryB, $bruiserTypeId);
    $unlockService = new UserUnlockService($this->pdo);
    $unlockService->grant($userId, UserUnlockService::NAMESPACE_UNIT_TYPE, 'frontline_bruiser_t1');
    $unlockService->grant($userId, UserUnlockService::NAMESPACE_UNIT_TYPE, 'support_banner_t1');

    $_SESSION['user_id'] = $userId;
    $_SESSION['csrf_token'] = 'valid_csrf';
    $_SERVER['HTTP_X_CSRF_TOKEN'] = 'valid_csrf';
    $this->setJsonBody([
      'primary_unit_instance_id' => (string)$primaryId,
      'secondary_unit_instance_ids' => [(string)$secondaryA, (string)$secondaryB],
      'destination_unit_type_id' => (string)$bannerTargetTypeId,
    ]);

    $controller = new GameplayController();
    $response = $this->invoke(fn() => $controller->promoteUnit((string)$primaryId));

    $this->assertSame(200, $response['status'], json_encode($response['body']));
    $this->assertSame((string)$bannerTargetTypeId, (string)($response['body']['data']['destination']['target_unit_type_id'] ?? ''));
    $this->assertSame(
      (string)$bannerTargetTypeId,
      (string)$this->scalar('SELECT `unit_type_id` FROM `unit_instances` WHERE `id` = ?', [$primaryId])
    );
    $this->assertSame(
      '2',
      (string)$this->scalar('SELECT `tier` FROM `unit_instances` WHERE `id` = ?', [$primaryId])
    );

    $unlockedAbilities = $this->rows(
      'SELECT `ability_id` FROM `unit_instance_unlocked_abilities` WHERE `unit_instance_id` = ? ORDER BY `ability_id` ASC',
      [$primaryId]
    );
    $abilityIds = array_map(static fn(array $row): string => (string)$row['ability_id'], $unlockedAbilities);
    $this->assertContains('heavy_strike', $abilityIds);
    $this->assertContains('bolster_ally', $abilityIds);
  }

  public function testPromoteUnitEndpointRejectsLockedSidewaysDestination(): void
  {
    $userId = $this->insertUser('promo_locked', 'Promotion Locked Destination User');
    [$bruiserTypeId, ] = $this->loadUnitType('frontline_bruiser_t1');
    [$bannerTargetTypeId, ] = $this->loadUnitType('support_banner_t1');
    $primaryId = $this->insertUnit($userId, $bruiserTypeId, 6, 0);
    $secondaryA = $this->insertUnit($userId, $bruiserTypeId, 6, 0);
    $secondaryB = $this->insertUnit($userId, $bruiserTypeId, 6, 0);

    $loadout = new UnitLoadoutService($this->pdo);
    $loadout->initializeUnit($primaryId, $bruiserTypeId);
    $loadout->initializeUnit($secondaryA, $bruiserTypeId);
    $loadout->initializeUnit($secondaryB, $bruiserTypeId);
    $unlockService = new UserUnlockService($this->pdo);
    $unlockService->grant($userId, UserUnlockService::NAMESPACE_UNIT_TYPE, 'frontline_bruiser_t1');

    $_SESSION['user_id'] = $userId;
    $_SESSION['csrf_token'] = 'valid_csrf';
    $_SERVER['HTTP_X_CSRF_TOKEN'] = 'valid_csrf';
    $this->setJsonBody([
      'primary_unit_instance_id' => (string)$primaryId,
      'secondary_unit_instance_ids' => [(string)$secondaryA, (string)$secondaryB],
      'destination_unit_type_id' => (string)$bannerTargetTypeId,
    ]);

    $controller = new GameplayController();
    $response = $this->invoke(fn() => $controller->promoteUnit((string)$primaryId));

    $this->assertSame(409, $response['status'], json_encode($response['body']));
    $this->assertSame('promotion_requirements_not_met', (string)($response['body']['error']['code'] ?? ''));
  }

  public function testTierTwoBannerbearerPromotionOptionsAllowWarcallerChainAndUnlockedTierOneSidewaysBranches(): void
  {
    $userId = $this->insertUser('promo_t3_banner', 'Promotion Tier Three Banner User');
    [$bruiserTypeId, ] = $this->loadUnitType('frontline_bruiser_t1');
    [$bannerTypeId, ] = $this->loadUnitType('support_banner_t1');
    $unitId = $this->insertUnit($userId, $bannerTypeId, 8, 0);
    $setTier = $this->pdo->prepare('UPDATE `unit_instances` SET `tier` = 2 WHERE `id` = ?');
    $setTier->execute([$unitId]);
    (new UnitLoadoutService($this->pdo))->initializeUnit($unitId, $bannerTypeId);
    $insertHistory = $this->pdo->prepare('
      INSERT IGNORE INTO `unit_instance_unlocked_abilities` (`unit_instance_id`, `ability_id`, `source_tier`, `source_unit_type_id`)
      VALUES (?, ?, 1, ?)
    ');
    $insertHistory->execute([$unitId, 'heavy_strike', $bruiserTypeId]);
    $insertHistory->execute([$unitId, 'thick_hide', $bruiserTypeId]);
    $unlockService = new UserUnlockService($this->pdo);
    $unlockService->grant($userId, UserUnlockService::NAMESPACE_UNIT_TYPE, 'support_banner_t1');
    $unlockService->grant($userId, UserUnlockService::NAMESPACE_UNIT_TYPE, 'frontline_bruiser_t1');

    $_SESSION['user_id'] = $userId;

    $controller = new GameplayController();
    $response = $this->invoke(fn() => $controller->getPromotionOptions((string)$unitId));

    $this->assertSame(200, $response['status'], json_encode($response['body']));
    $options = $response['body']['data']['options'] ?? null;
    $this->assertIsArray($options);
    $this->assertCount(2, $options);
    $this->assertSame('chain', (string)($options[0]['mode'] ?? ''));
    $this->assertSame('Warcaller', (string)($options[0]['target_unit_type_name'] ?? ''));
    $this->assertSame(3, (int)($options[0]['target_tier'] ?? 0));

    $sideways = $options[1] ?? [];
    $this->assertSame('sideways', (string)($sideways['mode'] ?? ''));
    $this->assertSame('Enforcer', (string)($sideways['target_unit_type_name'] ?? ''));
    $this->assertSame(3, (int)($sideways['target_tier'] ?? 0));
  }

  public function testTierTwoEnforcerPromotionOptionsAllowUnstartedUnlockedTierOneBranches(): void
  {
    $userId = $this->insertUser('promo_t3_enforcer', 'Promotion Tier Three Enforcer User');
    [$bruiserTypeId, ] = $this->loadUnitType('frontline_bruiser_t1');
    [$enforcerTypeId, ] = $this->loadUnitType('frontline_bruiser_t2');
    $unitId = $this->insertUnit($userId, $enforcerTypeId, 10, 0);
    $setTier = $this->pdo->prepare('UPDATE `unit_instances` SET `tier` = 2 WHERE `id` = ?');
    $setTier->execute([$unitId]);
    (new UnitLoadoutService($this->pdo))->initializeUnit($unitId, $enforcerTypeId);
    $insertHistory = $this->pdo->prepare('
      INSERT IGNORE INTO `unit_instance_unlocked_abilities` (`unit_instance_id`, `ability_id`, `source_tier`, `source_unit_type_id`)
      VALUES (?, ?, 1, ?)
    ');
    $insertHistory->execute([$unitId, 'heavy_strike', $bruiserTypeId]);
    $insertHistory->execute([$unitId, 'thick_hide', $bruiserTypeId]);
    $unlockService = new UserUnlockService($this->pdo);
    $unlockService->grant($userId, UserUnlockService::NAMESPACE_UNIT_TYPE, 'frontline_bruiser_t1');
    $unlockService->grant($userId, UserUnlockService::NAMESPACE_UNIT_TYPE, 'support_banner_t1');

    $_SESSION['user_id'] = $userId;

    $controller = new GameplayController();
    $response = $this->invoke(fn() => $controller->getPromotionOptions((string)$unitId));

    $this->assertSame(200, $response['status'], json_encode($response['body']));
    $options = $response['body']['data']['options'] ?? null;
    $this->assertIsArray($options);
    $this->assertCount(2, $options);
    $this->assertSame('chain', (string)($options[0]['mode'] ?? ''));
    $this->assertSame('Juggernaut', (string)($options[0]['target_unit_type_name'] ?? ''));
    $this->assertSame(3, (int)($options[0]['target_tier'] ?? 0));

    $sideways = $options[1] ?? [];
    $this->assertSame('sideways', (string)($sideways['mode'] ?? ''));
    $this->assertSame('Bannerbearer', (string)($sideways['target_unit_type_name'] ?? ''));
    $this->assertSame(3, (int)($sideways['target_tier'] ?? 0));
  }

  public function testPromotionBackfillsCurrentCatalogBeforeTypeSwapAndKeepsEquippedAbilitiesValid(): void
  {
    $userId = $this->insertUser('promo_backfill', 'Promotion Backfill User');
    [$bruiserTypeId, ] = $this->loadUnitType('frontline_bruiser_t1');
    [$marksmanTypeId, ] = $this->loadUnitType('backline_marksman_t1');
    $primaryId = $this->insertUnit($userId, $bruiserTypeId, 6, 0);
    $secondaryA = $this->insertUnit($userId, $bruiserTypeId, 6, 0);
    $secondaryB = $this->insertUnit($userId, $bruiserTypeId, 6, 0);

    $loadout = new UnitLoadoutService($this->pdo);
    $loadout->initializeUnit($primaryId, $bruiserTypeId);
    $loadout->initializeUnit($secondaryA, $bruiserTypeId);
    $loadout->initializeUnit($secondaryB, $bruiserTypeId);
    $loadout->replaceEquippedAbilities($primaryId, ['basic_attack_melee', 'heavy_strike']);
    $unlockService = new UserUnlockService($this->pdo);
    $unlockService->grant($userId, UserUnlockService::NAMESPACE_UNIT_TYPE, 'frontline_bruiser_t1');
    $unlockService->grant($userId, UserUnlockService::NAMESPACE_UNIT_TYPE, 'backline_marksman_t1');

    $deleteUnlocked = $this->pdo->prepare('DELETE FROM `unit_instance_unlocked_abilities` WHERE `unit_instance_id` = ?');
    $deleteUnlocked->execute([$primaryId]);

    $_SESSION['user_id'] = $userId;
    $_SESSION['csrf_token'] = 'valid_csrf';
    $_SERVER['HTTP_X_CSRF_TOKEN'] = 'valid_csrf';
    $this->setJsonBody([
      'primary_unit_instance_id' => (string)$primaryId,
      'secondary_unit_instance_ids' => [(string)$secondaryA, (string)$secondaryB],
      'destination_unit_type_id' => (string)$marksmanTypeId,
    ]);

    $controller = new GameplayController();
    $response = $this->invoke(fn() => $controller->promoteUnit((string)$primaryId));

    $this->assertSame(200, $response['status'], json_encode($response['body']));

    $unlockedAbilityIds = array_map(
      static fn(array $row): string => (string)$row['ability_id'],
      $this->rows(
        'SELECT `ability_id` FROM `unit_instance_unlocked_abilities` WHERE `unit_instance_id` = ? ORDER BY `ability_id` ASC',
        [$primaryId]
      )
    );
    $equippedAbilityIds = array_map(
      static fn(array $row): string => (string)$row['ability_id'],
      $this->rows(
        'SELECT `ability_id` FROM `unit_instance_equipped_abilities` WHERE `unit_instance_id` = ? ORDER BY `equip_order` ASC, `id` ASC',
        [$primaryId]
      )
    );

    $this->assertContains('heavy_strike', $unlockedAbilityIds);
    $this->assertContains('thick_hide', $unlockedAbilityIds);
    $this->assertContains('aimed_shot', $unlockedAbilityIds);
    $this->assertContains('sharpshooter', $unlockedAbilityIds);
    $this->assertSame(['basic_attack_melee', 'heavy_strike'], $equippedAbilityIds);
    foreach ($equippedAbilityIds as $abilityId) {
      $this->assertContains($abilityId, $unlockedAbilityIds);
    }
  }

  public function testReplaceEquippedAbilitiesEndpointRejectsActiveRunUnitsEvenWithRestContext(): void
  {
    $userId = $this->insertUser('load_rest', 'Loadout Rest User');
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

    $this->setJsonBody(['ability_ids' => ['basic_attack_melee', 'heavy_strike']]);
    $blocked = $this->invoke(fn() => $controller->replaceEquippedAbilities((string)$unitId));
    $this->assertSame(409, $blocked['status']);
    $this->assertSame('active_run_unit_locked', (string)($blocked['body']['error']['code'] ?? ''));

    $this->setJsonBody([
      'ability_ids' => ['basic_attack_melee', 'heavy_strike'],
      'run_id' => (string)$runId,
      'node_id' => (string)$restNodeId,
    ]);
    $stillBlocked = $this->invoke(fn() => $controller->replaceEquippedAbilities((string)$unitId));
    $this->assertSame(409, $stillBlocked['status']);
    $this->assertSame('active_run_unit_locked', (string)($stillBlocked['body']['error']['code'] ?? ''));
  }

  public function testAbilitySlotDiceEndpointsAssignAndClearBinding(): void
  {
    $userId = $this->insertUser('slotdice', 'Slot Dice User');
    [$unitTypeId, ] = $this->loadUnitType('frontline_bruiser_t1');
    $unitId = $this->insertUnit($userId, $unitTypeId, 1, 0);
    (new UnitLoadoutService($this->pdo))->initializeUnit($unitId, $unitTypeId);
    $diceId = $this->insertDiceInstance($userId, $this->pickAnyDiceDefinitionId());

    $_SESSION['user_id'] = $userId;
    $_SESSION['csrf_token'] = 'valid_csrf';
    $_SERVER['HTTP_X_CSRF_TOKEN'] = 'valid_csrf';

    $controller = new GameplayController();

    $this->setJsonBody(['dice_instance_id' => (string)$diceId]);
    $assign = $this->invoke(fn() => $controller->assignAbilitySlotDie((string)$unitId, 'heavy_strike', '0'));
    $this->assertSame(200, $assign['status'], json_encode($assign['body']));
    $abilityDice = $assign['body']['data']['ability_dice'] ?? null;
    $this->assertIsArray($abilityDice);
    $this->assertTrue(
      count(array_filter($abilityDice, static fn(array $row): bool => (string)$row['ability_id'] === 'heavy_strike' && (int)$row['slot_index'] === 0 && (string)$row['dice_instance_id'] === (string)$diceId)) === 1
    );

    $this->setJsonBody([]);
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

  public function testAbilitySlotDiceAssignAllowsUnlockedActiveAbilityOutsideCurrentLoadout(): void
  {
    $userId = $this->insertUser('slot_ool', 'Outside Loadout User');
    [$unitTypeId, ] = $this->loadUnitType('frontline_bruiser_t1');
    $unitId = $this->insertUnit($userId, $unitTypeId, 1, 0);
    $loadout = new UnitLoadoutService($this->pdo);
    $loadout->initializeUnit($unitId, $unitTypeId);
    $loadout->replaceEquippedAbilities($unitId, ['basic_attack_melee', 'basic_attack_melee']);
    $diceId = $this->insertDiceInstance($userId, $this->pickAnyDiceDefinitionId());

    $_SESSION['user_id'] = $userId;
    $_SESSION['csrf_token'] = 'valid_csrf';
    $_SERVER['HTTP_X_CSRF_TOKEN'] = 'valid_csrf';

    $controller = new GameplayController();

    $this->setJsonBody(['dice_instance_id' => (string)$diceId]);
    $assign = $this->invoke(fn() => $controller->assignAbilitySlotDie((string)$unitId, 'heavy_strike', '0'));

    $this->assertSame(200, $assign['status'], json_encode($assign['body']));
    $this->assertSame(
      ['basic_attack_melee', 'basic_attack_melee'],
      array_map(
        static fn(array $row): string => (string)$row['ability_id'],
        $this->rows(
          'SELECT `ability_id` FROM `unit_instance_equipped_abilities` WHERE `unit_instance_id` = ? ORDER BY `equip_order` ASC, `id` ASC',
          [$unitId]
        )
      )
    );
    $this->assertSame(
      '1',
      (string)$this->scalar(
        'SELECT COUNT(*) FROM `unit_ability_dice` WHERE `unit_instance_id` = ? AND `ability_id` = ? AND `slot_index` = 0 AND `dice_instance_id` = ?',
        [$unitId, 'heavy_strike', $diceId]
      )
    );
  }

  public function testAbilitySlotDiceAssignRejectsActiveRunUnitsEvenWithRestContext(): void
  {
    $userId = $this->insertUser('slotrest', 'Slot Dice Rest User');
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

    $this->setJsonBody(['dice_instance_id' => (string)$diceId]);
    $blocked = $this->invoke(fn() => $controller->assignAbilitySlotDie((string)$unitId, 'heavy_strike', '0'));
    $this->assertSame(409, $blocked['status']);
    $this->assertSame('active_run_unit_locked', (string)($blocked['body']['error']['code'] ?? ''));

    $this->setJsonBody([
      'dice_instance_id' => (string)$diceId,
      'run_id' => (string)$runId,
      'node_id' => (string)$restNodeId,
    ]);
    $stillBlocked = $this->invoke(fn() => $controller->assignAbilitySlotDie((string)$unitId, 'heavy_strike', '0'));
    $this->assertSame(409, $stillBlocked['status']);
    $this->assertSame('active_run_unit_locked', (string)($stillBlocked['body']['error']['code'] ?? ''));
  }

  public function testSellDiceIgnoresLegacyUnitDiceRowsButBlocksAbilitySlotAssignments(): void
  {
    $userId = $this->insertUser('sell_dice', 'Sell Dice User');
    [$unitTypeId, ] = $this->loadUnitType('frontline_bruiser_t1');
    $unitId = $this->insertUnit($userId, $unitTypeId, 1, 0);
    $loadout = new UnitLoadoutService($this->pdo);
    $loadout->initializeUnit($unitId, $unitTypeId);

    $legacyDiceId = $this->insertDiceInstance($userId, $this->pickAnyDiceDefinitionId());
    $abilityDiceId = $this->insertDiceInstance($userId, $this->pickAnyDiceDefinitionId());

    $legacyInsert = $this->pdo->prepare('
      INSERT INTO `unit_dice` (`unit_instance_id`, `dice_instance_id`, `slot_index`)
      VALUES (?, ?, 0)
    ');
    $legacyInsert->execute([$unitId, $legacyDiceId]);

    $loadout->assignDieToAbilitySlot($unitId, 'heavy_strike', 0, $abilityDiceId);

    $_SESSION['user_id'] = $userId;
    $_SESSION['csrf_token'] = 'valid_csrf';
    $_SERVER['HTTP_X_CSRF_TOKEN'] = 'valid_csrf';

    $controller = new GameplayController();

    $legacySell = $this->invoke(fn() => $controller->sellDice((string)$legacyDiceId));
    $this->assertSame(200, $legacySell['status'], json_encode($legacySell['body']));
    $this->assertSame(
      '0',
      (string)$this->scalar('SELECT COUNT(*) FROM `dice_instances` WHERE `id` = ?', [$legacyDiceId])
    );

    $abilitySell = $this->invoke(fn() => $controller->sellDice((string)$abilityDiceId));
    $this->assertSame(400, $abilitySell['status'], json_encode($abilitySell['body']));
    $this->assertSame('validation_error', (string)($abilitySell['body']['error']['code'] ?? ''));
    $this->assertSame('Equipped dice cannot be sold.', (string)($abilitySell['body']['error']['message'] ?? ''));
  }

  public function testLegacyUnitLevelDiceEndpointsAreRejected(): void
  {
    $userId = $this->insertUser('legacy_dice', 'Legacy Dice User');
    [$unitTypeId, ] = $this->loadUnitType('frontline_bruiser_t1');
    $unitId = $this->insertUnit($userId, $unitTypeId, 1, 0);
    $diceId = $this->insertDiceInstance($userId, $this->pickAnyDiceDefinitionId());

    $_SESSION['user_id'] = $userId;
    $_SESSION['csrf_token'] = 'valid_csrf';
    $_SERVER['HTTP_X_CSRF_TOKEN'] = 'valid_csrf';
    $this->setJsonBody(['dice_instance_id' => (string)$diceId]);

    $controller = new GameplayController();

    $equip = $this->invoke(fn() => $controller->equipDice((string)$unitId));
    $this->assertSame(400, $equip['status'], json_encode($equip['body']));
    $this->assertSame('validation_error', (string)($equip['body']['error']['code'] ?? ''));
    $this->assertSame(
      'Legacy unit-level dice equip is no longer supported. Assign dice to ability slots instead.',
      (string)($equip['body']['error']['message'] ?? '')
    );

    $this->setJsonBody(['dice_instance_id' => (string)$diceId]);
    $unequip = $this->invoke(fn() => $controller->unequipDice((string)$unitId));
    $this->assertSame(400, $unequip['status'], json_encode($unequip['body']));
    $this->assertSame('validation_error', (string)($unequip['body']['error']['code'] ?? ''));
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
