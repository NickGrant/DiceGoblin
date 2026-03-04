<?php
declare(strict_types=1);

namespace DiceGoblins\Controllers;

use DiceGoblins\Core\Db;
use DiceGoblins\Core\Response;
use DiceGoblins\Repositories\DiceRepository;
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
use RuntimeException;
use Throwable;

final class GameplayController
{
  public function openRest(?string $runId = null, ?string $nodeId = null): void
  {
    $svc = $this->services();
    $userId = $this->requireUserId($svc['sessionService']);
    if ($userId === null || !$this->requireCsrf($svc['csrfService'])) {
      return;
    }

    $runIdInt = $this->requirePositiveInt($runId, 'runId');
    $nodeIdInt = $this->requirePositiveInt($nodeId, 'nodeId');
    if ($runIdInt === null || $nodeIdInt === null) {
      return;
    }

    /** @var PDO $pdo */
    $pdo = $svc['pdo'];
    try {
      $pdo->beginTransaction();
      $run = $this->requireActiveOwnedRun($svc['runRepo'], $userId, $runIdInt);
      if ($run === null) {
        $pdo->rollBack();
        return;
      }
      $node = $this->requireAvailableRestNode($svc['runNodeRepo'], $runIdInt, $nodeIdInt);
      if ($node === null) {
        $pdo->rollBack();
        return;
      }

      $activeTeam = $svc['teamRepo']->getActiveTeamForUser($userId);
      if ($activeTeam === null) {
        $pdo->rollBack();
        Response::json([
          'ok' => false,
          'error' => ['code' => 'validation_error', 'message' => 'No active squad found.'],
        ], 400);
        return;
      }

      $teamId = (int)$activeTeam['id'];
      $teamUnits = $svc['teamRepo']->getTeamUnitIds($userId, $teamId);
      $formation = $this->loadTeamFormation($pdo, $teamId);
      $runState = $svc['runRepo']->getRunUnitState($runIdInt);

      $pdo->commit();
      Response::json([
        'ok' => true,
        'data' => [
          'run_id' => (string)$runIdInt,
          'node_id' => (string)$nodeIdInt,
          'status' => 'open',
          'team_id' => (string)$teamId,
          'unit_ids' => array_map('strval', $teamUnits),
          'formation' => $formation,
          'run_unit_state' => $runState,
        ],
      ]);
    } catch (Throwable $e) {
      if ($pdo->inTransaction()) {
        $pdo->rollBack();
      }
      Response::json(['ok' => false, 'error' => ['code' => 'server_error', 'message' => 'Unexpected error.']], 500);
    }
  }

