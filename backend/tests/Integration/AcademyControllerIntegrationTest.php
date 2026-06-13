<?php
declare(strict_types=1);

namespace DiceGoblins\Tests\Integration;

use DiceGoblins\Controllers\AcademyController;
use DiceGoblins\Tests\Support\IntegrationTestCase;

final class AcademyControllerIntegrationTest extends IntegrationTestCase
{
  protected function integrationSkipMessage(): string
  {
    return 'Set TEST_DB_DSN to run academy integration tests.';
  }

  public function testCatalogShowsTierOneUnlocksWithExpectedPricing(): void
  {
    $userId = $this->insertUser('qa_academy', 'QA Academy');
    $_SESSION['user_id'] = $userId;

    $controller = new AcademyController();
    $response = $this->invoke(fn() => $controller->catalog());

    $this->assertSame(200, $response['status']);
    $data = is_array($response['body']['data'] ?? null) ? $response['body']['data'] : [];
    $unitUnlocks = is_array($data['unit_unlocks'] ?? null) ? $data['unit_unlocks'] : [];

    $bySlug = [];
    foreach ($unitUnlocks as $row) {
      $bySlug[(string)($row['unit_type_slug'] ?? '')] = $row;
    }

    $this->assertSame(500, (int)($bySlug['frontline_guardian_t1']['cost'] ?? 0));
    $this->assertSame(250, (int)($bySlug['support_banner_t1']['cost'] ?? 0));
    $this->assertTrue((bool)($bySlug['frontline_bruiser_t1']['is_unlocked'] ?? false));
    $this->assertTrue((bool)($bySlug['backline_marksman_t1']['is_unlocked'] ?? false));
    $this->assertFalse((bool)($bySlug['support_banner_t1']['is_unlocked'] ?? true));
  }

  public function testUnlockUnitTypeSpendsCurrencyAndGrantsUnlock(): void
  {
    $userId = $this->insertUser('qa_academy_buy', 'QA Academy Buy');
    $_SESSION['user_id'] = $userId;
    $_SESSION['csrf_token'] = 'valid_csrf';
    $_SERVER['HTTP_X_CSRF_TOKEN'] = 'valid_csrf';

    $this->pdo?->prepare('UPDATE `player_state` SET `currency_soft` = 300 WHERE `user_id` = ?')->execute([$userId]);

    $controller = new AcademyController();
    $this->setJsonBody([
      'unit_type_slug' => 'support_banner_t1',
    ]);
    $response = $this->invoke(fn() => $controller->unlockUnitType());

    $this->assertSame(200, $response['status']);
    $this->assertSame(250, (int)($response['body']['data']['cost'] ?? 0));
    $this->assertSame(50, (int)($response['body']['data']['currency_soft'] ?? 0));
    $this->assertSame(
      '1',
      (string)$this->scalar(
        "SELECT COUNT(*) FROM `user_unlocks` WHERE `user_id` = ? AND `unlock_namespace` = 'unit_type' AND `unlock_key` = 'support_banner_t1'",
        [$userId]
      )
    );
  }
}
