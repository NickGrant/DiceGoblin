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

  public function testUpdateTeamRejectsSquadsOverCurrentUnitCap(): void
  {
    $ownerId = $this->insertUser('qa_ep', 'QA Endpoint');
    $teamId = $this->insertTeam($ownerId, 'Owned Team', true);
    $unitIds = [
      $this->insertUnitInstance($ownerId),
      $this->insertUnitInstance($ownerId),
      $this->insertUnitInstance($ownerId),
      $this->insertUnitInstance($ownerId),
      $this->insertUnitInstance($ownerId),
    ];

    $_SESSION['user_id'] = $ownerId;
    $_SESSION['csrf_token'] = 'valid_token';
    $_SERVER['HTTP_X_CSRF_TOKEN'] = 'valid_token';
    $this->setJsonBody([
      'name' => 'Owned Team',
      'unit_ids' => array_map('strval', $unitIds),
      'formation' => [],
    ]);

    $controller = new TeamController();
    $response = $this->invoke(fn() => $controller->updateTeam((string)$teamId));

    $this->assertSame(400, $response['status']);
    $this->assertSame('validation_error', $response['body']['error']['code'] ?? null);
    $this->assertSame('Squads are currently capped at 4 units.', $response['body']['error']['message'] ?? null);
  }

  private function insertUnitInstance(int $userId): int
  {
    $unitTypeId = $this->insertUnitType();
    $stmt = $this->pdo?->prepare('
      INSERT INTO `unit_instances` (`user_id`, `unit_type_id`, `tier`, `level`, `xp`, `locked`)
      VALUES (?, ?, 1, 1, 0, 0)
    ');
    $stmt?->execute([$userId, $unitTypeId]);
    return (int)$this->pdo?->lastInsertId();
  }

  private function insertUnitType(): int
  {
    $token = bin2hex(random_bytes(6));
    $stmt = $this->pdo?->prepare('
      INSERT INTO `unit_types`
      (`slug`, `name`, `role`, `base_stats_json`, `ability_set_json`, `max_level`, `attack_per_level`, `defense_per_level`, `max_hp_per_level`)
      VALUES (?, ?, ?, ?, ?, 50, 1, 1, 5)
    ');
    $stmt?->execute([
      "qa_endpoint_unit_$token",
      "QA Endpoint Unit $token",
      'fighter',
      json_encode([
        'attack' => 5,
        'defense' => 3,
        'max_hp' => 20,
        'formation' => ['w' => 1, 'h' => 1],
      ], JSON_THROW_ON_ERROR),
      json_encode(['active' => [], 'passive' => []], JSON_THROW_ON_ERROR),
    ]);

    return (int)$this->pdo?->lastInsertId();
  }
}
