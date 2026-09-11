<?php
declare(strict_types=1);

namespace DiceGoblins\Services;

use DiceGoblins\Repositories\PlayerStateRepository;
use DiceGoblins\Repositories\UserRepository;
use PDO;
use Throwable;

/** Owns the transaction that creates an account and its required player state. */
final class AccountCreationService
{
  public function __construct(
    private readonly PDO $pdo,
    private readonly UserRepository $users,
    private readonly PlayerStateRepository $playerState,
  ) {}

  public function createLocal(string $email, string $passwordHash, string $displayName): int
  {
    return $this->transactional(function () use ($email, $passwordHash, $displayName): int {
      $userId = $this->users->createUser($displayName, null);
      $this->users->createLocalCredential($userId, $email, $passwordHash);
      $this->playerState->createInitialState($userId);
      return $userId;
    });
  }

  public function findOrCreateExternal(string $provider, string $providerUserId, string $displayName, ?string $avatarUrl, ?string $providerEmail = null): int
  {
    return $this->transactional(function () use ($provider, $providerUserId, $displayName, $avatarUrl, $providerEmail): int {
      $existing = $this->users->getUserByExternalIdentity($provider, $providerUserId, true);
      if ($existing !== null) {
        $userId = (int)$existing['id'];
        $this->users->updateUserProfile($userId, $displayName, $avatarUrl);
        return $userId;
      }
      $userId = $this->users->createUser($displayName, $avatarUrl);
      $this->users->createExternalIdentity($userId, $provider, $providerUserId, $providerEmail);
      $this->playerState->createInitialState($userId);
      return $userId;
    });
  }

  private function transactional(callable $operation): int
  {
    try {
      $this->pdo->beginTransaction();
      $result = $operation();
      $this->pdo->commit();
      return $result;
    } catch (Throwable $e) {
      if ($this->pdo->inTransaction()) $this->pdo->rollBack();
      throw $e;
    }
  }
}
