<?php
declare(strict_types=1);

namespace DiceGoblins\Repositories;

use PDO;
use RuntimeException;

final class PlayerStateRepository
{
  public function __construct(private readonly PDO $pdo) {}

  /** @return array{user_id:string,teeth:int,raw_chaos:int,energy_current:int,energy_last_regen_at:string,player_revision:int,created_at:string,updated_at:string}|null */
  public function getPlayerState(int $userId): ?array
  {
    $stmt = $this->pdo->prepare('SELECT `user_id`, `teeth`, `raw_chaos`, `energy_current`, `energy_last_regen_at`, `player_revision`, `created_at`, `updated_at` FROM `user_state` WHERE `user_id` = ? LIMIT 1');
    $stmt->execute([$userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) return null;
    return [
      'user_id' => (string)$row['user_id'], 'teeth' => (int)$row['teeth'],
      'raw_chaos' => (int)$row['raw_chaos'], 'energy_current' => (int)$row['energy_current'],
      'energy_last_regen_at' => (string)$row['energy_last_regen_at'],
      'player_revision' => (int)$row['player_revision'], 'created_at' => (string)$row['created_at'],
      'updated_at' => (string)$row['updated_at'],
    ];
  }

  /** Called only by an authoritative account-creation mutation. */
  public function createInitialState(int $userId): void
  {
    if ($userId <= 0) throw new RuntimeException('userId must be positive.');
    $this->pdo->prepare('INSERT INTO `user_state` (`user_id`) VALUES (?)')->execute([$userId]);
  }

  /** @return array{teeth:int,raw_chaos:int,energy_current:int,energy_last_regen_at:string,player_revision:int}|null */
  public function getPlayerStateForUpdate(int $userId): ?array
  {
    $stmt = $this->pdo->prepare('SELECT `teeth`, `raw_chaos`, `energy_current`, `energy_last_regen_at`, `player_revision` FROM `user_state` WHERE `user_id` = ? LIMIT 1 FOR UPDATE');
    $stmt->execute([$userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) return null;
    return [
      'teeth' => (int)$row['teeth'], 'raw_chaos' => (int)$row['raw_chaos'],
      'energy_current' => (int)$row['energy_current'],
      'energy_last_regen_at' => (string)$row['energy_last_regen_at'],
      'player_revision' => (int)$row['player_revision'],
    ];
  }
}
