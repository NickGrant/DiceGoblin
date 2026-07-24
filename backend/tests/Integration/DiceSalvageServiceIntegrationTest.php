<?php
declare(strict_types=1);

namespace DiceGoblins\Tests\Integration;

use DiceGoblins\Controllers\GameplayController;
use DiceGoblins\Services\DiceValuationService;
use DiceGoblins\Services\UnitLoadoutService;
use DiceGoblins\Tests\Support\BattleFlowIntegrationCase;

final class DiceSalvageServiceIntegrationTest extends BattleFlowIntegrationCase
{
  protected function integrationSkipMessage(): string
  {
    return 'Set TEST_DB_DSN to run dice salvage integration tests.';
  }

  public function testSalvageDiceAwardsRawChaosAndDeletesUnequippedDie(): void
  {
    $userId = $this->insertUser('salvage_dice', 'Salvage Dice User');
    $diceDefinitionId = $this->pickAnyDiceDefinitionId();
    $diceId = $this->insertDiceInstance($userId, $diceDefinitionId);

    $_SESSION['user_id'] = $userId;
    $_SESSION['csrf_token'] = 'valid_csrf';
    $_SERVER['HTTP_X_CSRF_TOKEN'] = 'valid_csrf';

    $controller = new GameplayController();
    $response = $this->invoke(fn() => $controller->salvageDice((string)$diceId));

    $expectedAward = $this->expectedRawChaosAward($diceDefinitionId);
    $this->assertSame(200, $response['status'], json_encode($response['body']));
    $this->assertSame((string)$diceId, (string)($response['body']['data']['dice_id'] ?? ''));
    $this->assertSame($expectedAward, (int)($response['body']['data']['raw_chaos_awarded'] ?? 0));
    $this->assertSame($expectedAward, (int)($response['body']['data']['currency_raw_chaos'] ?? 0));
    $this->assertSame(
      '0',
      (string)$this->scalar('SELECT COUNT(*) FROM `dice_instances` WHERE `id` = ?', [$diceId])
    );
    $this->assertSame(
      (string)$expectedAward,
      (string)$this->scalar('SELECT `currency_raw_chaos` FROM `player_state` WHERE `user_id` = ?', [$userId])
    );
  }

  public function testSalvageDiceBlocksEquippedDice(): void
  {
    $userId = $this->insertUser('salvage_equipped', 'Salvage Equipped User');
    [$unitTypeId, ] = $this->loadUnitType('frontline_bruiser_t1');
    $unitId = $this->insertUnit($userId, $unitTypeId, 1, 0);
    $loadout = new UnitLoadoutService($this->pdo);
    $loadout->initializeUnit($unitId, $unitTypeId);
    $diceId = $this->insertDiceInstance($userId, $this->pickAnyDiceDefinitionId());
    $loadout->assignDieToAbilitySlot($unitId, 'heavy_strike', 0, $diceId);

    $_SESSION['user_id'] = $userId;
    $_SESSION['csrf_token'] = 'valid_csrf';
    $_SERVER['HTTP_X_CSRF_TOKEN'] = 'valid_csrf';

    $controller = new GameplayController();
    $response = $this->invoke(fn() => $controller->salvageDice((string)$diceId));

    $this->assertSame(400, $response['status'], json_encode($response['body']));
    $this->assertSame('validation_error', (string)($response['body']['error']['code'] ?? ''));
    $this->assertSame('Equipped dice cannot be salvaged.', (string)($response['body']['error']['message'] ?? ''));
    $this->assertSame(
      '1',
      (string)$this->scalar('SELECT COUNT(*) FROM `dice_instances` WHERE `id` = ?', [$diceId])
    );
  }

  /** @return array{0:int,1:string} */
  private function loadUnitType(string $slug): array
  {
    $rows = $this->rows(
      'SELECT `id`, `slug` FROM `unit_types` WHERE `slug` = ? LIMIT 1',
      [$slug]
    );
    $this->assertCount(1, $rows, 'Expected seeded unit type to exist.');
    return [(int)$rows[0]['id'], (string)$rows[0]['slug']];
  }

  private function expectedRawChaosAward(int $diceDefinitionId): int
  {
    $rows = $this->rows(
      'SELECT `sides`, `rarity` FROM `dice_definitions` WHERE `id` = ? LIMIT 1',
      [$diceDefinitionId]
    );
    $this->assertCount(1, $rows);

    return DiceValuationService::calculateRawChaosSalvageValue(
      (int)$rows[0]['sides'],
      (string)$rows[0]['rarity'],
      []
    );
  }
}
