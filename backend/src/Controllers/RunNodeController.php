<?php
declare(strict_types=1);

/**
 * File: C:\xampp\htdocs\dice-goblin\backend\src\Controllers\RunNodeController.php
 * Purpose: Project PHP module.
 */

namespace DiceGoblins\Controllers;

use DiceGoblins\Combat\Engine\DeterministicRunNodeResolver;
use DiceGoblins\Controllers\Concerns\RequiresCsrf;
use DiceGoblins\Core\Db;
use DiceGoblins\Core\Response;

use DiceGoblins\Repositories\BattleLogRepository;
use DiceGoblins\Repositories\BattleRepository;
use DiceGoblins\Repositories\BattleRewardsRepository;
use DiceGoblins\Repositories\EnergyRepository;
use DiceGoblins\Repositories\PlayerStateRepository;
use DiceGoblins\Repositories\RunEdgeRepository;
use DiceGoblins\Repositories\RunNodeRepository;
use DiceGoblins\Repositories\RunRepository;
use DiceGoblins\Repositories\TeamRepository;
use DiceGoblins\Repositories\UserRepository;

use DiceGoblins\Services\CsrfService;
use DiceGoblins\Services\GrantService;
use DiceGoblins\Services\PlayerBootstrapper;
use DiceGoblins\Services\SessionService;

use PDO;
use Throwable;

final class RunNodeController
{
  use RequiresCsrf;

