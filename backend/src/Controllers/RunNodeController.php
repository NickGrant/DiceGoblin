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
use DiceGoblins\Http\JsonRequestBody;

use DiceGoblins\Repositories\BattleLogRepository;
use DiceGoblins\Repositories\BattleRepository;
use DiceGoblins\Repositories\BattleRewardsRepository;
use DiceGoblins\Repositories\EnergyRepository;
use DiceGoblins\Repositories\PlayerStateRepository;
use DiceGoblins\Repositories\RegionRepository;
use DiceGoblins\Repositories\RunEdgeRepository;
use DiceGoblins\Repositories\RunNodeRepository;
use DiceGoblins\Repositories\RunRepository;
use DiceGoblins\Repositories\TeamRepository;
use DiceGoblins\Repositories\UserRepository;

use DiceGoblins\Services\CsrfService;
use DiceGoblins\Services\RunLifecycleService;
use DiceGoblins\Services\SessionService;
use DiceGoblins\Services\SquadCapacityService;
use DiceGoblins\Services\UserAssetGrantService;
use DiceGoblins\Support\RunSummaryBuilder;

use PDO;
use RuntimeException;
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
  *      "battle": { "battle_id": "555", "outcome": "victory", "rounds": 3, "ticks": 60, "status": "completed", "log": { "meta": {}, "events": [] } },
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
      $svc['runNodeRepo']->syncAvailableNodesFromClearedParents($runIdInt);
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

      $squadUnitCap = (new SquadCapacityService($pdo))->getCapForUser($userId);
      $teamUnitIds = $svc['teamRepo']->getTeamUnitIds($userId, $teamIdInt);
      if (count($teamUnitIds) > $squadUnitCap) {
        $pdo->rollBack();
        Response::json([
          'ok' => false,
          'error' => [
            'code' => 'validation_error',
            'message' => "Selected squad exceeds your current {$squadUnitCap}-unit cap. Trim the squad before starting the run.",
          ],
        ], 409);
        return;
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
          $existingLog = $svc['battleLogRepo']->getForUser((int)$existing['id'], $userId);
          $existingRewards = $svc['battleRewardsRepo']->getForBattle((int)$existing['id']);
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
                'log' => $this->decodeLogJson($existingLog['log_json'] ?? null),
                'reward_preview' => $this->buildRewardPreview(
                  $pdo,
                  $userId,
                  (string)($node['node_type'] ?? ''),
                  $existingRewards
                ),
              ],
              'next' => [
                'unlocked_node_ids' => $svc['runNodeRepo']->listAvailableNodeIds($runIdInt),
              ],
            ],
          ]);
          return;
        }
      }

      try {
        $resolution = $svc['resolver']->resolve($userId, $teamIdInt, $run, $node);
      } catch (RuntimeException $e) {
        if ($e->getMessage() !== 'combat_no_enemies') {
          throw $e;
        }

        $pdo->rollBack();
        Response::json([
          'ok' => false,
          'error' => [
            'code' => 'combat_no_enemies',
            'message' => 'Combat encounter has no enemies and was aborted.',
            'details' => [
              'run_id' => (string)$runIdInt,
              'node_id' => (string)$nodeIdInt,
              'rounds' => 0,
              'ticks' => 0,
            ],
          ],
        ], 409);
        return;
      }
      $seed = (int)$resolution['seed'];
      $outcome = (string)$resolution['outcome'];
      $ticks = (int)$resolution['ticks'];
      $rounds = (int)$resolution['rounds'];
      $resolvedRewards = is_array($resolution['rewards'] ?? null) ? $resolution['rewards'] : [];
      $grantedUnitIds = $svc['userAssetGrantService']->materializeRewardUnitGrants($userId, $resolvedRewards);
      $grantedDiceIds = $svc['userAssetGrantService']->materializeRewardDiceGrants($userId, $resolvedRewards);
      if (count($grantedUnitIds) > 0) {
        $existing = is_array($resolvedRewards['new_unit_instance_ids'] ?? null) ? $resolvedRewards['new_unit_instance_ids'] : [];
        $resolvedRewards['new_unit_instance_ids'] = array_values(array_unique(array_map('strval', array_merge($existing, $grantedUnitIds))));
      }
      if (count($grantedDiceIds) > 0) {
        $existing = is_array($resolvedRewards['new_dice_instance_ids'] ?? null) ? $resolvedRewards['new_dice_instance_ids'] : [];
        $resolvedRewards['new_dice_instance_ids'] = array_values(array_unique(array_map('strval', array_merge($existing, $grantedDiceIds))));
      }

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
        $resolvedRewards
      );
      $rewardPreview = $this->buildRewardPreview(
        $pdo,
        $userId,
        (string)($node['node_type'] ?? ''),
        [
          'xp_total' => (int)$resolution['xp_total'],
          'currency_soft' => (int)$resolution['currency_soft'],
          'rewards_json' => json_encode($resolvedRewards, JSON_UNESCAPED_SLASHES),
        ]
      );

      $isCombatLikeNode = ((string)$node['node_type'] === 'combat' || (string)$node['node_type'] === 'boss');
      $runFailed = $isCombatLikeNode && $outcome === 'defeat';

      if ($runFailed) {
        $svc['runLifecycleService']->failRun($userId, $runIdInt);
      }

      $unlocked = [];
      if (!$runFailed && ($outcome === 'victory' || $node['node_type'] !== 'combat')) {
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
            'status' => $runFailed
              ? 'failed'
              : (($outcome === 'victory' || $node['node_type'] !== 'combat') ? 'completed' : 'available'),
          ],
          'battle' => [
            'battle_id' => (string)$battleId,
            'outcome' => $outcome,
            'rounds' => $rounds,
            'ticks' => $ticks,
            'status' => 'completed',
            'log' => $resolution['log'],
            'reward_preview' => $rewardPreview,
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
   * @return array<string,mixed>|null
   */
  private function decodeLogJson(mixed $logJson): ?array
  {
    if (!is_string($logJson) || $logJson === '') {
      return null;
    }

    $decoded = json_decode($logJson, true);
    if (!is_array($decoded)) {
      return null;
    }

    return $decoded;
  }

  /**
   * @param array{xp_total:int,currency_soft:int,rewards_json:string}|null $rewardRow
   * @return array{
   *   node_type:string,
   *   xp_total:int,
   *   currency_soft:int,
   *   new_unit_labels:array<int,string>,
   *   new_dice_labels:array<int,string>,
   *   units:array<int,array{
   *     unit_instance_id:string|null,
   *     name:string,
   *     unit_type_slug:string|null,
   *     unit_type_name:string,
   *     tier:int,
   *     level:int
   *   }>,
   *   dice:array<int,array{
   *     dice_instance_id:string|null,
   *     label:string,
   *     rarity:string,
   *     material:string,
   *     sides:int,
   *     affixes:array<int,array{
   *       affix_definition_id:string,
   *       affix_slug:string,
   *       name:string,
   *       rarity:string,
   *       kind:string,
   *       description:string,
   *       value:float
   *     }>
   *   }>
   * }|null
   */
  private function buildRewardPreview(PDO $pdo, int $userId, string $nodeType, ?array $rewardRow): ?array
  {
    if ($rewardRow === null) {
      return null;
    }

    $rewards = json_decode((string)($rewardRow['rewards_json'] ?? ''), true);
    if (!is_array($rewards)) {
      $rewards = [];
    }

    $summaryBuilder = new RunSummaryBuilder($pdo);
    $labels = $summaryBuilder->buildBattleRewardLabels($userId, $rewards);
    $details = $summaryBuilder->buildBattleRewardDetails($userId, $rewards);

    return [
      'node_type' => $nodeType,
      'xp_total' => max(0, (int)($rewardRow['xp_total'] ?? 0)),
      'currency_soft' => max(0, (int)($rewardRow['currency_soft'] ?? 0)),
      'new_unit_labels' => array_values(is_array($labels['new_unit_labels'] ?? null) ? $labels['new_unit_labels'] : []),
      'new_dice_labels' => array_values(is_array($labels['new_dice_labels'] ?? null) ? $labels['new_dice_labels'] : []),
      'units' => array_values(is_array($details['units'] ?? null) ? $details['units'] : []),
      'dice' => array_values(is_array($details['dice'] ?? null) ? $details['dice'] : []),
    ];
  }

  /**
   * Unlock direct downstream nodes once this node is cleared.
   *
   * Progression rule is OR-based for converging paths: clearing any connected
   * parent unlocks the child node.
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
   *   runLifecycleService: RunLifecycleService,
   *   userAssetGrantService: UserAssetGrantService,
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
      'runLifecycleService' => new RunLifecycleService(
        $pdo,
        new RunRepository($pdo),
        new RegionRepository($pdo),
        new RunNodeRepository($pdo),
      ),
      'userAssetGrantService' => new UserAssetGrantService($pdo),
      'sessionService' => $core['sessionService'],
      'csrfService' => $core['csrfService'],
    ];
  }

  /**
   * @return array<string,mixed>|null
   */
  private function readJsonBody(): ?array
  {
    return JsonRequestBody::decode();
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
