<?php
declare(strict_types=1);

namespace DiceGoblins\Application\Commands;

use Closure;
use DiceGoblins\Application\NormalUnitCreationService;
use DiceGoblins\Application\Queries\OnboardingQuery;
use DiceGoblins\Content\ContentRegistry;
use DiceGoblins\Repositories\IdempotencyRequestRepository;
use DiceGoblins\Repositories\PlayerStateRepository;
use DiceGoblins\Repositories\SquadRepository;
use DiceGoblins\Repositories\WarbandDiceRepository;
use DiceGoblins\Repositories\WarbandUnitRepository;
use DiceGoblins\Support\ClientSafeInteger;
use PDO;
use RuntimeException;
use Throwable;

final class ProvisionStartingWarbandCommand
{
  private const OPERATION = 'provision_starting_warband';

  public function __construct(
    private readonly PDO $pdo,
    private readonly PlayerStateRepository $playerState,
    private readonly IdempotencyRequestRepository $idempotency,
    private readonly OnboardingQuery $onboarding,
    private readonly NormalUnitCreationService $unitCreation,
    private readonly WarbandUnitRepository $units,
    private readonly WarbandDiceRepository $dice,
    private readonly SquadRepository $squads,
    private readonly ContentRegistry $content,
    private readonly ?Closure $beforeCommit = null,
  ) {}

  /** @param array<string,mixed> $request @return array<string,mixed> */
  public function execute(int $userId, array $request, ?string $providedKey): array
  {
    if ($request !== []) {
      throw new OnboardingProvisionException('invalid_onboarding_provision', 'Provision request must be empty.', 422);
    }
    $key = IdempotencyKey::validate($providedKey);
    $requestHash = hash('sha256', '{}');

    try {
      $this->pdo->beginTransaction();
      $state = $this->playerState->getPlayerStateForUpdate($userId);
      if ($state === null) throw new RuntimeException('Required player state is missing.');

      $prior = $this->idempotency->getForUser($userId, $key);
      if ($prior !== null) {
        if ($prior['operation_type'] !== self::OPERATION || !hash_equals($prior['request_hash'], $requestHash)) {
          throw new IdempotencyConflictException('Idempotency key was already used for another request.');
        }
        $this->validateResult($prior['result']);
        $this->pdo->commit();
        return $prior['result'];
      }

      if ($this->onboarding->execute($userId)['status'] !== 'ready_to_provision') {
        throw new OnboardingProvisionException('onboarding_not_ready', 'Onboarding is not ready for provisioning.', 409);
      }
      if ($state['player_revision'] < 0 || $state['player_revision'] >= ClientSafeInteger::MAXIMUM) {
        throw new RuntimeException('Player revision is incoherent.');
      }
      $starting = $this->content->onboardingFlow()['starting_warband'];
      $kinId = $this->content->onboardingFlow()['starting_kin_id'];
      $unit = $this->unitCreation->create($userId, $starting['unit_type_id'], $kinId);
      $die = $this->dice->createActive($userId, $starting['die_size'], $starting['dice_profile_id']);
      $this->units->replaceLoadout((int)$unit['id'], [[
        'ability_id' => $starting['ability_id'], 'dice_instance_ids' => [(int)$die['id']],
      ]]);
      $squadId = $this->squads->create($userId, $starting['squad_name']);
      $this->squads->replaceFormation($squadId, [0 => (int)$unit['id']]);
      $revision = $this->playerState->setActiveSquadAndIncrementRevision($userId, $squadId);
      if ($revision !== $state['player_revision'] + 1 || $revision > ClientSafeInteger::MAXIMUM) {
        throw new RuntimeException('Player revision transition is incoherent.');
      }
      $result = ['unit_id' => $unit['id'], 'die_id' => $die['id'],
        'active_squad_id' => (string)$squadId, 'player_revision' => $revision,
        'onboarding_status' => 'complete'];
      $this->idempotency->insertFinalized($userId, $key, self::OPERATION, $requestHash, $result);
      $stored = $this->idempotency->getForUser($userId, $key);
      if ($stored === null || $stored['operation_type'] !== self::OPERATION
        || !hash_equals($stored['request_hash'], $requestHash)) {
        throw new RuntimeException('Finalized onboarding receipt is unavailable.');
      }
      $this->validateResult($stored['result']);
      if ($this->beforeCommit !== null) ($this->beforeCommit)();
      $this->pdo->commit();
      return $stored['result'];
    } catch (Throwable $e) {
      if ($this->pdo->inTransaction()) $this->pdo->rollBack();
      throw $e;
    }
  }

  /** @param array<string,mixed> $result */
  private function validateResult(array $result): void
  {
    $keys = array_keys($result); sort($keys, SORT_STRING);
    if ($keys !== ['active_squad_id', 'die_id', 'onboarding_status', 'player_revision', 'unit_id']
      || ($result['onboarding_status'] ?? null) !== 'complete'
      || !is_int($result['player_revision'] ?? null) || $result['player_revision'] < 1
      || $result['player_revision'] > ClientSafeInteger::MAXIMUM) {
      throw new RuntimeException('Persisted onboarding receipt is invalid.');
    }
    foreach (['unit_id', 'die_id', 'active_squad_id'] as $field) {
      if (!is_string($result[$field]) || preg_match('/^[1-9][0-9]*$/D', $result[$field]) !== 1) {
        throw new RuntimeException('Persisted onboarding receipt is invalid.');
      }
    }
  }
}
