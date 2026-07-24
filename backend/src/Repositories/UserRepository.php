<?php
declare(strict_types=1);

/**
 * File: C:\xampp\htdocs\dice-goblin\backend\src\Repositories\UserRepository.php
 * Purpose: Project PHP module.
 */

namespace DiceGoblins\Repositories;

use PDO;
use RuntimeException;
use Throwable;

final class UserRepository
{
  public function __construct(
    private readonly PDO $pdo,
  ) {}

  /**
   * Fetch user by internal id.
   *
   * @return array{id:string,discord_id:?string,local_email:?string,display_name:string,avatar_url:?string,created_at:string,updated_at:string}|null
   */
  public function getUserById(int $userId): ?array
  {
    $stmt = $this->pdo->prepare('
      SELECT `id`, `discord_id`, `local_email`, `display_name`, `avatar_url`, `created_at`, `updated_at`
      FROM `users`
      WHERE `id` = ?
      LIMIT 1
    ');
    $stmt->execute([$userId]);

    $r = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$r) {
      return null;
    }

    return [
      'id' => (string)$r['id'],
      'discord_id' => $r['discord_id'] !== null ? (string)$r['discord_id'] : null,
      'local_email' => $r['local_email'] !== null ? (string)$r['local_email'] : null,
      'display_name' => (string)$r['display_name'],
      'avatar_url' => $r['avatar_url'] !== null ? (string)$r['avatar_url'] : null,
      'created_at' => (string)$r['created_at'],
      'updated_at' => (string)$r['updated_at'],
    ];
  }

  /**
   * Fetch user by Discord id.
   *
   * @return array{id:string,discord_id:string,display_name:string,avatar_url:?string}|null
   */
  public function getUserByDiscordId(string $discordId): ?array
  {
    $stmt = $this->pdo->prepare('
      SELECT `id`, `discord_id`, `display_name`, `avatar_url`
      FROM `users`
      WHERE `discord_id` = ?
      LIMIT 1
    ');
    $stmt->execute([$discordId]);

    $r = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$r) {
      return null;
    }

    return [
      'id' => (string)$r['id'],
      'discord_id' => (string)$r['discord_id'],
      'display_name' => (string)$r['display_name'],
      'avatar_url' => $r['avatar_url'] !== null ? (string)$r['avatar_url'] : null,
    ];
  }