  /**
   * POST /api/v1/runs/:runId/nodes/:nodeId/resolve
   *
   * Request body:
   *  { "team_id": "10" }
   *
   * Response:
   *  {
   *    "ok": true,
   *    "data": {
   *      "node": { "id": "300", "status": "completed" },
   *      "battle": { "battle_id": "555", "outcome": "victory", "rounds": 3, "ticks": 60, "status": "completed" },
   *      "next": { "unlocked_node_ids": ["301"] }
   *    }
   *  }
   */
  public function resolveNode(?string $runId = null, ?string $nodeId = null): void
  {
    $svc = $this->services();

    // Auth
    try {
      $userId = $svc['sessionService']->requireUserId();
    } catch (Throwable $e) {
      Response::json([
        'ok' => false,
        'error' => [
          'code' => 'unauthorized',
          'message' => 'No active session.',
        ],
      ], 401);
      return;
    }

    // CSRF required for mutation
    if (!$this->requireCsrf($svc['csrfService'])) {
      return;
    }

    $runIdInt = $this->requirePositiveInt($runId, 'runId');
    $nodeIdInt = $this->requirePositiveInt($nodeId, 'nodeId');
    if ($runIdInt === null || $nodeIdInt === null) {
      return;
    }

    $body = $this->readJsonBody();
    if ($body === null) {
      Response::json([
        'ok' => false,
        'error' => [
          'code' => 'validation_error',
          'message' => 'Invalid JSON body.',
        ],
      ], 400);
      return;
    }

    $teamIdInt = 0;
    if (array_key_exists('team_id', $body)) {
      $teamIdInt = (int)$body['team_id'];
    }

    /** @var PDO $pdo */
    $pdo = $svc['pdo'];

    try {
      $pdo->beginTransaction();

      // Run ownership + status
      $run = $svc['runRepo']->getRunForUser($userId, $runIdInt);
      if ($run === null) {
        $pdo->rollBack();
        Response::json([
          'ok' => false,
          'error' => [
            'code' => 'forbidden',
            'message' => 'Run not found or not owned by user.',
          ],
        ], 403);
        return;
      }

      if (($run['status'] ?? null) !== 'active') {
        $pdo->rollBack();
        Response::json([
          'ok' => false,
          'error' => [
            'code' => 'run_not_active',
            'message' => 'Run is not active.',
          ],
        ], 409);
        return;
      }

      // Lock node
      $node = $svc['runNodeRepo']->getForUpdate($runIdInt, $nodeIdInt);
      if ($node === null) {
        $pdo->rollBack();
        Response::json([
          'ok' => false,
          'error' => [
            'code' => 'not_found',
            'message' => 'Node not found for run.',
          ],
        ], 404);
        return;
      }

      if ($node['status'] === 'locked') {
        $pdo->rollBack();
        Response::json([
          'ok' => false,
          'error' => [
            'code' => 'node_not_available',
            'message' => 'Node is locked.',
          ],
        ], 409);
        return;
      }

      if ((string)$node['node_type'] === 'exit') {
        $pdo->rollBack();
        Response::json([
          'ok' => false,
          'error' => [
            'code' => 'invalid_node_type',
            'message' => 'Exit nodes are completed via /api/v1/runs/:runId/exit.',
          ],
        ], 409);
        return;
      }

      // Determine squad selection (defaults to active squad route/table naming remains team_id)
      if ($teamIdInt <= 0) {
        $activeTeam = $svc['teamRepo']->getActiveTeamForUser($userId);
        if ($activeTeam === null) {
          $pdo->rollBack();
          Response::json([
            'ok' => false,
            'error' => [
              'code' => 'validation_error',
              'message' => 'No active squad. Provide team_id.',
            ],
          ], 400);
          return;
        }
        $teamIdInt = (int)$activeTeam['id'];
      } else {
        $ownedTeam = $svc['teamRepo']->getTeamForUser($userId, $teamIdInt);
        if ($ownedTeam === null) {
          $pdo->rollBack();
          Response::json([
            'ok' => false,
            'error' => [
              'code' => 'validation_error',
              'message' => 'team_id is not owned by the user.',
            ],
          ], 400);
          return;
        }
      }

      // Idempotency: one battle per (run_id, node_id)
      $existing = $svc['battleRepo']->getByRunNode($runIdInt, $nodeIdInt);
      if ($existing !== null) {
        $canRetryClaimedDefeat =
          (string)$existing['outcome'] === 'defeat'
          && (string)$existing['status'] === 'claimed'
          && (string)$node['status'] !== 'cleared';

        if ($canRetryClaimedDefeat) {
          $svc['battleRepo']->deleteBattleForRetry((int)$existing['id'], $userId);
        } else {
          $pdo->commit();
          Response::json([
            'ok' => true,
            'data' => [
              'node' => [
                'id' => (string)$nodeIdInt,
                'status' => (string)($node['status'] === 'cleared' ? 'completed' : 'available'),
              ],
              'battle' => [
                'battle_id' => (string)$existing['id'],
                'outcome' => (string)$existing['outcome'],
                'rounds' => (int)$existing['rounds'],
                'ticks' => (int)$existing['ticks'],
                'status' => (string)$existing['status'],
              ],
              'next' => [
                'unlocked_node_ids' => $svc['runNodeRepo']->listAvailableNodeIds($runIdInt),
              ],
            ],
          ]);
          return;
        }
      }

      $resolution = $svc['resolver']->resolve($userId, $teamIdInt, $run, $node);
      $seed = (int)$resolution['seed'];
      $outcome = (string)$resolution['outcome'];
      $ticks = (int)$resolution['ticks'];
      $rounds = (int)$resolution['rounds'];

      $battleId = $svc['battleRepo']->createCompleted(
        $userId,
        $runIdInt,
        $nodeIdInt,
        $teamIdInt,
        $seed,
        $outcome,
        $ticks,
        $rounds
      );

      $svc['battleLogRepo']->insert($battleId, $resolution['log']);
      $svc['battleRewardsRepo']->insert(
        $battleId,
        (int)$resolution['xp_total'],
        (int)$resolution['currency_soft'],
        (array)$resolution['rewards']
      );

      $unlocked = [];
      if ($outcome === 'victory' || $node['node_type'] !== 'combat') {
        // Mark node cleared in DB and unlock downstream progression.
        $svc['runNodeRepo']->markCleared($runIdInt, $nodeIdInt);
        $unlocked = $this->unlockFromNode(
          $svc['runEdgeRepo'],
          $svc['runNodeRepo'],
          $runIdInt,
          $nodeIdInt
        );
      }

      $pdo->commit();

      Response::json([
        'ok' => true,
        'data' => [
          'node' => [
            'id' => (string)$nodeIdInt,
            'status' => ($outcome === 'victory' || $node['node_type'] !== 'combat') ? 'completed' : 'available',
          ],
          'battle' => [
            'battle_id' => (string)$battleId,
            'outcome' => $outcome,
            'rounds' => $rounds,
            'ticks' => $ticks,
            'status' => 'completed',
          ],
          'next' => [
            'unlocked_node_ids' => array_map('strval', $unlocked),
          ],
        ],
      ]);
    } catch (Throwable $e) {
      if ($pdo->inTransaction()) {
        $pdo->rollBack();
      }

      Response::json([
        'ok' => false,
        'error' => [
          'code' => 'server_error',
          'message' => 'Unexpected error.',
        ],
      ], 500);
    }
  }