  public function updateRestState(?string $runId = null, ?string $nodeId = null): void
  {
    $svc = $this->services();
    $userId = $this->requireUserId($svc['sessionService']);
    if ($userId === null || !$this->requireCsrf($svc['csrfService'])) {
      return;
    }

    $runIdInt = $this->requirePositiveInt($runId, 'runId');
    $nodeIdInt = $this->requirePositiveInt($nodeId, 'nodeId');
    if ($runIdInt === null || $nodeIdInt === null) {
      return;
    }

    $body = $this->readJsonBody();
    if ($body === null) {
      Response::json(['ok' => false, 'error' => ['code' => 'validation_error', 'message' => 'Invalid JSON body.']], 400);
      return;
    }

    $unitIdsRaw = $body['unit_ids'] ?? null;
    $formationRaw = $body['formation'] ?? null;
    if (!is_array($unitIdsRaw) || !is_array($formationRaw)) {
      Response::json(['ok' => false, 'error' => ['code' => 'validation_error', 'message' => 'unit_ids and formation are required.']], 400);
      return;
    }
    $unitIds = array_values(array_unique(array_map(static fn($v): int => (int)$v, $unitIdsRaw)));

    /** @var PDO $pdo */
    $pdo = $svc['pdo'];
    try {
      $pdo->beginTransaction();
      $run = $this->requireActiveOwnedRun($svc['runRepo'], $userId, $runIdInt);
      if ($run === null) {
        $pdo->rollBack();
        return;
      }
      $node = $this->requireAvailableRestNode($svc['runNodeRepo'], $runIdInt, $nodeIdInt);
      if ($node === null) {
        $pdo->rollBack();
        return;
      }

      $activeTeam = $svc['teamRepo']->getActiveTeamForUser($userId);
      if ($activeTeam === null) {
        $pdo->rollBack();
        Response::json(['ok' => false, 'error' => ['code' => 'validation_error', 'message' => 'No active squad found.']], 400);
        return;
      }
      $teamId = (int)$activeTeam['id'];

      $this->assertUnitsOwnedByUserForUpdate($pdo, $userId, $unitIds);
      $this->replaceTeamMembership($pdo, $teamId, $unitIds);
      $this->replaceTeamFormation($pdo, $teamId, $unitIds, $formationRaw);

      $existingState = $svc['runRepo']->getRunUnitStateForUpdate($runIdInt);
      $existingIds = array_map(static fn(array $r): int => (int)$r['unit_instance_id'], $existingState);
      $existingSet = array_fill_keys($existingIds, true);
      $targetSet = array_fill_keys($unitIds, true);

      $toRemove = [];
      foreach ($existingIds as $uid) {
        if (!isset($targetSet[$uid])) {
          $toRemove[] = $uid;
        }
      }
      if (count($toRemove) > 0) {
        $svc['runRepo']->deleteRunUnitStateByUnitIds($runIdInt, $toRemove);
      }

      $toAdd = [];
      foreach ($unitIds as $uid) {
        if (!isset($existingSet[$uid])) {
          $toAdd[] = $uid;
        }
      }
      if (count($toAdd) > 0) {
        $svc['runRepo']->insertRunUnitStateBulk($runIdInt, $this->buildRunUnitSeedRows($pdo, $userId, $toAdd));
      }

      $runState = $svc['runRepo']->getRunUnitState($runIdInt);
      $pdo->commit();

      Response::json([
        'ok' => true,
        'data' => [
          'run_id' => (string)$runIdInt,
          'node_id' => (string)$nodeIdInt,
          'team_id' => (string)$teamId,
          'unit_ids' => array_map('strval', $unitIds),
          'formation' => $this->loadTeamFormation($pdo, $teamId),
          'run_unit_state' => $runState,
        ],
      ]);
    } catch (RuntimeException $e) {
      if ($pdo->inTransaction()) {
        $pdo->rollBack();
      }
      Response::json(['ok' => false, 'error' => ['code' => 'validation_error', 'message' => $e->getMessage()]], 400);
    } catch (Throwable $e) {
      if ($pdo->inTransaction()) {
        $pdo->rollBack();
      }
      Response::json(['ok' => false, 'error' => ['code' => 'server_error', 'message' => 'Unexpected error.']], 500);
    }
  }

  public function finalizeRest(?string $runId = null, ?string $nodeId = null): void
  {
    $svc = $this->services();
    $userId = $this->requireUserId($svc['sessionService']);
    if ($userId === null || !$this->requireCsrf($svc['csrfService'])) {
      return;
    }

    $runIdInt = $this->requirePositiveInt($runId, 'runId');
    $nodeIdInt = $this->requirePositiveInt($nodeId, 'nodeId');
    if ($runIdInt === null || $nodeIdInt === null) {
      return;
    }

    /** @var PDO $pdo */
    $pdo = $svc['pdo'];
    try {
      $pdo->beginTransaction();
      $run = $this->requireActiveOwnedRun($svc['runRepo'], $userId, $runIdInt);
      if ($run === null) {
        $pdo->rollBack();
        return;
      }
      $node = $this->requireAvailableRestNode($svc['runNodeRepo'], $runIdInt, $nodeIdInt);
      if ($node === null) {
        $pdo->rollBack();
        return;
      }

      $svc['runNodeRepo']->markCleared($runIdInt, $nodeIdInt);
      $unlocked = $this->unlockFromNode($svc['runEdgeRepo'], $svc['runNodeRepo'], $runIdInt, $nodeIdInt);
      $progression = $svc['runRepo']->applyAutoLevelForRunUnits($runIdInt, $userId);

      $pdo->commit();
      Response::json([
        'ok' => true,
        'data' => [
          'run_id' => (string)$runIdInt,
          'node' => ['id' => (string)$nodeIdInt, 'status' => 'completed'],
          'next' => ['unlocked_node_ids' => array_map('strval', $unlocked)],
          'progression' => $progression,
        ],
      ]);
    } catch (Throwable $e) {
      if ($pdo->inTransaction()) {
        $pdo->rollBack();
      }
      Response::json(['ok' => false, 'error' => ['code' => 'server_error', 'message' => 'Unexpected error.']], 500);
    }
  }