  /**
   * Create user row for a Discord identity.
   *
   * @return int new user id
   */
  public function createUser(string $discordId, string $displayName, ?string $avatarUrl): int
  {
    $discordId = trim($discordId);
    $displayName = trim($displayName);

    if ($discordId === '') {
      throw new RuntimeException('discordId cannot be empty.');
    }
    if ($displayName === '') {
      $displayName = 'Goblin';
    }

    $stmt = $this->pdo->prepare('
      INSERT INTO `users` (`discord_id`, `display_name`, `avatar_url`)
      VALUES (?, ?, ?)
    ');
    $stmt->execute([$discordId, $displayName, $avatarUrl]);

    return (int)$this->pdo->lastInsertId();
  }

  public function createLocalUser(string $email, string $passwordHash, string $displayName): int
  {
    $email = $this->normalizeEmail($email);
    $displayName = trim($displayName);

    if ($email === '') {
      throw new RuntimeException('email cannot be empty.');
    }
    if ($passwordHash === '') {
      throw new RuntimeException('passwordHash cannot be empty.');
    }
    if ($displayName === '') {
      $displayName = 'Goblin';
    }

    $stmt = $this->pdo->prepare('
      INSERT INTO `users` (`discord_id`, `local_email`, `password_hash`, `display_name`, `avatar_url`)
      VALUES (NULL, ?, ?, ?, NULL)
    ');
    $stmt->execute([$email, $passwordHash, $displayName]);

    return (int)$this->pdo->lastInsertId();
  }

  /**
   * @return array{id:string,local_email:string,password_hash:string,display_name:string,avatar_url:?string}|null
   */
  public function getUserByLocalEmail(string $email): ?array
  {
    $stmt = $this->pdo->prepare('
      SELECT `id`, `local_email`, `password_hash`, `display_name`, `avatar_url`
      FROM `users`
      WHERE `local_email` = ?
      LIMIT 1
    ');
    $stmt->execute([$this->normalizeEmail($email)]);

    $r = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$r) {
      return null;
    }

    return [
      'id' => (string)$r['id'],
      'local_email' => (string)$r['local_email'],
      'password_hash' => (string)$r['password_hash'],
      'display_name' => (string)$r['display_name'],
      'avatar_url' => $r['avatar_url'] !== null ? (string)$r['avatar_url'] : null,
    ];
  }

  public function createPasswordResetToken(int $userId, string $tokenHash, string $expiresAt): void
  {
    if ($userId <= 0) {
      throw new RuntimeException('userId must be positive.');
    }
    if ($tokenHash === '') {
      throw new RuntimeException('tokenHash cannot be empty.');
    }

    try {
      $this->pdo->beginTransaction();

      $markOld = $this->pdo->prepare('
        UPDATE `password_reset_tokens`
        SET `used_at` = UTC_TIMESTAMP()
        WHERE `user_id` = ?
          AND `used_at` IS NULL
      ');
      $markOld->execute([$userId]);

      $stmt = $this->pdo->prepare('
        INSERT INTO `password_reset_tokens` (`user_id`, `token_hash`, `expires_at`)
        VALUES (?, ?, ?)
      ');
      $stmt->execute([$userId, $tokenHash, $expiresAt]);

      $this->pdo->commit();
    } catch (Throwable $e) {
      if ($this->pdo->inTransaction()) {
        $this->pdo->rollBack();
      }
      throw $e;
    }
  }

  public function consumePasswordResetToken(string $tokenHash, string $passwordHash): ?int
  {
    if ($tokenHash === '' || $passwordHash === '') {
      return null;
    }

    try {
      $this->pdo->beginTransaction();

      $stmt = $this->pdo->prepare('
        SELECT `id`, `user_id`
        FROM `password_reset_tokens`
        WHERE `token_hash` = ?
          AND `used_at` IS NULL
          AND `expires_at` > UTC_TIMESTAMP()
        LIMIT 1
        FOR UPDATE
      ');
      $stmt->execute([$tokenHash]);

      $token = $stmt->fetch(PDO::FETCH_ASSOC);
      if (!$token) {
        $this->pdo->rollBack();
        return null;
      }

      $userId = (int)$token['user_id'];
      $updateUser = $this->pdo->prepare('
        UPDATE `users`
        SET `password_hash` = ?
        WHERE `id` = ?
          AND `local_email` IS NOT NULL
      ');
      $updateUser->execute([$passwordHash, $userId]);

      if ($updateUser->rowCount() !== 1) {
        $this->pdo->rollBack();
        return null;
      }

      $markUsed = $this->pdo->prepare('
        UPDATE `password_reset_tokens`
        SET `used_at` = UTC_TIMESTAMP()
        WHERE `id` = ?
      ');
      $markUsed->execute([(int)$token['id']]);

      $this->pdo->commit();
      return $userId;
    } catch (Throwable $e) {
      if ($this->pdo->inTransaction()) {
        $this->pdo->rollBack();
      }
      throw $e;
    }
  }

  /**
   * Update display_name and avatar_url for an existing user.
   */
  public function updateUserProfile(int $userId, string $displayName, ?string $avatarUrl): void
  {
    $displayName = trim($displayName);
    if ($displayName === '') {
      $displayName = 'Goblin';
    }

    $stmt = $this->pdo->prepare('
      UPDATE `users`
      SET `display_name` = ?, `avatar_url` = ?
      WHERE `id` = ?
    ');
    
    $stmt->execute([$displayName, $avatarUrl, $userId]);
  }

  /**
   * Upsert a user by Discord id. Returns the internal user id.
   *
   * - If the Discord id is new: inserts, returns new id
   * - If it exists: updates profile fields, returns existing id
   *
   * NOTE: This implementation avoids relying on MySQL "ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)"
   * trick, keeping behavior explicit and portable across MySQL variants.
   */
  public function upsertUserByDiscordId(string $discordId, string $displayName, ?string $avatarUrl): int
  {
    try {
      $this->pdo->beginTransaction();

      $existing = $this->getUserByDiscordIdForUpdate($discordId);

      if ($existing) {
        $userId = (int)$existing['id'];

        $this->updateUserProfile($userId, $displayName, $avatarUrl);

        $this->pdo->commit();
        return $userId;
      }

      $userId = $this->createUser($discordId, $displayName, $avatarUrl);
      $this->pdo->commit();
      return $userId;
    } catch (Throwable $e) {
      if ($this->pdo->inTransaction()) {
        $this->pdo->rollBack();
      }
      throw $e;
    }
  }

  /**
   * Returns the minimal session identity data used by ApiController::session().
   *
   * @return array{id:string,display_name:string,avatar_url:?string}|null
   */
  public function getSessionIdentity(int $userId): ?array
  {
    $stmt = $this->pdo->prepare('
      SELECT `id`, `display_name`, `avatar_url`
      FROM `users`
      WHERE `id` = ?
      LIMIT 1
    ');
    $stmt->execute([$userId]);

    $r = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$r) {
      return null;
    }

    return [
      'id' => (string)$r['id'],
      'display_name' => (string)$r['display_name'],
      'avatar_url' => $r['avatar_url'] !== null ? (string)$r['avatar_url'] : null,
    ];
  }

  /**
   * List users (admin/debug use).
   *
   * @return array<int, array{id:string,discord_id:string,display_name:string,avatar_url:?string,created_at:string}>
   */
  public function listUsers(int $limit = 50): array
  {
    $limit = max(1, min(200, $limit));

    // LIMIT cannot be bound in some PDO/MySQL configurations unless emulation is enabled; interpolate safely.
    $stmt = $this->pdo->query("
      SELECT `id`, `discord_id`, `display_name`, `avatar_url`, `created_at`
      FROM `users`
      ORDER BY `id` DESC
      LIMIT {$limit}
    ");

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return array_map(static fn(array $r): array => [
      'id' => (string)$r['id'],
      'discord_id' => (string)$r['discord_id'],
      'display_name' => (string)$r['display_name'],
      'avatar_url' => $r['avatar_url'] !== null ? (string)$r['avatar_url'] : null,
      'created_at' => (string)$r['created_at'],
    ], $rows);
  }

  // -----------------------------
  // Internals
  // -----------------------------

  /**
   * @return array{id:string,discord_id:string,display_name:string,avatar_url:?string}|null
   */
  private function getUserByDiscordIdForUpdate(string $discordId): ?array
  {
    $stmt = $this->pdo->prepare('
      SELECT `id`, `discord_id`, `display_name`, `avatar_url`
      FROM `users`
      WHERE `discord_id` = ?
      LIMIT 1
      FOR UPDATE
    ');
    $stmt->execute([$discordId]);

    $r = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$r) {
      return null;
    }

    return [
      'id' => (string)$r['id'],
      'discord_id' => (string)$r['discord_id'],
      'display_name' => (string)$r['display_name'],
      'avatar_url' => $r['avatar_url'] !== null ? (string)$r['avatar_url'] : null,
    ];
  }

  private function normalizeEmail(string $email): string
  {
    return strtolower(trim($email));
  }
}
