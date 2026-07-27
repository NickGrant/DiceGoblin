<?php
declare(strict_types=1);

namespace DiceGoblins\Controllers;

use DiceGoblins\Controllers\Concerns\HandlesControllerRequests;
use DiceGoblins\Controllers\Concerns\RequiresCsrf;
use DiceGoblins\Core\Db;
use DiceGoblins\Core\Response;
use DiceGoblins\Repositories\DiceRepository;
use DiceGoblins\Repositories\PlayerStateRepository;
use DiceGoblins\Repositories\RunEdgeRepository;
use DiceGoblins\Repositories\RunNodeRepository;
use DiceGoblins\Repositories\RunRepository;
use DiceGoblins\Repositories\UnitRepository;
use DiceGoblins\Services\ConsumableItemService;
use DiceGoblins\Services\DiceSalvageService;
use DiceGoblins\Services\EconomyModifierService;
use DiceGoblins\Services\ItemInventoryService;
use DiceGoblins\Services\PromotionService;
use DiceGoblins\Services\UnitCapstoneService;
use DiceGoblins\Services\UnitLoadoutService;
use DiceGoblins\Services\UnitMutationGuardService;
use DiceGoblins\Services\UnitProgressionService;
use PDO;
use RuntimeException;
use Throwable;

final class GameplayController
{
  use HandlesControllerRequests;
  use RequiresCsrf;

