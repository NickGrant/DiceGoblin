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
}