  public function promoteUnit(?string $unitInstanceId = null): void
  {
    $svc = $this->services();
    $userId = $this->requireUserId($svc['sessionService']);
    if ($userId === null || !$this->requireCsrf($svc['csrfService'])) {
      return;
    }

    $pathUnitId = $this->requirePositiveInt($unitInstanceId, 'unitInstanceId');
    if ($pathUnitId === null) {
      return;
    }
    $body = $this->readJsonBody();
    if ($body === null) {
      Response::json(['ok' => false, 'error' => ['code' => 'validation_error', 'message' => 'Invalid JSON body.']], 400);
      return;
    }

    $primaryId = (int)($body['primary_unit_instance_id'] ?? 0);
    $secondariesRaw = $body['secondary_unit_instance_ids'] ?? [];
    if ($primaryId <= 0 || !is_array($secondariesRaw) || count($secondariesRaw) !== 2) {
      Response::json(['ok' => false, 'error' => ['code' => 'validation_error', 'message' => 'Invalid promotion payload.']], 400);
      return;
    }
    if ($pathUnitId !== $primaryId) {
      Response::json(['ok' => false, 'error' => ['code' => 'validation_error', 'message' => 'Path unit id must match primary_unit_instance_id.']], 400);
      return;
    }
    $secondaryIds = array_values(array_map(static fn($v): int => (int)$v, $secondariesRaw));
    if ($secondaryIds[0] <= 0 || $secondaryIds[1] <= 0 || $secondaryIds[0] === $secondaryIds[1]) {
      Response::json(['ok' => false, 'error' => ['code' => 'validation_error', 'message' => 'secondary_unit_instance_ids must contain two distinct ids.']], 400);
      return;
    }

    $activeRun = $svc['runRepo']->getActiveRunForUser($userId);
    if ($activeRun !== null) {
      if (!$this->hasValidRestContextForRun($svc['pdo'], $userId, (int)$activeRun['run_id'], $body)) {
        Response::json(['ok' => false, 'error' => ['code' => 'run_rest_context_required', 'message' => 'Active-run promotion is only allowed during rest workflow.']], 409);
        return;
      }
      $activeRunId = (int)$activeRun['run_id'];
      foreach ($secondaryIds as $sid) {
        if ($this->isUnitInRunSnapshot($svc['pdo'], $activeRunId, $sid)) {
          Response::json(['ok' => false, 'error' => ['code' => 'unit_in_active_run', 'message' => 'Secondary units in active run snapshot cannot be consumed.']], 409);
          return;
        }
      }
    }

    /** @var PDO $pdo */
    $pdo = $svc['pdo'];
    try {
      $pdo->beginTransaction();

      $allIds = [$primaryId, $secondaryIds[0], $secondaryIds[1]];
      $units = $this->loadPromotionUnitsForUpdate($pdo, $userId, $allIds);
      if (count($units) !== 3) {
        throw new RuntimeException('promotion_requirements_not_met');
      }

      $first = reset($units);
      foreach ($units as $u) {
        if ((int)$u['unit_type_id'] !== (int)$first['unit_type_id'] || (int)$u['tier'] !== (int)$first['tier']) {
          throw new RuntimeException('promotion_requirements_not_met');
        }
        if ((int)$u['level'] < (int)$u['max_level']) {
          throw new RuntimeException('promotion_requirements_not_met');
        }
      }

      $newTier = ((int)$first['tier']) + 1;
      if ($newTier > 3) {
        throw new RuntimeException('promotion_requirements_not_met');
      }

      $update = $pdo->prepare('
        UPDATE `unit_instances`
        SET `tier` = ?, `level` = 1, `xp` = 0
        WHERE `id` = ? AND `user_id` = ?
      ');
      $update->execute([$newTier, $primaryId, $userId]);

      $this->detachAndDeleteUnits($pdo, $userId, $secondaryIds);

      $promo = $pdo->prepare('
        INSERT INTO `unit_promotions` (`user_id`, `result_unit_instance_id`, `consumed_units_json`, `consumed_region_item_id`)
        VALUES (?, ?, ?, NULL)
      ');
      $promo->execute([$userId, $primaryId, json_encode(array_map('strval', $secondaryIds), JSON_UNESCAPED_UNICODE)]);

      $pdo->commit();
      Response::json([
        'ok' => true,
        'data' => [
          'unit' => ['id' => (string)$primaryId, 'tier' => $newTier, 'level' => 1, 'xp' => 0],
          'consumed_units' => array_map('strval', $secondaryIds),
        ],
      ]);
    } catch (RuntimeException $e) {
      if ($pdo->inTransaction()) {
        $pdo->rollBack();
      }
      $code = $e->getMessage() === 'promotion_requirements_not_met' ? 'promotion_requirements_not_met' : 'validation_error';
      Response::json(['ok' => false, 'error' => ['code' => $code, 'message' => $e->getMessage()]], 409);
    } catch (Throwable $e) {
      if ($pdo->inTransaction()) {
        $pdo->rollBack();
      }
      Response::json(['ok' => false, 'error' => ['code' => 'server_error', 'message' => 'Unexpected error.']], 500);
    }
  }

  public function equipDice(?string $unitInstanceId = null): void
  {
    $this->handleDiceMutation($unitInstanceId, true);
  }

  public function unequipDice(?string $unitInstanceId = null): void
  {
    $this->handleDiceMutation($unitInstanceId, false);
  }

  private function handleDiceMutation(?string $unitInstanceId, bool $isEquip): void
  {
    $svc = $this->services();
    $userId = $this->requireUserId($svc['sessionService']);
    if ($userId === null || !$this->requireCsrf($svc['csrfService'])) {
      return;
    }

    $unitId = $this->requirePositiveInt($unitInstanceId, 'unitInstanceId');
    if ($unitId === null) {
      return;
    }
    $body = $this->readJsonBody();
    if ($body === null) {
      Response::json(['ok' => false, 'error' => ['code' => 'validation_error', 'message' => 'Invalid JSON body.']], 400);
      return;
    }
    $diceId = (int)($body['dice_instance_id'] ?? 0);
    if ($diceId <= 0) {
      Response::json(['ok' => false, 'error' => ['code' => 'validation_error', 'message' => 'dice_instance_id is required.']], 400);
      return;
    }

    if (!$this->assertDiceMutationContextAllowed($svc['pdo'], $svc['runRepo'], $userId, $unitId, $body)) {
      Response::json(['ok' => false, 'error' => ['code' => 'run_rest_context_required', 'message' => 'Active run equipment changes are allowed only during rest workflow.']], 409);
      return;
    }

    try {
      $equipped = $isEquip
        ? $svc['diceRepo']->equipDiceToUnit($userId, $unitId, $diceId)
        : $svc['diceRepo']->unequipDiceFromUnit($userId, $unitId, $diceId);

      Response::json([
        'ok' => true,
        'data' => ['unit_id' => (string)$unitId, 'equipped_dice' => $equipped],
      ]);
    } catch (RuntimeException $e) {
      Response::json(['ok' => false, 'error' => ['code' => 'validation_error', 'message' => $e->getMessage()]], 400);
    } catch (Throwable $e) {
      Response::json(['ok' => false, 'error' => ['code' => 'server_error', 'message' => 'Unexpected error.']], 500);
    }
  }

  private function unlockFromNode(RunEdgeRepository $edges, RunNodeRepository $nodes, int $runId, int $fromNodeId): array
  {
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

  private function requireUserId(SessionService $sessionService): ?int
  {
    try {
      return $sessionService->requireUserId();
    } catch (Throwable $e) {
      Response::json(['ok' => false, 'error' => ['code' => 'unauthorized', 'message' => 'No active session.']], 401);
      return null;
    }
  }

  private function requireActiveOwnedRun(RunRepository $runRepo, int $userId, int $runId): ?array
  {
    $run = $runRepo->getRunForUser($userId, $runId);
    if ($run === null) {
      Response::json(['ok' => false, 'error' => ['code' => 'forbidden', 'message' => 'Run not found or not owned by user.']], 403);
      return null;
    }
    if (($run['status'] ?? null) !== 'active') {
      Response::json(['ok' => false, 'error' => ['code' => 'run_not_active', 'message' => 'Run is not active.']], 409);
      return null;
    }
    return $run;
  }

  private function requireAvailableRestNode(RunNodeRepository $runNodeRepo, int $runId, int $nodeId): ?array
  {
    $node = $runNodeRepo->getForUpdate($runId, $nodeId);
    if ($node === null) {
      Response::json(['ok' => false, 'error' => ['code' => 'not_found', 'message' => 'Node not found for run.']], 404);
      return null;
    }
    if ((string)$node['node_type'] !== 'rest' || (string)$node['status'] !== 'available') {
      Response::json(['ok' => false, 'error' => ['code' => 'node_not_available', 'message' => 'Rest node is not available.']], 409);
      return null;
    }
    return $node;
  }

  private function hasValidRestContextForRun(PDO $pdo, int $userId, int $activeRunId, array $body): bool
  {
    $ctxRunId = (int)($body['run_id'] ?? 0);
    $ctxNodeId = (int)($body['node_id'] ?? 0);
    if ($ctxRunId !== $activeRunId || $ctxNodeId <= 0) {
      return false;
    }
    $stmt = $pdo->prepare('
      SELECT rn.`id`
      FROM `run_nodes` rn
      JOIN `region_runs` rr ON rr.`id` = rn.`run_id`
      WHERE rn.`id` = ? AND rn.`run_id` = ? AND rr.`user_id` = ? AND rr.`status` = \'active\'
        AND rn.`node_type` = \'rest\' AND rn.`status` = \'available\'
      LIMIT 1
    ');
    $stmt->execute([$ctxNodeId, $ctxRunId, $userId]);
    return (bool)$stmt->fetchColumn();
  }

  private function isUnitInRunSnapshot(PDO $pdo, int $runId, int $unitId): bool
  {
    $stmt = $pdo->prepare('
      SELECT 1 FROM `run_unit_state`
      WHERE `run_id` = ? AND `unit_instance_id` = ?
      LIMIT 1
    ');
    $stmt->execute([$runId, $unitId]);
    return (bool)$stmt->fetchColumn();
  }

  private function assertDiceMutationContextAllowed(PDO $pdo, RunRepository $runRepo, int $userId, int $unitId, array $body): bool
  {
    $activeRun = $runRepo->getActiveRunForUser($userId);
    if ($activeRun === null) {
      return true;
    }
    $activeRunId = (int)$activeRun['run_id'];
    if (!$this->isUnitInRunSnapshot($pdo, $activeRunId, $unitId)) {
      return true;
    }
    return $this->hasValidRestContextForRun($pdo, $userId, $activeRunId, $body);
  }

  private function loadPromotionUnitsForUpdate(PDO $pdo, int $userId, array $unitIds): array
  {
    $placeholders = implode(',', array_fill(0, count($unitIds), '?'));
    $params = array_merge([$userId], $unitIds);
    $stmt = $pdo->prepare("
      SELECT ui.`id`, ui.`unit_type_id`, ui.`tier`, ui.`level`, ut.`max_level`
      FROM `unit_instances` ui
      JOIN `unit_types` ut ON ut.`id` = ui.`unit_type_id`
      WHERE ui.`user_id` = ? AND ui.`id` IN ($placeholders)
      FOR UPDATE
    ");
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $byId = [];
    foreach ($rows as $row) {
      $byId[(int)$row['id']] = $row;
    }
    $out = [];
    foreach ($unitIds as $id) {
      if (isset($byId[$id])) {
        $out[] = $byId[$id];
      }
    }
    return $out;
  }

  private function detachAndDeleteUnits(PDO $pdo, int $userId, array $unitIds): void
  {
    $placeholders = implode(',', array_fill(0, count($unitIds), '?'));

    $stmt = $pdo->prepare("DELETE FROM `unit_dice` WHERE `unit_instance_id` IN ($placeholders)");
    $stmt->execute($unitIds);

    $stmt = $pdo->prepare("
      UPDATE `team_formation`
      SET `unit_instance_id` = NULL
      WHERE `unit_instance_id` IN ($placeholders)
        AND `team_id` IN (SELECT `id` FROM `teams` WHERE `user_id` = ?)
    ");
    $stmt->execute(array_merge($unitIds, [$userId]));

    $stmt = $pdo->prepare("
      DELETE FROM `team_units`
      WHERE `unit_instance_id` IN ($placeholders)
        AND `team_id` IN (SELECT `id` FROM `teams` WHERE `user_id` = ?)
    ");
    $stmt->execute(array_merge($unitIds, [$userId]));

    $stmt = $pdo->prepare("DELETE FROM `run_unit_state` WHERE `unit_instance_id` IN ($placeholders)");
    $stmt->execute($unitIds);

    $stmt = $pdo->prepare("
      DELETE FROM `unit_instances`
      WHERE `user_id` = ? AND `id` IN ($placeholders)
    ");
    $stmt->execute(array_merge([$userId], $unitIds));
  }

  private function loadTeamFormation(PDO $pdo, int $teamId): array
  {
    $stmt = $pdo->prepare('
      SELECT `cell`, `unit_instance_id`
      FROM `team_formation`
      WHERE `team_id` = ?
      ORDER BY `cell` ASC
    ');
    $stmt->execute([$teamId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    return array_map(static fn(array $r): array => [
      'cell' => (string)$r['cell'],
      'unit_instance_id' => $r['unit_instance_id'] !== null ? (string)$r['unit_instance_id'] : null,
    ], $rows);
  }

  private function assertUnitsOwnedByUserForUpdate(PDO $pdo, int $userId, array $unitIds): void
  {
    foreach ($unitIds as $uid) {
      $stmt = $pdo->prepare('SELECT 1 FROM `unit_instances` WHERE `id` = ? AND `user_id` = ? LIMIT 1 FOR UPDATE');
      $stmt->execute([$uid, $userId]);
      if (!(bool)$stmt->fetchColumn()) {
        throw new RuntimeException('unit_ids must contain only owned units.');
      }
    }
  }

  private function replaceTeamMembership(PDO $pdo, int $teamId, array $unitIds): void
  {
    $stmt = $pdo->prepare('DELETE FROM `team_units` WHERE `team_id` = ?');
    $stmt->execute([$teamId]);
    if (count($unitIds) === 0) {
      return;
    }
    $valuesSql = [];
    $params = [];
    foreach ($unitIds as $uid) {
      $valuesSql[] = '(?, ?)';
      $params[] = $teamId;
      $params[] = $uid;
    }
    $sql = 'INSERT INTO `team_units` (`team_id`, `unit_instance_id`) VALUES ' . implode(',', $valuesSql);
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
  }

  private function replaceTeamFormation(PDO $pdo, int $teamId, array $unitIds, array $formationRaw): void
  {
    $unitSet = array_fill_keys($unitIds, true);
    $stmt = $pdo->prepare('DELETE FROM `team_formation` WHERE `team_id` = ?');
    $stmt->execute([$teamId]);
    foreach ($formationRaw as $row) {
      if (!is_array($row)) {
        continue;
      }
      $cell = strtoupper(trim((string)($row['cell'] ?? '')));
      if (!preg_match('/^[ABC][123]$/', $cell)) {
        throw new RuntimeException('Invalid formation cell.');
      }
      $uidRaw = $row['unit_instance_id'] ?? null;
      $uid = ($uidRaw !== null && $uidRaw !== '') ? (int)$uidRaw : null;
      if ($uid !== null && !isset($unitSet[$uid])) {
        throw new RuntimeException('formation.unit_instance_id must exist in unit_ids.');
      }
      if ($uid !== null) {
        $insert = $pdo->prepare('INSERT INTO `team_formation` (`team_id`, `cell`, `unit_instance_id`) VALUES (?, ?, ?)');
        $insert->execute([$teamId, $cell, $uid]);
      }
    }
  }

  private function buildRunUnitSeedRows(PDO $pdo, int $userId, array $unitIds): array
  {
    if (count($unitIds) === 0) {
      return [];
    }
    $placeholders = implode(',', array_fill(0, count($unitIds), '?'));
    $params = array_merge([$userId], $unitIds);
    $stmt = $pdo->prepare("
      SELECT ui.`id` AS `unit_instance_id`, ui.`level`, ut.`base_stats_json`, ut.`max_hp_per_level`
      FROM `unit_instances` ui
      JOIN `unit_types` ut ON ut.`id` = ui.`unit_type_id`
      WHERE ui.`user_id` = ? AND ui.`id` IN ($placeholders)
    ");
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $out = [];
    foreach ($rows as $row) {
      $base = json_decode((string)$row['base_stats_json'], true);
      if (!is_array($base)) {
        $base = [];
      }
      $level = max(1, (int)$row['level']);
      $baseMaxHp = max(1, (int)($base['max_hp'] ?? 1));
      $per = max(0, (int)$row['max_hp_per_level']);
      $maxHp = $baseMaxHp + (($level - 1) * $per);
      $out[] = [
        'unit_instance_id' => (int)$row['unit_instance_id'],
        'current_hp' => $maxHp,
        'is_defeated' => false,
        'cooldowns_json' => '{}',
        'status_effects_json' => '[]',
      ];
    }
    return $out;
  }

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

  private function requireCsrf(CsrfService $csrfService): bool
  {
    $provided = $csrfService->extractProvidedToken();
    if (!$csrfService->validateToken($provided)) {
      Response::json(['ok' => false, 'error' => ['code' => 'csrf_invalid', 'message' => 'Invalid CSRF token.']], 403);
      return false;
    }
    return true;
  }

  private function requirePositiveInt(?string $raw, string $field): ?int
  {
    $v = (int)($raw ?? 0);
    if ($v <= 0) {
      Response::json(['ok' => false, 'error' => ['code' => 'validation_error', 'message' => "{$field} is required."]], 400);
      return null;
    }
    return $v;
  }

  private function services(): array
  {
    $pdo = Db::pdo();
    $userRepo = new UserRepository($pdo);
    $playerStateRepo = new PlayerStateRepository($pdo);
    $energyRepo = new EnergyRepository($pdo);
    $csrfService = new CsrfService();
    $grantService = new GrantService();
    $bootstrapper = new PlayerBootstrapper($playerStateRepo, $energyRepo, $grantService);
    $sessionService = new SessionService($userRepo, $csrfService, $bootstrapper);

    return [
      'pdo' => $pdo,
      'sessionService' => $sessionService,
      'csrfService' => $csrfService,
      'runRepo' => new RunRepository($pdo),
      'runNodeRepo' => new RunNodeRepository($pdo),
      'runEdgeRepo' => new RunEdgeRepository($pdo),
      'teamRepo' => new TeamRepository($pdo),
      'diceRepo' => new DiceRepository($pdo),
    ];
  }
}