  /**
   * Unlock nodes reachable from fromNodeId if all prerequisites are cleared.
   *
   * @return array<int,int>
   */
  private function unlockFromNode(
    RunEdgeRepository $edges,
    RunNodeRepository $nodes,
    int $runId,
    int $fromNodeId
  ): array {
    $toIds = $edges->getToNodeIdsFrom($runId, $fromNodeId);

    $unlocked = [];
    foreach ($toIds as $toId) {
      $blocked = $edges->countUnclearedPrerequisites($runId, $toId);
      if ($blocked !== 0) {
        continue;
      }

      if ($nodes->setAvailableIfLocked($runId, $toId)) {
        $unlocked[] = $toId;
      }
    }

    return $unlocked;
  }

  /**
   * @return array{
   *   pdo: PDO,
   *   runRepo: RunRepository,
   *   runNodeRepo: RunNodeRepository,
   *   runEdgeRepo: RunEdgeRepository,
   *   battleRepo: BattleRepository,
   *   battleLogRepo: BattleLogRepository,
   *   battleRewardsRepo: BattleRewardsRepository,
   *   teamRepo: TeamRepository,
   *   resolver: DeterministicRunNodeResolver,
   *   sessionService: SessionService,
   *   csrfService: CsrfService
   * }
   */
  private function services(): array
  {
    $pdo = Db::pdo();
    $core = ControllerServiceFactory::buildCore($pdo);

    return [
      'pdo' => $pdo,
      'runRepo' => new RunRepository($pdo),
      'runNodeRepo' => new RunNodeRepository($pdo),
      'runEdgeRepo' => new RunEdgeRepository($pdo),
      'battleRepo' => new BattleRepository($pdo),
      'battleLogRepo' => new BattleLogRepository($pdo),
      'battleRewardsRepo' => new BattleRewardsRepository($pdo),
      'teamRepo' => new TeamRepository($pdo),
      'resolver' => new DeterministicRunNodeResolver($pdo),
      'sessionService' => $core['sessionService'],
      'csrfService' => $core['csrfService'],
    ];
  }

  /**
   * @return array<string,mixed>|null
   */
  private function readJsonBody(): ?array
  {
    $raw = file_get_contents('php://input');
    if ($raw === false) return null;

    $raw = trim($raw);
    if ($raw === '') {
      if (isset($_POST) && is_array($_POST) && count($_POST) > 0) {
        return $_POST;
      }
      return [];
    }

    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : null;
  }

  private function requirePositiveInt(?string $raw, string $field): ?int
  {
    $v = (int)($raw ?? 0);
    if ($v <= 0) {
      Response::json([
        'ok' => false,
        'error' => [
          'code' => 'validation_error',
          'message' => "{$field} is required.",
        ],
      ], 400);
      return null;
    }
    return $v;
  }
}
