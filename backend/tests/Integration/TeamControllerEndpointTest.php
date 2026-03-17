<?php
declare(strict_types=1);

/**
 * File: C:\xampp\htdocs\dice-goblin\backend\tests\Integration\TeamControllerEndpointTest.php
 * Purpose: Project PHP module.
 */

namespace DiceGoblins\Tests\Integration;

use DiceGoblins\Controllers\TeamController;
use DiceGoblins\Tests\Support\IntegrationTestCase;

final class TeamControllerEndpointTest extends IntegrationTestCase
{
  protected function integrationSkipMessage(): string
  {
    return 'Set TEST_DB_DSN to run endpoint integration tests.';
  }

  public function testCreateTeamRequiresAuthentication(): void
  {
    $controller = new TeamController();
    $response = $this->invoke(fn() => $controller->createTeam());

    $this->assertSame(401, $response['status']);
    $this->assertSame('unauthorized', $response['body']['error']['code'] ?? null);
  }

  public function testCreateTeamRejectsInvalidCsrfWhenAuthenticated(): void
  {
    $userId = $this->insertUser('qa_ep', 'QA Endpoint');
    $_SESSION['user_id'] = $userId;
    $_SESSION['csrf_token'] = 'expected_token';
    $_SERVER['HTTP_X_CSRF_TOKEN'] = 'wrong_token';

    $controller = new TeamController();
    $response = $this->invoke(fn() => $controller->createTeam());

    $this->assertSame(403, $response['status']);
    $this->assertSame('csrf_invalid', $response['body']['error']['code'] ?? null);
  }

  public function testActivateTeamRejectsCrossUserOwnership(): void
  {
    $ownerId = $this->insertUser('qa_ep', 'QA Endpoint');
    $otherUserId = $this->insertUser('qa_ep', 'QA Endpoint');
    $teamId = $this->insertTeam($ownerId, 'Owned Team', true);

    $_SESSION['user_id'] = $otherUserId;
    $_SESSION['csrf_token'] = 'valid_token';
    $_SERVER['HTTP_X_CSRF_TOKEN'] = 'valid_token';

    $controller = new TeamController();
    $response = $this->invoke(fn() => $controller->activateTeam((string)$teamId));

    $this->assertSame(400, $response['status']);
    $this->assertSame('validation_error', $response['body']['error']['code'] ?? null);
    $this->assertStringContainsString('not owned', (string)($response['body']['error']['message'] ?? ''));
  }

  public function testUpdateTeamRejectsInvalidCsrfWhenAuthenticated(): void
  {
    $ownerId = $this->insertUser('qa_ep', 'QA Endpoint');
    $teamId = $this->insertTeam($ownerId, 'Owned Team', true);

    $_SESSION['user_id'] = $ownerId;
    $_SESSION['csrf_token'] = 'expected_token';
    $_SERVER['HTTP_X_CSRF_TOKEN'] = 'wrong_token';

    $controller = new TeamController();
    $response = $this->invoke(fn() => $controller->updateTeam((string)$teamId));

    $this->assertSame(403, $response['status']);
    $this->assertSame('csrf_invalid', $response['body']['error']['code'] ?? null);
  }
}
