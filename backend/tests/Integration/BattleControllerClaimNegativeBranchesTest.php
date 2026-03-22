<?php
declare(strict_types=1);

namespace DiceGoblins\Tests\Integration;

use DiceGoblins\Controllers\BattleController;
use DiceGoblins\Tests\Support\IntegrationTestCase;

final class BattleControllerClaimNegativeBranchesTest extends IntegrationTestCase
{
  protected function integrationSkipMessage(): string
  {
    return 'Set TEST_DB_DSN to run claim negative-branch tests.';
  }

  public function testClaimRejectsBattleNotCompletedState(): void
  {
    $userId = $this->insertUser('qa_claim', 'QA Claim');
    [$runId, $nodeId, $teamId] = $this->seedRunGraphScaffold($userId);
    $battleId = $this->insertBattle($userId, $runId, $nodeId, $teamId, 'claimed', 'victory');
    $this->insertBattleRewards($battleId);

    $_SESSION['user_id'] = $userId;
    $_SESSION['csrf_token'] = 'valid_csrf';
    $_SERVER['HTTP_X_CSRF_TOKEN'] = 'valid_csrf';

    $controller = new BattleController();
    $res = $this->invoke(fn() => $controller->claimBattle((string)$battleId));

    $this->assertSame(200, $res['status']);
    $this->assertTrue((bool)($res['body']['ok'] ?? false));
    $this->assertSame('claimed', (string)($res['body']['data']['status'] ?? ''));
  }

  public function testClaimRejectsOwnershipMismatch(): void
  {
    $ownerId = $this->insertUser('qa_claim', 'QA Claim');
    $attackerId = $this->insertUser('qa_claim', 'QA Claim');
    [$runId, $nodeId, $teamId] = $this->seedRunGraphScaffold($ownerId);
    $battleId = $this->insertBattle($ownerId, $runId, $nodeId, $teamId, 'completed', 'victory');
    $this->insertBattleRewards($battleId);

    $_SESSION['user_id'] = $attackerId;
    $_SESSION['csrf_token'] = 'valid_csrf';
    $_SERVER['HTTP_X_CSRF_TOKEN'] = 'valid_csrf';

    $controller = new BattleController();
    $res = $this->invoke(fn() => $controller->claimBattle((string)$battleId));

    $this->assertSame(403, $res['status']);
    $this->assertSame('forbidden', (string)($res['body']['error']['code'] ?? ''));
  }

  public function testClaimRejectsMissingBattle(): void
  {
    $userId = $this->insertUser('qa_claim', 'QA Claim');

    $_SESSION['user_id'] = $userId;
    $_SESSION['csrf_token'] = 'valid_csrf';
    $_SERVER['HTTP_X_CSRF_TOKEN'] = 'valid_csrf';

    $controller = new BattleController();
    $res = $this->invoke(fn() => $controller->claimBattle('999999'));

    $this->assertSame(403, $res['status']);
    $this->assertSame('forbidden', (string)($res['body']['error']['code'] ?? ''));
  }

  /**
   * @return array{0:int,1:int,2:int}
   */
  private function seedRunGraphScaffold(int $userId): array
  {
    $regionId = $this->insertRegion(5, true, 'qa-claim-region', 'QA Claim Region');
    $teamId = $this->insertTeam($userId, 'QA Team', true);
    $runId = $this->insertRun($userId, $regionId, 10101, 'active');

    $nodeStmt = $this->pdo?->prepare(
      "INSERT INTO `run_nodes` (`run_id`, `node_index`, `node_type`, `status`, `encounter_template_id`, `meta_json`) VALUES (?, 1, 'combat', 'available', NULL, NULL)"
    );
    $nodeStmt?->execute([$runId]);
    $nodeId = (int)$this->pdo?->lastInsertId();

    return [$runId, $nodeId, $teamId];
  }

  private function insertBattle(int $userId, int $runId, int $nodeId, int $teamId, string $status, string $outcome): int
  {
    $stmt = $this->pdo?->prepare(
      "INSERT INTO `battles` (`user_id`, `run_id`, `node_id`, `team_id`, `rules_version`, `seed`, `status`, `outcome`, `ticks`, `rounds`)
       VALUES (?, ?, ?, ?, 'combat_v1', 12345, ?, ?, 1, 1)"
    );
    $stmt?->execute([$userId, $runId, $nodeId, $teamId, $status, $outcome]);
    return (int)$this->pdo?->lastInsertId();
  }

  private function insertBattleRewards(int $battleId): void
  {
    $stmt = $this->pdo?->prepare(
      "INSERT INTO `battle_rewards` (`battle_id`, `xp_total`, `currency_soft`, `rewards_json`) VALUES (?, 0, 0, '{\"new_dice_instance_ids\":[],\"region_items\":[]}')"
    );
    $stmt?->execute([$battleId]);
  }
}
