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

  public function testCreateRunRejectsActiveSquadsOverCurrentUnitCap(): void
  {
    $userId = $this->insertUser('qa_create_run', 'QA Create Run');
    $regionId = $this->insertRegion(5, true, 'qa-region', 'QA Region');
    $teamId = $this->insertTeam($userId, 'QA Squad', true);
    $this->unlockRegion($userId, $regionId);
    $this->setEnergy($userId, 50, 50);

    for ($index = 0; $index < 5; $index++) {
      $unitId = $this->insertUnitInstance($userId);
      $stmt = $this->pdo?->prepare('INSERT INTO `team_units` (`team_id`, `unit_instance_id`) VALUES (?, ?)');
      $stmt?->execute([$teamId, $unitId]);
    }

    $_SESSION['user_id'] = $userId;
    $_SESSION['csrf_token'] = 'valid_csrf';
    $_SERVER['HTTP_X_CSRF_TOKEN'] = 'valid_csrf';
    $this->setJsonBody(['region_id' => (string)$regionId]);

    $api = new ApiController();
    $res = $this->invoke(fn() => $api->createRun());

    $this->assertSame(409, $res['status']);
    $this->assertSame('validation_error', (string)($res['body']['error']['code'] ?? ''));
    $this->assertSame(
      'Active squad exceeds your current 4-unit cap. Trim the squad before starting a run.',
      (string)($res['body']['error']['message'] ?? '')
    );
  }

  public function testCreateRunWithAbandonActiveAppliesSameCleanupSemanticsAsExplicitAbandon(): void
  {
    $userId = $this->insertUser('qa_create_run', 'QA Create Run');
    $oldRegionId = $this->insertRegion(5, true, 'qa-old-region', 'QA Old Region');
    $newRegionId = $this->insertRegion(5, true, 'qa-new-region', 'QA New Region');
    $this->unlockRegion($userId, $oldRegionId);
    $this->unlockRegion($userId, $newRegionId);
    $teamId = $this->insertTeam($userId, 'QA Squad', true);
    $this->setEnergy($userId, 50, 50);

    $runId = $this->insertRun($userId, $oldRegionId, 6789, 'active');
    $unitId = $this->insertUnitInstance($userId, 18);
    $stmt = $this->pdo?->prepare('INSERT INTO `team_units` (`team_id`, `unit_instance_id`) VALUES (?, ?)');
    $stmt?->execute([$teamId, $unitId]);
    $this->insertRunUnitStateRow($runId, $unitId, 0, true);

    $_SESSION['user_id'] = $userId;
    $_SESSION['csrf_token'] = 'valid_csrf';
    $_SERVER['HTTP_X_CSRF_TOKEN'] = 'valid_csrf';
    $this->setJsonBody([
      'region_id' => (string)$newRegionId,
      'abandon_active' => true,
    ]);

    $api = new ApiController();
    $res = $this->invoke(fn() => $api->createRun());

    $this->assertSame(200, $res['status'], json_encode($res['body']));
    $this->assertSame('abandoned', (string)$this->scalar('SELECT `status` FROM `region_runs` WHERE `id` = ?', [$runId]));
    $this->assertSame('0', (string)$this->scalar('SELECT `xp` FROM `unit_instances` WHERE `id` = ?', [$unitId]));

    $stmt = $this->pdo?->prepare(
      'SELECT `current_hp`, `is_defeated`, `cooldowns_json`, `status_effects_json`
       FROM `run_unit_state`
       WHERE `run_id` = ? AND `unit_instance_id` = ?'
    );
    $stmt?->execute([$runId, $unitId]);
    $state = $stmt?->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    $this->assertCount(1, $state);
    $this->assertGreaterThan(0, (int)$state[0]['current_hp']);
    $this->assertSame('0', (string)$state[0]['is_defeated']);
    $this->assertSame('{}', (string)$state[0]['cooldowns_json']);
    $this->assertSame('[]', (string)$state[0]['status_effects_json']);

    $activeRuns = (int)$this->scalar(
      'SELECT COUNT(*) FROM `region_runs` WHERE `user_id` = ? AND `status` = \'active\'',
      [$userId]
    );
    $this->assertSame(1, $activeRuns);
  }

  private function insertActiveRun(int $userId, int $regionId, int $seed): void
  {
    $this->insertRun($userId, $regionId, $seed, 'active');
  }

  private function insertUnitInstance(int $userId, int $xp = 0): int
  {
    $unitTypeId = $this->insertUnitType();
    $stmt = $this->pdo?->prepare('
      INSERT INTO `unit_instances` (`user_id`, `unit_type_id`, `tier`, `level`, `xp`, `locked`)
      VALUES (?, ?, 1, 1, ?, 0)
    ');
    $stmt?->execute([$userId, $unitTypeId, $xp]);
    return (int)$this->pdo?->lastInsertId();
  }

  private function insertRunUnitStateRow(int $runId, int $unitId, int $hp, bool $isDefeated): void
  {
    $stmt = $this->pdo?->prepare('
      INSERT INTO `run_unit_state` (`run_id`, `unit_instance_id`, `current_hp`, `is_defeated`, `cooldowns_json`, `status_effects_json`)
      VALUES (?, ?, ?, ?, ?, ?)
    ');
    $stmt?->execute([$runId, $unitId, $hp, $isDefeated ? 1 : 0, '{}', '[]']);
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
      "qa_create_unit_$token",
      "QA Create Unit $token",
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
