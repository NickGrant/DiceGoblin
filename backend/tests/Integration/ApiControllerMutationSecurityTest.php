<?php
declare(strict_types=1);

/**
 * File: C:\xampp\htdocs\dice-goblin\backend\tests\Integration\ApiControllerMutationSecurityTest.php
 * Purpose: Project PHP module.
 */

namespace DiceGoblins\Tests\Integration;

use DiceGoblins\Controllers\ApiController;
use DiceGoblins\Services\UserUnlockService;
use DiceGoblins\Tests\Support\IntegrationTestCase;

final class ApiControllerMutationSecurityTest extends IntegrationTestCase
{
  protected function integrationSkipMessage(): string
  {
    return 'Set TEST_DB_DSN to run endpoint integration tests.';
  }

  public function testCreateRunRequiresAuthentication(): void
  {
    $controller = new ApiController();
    $response = $this->invoke(fn() => $controller->createRun());

    $this->assertSame(401, $response['status']);
    $this->assertSame('unauthorized', $response['body']['error']['code'] ?? null);
  }

  public function testCreateRunRejectsInvalidCsrfWhenAuthenticated(): void
  {
    $userId = $this->insertUser('qa_api', 'QA API');
    $_SESSION['user_id'] = $userId;
    $_SESSION['csrf_token'] = 'expected_token';
    $_SERVER['HTTP_X_CSRF_TOKEN'] = 'wrong_token';

    $controller = new ApiController();
    $response = $this->invoke(fn() => $controller->createRun());

    $this->assertSame(403, $response['status']);
    $this->assertSame('csrf_invalid', $response['body']['error']['code'] ?? null);
  }

  public function testMarkDialogueSeenUsesRouteParameterAndPersistsUnlock(): void
  {
    $userId = $this->insertUser('qa_dialogue', 'QA Dialogue');
    $_SESSION['user_id'] = $userId;
    $_SESSION['csrf_token'] = 'expected_token';
    $_SERVER['HTTP_X_CSRF_TOKEN'] = 'expected_token';

    $controller = new ApiController();
    $response = $this->invoke(fn() => $controller->markDialogueSeen('start-run-kickoff'));

    $this->assertSame(200, $response['status'], json_encode($response['body']));
    $this->assertSame(true, $response['body']['ok'] ?? null);
    $this->assertSame('start-run-kickoff', $response['body']['data']['dialogue_id'] ?? null);

    $count = (int)$this->scalar(
      'SELECT COUNT(*) FROM `user_unlocks` WHERE `user_id` = ? AND `unlock_namespace` = ? AND `unlock_key` = ?',
      [$userId, UserUnlockService::NAMESPACE_DIALOGUE, 'start-run-kickoff'],
    );
    $this->assertSame(1, $count);
  }
}
