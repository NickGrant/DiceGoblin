<?php
declare(strict_types=1);

namespace DiceGoblins\Application\Commands;

use DiceGoblins\Repositories\IdempotencyRequestRepository;
use DiceGoblins\Repositories\PlayerStateRepository;
use DiceGoblins\Repositories\SquadRepository;
use JsonException;
use PDO;
use Throwable;

final class CreateSquadCommand
{
  public function __construct(
    private readonly PDO $pdo,
    private readonly PlayerStateRepository $playerState,
    private readonly SquadRepository $squads,
    private readonly IdempotencyRequestRepository $idempotency,
    private readonly SquadCommandSupport $support,
  ) {}

  /** @param array<string,mixed> $request @return array<string,mixed> */
  public function execute(int $userId, array $request, ?string $providedKey): array
  {
    $configuration = SquadConfiguration::fromRequest($request);
    $key = IdempotencyKey::validate($providedKey);
    try {
      $hash = hash('sha256', json_encode($configuration->canonicalRequest(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
    } catch (JsonException $e) {
      throw new SquadValidationException('Squad request cannot be encoded.', 0, $e);
    }

    try {
      $this->pdo->beginTransaction();
      $context = $this->support->lockPlayer($userId);
      $prior = $this->idempotency->getForUser($userId, $key);
      if ($prior !== null) {
        if ($prior['operation_type'] !== 'create_squad' || !hash_equals($prior['request_hash'], $hash)) {
          throw new IdempotencyConflictException('Idempotency key was already used for another request.');
        }
        $this->pdo->commit();
        return $prior['result'];
      }

      $this->support->validateUnits($userId, $configuration);
      $squadId = $this->squads->create($userId, $configuration->name);
      $this->squads->replaceFormation($squadId, $configuration->formation);
      $active = $context['active_squad_id'];
      if ($active === null) {
        $active = $squadId;
        $revision = $this->playerState->setActiveSquadAndIncrementRevision($userId, $squadId);
      } else {
        $revision = $this->playerState->incrementRevision($userId);
      }
      $result = [
        'squad' => $this->support->squadView($userId, $squadId, $active),
        'active_squad_id' => (string)$active,
        'player_revision' => $revision,
      ];
      $this->idempotency->insertFinalized($userId, $key, 'create_squad', $hash, $result);
      $this->pdo->commit();
      return $result;
    } catch (Throwable $e) {
      if ($this->pdo->inTransaction()) $this->pdo->rollBack();
      throw $e;
    }
  }
}
