<?php
declare(strict_types=1);

namespace DiceGoblins\Controllers;

use DiceGoblins\Controllers\Concerns\RequiresCsrf;
use DiceGoblins\Core\Db;
use DiceGoblins\Core\Response;
use DiceGoblins\Http\JsonRequestBody;
use DiceGoblins\Repositories\DiceRepository;
use DiceGoblins\Repositories\EnergyRepository;
use DiceGoblins\Repositories\PlayerStateRepository;
use DiceGoblins\Repositories\RunEdgeRepository;
use DiceGoblins\Repositories\RunNodeRepository;
use DiceGoblins\Repositories\RunRepository;
use DiceGoblins\Repositories\TeamRepository;
use DiceGoblins\Repositories\UnitRepository;
use DiceGoblins\Repositories\UserRepository;
use DiceGoblins\Services\CsrfService;
use DiceGoblins\Services\EconomyModifierService;
use DiceGoblins\Services\PlayerBootstrapper;
use DiceGoblins\Services\PromotionService;
use DiceGoblins\Services\UnitCapstoneService;
use DiceGoblins\Services\SessionService;
use DiceGoblins\Services\UnitLoadoutService;
use DiceGoblins\Services\UnitProgressionService;
use PDO;
use RuntimeException;
use Throwable;

final class GameplayController
{
  use RequiresCsrf;

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

      $runState = $svc['runRepo']->getRunUnitState($runIdInt);

