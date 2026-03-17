<?php
declare(strict_types=1);

/**
 * File: C:\xampp\htdocs\dice-goblin\backend\tests\Integration\RunBattleIdempotencyTest.php
 * Purpose: Project PHP module.
 */

namespace DiceGoblins\Tests\Integration;

use DiceGoblins\Controllers\BattleController;
use DiceGoblins\Controllers\RunNodeController;
use DiceGoblins\Tests\Support\IntegrationTestCase;

final class RunBattleIdempotencyTest extends IntegrationTestCase
{
  protected function integrationSkipMessage(): string
  {
    return 'Set TEST_DB_DSN to run idempotency regression tests.';
  }

  public function testResolveNodeAndClaimBattleAreIdempotent(): void
  {
    $userId = $this->insertUser('qa_idem', 'QA Idempotency');
    $regionId = $this->insertRegion(5, true, 'qa-region', 'QA Region');
    $teamId = $this->insertTeam($userId, 'QA Team', true);
    $runId = $this->insertRun($userId, $regionId, 12345, 'active');
    $nodeId = $this->insertRunNode($runId, 'combat', 'available');

    $_SESSION['user_id'] = $userId;
    $_SESSION['csrf_token'] = 'valid_csrf';
    $_SERVER['HTTP_X_CSRF_TOKEN'] = 'valid_csrf';

    $runNodeController = new RunNodeController();
    $firstResolve = $this->invoke(fn() => $runNodeController->resolveNode((string)$runId, (string)$nodeId));
    $secondResolve = $this->invoke(fn() => $runNodeController->resolveNode((string)$runId, (string)$nodeId));

    $this->assertSame(200, $firstResolve['status']);
    $this->assertSame(200, $secondResolve['status']);
    $firstBattleId = (int)($firstResolve['body']['data']['battle']['battle_id'] ?? 0);
    $secondBattleId = (int)($secondResolve['body']['data']['battle']['battle_id'] ?? 0);
    $this->assertGreaterThan(0, $firstBattleId);
    $this->assertSame($firstBattleId, $secondBattleId);

    $battleCount = (int)$this->scalar('SELECT COUNT(*) FROM `battles` WHERE `run_id` = ? AND `node_id` = ?', [$runId, $nodeId]);
    $rewardCount = (int)$this->scalar('SELECT COUNT(*) FROM `battle_rewards` WHERE `battle_id` = ?', [$firstBattleId]);
    $logCount = (int)$this->scalar('SELECT COUNT(*) FROM `battle_logs` WHERE `battle_id` = ?', [$firstBattleId]);
    $nodeStatus = (string)$this->scalar('SELECT `status` FROM `run_nodes` WHERE `id` = ?', [$nodeId]);

    $this->assertSame(1, $battleCount);
    $this->assertSame(1, $rewardCount);
    $this->assertSame(1, $logCount);
    $this->assertContains($nodeStatus, ['cleared', 'available']);

    $battleController = new BattleController();
    $firstClaim = $this->invoke(fn() => $battleController->claimBattle((string)$firstBattleId));
    $secondClaim = $this->invoke(fn() => $battleController->claimBattle((string)$firstBattleId));

    $this->assertSame(200, $firstClaim['status']);
    $this->assertSame(200, $secondClaim['status']);
    $this->assertSame('claimed', $firstClaim['body']['data']['status'] ?? null);
    $this->assertSame('claimed', $secondClaim['body']['data']['status'] ?? null);

    $battleStatus = (string)$this->scalar('SELECT `status` FROM `battles` WHERE `id` = ?', [$firstBattleId]);
    $this->assertSame('claimed', $battleStatus);
    $this->assertSame(1, (int)$this->scalar('SELECT COUNT(*) FROM `battle_rewards` WHERE `battle_id` = ?', [$firstBattleId]));
  }

  public function testResolveNodeUsesDeterministicSeedDerivedFromRunAndContext(): void
  {
    $userId = $this->insertUser('qa_idem', 'QA Idempotency');
    $regionId = $this->insertRegion(5, true, 'qa-region', 'QA Region');
    $teamId = $this->insertTeam($userId, 'QA Team', true);
    $runId = $this->insertRun($userId, $regionId, 12345, 'active');
    $nodeId = $this->insertRunNode($runId, 'combat', 'available');

    $_SESSION['user_id'] = $userId;
    $_SESSION['csrf_token'] = 'valid_csrf';
    $_SERVER['HTTP_X_CSRF_TOKEN'] = 'valid_csrf';

    $runNodeController = new RunNodeController();
    $resolve = $this->invoke(fn() => $runNodeController->resolveNode((string)$runId, (string)$nodeId));

    $this->assertSame(200, $resolve['status']);
    $battleId = (int)($resolve['body']['data']['battle']['battle_id'] ?? 0);
    $this->assertGreaterThan(0, $battleId);

    $actualSeed = (int)$this->scalar('SELECT `seed` FROM `battles` WHERE `id` = ?', [$battleId]);
    $runSeed = (string)$this->scalar('SELECT `seed` FROM `region_runs` WHERE `id` = ?', [$runId]);
    $encounterTemplateIdRaw = $this->scalar('SELECT `encounter_template_id` FROM `run_nodes` WHERE `id` = ?', [$nodeId]);
    $encounterTemplateId = (string)$encounterTemplateIdRaw !== '' ? (int)$encounterTemplateIdRaw : null;

    $expectedSeed = $this->deriveExpectedSeed(
      $userId,
      $runId,
      $runSeed,
      $nodeId,
      $teamId,
      $encounterTemplateId
    );

    $this->assertSame($expectedSeed, $actualSeed);
  }

  private function insertRunNode(int $runId, string $nodeType, string $status): int
  {
    $encounterTemplateId = $this->pickEncounterTemplateIdForNodeType($nodeType);

    $stmt = $this->pdo?->prepare('
      INSERT INTO `run_nodes` (`run_id`, `node_index`, `node_type`, `status`, `encounter_template_id`, `meta_json`)
      VALUES (?, 1, ?, ?, ?, NULL)
    ');
    $stmt?->execute([$runId, $nodeType, $status, $encounterTemplateId]);
    return (int)$this->pdo?->lastInsertId();
  }

  private function pickEncounterTemplateIdForNodeType(string $nodeType): ?int
  {
    $slugPattern = match ($nodeType) {
      'combat' => '%_combat_%',
      'boss' => '%_boss_%',
      'loot' => '%_loot_%',
      'rest' => '%_rest_%',
      default => null,
    };

    if ($slugPattern === null) {
      return null;
    }

    $stmt = $this->pdo?->prepare('SELECT `id` FROM `encounter_templates` WHERE `slug` LIKE ? ORDER BY `id` ASC LIMIT 1');
    $stmt?->execute([$slugPattern]);
    $value = $stmt?->fetchColumn();

    if ($value === false || $value === null || $value === '') {
      return null;
    }

    return (int)$value;
  }

  private function deriveExpectedSeed(
    int $userId,
    int $runId,
    string $runSeed,
    int $nodeId,
    int $teamId,
    ?int $encounterTemplateId
  ): int {
    $seedKey = sprintf(
      'seed_v2|user:%d|run:%d|run_seed:%s|node:%d|team:%d|enc:%s',
      $userId,
      $runId,
      $runSeed,
      $nodeId,
      $teamId,
      $encounterTemplateId !== null ? (string)$encounterTemplateId : 'none'
    );

    $rngState = hash('sha256', $seedKey);
    $seedHex = substr($rngState, 0, 15);
    $seed = (int)base_convert($seedHex, 16, 10);
    return $seed > 0 ? $seed : 1;
  }
}
