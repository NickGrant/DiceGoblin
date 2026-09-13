<?php
declare(strict_types=1);

namespace DiceGoblins\Repositories;

use PDO;
use RuntimeException;
use DateTimeImmutable;

final class PlayerStateRepository
{
  public function __construct(private readonly PDO $pdo) {}

  /** @return array{user_id:string,teeth:int,raw_chaos:int,energy_current:int,energy_last_regen_at:string,active_squad_id:?string,player_revision:int,created_at:string,updated_at:string}|null */
  public function getPlayerState(int $userId): ?array
  {
    $stmt = $this->pdo->prepare('SELECT `user_id`, `teeth`, `raw_chaos`, `energy_current`, `energy_last_regen_at`, `active_squad_id`, `player_revision`, `created_at`, `updated_at` FROM `user_state` WHERE `user_id` = ? LIMIT 1');
    $stmt->execute([$userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) return null;
    return [
      'user_id' => (string)$row['user_id'], 'teeth' => (int)$row['teeth'],
      'raw_chaos' => (int)$row['raw_chaos'], 'energy_current' => (int)$row['energy_current'],
      'energy_last_regen_at' => (string)$row['energy_last_regen_at'],
      'active_squad_id' => $row['active_squad_id'] !== null ? (string)$row['active_squad_id'] : null,
      'player_revision' => (int)$row['player_revision'], 'created_at' => (string)$row['created_at'],
      'updated_at' => (string)$row['updated_at'],
    ];
  }

  /** Called only by an authoritative account-creation mutation. */
  public function createInitialState(int $userId, int $initialEnergy): void
  {
    if ($userId <= 0) throw new RuntimeException('userId must be positive.');
    if ($initialEnergy < 0) throw new RuntimeException('initialEnergy cannot be negative.');
    $this->pdo->prepare('INSERT INTO `user_state` (`user_id`, `energy_current`) VALUES (?, ?)')->execute([$userId, $initialEnergy]);
  }

  /** @return array{teeth:int,raw_chaos:int,energy_current:int,energy_last_regen_at:string,active_squad_id:?string,player_revision:int}|null */
  public function getPlayerStateForUpdate(int $userId): ?array
  {
    $stmt = $this->pdo->prepare('SELECT `teeth`, `raw_chaos`, `energy_current`, `energy_last_regen_at`, `active_squad_id`, `player_revision` FROM `user_state` WHERE `user_id` = ? LIMIT 1 FOR UPDATE');
    $stmt->execute([$userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) return null;
    return [
      'teeth' => (int)$row['teeth'], 'raw_chaos' => (int)$row['raw_chaos'],
      'energy_current' => (int)$row['energy_current'],
      'energy_last_regen_at' => (string)$row['energy_last_regen_at'],
      'active_squad_id' => $row['active_squad_id'] !== null ? (string)$row['active_squad_id'] : null,
      'player_revision' => (int)$row['player_revision'],
    ];
  }

  public function incrementRevision(int $userId): int
  {
    $stmt = $this->pdo->prepare('UPDATE `user_state` SET `player_revision` = `player_revision` + 1 WHERE `user_id` = ?');
    $stmt->execute([$userId]);
    if ($stmt->rowCount() !== 1) throw new RuntimeException('Required player state is unavailable.');
    return $this->revisionForUser($userId);
  }

  public function setActiveSquadAndIncrementRevision(int $userId, ?int $squadId): int
  {
    $stmt = $this->pdo->prepare('UPDATE `user_state` SET `active_squad_id` = ?, `player_revision` = `player_revision` + 1 WHERE `user_id` = ?');
    $stmt->execute([$squadId, $userId]);
    if ($stmt->rowCount() !== 1) throw new RuntimeException('Required player state is unavailable.');
    return $this->revisionForUser($userId);
  }

  public function persistEnergyAndIncrementRevision(
    int $userId,
    int $energyCurrent,
    DateTimeImmutable $lastRegenerationAt,
  ): int {
    $stmt = $this->pdo->prepare('UPDATE `user_state`
      SET `energy_current` = ?, `energy_last_regen_at` = ?, `player_revision` = `player_revision` + 1
      WHERE `user_id` = ?');
    $stmt->execute([$energyCurrent, $lastRegenerationAt->format('Y-m-d H:i:s'), $userId]);
    if ($stmt->rowCount() !== 1) throw new RuntimeException('Required player state is unavailable.');
    return $this->revisionForUser($userId);
  }

  private function revisionForUser(int $userId): int
  {
    $stmt = $this->pdo->prepare('SELECT `player_revision` FROM `user_state` WHERE `user_id` = ? LIMIT 1');
    $stmt->execute([$userId]);
    $revision = $stmt->fetchColumn();
    if ($revision === false) throw new RuntimeException('Required player state is unavailable.');
    return (int)$revision;
  }
}