      $pdo->commit();
      Response::json([
        'ok' => true,
        'data' => [
          'run_id' => (string)$runIdInt,
          'node_id' => (string)$nodeIdInt,
          'status' => 'open',
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

      $this->healRunUnitsAtRest($pdo, $runIdInt, $userId);
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

  public function getPromotionOptions(?string $unitInstanceId = null): void
  {
    $svc = $this->services();
    $userId = $this->requireUserId($svc['sessionService']);
    if ($userId === null) {
      return;
    }

    $pathUnitId = $this->requirePositiveInt($unitInstanceId, 'unitInstanceId');
    if ($pathUnitId === null) {
      return;
    }

    $unit = $svc['promotionService']->getPromotionUnitSnapshot($userId, $pathUnitId);
    if (!is_array($unit)) {
      Response::json(['ok' => false, 'error' => ['code' => 'not_found', 'message' => 'Unit not found.']], 404);
      return;
    }

    Response::json([
      'ok' => true,
      'data' => [
        'unit_id' => (string)$pathUnitId,
        'current_tier' => (int)$unit['tier'],
        ...$svc['promotionService']->getPromotionPreviewContext($unit),
        'options' => $svc['promotionService']->listPromotionOptions($userId, $unit),
      ],
    ]);
  }

  public function selectCapstone(?string $unitInstanceId = null): void
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

    $abilityId = trim((string)($body['ability_id'] ?? ''));
    if ($abilityId === '') {
      Response::json(['ok' => false, 'error' => ['code' => 'validation_error', 'message' => 'ability_id is required.']], 400);
      return;
    }

    if (!$this->assertUnitMutationContextAllowed($svc['pdo'], $svc['runRepo'], $userId, $unitId)) {
      Response::json(['ok' => false, 'error' => ['code' => 'active_run_unit_locked', 'message' => 'Active run units cannot be changed until the run ends.']], 409);
      return;
    }

    try {
      $selection = $svc['unitCapstoneService']->selectCapstone($userId, $unitId, $abilityId);
      Response::json([
        'ok' => true,
        'data' => [
          'unit_id' => (string)$unitId,
          'selected_capstone' => $selection,
        ],
      ]);
    } catch (RuntimeException $e) {
      Response::json(['ok' => false, 'error' => ['code' => 'validation_error', 'message' => $e->getMessage()]], 400);
    } catch (Throwable $e) {
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
    $destinationUnitTypeId = (int)($body['destination_unit_type_id'] ?? 0);
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
      $activeRunId = (int)$activeRun['run_id'];
      if ($this->isUnitInRunSnapshot($svc['pdo'], $activeRunId, $primaryId)) {
        Response::json(['ok' => false, 'error' => ['code' => 'unit_in_active_run', 'message' => 'Units in an active run cannot be promoted.']], 409);
        return;
      }
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

      $promotionTarget = $svc['promotionService']->promoteUnit(
        $userId,
        $primaryId,
        $secondaryIds,
        $destinationUnitTypeId
      );

      $pdo->commit();
      Response::json([
        'ok' => true,
        'data' => [
          'unit' => ['id' => (string)$primaryId, 'tier' => (int)$promotionTarget['target_tier'], 'level' => 1, 'xp' => 0],
          'consumed_units' => array_map('strval', $secondaryIds),
          'destination' => $promotionTarget,
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

  public function renameUnit(?string $unitInstanceId = null): void
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

    $displayName = trim((string)($body['display_name'] ?? ''));
    if ($displayName === '' || mb_strlen($displayName) > 32) {
      Response::json(['ok' => false, 'error' => ['code' => 'validation_error', 'message' => 'display_name must be 1-32 characters.']], 400);
      return;
    }

    try {
      $svc['unitRepo']->renameUnit($userId, $unitId, $displayName);
      Response::json([
        'ok' => true,
        'data' => [
          'unit_id' => (string)$unitId,
          'display_name' => $displayName,
        ],
      ]);
    } catch (RuntimeException $e) {
      Response::json(['ok' => false, 'error' => ['code' => 'validation_error', 'message' => $e->getMessage()]], 400);
    } catch (Throwable $e) {
      Response::json(['ok' => false, 'error' => ['code' => 'server_error', 'message' => 'Unexpected error.']], 500);
    }
  }

  public function replaceEquippedAbilities(?string $unitInstanceId = null): void
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

    $abilityIdsRaw = $body['ability_ids'] ?? null;
    if (!is_array($abilityIdsRaw)) {
      Response::json(['ok' => false, 'error' => ['code' => 'validation_error', 'message' => 'ability_ids must be an array.']], 400);
      return;
    }

    if (!$this->assertUnitMutationContextAllowed($svc['pdo'], $svc['runRepo'], $userId, $unitId)) {
      Response::json(['ok' => false, 'error' => ['code' => 'active_run_unit_locked', 'message' => 'Active run units cannot be changed until the run ends.']], 409);
      return;
    }

    $abilityIds = array_values(array_map(static fn($value): string => trim((string)$value), $abilityIdsRaw));

    try {
      $svc['unitLoadoutService']->replaceEquippedAbilities($unitId, $abilityIds);
      $equippedByUnit = $svc['unitRepo']->getEquippedAbilitiesForUnitIds([$unitId]);
      Response::json([
        'ok' => true,
        'data' => [
          'unit_id' => (string)$unitId,
          'equipped_abilities' => $equippedByUnit[(string)$unitId] ?? [],
        ],
      ]);
    } catch (RuntimeException $e) {
      Response::json(['ok' => false, 'error' => ['code' => 'validation_error', 'message' => $e->getMessage()]], 400);
    } catch (Throwable $e) {
      Response::json(['ok' => false, 'error' => ['code' => 'server_error', 'message' => 'Unexpected error.']], 500);
    }
  }

  public function assignAbilitySlotDie(?string $unitInstanceId = null, ?string $abilityId = null, ?string $slotIndex = null): void
  {
    $this->handleAbilitySlotDiceMutation($unitInstanceId, $abilityId, $slotIndex, true);
  }

  public function clearAbilitySlotDie(?string $unitInstanceId = null, ?string $abilityId = null, ?string $slotIndex = null): void
  {
    $this->handleAbilitySlotDiceMutation($unitInstanceId, $abilityId, $slotIndex, false);
  }

  public function sellDice(?string $diceInstanceId = null): void
  {
    $svc = $this->services();
    $userId = $this->requireUserId($svc['sessionService']);
    if ($userId === null || !$this->requireCsrf($svc['csrfService'])) {
      return;
    }

    $diceId = $this->requirePositiveInt($diceInstanceId, 'diceInstanceId');
    if ($diceId === null) {
      return;
    }

    /** @var PDO $pdo */
    $pdo = $svc['pdo'];
    try {
      $pdo->beginTransaction();

      $dice = $svc['diceRepo']->getDiceWithAffixesForUserByIdForUpdate($userId, $diceId);
      if (!is_array($dice)) {
        $pdo->rollBack();
        Response::json(['ok' => false, 'error' => ['code' => 'not_found', 'message' => 'Dice not found.']], 404);
        return;
      }

      if ($svc['diceRepo']->isDiceAssignedToAbilitySlotForUpdate($diceId)) {
        $pdo->rollBack();
        Response::json(['ok' => false, 'error' => ['code' => 'validation_error', 'message' => 'Equipped dice cannot be sold.']], 400);
        return;
      }

      $svc['playerStateRepo']->ensurePlayerState($userId);
      $state = $svc['playerStateRepo']->getPlayerStateForUpdate($userId);
      if (!is_array($state)) {
        $pdo->rollBack();
        Response::json(['ok' => false, 'error' => ['code' => 'server_error', 'message' => 'Player state unavailable.']], 500);
        return;
      }

      $sellValue = (new EconomyModifierService($pdo))
        ->adjustedSellValueForUser($userId, max(1, (int)($dice['sell_value'] ?? 0)));
      $nextSoft = max(0, (int)$state['currency_soft']) + $sellValue;
      $svc['playerStateRepo']->setCurrency($userId, $nextSoft, max(0, (int)$state['currency_hard']));
      $svc['diceRepo']->deleteOwnedDiceInstance($userId, $diceId);

      $pdo->commit();
      Response::json([
        'ok' => true,
        'data' => [
          'dice_id' => (string)$diceId,
          'sell_value' => $sellValue,
          'currency_soft' => $nextSoft,
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

    if (!$this->assertUnitMutationContextAllowed($svc['pdo'], $svc['runRepo'], $userId, $unitId)) {
      Response::json(['ok' => false, 'error' => ['code' => 'active_run_unit_locked', 'message' => 'Active run units cannot be changed until the run ends.']], 409);
      return;
    }

    Response::json([
      'ok' => false,
      'error' => [
        'code' => 'validation_error',
        'message' => 'Legacy unit-level dice equip is no longer supported. Assign dice to ability slots instead.',
      ],
    ], 400);
  }

  private function handleAbilitySlotDiceMutation(?string $unitInstanceId, ?string $abilityId, ?string $slotIndex, bool $isAssign): void
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

    $normalizedAbilityId = trim((string)($abilityId ?? ''));
    if ($normalizedAbilityId === '') {
      Response::json(['ok' => false, 'error' => ['code' => 'validation_error', 'message' => 'abilityId is required.']], 400);
      return;
    }

    $slotIndexInt = $this->requireNonNegativeInt($slotIndex, 'slotIndex');
    if ($slotIndexInt === null) {
      return;
    }

    $body = $this->readJsonBody();
    if ($body === null) {
      Response::json(['ok' => false, 'error' => ['code' => 'validation_error', 'message' => 'Invalid JSON body.']], 400);
      return;
    }

    if (!$this->assertUnitMutationContextAllowed($svc['pdo'], $svc['runRepo'], $userId, $unitId)) {
      Response::json(['ok' => false, 'error' => ['code' => 'active_run_unit_locked', 'message' => 'Active run units cannot be changed until the run ends.']], 409);
      return;
    }

    try {
      if ($isAssign) {
        $diceId = (int)($body['dice_instance_id'] ?? 0);
        if ($diceId <= 0) {
          Response::json(['ok' => false, 'error' => ['code' => 'validation_error', 'message' => 'dice_instance_id is required.']], 400);
          return;
        }
        $svc['unitLoadoutService']->assignDieToAbilitySlot($unitId, $normalizedAbilityId, $slotIndexInt, $diceId);
      } else {
        $svc['unitLoadoutService']->clearAbilitySlotDie($unitId, $normalizedAbilityId, $slotIndexInt);
      }

      $abilityDiceByUnit = $svc['unitRepo']->getAbilityDiceBindingsForUnitIds([$unitId]);
      Response::json([
        'ok' => true,
        'data' => [
          'unit_id' => (string)$unitId,
          'ability_dice' => $abilityDiceByUnit[(string)$unitId] ?? [],
        ],
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
    $runNodeRepo->syncAvailableNodesFromClearedParents($runId);
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

  private function assertUnitMutationContextAllowed(PDO $pdo, RunRepository $runRepo, int $userId, int $unitId): bool
  {
    $activeRun = $runRepo->getActiveRunForUser($userId);
    if ($activeRun === null) {
      return true;
    }
    $activeRunId = (int)$activeRun['run_id'];
    return !$this->isUnitInRunSnapshot($pdo, $activeRunId, $unitId);
  }

  private function healRunUnitsAtRest(PDO $pdo, int $runId, int $userId): void
  {
    $stmt = $pdo->prepare(' 
      SELECT
        rus.`unit_instance_id`,
        ui.`level`,
        ut.`base_stats_json`,
        ut.`max_hp_per_level`
      FROM `run_unit_state` rus
      JOIN `unit_instances` ui ON ui.`id` = rus.`unit_instance_id`
      JOIN `unit_types` ut ON ut.`id` = ui.`unit_type_id`
      WHERE rus.`run_id` = ? AND ui.`user_id` = ?
      FOR UPDATE
    ');
    $stmt->execute([$runId, $userId]);

    $upsert = $pdo->prepare(' 
      INSERT INTO `run_unit_state` (`run_id`, `unit_instance_id`, `current_hp`, `is_defeated`, `cooldowns_json`, `status_effects_json`)
      VALUES (?, ?, ?, 0, ?, ?)
      ON DUPLICATE KEY UPDATE
        `current_hp` = VALUES(`current_hp`),
        `is_defeated` = VALUES(`is_defeated`),
        `cooldowns_json` = VALUES(`cooldowns_json`),
        `status_effects_json` = VALUES(`status_effects_json`)
    ');

    $progression = new UnitProgressionService();
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
      $maxHp = $progression->maxHpForLevel(
        $row['base_stats_json'],
        (int)$row['level'],
        (int)$row['max_hp_per_level']
      );
      $upsert->execute([$runId, (int)$row['unit_instance_id'], $maxHp, '{}', '[]']);
    }
  }

  private function readJsonBody(): ?array
  {
    return JsonRequestBody::decode();
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

  private function requireNonNegativeInt(?string $raw, string $field): ?int
  {
    if ($raw === null || $raw === '' || !preg_match('/^\d+$/', $raw)) {
      Response::json(['ok' => false, 'error' => ['code' => 'validation_error', 'message' => "{$field} is required."]], 400);
      return null;
    }

    return (int)$raw;
  }

  private function services(): array
  {
    $pdo = Db::pdo();
    $core = ControllerServiceFactory::buildCore($pdo);

    return [
      'pdo' => $pdo,
      'sessionService' => $core['sessionService'],
      'csrfService' => $core['csrfService'],
      'runRepo' => new RunRepository($pdo),
      'runNodeRepo' => new RunNodeRepository($pdo),
      'runEdgeRepo' => new RunEdgeRepository($pdo),
      'teamRepo' => new TeamRepository($pdo),
      'unitRepo' => new UnitRepository($pdo),
      'diceRepo' => new DiceRepository($pdo),
      'playerStateRepo' => new PlayerStateRepository($pdo),
      'unitLoadoutService' => new UnitLoadoutService($pdo),
      'promotionService' => new PromotionService($pdo),
      'unitCapstoneService' => new UnitCapstoneService($pdo),
    ];
  }
}