  public function openRest(?string $runId = null, ?string $nodeId = null): void
  {
    $svc = $this->services();
    $userId = $this->requireMutationUserId($svc['sessionService'], $svc['csrfService']);
    if ($userId === null) {
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
    $userId = $this->requireMutationUserId($svc['sessionService'], $svc['csrfService']);
    if ($userId === null) {
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
    $userId = $this->requireMutationUserId($svc['sessionService'], $svc['csrfService']);
    if ($userId === null) {
      return;
    }

    $unitId = $this->requirePositiveInt($unitInstanceId, 'unitInstanceId');
    if ($unitId === null) {
      return;
    }

    $body = $this->readJsonBody();
    if ($body === null) {
      return;
    }

    $abilityId = trim((string)($body['ability_id'] ?? ''));
    if ($abilityId === '') {
      Response::json(['ok' => false, 'error' => ['code' => 'validation_error', 'message' => 'ability_id is required.']], 400);
      return;
    }

    if (!$this->requireMutableUnit($svc['unitMutationGuardService'], $userId, $unitId)) {
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
    $userId = $this->requireMutationUserId($svc['sessionService'], $svc['csrfService']);
    if ($userId === null) {
      return;
    }

    $pathUnitId = $this->requirePositiveInt($unitInstanceId, 'unitInstanceId');
    if ($pathUnitId === null) {
      return;
    }
    $body = $this->readJsonBody();
    if ($body === null) {
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

    $lockedUnitIds = array_flip($svc['unitMutationGuardService']->getLockedUnitIdsForUser($userId, array_merge([$primaryId], $secondaryIds)));
    if (isset($lockedUnitIds[$primaryId])) {
      Response::json(['ok' => false, 'error' => ['code' => 'unit_in_active_run', 'message' => 'Units in an active run cannot be promoted.']], 409);
      return;
    }
    foreach ($secondaryIds as $sid) {
      if (isset($lockedUnitIds[$sid])) {
        Response::json(['ok' => false, 'error' => ['code' => 'unit_in_active_run', 'message' => 'Secondary units in active run snapshot cannot be consumed.']], 409);
        return;
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

  public function renameUnit(?string $unitInstanceId = null): void
  {
    $svc = $this->services();
    $userId = $this->requireMutationUserId($svc['sessionService'], $svc['csrfService']);
    if ($userId === null) {
      return;
    }

    $unitId = $this->requirePositiveInt($unitInstanceId, 'unitInstanceId');
    if ($unitId === null) {
      return;
    }

    $body = $this->readJsonBody();
    if ($body === null) {
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
    $userId = $this->requireMutationUserId($svc['sessionService'], $svc['csrfService']);
    if ($userId === null) {
      return;
    }

    $unitId = $this->requirePositiveInt($unitInstanceId, 'unitInstanceId');
    if ($unitId === null) {
      return;
    }

    $body = $this->readJsonBody();
    if ($body === null) {
      return;
    }

    $abilityIdsRaw = $body['ability_ids'] ?? null;
    if (!is_array($abilityIdsRaw)) {
      Response::json(['ok' => false, 'error' => ['code' => 'validation_error', 'message' => 'ability_ids must be an array.']], 400);
      return;
    }

    if (!$this->requireMutableUnit($svc['unitMutationGuardService'], $userId, $unitId)) {
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
    $userId = $this->requireMutationUserId($svc['sessionService'], $svc['csrfService']);
    if ($userId === null) {
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

  public function salvageDice(?string $diceInstanceId = null): void
  {
    $svc = $this->services();
    $userId = $this->requireMutationUserId($svc['sessionService'], $svc['csrfService']);
    if ($userId === null) {
      return;
    }

    $diceId = $this->requirePositiveInt($diceInstanceId, 'diceInstanceId');
    if ($diceId === null) {
      return;
    }

    try {
      $result = $svc['diceSalvageService']->salvageDice($userId, $diceId);
      Response::json([
        'ok' => true,
        'data' => $result,
      ]);
    } catch (RuntimeException $e) {
      $message = $e->getMessage();
      if ($message === 'dice_not_found') {
        Response::json(['ok' => false, 'error' => ['code' => 'not_found', 'message' => 'Dice not found.']], 404);
        return;
      }
      if ($message === 'equipped_dice_cannot_be_salvaged') {
        Response::json(['ok' => false, 'error' => ['code' => 'validation_error', 'message' => 'Equipped dice cannot be salvaged.']], 400);
        return;
      }
      if ($message === 'wrong_machine_locked') {
        Response::json(['ok' => false, 'error' => ['code' => 'wrong_machine_locked', 'message' => 'Recover the Wrong Machine before salvaging dice into Raw Chaos.']], 403);
        return;
      }
      Response::json(['ok' => false, 'error' => ['code' => 'validation_error', 'message' => $message]], 400);
    } catch (Throwable) {
      Response::json(['ok' => false, 'error' => ['code' => 'server_error', 'message' => 'Unexpected error.']], 500);
    }
  }

  public function healRunUnitWithItem(?string $runId = null, ?string $unitInstanceId = null): void
  {
    $svc = $this->services();
    $userId = $this->requireMutationUserId($svc['sessionService'], $svc['csrfService']);
    if ($userId === null) {
      return;
    }

    $runIdInt = $this->requirePositiveInt($runId, 'runId');
    $unitIdInt = $this->requirePositiveInt($unitInstanceId, 'unitInstanceId');
    if ($runIdInt === null || $unitIdInt === null) {
      return;
    }

    $body = $this->readJsonBody();
    if ($body === null) {
      return;
    }

    $itemSlug = trim((string)($body['item_slug'] ?? ''));
    if ($itemSlug === '') {
      Response::json(['ok' => false, 'error' => ['code' => 'validation_error', 'message' => 'item_slug is required.']], 400);
      return;
    }

    try {
      Response::json([
        'ok' => true,
        'data' => $svc['consumableItemService']->healRunUnit($userId, $runIdInt, $unitIdInt, $itemSlug),
      ]);
    } catch (RuntimeException $e) {
      $message = $e->getMessage();
      $status = match ($message) {
        'run_not_active', 'combat_resolution_active', 'unit_not_wounded' => 409,
        'unit_not_in_run' => 404,
        'insufficient_items' => 409,
        'item_not_healing_consumable' => 400,
        default => 400,
      };
      $publicMessage = match ($message) {
        'run_not_active' => 'Run is not active.',
        'combat_resolution_active' => 'Healing items cannot be used while combat is resolving.',
        'unit_not_wounded' => 'Unit is already at full health.',
        'unit_not_in_run' => 'Unit is not part of this run.',
        'insufficient_items' => 'Not enough items.',
        'item_not_healing_consumable' => 'Item cannot heal run units.',
        default => $message,
      };
      Response::json(['ok' => false, 'error' => ['code' => $message, 'message' => $publicMessage]], $status);
    } catch (Throwable) {
      Response::json(['ok' => false, 'error' => ['code' => 'server_error', 'message' => 'Unexpected error.']], 500);
    }
  }

  public function restoreEnergyWithItem(): void
  {
    $svc = $this->services();
    $userId = $this->requireMutationUserId($svc['sessionService'], $svc['csrfService']);
    if ($userId === null) {
      return;
    }

    $body = $this->readJsonBody();
    if ($body === null) {
      return;
    }

    $itemSlug = trim((string)($body['item_slug'] ?? ''));
    if ($itemSlug === '') {
      Response::json(['ok' => false, 'error' => ['code' => 'validation_error', 'message' => 'item_slug is required.']], 400);
      return;
    }

    try {
      Response::json([
        'ok' => true,
        'data' => $svc['consumableItemService']->restoreEnergy($userId, $itemSlug),
      ]);
    } catch (RuntimeException $e) {
      $message = $e->getMessage();
      $status = match ($message) {
        'energy_full', 'insufficient_items' => 409,
        'item_not_energy_consumable' => 400,
        default => 400,
      };
      $publicMessage = match ($message) {
        'energy_full' => 'Energy is already full.',
        'insufficient_items' => 'Not enough items.',
        'item_not_energy_consumable' => 'Item cannot restore energy.',
        default => $message,
      };
      Response::json(['ok' => false, 'error' => ['code' => $message, 'message' => $publicMessage]], $status);
    } catch (Throwable) {
      Response::json(['ok' => false, 'error' => ['code' => 'server_error', 'message' => 'Unexpected error.']], 500);
    }
  }

  private function handleAbilitySlotDiceMutation(?string $unitInstanceId, ?string $abilityId, ?string $slotIndex, bool $isAssign): void
  {
    $svc = $this->services();
    $userId = $this->requireMutationUserId($svc['sessionService'], $svc['csrfService']);
    if ($userId === null) {
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
      return;
    }

    if (!$this->requireMutableUnit($svc['unitMutationGuardService'], $userId, $unitId)) {
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
      'unitRepo' => new UnitRepository($pdo),
      'diceRepo' => new DiceRepository($pdo),
      'playerStateRepo' => new PlayerStateRepository($pdo),
      'diceSalvageService' => new DiceSalvageService($pdo, new DiceRepository($pdo), new PlayerStateRepository($pdo)),
      'consumableItemService' => new ConsumableItemService($pdo, new ItemInventoryService($pdo)),
      'unitLoadoutService' => new UnitLoadoutService($pdo),
      'promotionService' => new PromotionService($pdo),
      'unitCapstoneService' => new UnitCapstoneService($pdo),
      'unitMutationGuardService' => new UnitMutationGuardService($pdo, new RunRepository($pdo)),
    ];
  }
}
