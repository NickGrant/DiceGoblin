<?php
declare(strict_types=1);

namespace DiceGoblins\Tests\Integration;

use DiceGoblins\Controllers\ApiController;
use DiceGoblins\Tests\Support\IntegrationTestCase;

final class ApiControllerCreateRunDomainErrorsTest extends IntegrationTestCase
{
  protected function integrationSkipMessage(): string
  {
    return 'Set TEST_DB_DSN to run create-run branch integration tests.';
  }

  public function testCreateRunReturnsConflictWhenRunAlreadyActive(): void
  {
    $userId = $this->insertUser('qa_create_run', 'QA Create Run');
    $regionId = $this->insertRegion(5, true, 'qa-region', 'QA Region');
    $this->unlockRegion($userId, $regionId);
    $this->insertTeam($userId, 'QA Squad', true);
    $this->setEnergy($userId, 50, 50);
    $this->insertActiveRun($userId, $regionId, 1234);

    $_SESSION['user_id'] = $userId;
    $_SESSION['csrf_token'] = 'valid_csrf';
    $_SERVER['HTTP_X_CSRF_TOKEN'] = 'valid_csrf';
    $this->setJsonBody(['region_id' => (string)$regionId]);

    $api = new ApiController();
    $res = $this->invoke(fn() => $api->createRun());

    $this->assertSame(409, $res['status']);
    $this->assertSame('run_already_active', (string)($res['body']['error']['code'] ?? ''));
  }

  public function testCreateRunReturnsNotFoundWhenRegionDoesNotExist(): void
  {
    $userId = $this->insertUser('qa_create_run', 'QA Create Run');
    $this->insertTeam($userId, 'QA Squad', true);
    $this->setEnergy($userId, 50, 50);

    $_SESSION['user_id'] = $userId;
    $_SESSION['csrf_token'] = 'valid_csrf';
    $_SERVER['HTTP_X_CSRF_TOKEN'] = 'valid_csrf';
    $this->setJsonBody(['region_id' => '999999']);

    $api = new ApiController();
    $res = $this->invoke(fn() => $api->createRun());

    $this->assertSame(404, $res['status']);
    $this->assertSame('region_not_found', (string)($res['body']['error']['code'] ?? ''));
  }

  public function testCreateRunReturnsForbiddenWhenRegionDisabled(): void
  {
    $userId = $this->insertUser('qa_create_run', 'QA Create Run');
    $regionId = $this->insertRegion(5, false, 'qa-region', 'QA Region');
    $this->insertTeam($userId, 'QA Squad', true);
    $this->setEnergy($userId, 50, 50);

    $_SESSION['user_id'] = $userId;
    $_SESSION['csrf_token'] = 'valid_csrf';
    $_SERVER['HTTP_X_CSRF_TOKEN'] = 'valid_csrf';
    $this->setJsonBody(['region_id' => (string)$regionId]);

    $api = new ApiController();
    $res = $this->invoke(fn() => $api->createRun());

    $this->assertSame(403, $res['status']);
    $this->assertSame('region_disabled', (string)($res['body']['error']['code'] ?? ''));
  }

  public function testCreateRunReturnsForbiddenWhenRegionLocked(): void
  {
    $userId = $this->insertUser('qa_create_run', 'QA Create Run');
    $regionId = $this->insertRegion(5, true, 'qa-region', 'QA Region');
    $this->insertTeam($userId, 'QA Squad', true);
    $this->setEnergy($userId, 50, 50);

    $_SESSION['user_id'] = $userId;
    $_SESSION['csrf_token'] = 'valid_csrf';
    $_SERVER['HTTP_X_CSRF_TOKEN'] = 'valid_csrf';
    $this->setJsonBody(['region_id' => (string)$regionId]);

    $api = new ApiController();
    $res = $this->invoke(fn() => $api->createRun());

    $this->assertSame(403, $res['status']);
    $this->assertSame('region_locked', (string)($res['body']['error']['code'] ?? ''));
  }

  public function testCreateRunReturnsValidationErrorWhenNoActiveSquadExists(): void
  {
    $userId = $this->insertUser('qa_create_run', 'QA Create Run');
    $regionId = $this->insertRegion(5, true, 'qa-region', 'QA Region');
    $this->unlockRegion($userId, $regionId);
    $this->setEnergy($userId, 50, 50);

    $_SESSION['user_id'] = $userId;
    $_SESSION['csrf_token'] = 'valid_csrf';
    $_SERVER['HTTP_X_CSRF_TOKEN'] = 'valid_csrf';
    $this->setJsonBody(['region_id' => (string)$regionId]);

    $api = new ApiController();
    $res = $this->invoke(fn() => $api->createRun());

    $this->assertSame(400, $res['status']);
    $this->assertSame('validation_error', (string)($res['body']['error']['code'] ?? ''));
  }

  public function testCreateRunReturnsInsufficientEnergyWhenCostCannotBePaid(): void
  {
    $userId = $this->insertUser('qa_create_run', 'QA Create Run');
    $regionId = $this->insertRegion(20, true, 'qa-region', 'QA Region');
    $this->unlockRegion($userId, $regionId);
    $this->insertTeam($userId, 'QA Squad', true);
    $this->setEnergy($userId, 0, 50);
    $this->assertSame('1', (string)$this->scalar('SELECT COUNT(*) FROM `teams` WHERE `user_id` = ? AND `is_active` = 1', [$userId]));
    $this->assertSame('1', (string)$this->scalar('SELECT COUNT(*) FROM `region_unlocks` WHERE `user_id` = ? AND `region_id` = ?', [$userId, $regionId]));
    $this->assertSame('0', (string)$this->scalar('SELECT `energy_current` FROM `energy_state` WHERE `user_id` = ?', [$userId]));

    $_SESSION['user_id'] = $userId;
    $_SESSION['csrf_token'] = 'valid_csrf';
    $_SERVER['HTTP_X_CSRF_TOKEN'] = 'valid_csrf';
    $this->setJsonBody(['region_id' => (string)$regionId]);

    $api = new ApiController();
    $res = $this->invoke(fn() => $api->createRun());

    $this->assertSame(409, $res['status'], json_encode($res['body']));
    $this->assertSame('insufficient_energy', (string)($res['body']['error']['code'] ?? ''), json_encode($res['body']));
  }

  private function insertActiveRun(int $userId, int $regionId, int $seed): void
  {
    $this->insertRun($userId, $regionId, $seed, 'active');
  }
}
