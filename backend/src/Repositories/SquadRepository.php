<?php
declare(strict_types=1);

namespace DiceGoblins\Repositories;

use PDO;

final class SquadRepository
{
  public function __construct(private readonly PDO $pdo) {}

  /** @return array<int,array<string,mixed>> */
  public function listForUser(int $userId): array
  {
    $stmt = $this->pdo->prepare('
      SELECT s.`id`, s.`name`, us.`active_squad_id`
      FROM `squads` s
      JOIN `user_state` us ON us.`user_id` = s.`user_id`
      WHERE s.`user_id` = ?
      ORDER BY s.`id` ASC
    ');
    $stmt->execute([$userId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
  }

  /** @return array<int,array<string,mixed>> */
  public function listFormationRowsForUser(int $userId): array
  {
    $stmt = $this->pdo->prepare('
      SELECT su.`squad_id`, su.`unit_id`, su.`position`, ui.`user_id` AS `unit_user_id`, ui.`lifecycle_status`
      FROM `squad_units` su
      JOIN `squads` s ON s.`id` = su.`squad_id`
      JOIN `unit_instances` ui ON ui.`id` = su.`unit_id`
      WHERE s.`user_id` = ?
      ORDER BY su.`squad_id` ASC, su.`position` ASC
    ');
    $stmt->execute([$userId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
  }

  public function activeSquadIdForUser(int $userId): ?int
  {
    $stmt = $this->pdo->prepare('SELECT `active_squad_id` FROM `user_state` WHERE `user_id` = ? LIMIT 1');
    $stmt->execute([$userId]);
    $value = $stmt->fetchColumn();
    return $value === false || $value === null ? null : (int)$value;
  }

  public function squadOwnerId(int $squadId): ?int
  {
    $stmt = $this->pdo->prepare('SELECT `user_id` FROM `squads` WHERE `id` = ? LIMIT 1');
    $stmt->execute([$squadId]);
    $value = $stmt->fetchColumn();
    return $value === false ? null : (int)$value;
  }

  /** @return array<int,int> */
  public function listIdsForUserForUpdate(int $userId): array
  {
    $stmt = $this->pdo->prepare('SELECT `id` FROM `squads` WHERE `user_id` = ? ORDER BY `id` ASC FOR UPDATE');
    $stmt->execute([$userId]);
    return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
  }

  /** @return array{id:int,name:string}|null */
  public function getForUser(int $userId, int $squadId, bool $forUpdate = false): ?array
  {
    $stmt = $this->pdo->prepare(
      'SELECT `id`, `name` FROM `squads` WHERE `id` = ? AND `user_id` = ? LIMIT 1' . ($forUpdate ? ' FOR UPDATE' : '')
    );
    $stmt->execute([$squadId, $userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return is_array($row) ? ['id' => (int)$row['id'], 'name' => (string)$row['name']] : null;
  }

  /** @return array<int,array<string,mixed>> */
  public function listFormationRowsForSquad(int $squadId, bool $forUpdate = false): array
  {
    $stmt = $this->pdo->prepare('SELECT su.`unit_id`, su.`position`, ui.`user_id` AS `unit_user_id`,
        ui.`unit_type_id`, ui.`kin_id`, ui.`display_name`, ui.`level`, ui.`xp`, ui.`lifecycle_status`
      FROM `squad_units` su
      JOIN `unit_instances` ui ON ui.`id` = su.`unit_id`
      WHERE su.`squad_id` = ? ORDER BY su.`position` ASC' . ($forUpdate ? ' FOR UPDATE' : ''));
    $stmt->execute([$squadId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
  }

  public function create(int $userId, string $name): int
  {
    $stmt = $this->pdo->prepare('INSERT INTO `squads` (`user_id`, `name`) VALUES (?, ?)');
    $stmt->execute([$userId, $name]);
    return (int)$this->pdo->lastInsertId();
  }

  public function updateName(int $userId, int $squadId, string $name): void
  {
    $this->pdo->prepare('UPDATE `squads` SET `name` = ? WHERE `id` = ? AND `user_id` = ?')
      ->execute([$name, $squadId, $userId]);
  }

  /** @param array<int,?int> $formation */
  public function replaceFormation(int $squadId, array $formation): void
  {
    $this->pdo->prepare('DELETE FROM `squad_units` WHERE `squad_id` = ?')->execute([$squadId]);
    $insert = $this->pdo->prepare('INSERT INTO `squad_units` (`squad_id`, `unit_id`, `position`) VALUES (?, ?, ?)');
    foreach ($formation as $position => $unitId) {
      if ($unitId !== null) $insert->execute([$squadId, $unitId, $position]);
    }
  }

  public function deleteOwned(int $userId, int $squadId): void
  {
    $this->pdo->prepare('DELETE FROM `squads` WHERE `id` = ? AND `user_id` = ?')->execute([$squadId, $userId]);
  }
}
