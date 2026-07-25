<?php
declare(strict_types=1);

namespace DiceGoblins\Tests\Integration;

use DiceGoblins\Controllers\WrongMachineController;
use DiceGoblins\Services\LineageUnlockService;
use DiceGoblins\Services\UserUnlockService;
use DiceGoblins\Tests\Support\IntegrationTestCase;

final class WrongMachineReconstructionControllerTest extends IntegrationTestCase
{
  protected function integrationSkipMessage(): string
  {
    return 'Set TEST_DB_DSN to run Wrong Machine reconstruction integration tests.';
  }

  public function testPreviewRequiresWrongMachineBeforeReconstructionIsAvailable(): void
  {
    $userId = $this->insertUser('qa_wrong_machine_preview', 'QA Wrong Machine Preview');
    $_SESSION['user_id'] = $userId;

    $response = $this->invoke(fn() => (new WrongMachineController())->reconstructions());

    $this->assertSame(200, $response['status']);
    $data = is_array($response['body']['data'] ?? null) ? $response['body']['data'] : [];
    $this->assertFalse((bool)($data['feature_unlocked'] ?? true));
    $recipe = $data['reconstructions'][0] ?? [];
    $this->assertSame(LineageUnlockService::PIG_KIN, (string)($recipe['lineage_slug'] ?? ''));
    $this->assertFalse((bool)($recipe['can_reconstruct'] ?? true));
  }

  public function testReconstructSpendsCostsGrantsLineageAndTutorialUnit(): void
  {
    $userId = $this->preparedUser();
    $_SESSION['user_id'] = $userId;
    $_SESSION['csrf_token'] = 'valid_csrf';
    $_SERVER['HTTP_X_CSRF_TOKEN'] = 'valid_csrf';

    $this->setJsonBody(['lineage_slug' => LineageUnlockService::PIG_KIN]);
    $response = $this->invoke(fn() => (new WrongMachineController())->reconstruct());

    $this->assertSame(200, $response['status'], json_encode($response['body']));
    $data = is_array($response['body']['data'] ?? null) ? $response['body']['data'] : [];
    $this->assertTrue((bool)($data['newly_reconstructed'] ?? false));
    $this->assertSame(LineageUnlockService::PIG_KIN, (string)($data['lineage']['lineage_slug'] ?? ''));
    $this->assertSame(LineageUnlockService::PIG_KIN, (string)($data['granted_unit']['kin_slug'] ?? ''));

    $this->assertSame('1', (string)$this->scalar(
      'SELECT COUNT(*) FROM `user_unlocks` WHERE `user_id` = ? AND `unlock_namespace` = ? AND `unlock_key` = ?',
      [$userId, UserUnlockService::NAMESPACE_LINEAGE, LineageUnlockService::PIG_KIN]
    ));
    $this->assertSame('5', (string)$this->scalar('SELECT `currency_raw_chaos` FROM `player_state` WHERE `user_id` = ?', [$userId]));
    $this->assertSame(1, $this->ownedItemQuantity($userId, 'pig_ear'));
    $this->assertSame(1, $this->ownedItemQuantity($userId, 'mudking_crown_fragment'));
    $this->assertSame('1', (string)$this->scalar(
      'SELECT COUNT(*) FROM `unit_instances` WHERE `user_id` = ? AND `splice_variant_slug` = ?',
      [$userId, LineageUnlockService::PIG_KIN]
    ));
  }

  public function testDuplicateReconstructDoesNotSpendAgainOrGrantAnotherUnit(): void
  {
    $userId = $this->preparedUser();
    $_SESSION['user_id'] = $userId;
    $_SESSION['csrf_token'] = 'valid_csrf';
    $_SERVER['HTTP_X_CSRF_TOKEN'] = 'valid_csrf';

    $controller = new WrongMachineController();
    $this->setJsonBody(['lineage_slug' => LineageUnlockService::PIG_KIN]);
    $first = $this->invoke(fn() => $controller->reconstruct());
    $this->assertSame(200, $first['status'], json_encode($first['body']));

    $this->setJsonBody(['lineage_slug' => LineageUnlockService::PIG_KIN]);
    $second = $this->invoke(fn() => $controller->reconstruct());

    $this->assertSame(200, $second['status'], json_encode($second['body']));
    $this->assertFalse((bool)($second['body']['data']['newly_reconstructed'] ?? true));
    $this->assertArrayHasKey('granted_unit', $second['body']['data']);
    $this->assertNull($second['body']['data']['granted_unit']);
    $this->assertSame('5', (string)$this->scalar('SELECT `currency_raw_chaos` FROM `player_state` WHERE `user_id` = ?', [$userId]));
    $this->assertSame(1, $this->ownedItemQuantity($userId, 'pig_ear'));
    $this->assertSame(1, $this->ownedItemQuantity($userId, 'mudking_crown_fragment'));
    $this->assertSame('1', (string)$this->scalar(
      'SELECT COUNT(*) FROM `unit_instances` WHERE `user_id` = ? AND `splice_variant_slug` = ?',
      [$userId, LineageUnlockService::PIG_KIN]
    ));
  }

  public function testReconstructRejectsMissingFeatureAndDoesNotSpend(): void
  {
    $userId = $this->preparedUser(false);
    $_SESSION['user_id'] = $userId;
    $_SESSION['csrf_token'] = 'valid_csrf';
    $_SERVER['HTTP_X_CSRF_TOKEN'] = 'valid_csrf';

    $this->setJsonBody(['lineage_slug' => LineageUnlockService::PIG_KIN]);
    $response = $this->invoke(fn() => (new WrongMachineController())->reconstruct());

    $this->assertSame(403, $response['status']);
    $this->assertSame('wrong_machine_locked', (string)($response['body']['error']['code'] ?? ''));
    $this->assertSame('10', (string)$this->scalar('SELECT `currency_raw_chaos` FROM `player_state` WHERE `user_id` = ?', [$userId]));
    $this->assertSame(4, $this->ownedItemQuantity($userId, 'pig_ear'));
  }

  private function preparedUser(bool $withWrongMachine = true): int
  {
    $userId = $this->insertUser('qa_wrong_machine', 'QA Wrong Machine');
    if ($withWrongMachine) {
      $this->grantUnlock($userId, UserUnlockService::NAMESPACE_FEATURE, UserUnlockService::FEATURE_WRONG_MACHINE);
    }

    $this->pdo?->prepare('
      INSERT INTO `player_state` (`user_id`, `currency_soft`, `currency_hard`, `currency_raw_chaos`, `last_login_at`)
      VALUES (?, 0, 0, 10, NULL)
      ON DUPLICATE KEY UPDATE `currency_raw_chaos` = VALUES(`currency_raw_chaos`)
    ')->execute([$userId]);

    $this->grantItem($userId, 'pig_ear', 4);
    $this->grantItem($userId, 'mudking_crown_fragment', 2);

    return $userId;
  }

  private function grantItem(int $userId, string $slug, int $quantity): void
  {
    $itemId = (int)$this->scalar('SELECT `id` FROM `items` WHERE `slug` = ? LIMIT 1', [$slug]);
    $this->pdo?->prepare('
      INSERT INTO `user_items` (`user_id`, `item_id`, `quantity`)
      VALUES (?, ?, ?)
      ON DUPLICATE KEY UPDATE `quantity` = `quantity` + VALUES(`quantity`)
    ')->execute([$userId, $itemId, $quantity]);
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
}
