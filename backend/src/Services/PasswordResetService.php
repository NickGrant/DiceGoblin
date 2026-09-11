<?php
declare(strict_types=1);

namespace DiceGoblins\Services;

use DiceGoblins\Repositories\UserRepository;
use PDO;
use Throwable;

/** Owns atomic password-reset issuance and consumption operations. */
final class PasswordResetService
{
  public function __construct(private readonly PDO $pdo, private readonly UserRepository $users) {}

  public function issueToken(int $userId, string $tokenHash, string $expiresAt): void
  {
    $this->transactional(function () use ($userId, $tokenHash, $expiresAt): void {
      $this->users->supersedeActivePasswordResetTokens($userId);
      $this->users->insertPasswordResetToken($userId, $tokenHash, $expiresAt);
    });
  }

  public function consumeToken(string $tokenHash, string $passwordHash): ?int
  {
    return $this->transactional(function () use ($tokenHash, $passwordHash): ?int {
      $token = $this->users->lockActivePasswordResetToken($tokenHash);
      if ($token === null || !$this->users->updateLocalPasswordHash($token['user_id'], $passwordHash)) {
        return null;
      }
      $this->users->markPasswordResetTokenUsed($token['id']);
      return $token['user_id'];
    });
  }

  private function transactional(callable $operation): mixed
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
