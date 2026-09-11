<?php
declare(strict_types=1);

namespace DiceGoblins\Repositories;

use PDO;
use RuntimeException;

final class UserRepository
{
  public function __construct(private readonly PDO $pdo) {}

  public function createUser(string $displayName, ?string $avatarUrl): int
  {
    $stmt = $this->pdo->prepare('INSERT INTO `users` (`display_name`, `avatar_url`) VALUES (?, ?)');
    $stmt->execute([trim($displayName) ?: 'Goblin', $avatarUrl]);
    return (int)$this->pdo->lastInsertId();
  }

  public function createLocalCredential(int $userId, string $email, string $passwordHash): void
  {
    $this->pdo->prepare('INSERT INTO `user_local_credentials` (`user_id`, `email`, `password_hash`) VALUES (?, ?, ?)')
      ->execute([$userId, $this->normalizeEmail($email), $passwordHash]);
  }

  /** @return array{id:string,email:string,password_hash:string,display_name:string,avatar_url:?string}|null */
  public function getUserByLocalEmail(string $email): ?array
  {
    $stmt = $this->pdo->prepare('SELECT u.`id`, c.`email`, c.`password_hash`, u.`display_name`, u.`avatar_url` FROM `user_local_credentials` c JOIN `users` u ON u.`id` = c.`user_id` WHERE c.`email` = ? LIMIT 1');
    $stmt->execute([$this->normalizeEmail($email)]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) return null;
    return ['id' => (string)$row['id'], 'email' => (string)$row['email'], 'password_hash' => (string)$row['password_hash'], 'display_name' => (string)$row['display_name'], 'avatar_url' => $row['avatar_url'] !== null ? (string)$row['avatar_url'] : null];
  }

  /** @return array{id:string,provider_user_id:string,display_name:string,avatar_url:?string}|null */
  public function getUserByExternalIdentity(string $provider, string $providerUserId, bool $forUpdate = false): ?array
  {
    $sql = 'SELECT u.`id`, i.`provider_user_id`, u.`display_name`, u.`avatar_url` FROM `user_external_identities` i JOIN `users` u ON u.`id` = i.`user_id` WHERE i.`provider` = ? AND i.`provider_user_id` = ? LIMIT 1' . ($forUpdate ? ' FOR UPDATE' : '');
    $stmt = $this->pdo->prepare($sql);
    $stmt->execute([trim($provider), trim($providerUserId)]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) return null;
    return ['id' => (string)$row['id'], 'provider_user_id' => (string)$row['provider_user_id'], 'display_name' => (string)$row['display_name'], 'avatar_url' => $row['avatar_url'] !== null ? (string)$row['avatar_url'] : null];
  }

  public function createExternalIdentity(int $userId, string $provider, string $providerUserId, ?string $providerEmail = null): void
  {
    $provider = trim($provider);
    $providerUserId = trim($providerUserId);
    if ($provider === '' || strlen($provider) > 32 || $providerUserId === '' || strlen($providerUserId) > 128) {
      throw new RuntimeException('External provider identity is invalid.');
    }
    $this->pdo->prepare('INSERT INTO `user_external_identities` (`user_id`, `provider`, `provider_user_id`, `provider_email`) VALUES (?, ?, ?, ?)')->execute([$userId, $provider, $providerUserId, $providerEmail]);
  }

  public function updateUserProfile(int $userId, string $displayName, ?string $avatarUrl): void
  {
    $this->pdo->prepare('UPDATE `users` SET `display_name` = ?, `avatar_url` = ? WHERE `id` = ?')->execute([trim($displayName) ?: 'Goblin', $avatarUrl, $userId]);
  }

  public function supersedeActivePasswordResetTokens(int $userId): void
  {
    $this->pdo->prepare('UPDATE `password_reset_tokens` SET `used_at` = UTC_TIMESTAMP() WHERE `user_id` = ? AND `used_at` IS NULL')->execute([$userId]);
  }

  public function insertPasswordResetToken(int $userId, string $tokenHash, string $expiresAt): void
  {
    $this->pdo->prepare('INSERT INTO `password_reset_tokens` (`user_id`, `token_hash`, `expires_at`) VALUES (?, ?, ?)')->execute([$userId, $tokenHash, $expiresAt]);
  }

  /** @return array{id:int,user_id:int}|null */
  public function lockActivePasswordResetToken(string $tokenHash): ?array
  {
    $stmt = $this->pdo->prepare('SELECT `id`, `user_id` FROM `password_reset_tokens` WHERE `token_hash` = ? AND `used_at` IS NULL AND `expires_at` > UTC_TIMESTAMP() LIMIT 1 FOR UPDATE');
    $stmt->execute([$tokenHash]);
    $token = $stmt->fetch(PDO::FETCH_ASSOC);
    return $token ? ['id' => (int)$token['id'], 'user_id' => (int)$token['user_id']] : null;
  }

  public function updateLocalPasswordHash(int $userId, string $passwordHash): bool
  {
    $stmt = $this->pdo->prepare('UPDATE `user_local_credentials` SET `password_hash` = ? WHERE `user_id` = ?');
    $stmt->execute([$passwordHash, $userId]);
    return $stmt->rowCount() === 1;
  }

  public function markPasswordResetTokenUsed(int $tokenId): void
  {
    $this->pdo->prepare('UPDATE `password_reset_tokens` SET `used_at` = UTC_TIMESTAMP() WHERE `id` = ?')->execute([$tokenId]);
  }

  /** @return array{id:string,display_name:string,avatar_url:?string}|null */
  public function getSessionIdentity(int $userId): ?array
  {
    $stmt = $this->pdo->prepare('SELECT `id`, `display_name`, `avatar_url` FROM `users` WHERE `id` = ? LIMIT 1');
    $stmt->execute([$userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ? ['id' => (string)$row['id'], 'display_name' => (string)$row['display_name'], 'avatar_url' => $row['avatar_url'] !== null ? (string)$row['avatar_url'] : null] : null;
  }

  /** @return array{id:string,display_name:string,role:string}|null */
  public function getGameIdentity(int $userId): ?array
  {
    $stmt = $this->pdo->prepare('SELECT `id`, `display_name`, `role` FROM `users` WHERE `id` = ? LIMIT 1');
    $stmt->execute([$userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ? [
      'id' => (string)$row['id'],
      'display_name' => (string)$row['display_name'],
      'role' => (string)$row['role'],
    ] : null;
  }

  private function normalizeEmail(string $email): string { return strtolower(trim($email)); }
}
