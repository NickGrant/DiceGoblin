<?php
declare(strict_types=1);

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
   * @return array{id:string,discord_id:string,display_name:string,avatar_url:?string,created_at:string,updated_at:string}|null
   */
  public function getUserById(int $userId): ?array
  {
    $stmt = $this->pdo->prepare('
      SELECT `id`, `discord_id`, `display_name`, `avatar_url`, `created_at`, `updated_at`
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
      'discord_id' => (string)$r['discord_id'],
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

    if ($stmt->rowCount() === 0) {
      throw new RuntimeException('User not found.');
    }
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
}
