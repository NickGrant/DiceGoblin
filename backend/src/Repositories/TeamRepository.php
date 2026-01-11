<?php
declare(strict_types=1);

namespace DiceGoblins\Repositories;

use PDO;
use RuntimeException;
use Throwable;

final class TeamRepository
{
  public function __construct(
    private readonly PDO $pdo,
  ) {}

  /**
   * @return array<int, array{id:string,name:string,is_active:bool}>
   */
  public function listTeamsForUser(int $userId): array
  {
    $stmt = $this->pdo->prepare('
      SELECT `id`, `name`, `is_active`
      FROM `teams`
      WHERE `user_id` = ?
      ORDER BY `id` ASC
    ');
    $stmt->execute([$userId]);

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return array_map(static fn(array $r): array => [
      'id' => (string)$r['id'],
      'name' => (string)$r['name'],
      'is_active' => ((int)$r['is_active']) === 1,
    ], $rows);
  }

  /**
   * Returns teams with membership + formation, ready for GET /profile.
   *
   * @return array<int, array{
   *   id:string,
   *   name:string,
   *   is_active:bool,
   *   unit_ids:array<int,string>,
   *   formation:array<int,array{cell:string,unit_instance_id:?string}>
   * }>
   */
  public function getTeamsWithMembershipAndFormationForUser(int $userId): array
  {
    $teams = $this->listTeamsForUser($userId);
    if (count($teams) === 0) {
      return [];
    }

    $teamIds = array_map(static fn(array $t): int => (int)$t['id'], $teams);
    $placeholders = implode(',', array_fill(0, count($teamIds), '?'));

    // Membership
    $stmt = $this->pdo->prepare("
      SELECT `team_id`, `unit_instance_id`
      FROM `team_units`
      WHERE `team_id` IN ($placeholders)
      ORDER BY `team_id` ASC, `unit_instance_id` ASC
    ");
    $stmt->execute($teamIds);

    $teamUnitsByTeam = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
      $tId = (string)$r['team_id'];
      $teamUnitsByTeam[$tId] ??= [];
      $teamUnitsByTeam[$tId][] = (string)$r['unit_instance_id'];
    }

    // Formation
    $stmt = $this->pdo->prepare("
      SELECT `team_id`, `cell`, `unit_instance_id`
      FROM `team_formation`
      WHERE `team_id` IN ($placeholders)
      ORDER BY `team_id` ASC, `cell` ASC
    ");
    $stmt->execute($teamIds);

    $formationByTeam = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
      $tId = (string)$r['team_id'];
      $formationByTeam[$tId] ??= [];
      $formationByTeam[$tId][] = [
        'cell' => (string)$r['cell'],
        'unit_instance_id' => $r['unit_instance_id'] !== null ? (string)$r['unit_instance_id'] : null,
      ];
    }

    $out = [];
    foreach ($teams as $t) {
      $tId = (string)$t['id'];
      $out[] = [
        'id' => $tId,
        'name' => (string)$t['name'],
        'is_active' => (bool)$t['is_active'],
        'unit_ids' => $teamUnitsByTeam[$tId] ?? [],
        'formation' => $formationByTeam[$tId] ?? [],
      ];
    }

    return $out;
  }

  /**
   * @return array{id:string,name:string,is_active:bool}|null
   */
  public function getTeamForUser(int $userId, int $teamId): ?array
  {
    $stmt = $this->pdo->prepare('
      SELECT `id`, `name`, `is_active`
      FROM `teams`
      WHERE `id` = ? AND `user_id` = ?
      LIMIT 1
    ');
    $stmt->execute([$teamId, $userId]);

    $r = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$r) {
      return null;
    }

    return [
      'id' => (string)$r['id'],
      'name' => (string)$r['name'],
      'is_active' => ((int)$r['is_active']) === 1,
    ];
  }

  /**
   * @return array{id:string,name:string,is_active:bool}|null
   */
  public function getActiveTeamForUser(int $userId): ?array
  {
    $stmt = $this->pdo->prepare('
      SELECT `id`, `name`, `is_active`
      FROM `teams`
      WHERE `user_id` = ? AND `is_active` = 1
      ORDER BY `id` ASC
      LIMIT 1
    ');
    $stmt->execute([$userId]);

    $r = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$r) {
      return null;
    }

    return [
      'id' => (string)$r['id'],
      'name' => (string)$r['name'],
      'is_active' => ((int)$r['is_active']) === 1,
    ];
  }

  /**
   * Create a team for a user.
   * If $makeActive is true, ensures this is the only active team for the user.
   *
   * @return int new team id
   */
  public function createTeam(int $userId, string $name, bool $makeActive = false): int
  {
    $name = trim($name);
    if ($name === '') {
      throw new RuntimeException('Team name cannot be empty.');
    }
    if (mb_strlen($name) > 64) {
      throw new RuntimeException('Team name is too long.');
    }

    try {
      $this->pdo->beginTransaction();

      if ($makeActive) {
        $stmt = $this->pdo->prepare('UPDATE `teams` SET `is_active` = 0 WHERE `user_id` = ?');
        $stmt->execute([$userId]);
      }

      $stmt = $this->pdo->prepare('
        INSERT INTO `teams` (`user_id`, `name`, `is_active`)
        VALUES (?, ?, ?)
      ');
      $stmt->execute([$userId, $name, $makeActive ? 1 : 0]);

      $teamId = (int)$this->pdo->lastInsertId();

      $this->pdo->commit();
      return $teamId;
    } catch (Throwable $e) {
      if ($this->pdo->inTransaction()) {
        $this->pdo->rollBack();
      }
      throw $e;
    }
  }

  public function renameTeam(int $userId, int $teamId, string $name): void
  {
    $name = trim($name);
    if ($name === '') {
      throw new RuntimeException('Team name cannot be empty.');
    }
    if (mb_strlen($name) > 64) {
      throw new RuntimeException('Team name is too long.');
    }

    $stmt = $this->pdo->prepare('
      UPDATE `teams`
      SET `name` = ?
      WHERE `id` = ? AND `user_id` = ?
    ');
    $stmt->execute([$name, $teamId, $userId]);

    if ($stmt->rowCount() === 0) {
      throw new RuntimeException('Team not found or not owned by user.');
    }
  }

  /**
   * Ensure only one active team for the user and set this team active.
   */
  public function setActiveTeam(int $userId, int $teamId): void
  {
    try {
      $this->pdo->beginTransaction();

      // Lock the team row to ensure ownership.
      $stmt = $this->pdo->prepare('
        SELECT 1
        FROM `teams`
        WHERE `id` = ? AND `user_id` = ?
        LIMIT 1
        FOR UPDATE
      ');
      $stmt->execute([$teamId, $userId]);

      if (!(bool)$stmt->fetchColumn()) {
        $this->pdo->rollBack();
        throw new RuntimeException('Team not found or not owned by user.');
      }

      $stmt = $this->pdo->prepare('UPDATE `teams` SET `is_active` = 0 WHERE `user_id` = ?');
      $stmt->execute([$userId]);

      $stmt = $this->pdo->prepare('
        UPDATE `teams`
        SET `is_active` = 1
        WHERE `id` = ? AND `user_id` = ?
      ');
      $stmt->execute([$teamId, $userId]);

      $this->pdo->commit();
    } catch (Throwable $e) {
      if ($this->pdo->inTransaction()) {
        $this->pdo->rollBack();
      }
      throw $e;
    }
  }

  /**
   * Idempotently add a unit to a team (membership).
   */
  public function addUnitToTeam(int $userId, int $teamId, int $unitInstanceId): void
  {
    try {
      $this->pdo->beginTransaction();

      $this->assertTeamOwnedByUserForUpdate($userId, $teamId);
      $this->assertUnitOwnedByUserForUpdate($userId, $unitInstanceId);

      $stmt = $this->pdo->prepare('
        INSERT INTO `team_units` (`team_id`, `unit_instance_id`)
        VALUES (?, ?)
        ON DUPLICATE KEY UPDATE `team_id` = `team_id`
      ');
      $stmt->execute([$teamId, $unitInstanceId]);

      $this->pdo->commit();
    } catch (Throwable $e) {
      if ($this->pdo->inTransaction()) {
        $this->pdo->rollBack();
      }
      throw $e;
    }
  }

  /**
   * Remove a unit from a team (membership) and clear it from formation cells (within this team).
   * Idempotent: if not present, succeeds.
   */
  public function removeUnitFromTeam(int $userId, int $teamId, int $unitInstanceId): void
  {
    try {
      $this->pdo->beginTransaction();

      $this->assertTeamOwnedByUserForUpdate($userId, $teamId);

      $stmt = $this->pdo->prepare('
        DELETE FROM `team_units`
        WHERE `team_id` = ? AND `unit_instance_id` = ?
      ');
      $stmt->execute([$teamId, $unitInstanceId]);

      // Clear from formation (team-local)
      $stmt = $this->pdo->prepare('
        UPDATE `team_formation`
        SET `unit_instance_id` = NULL
        WHERE `team_id` = ? AND `unit_instance_id` = ?
      ');
      $stmt->execute([$teamId, $unitInstanceId]);

      $this->pdo->commit();
    } catch (Throwable $e) {
      if ($this->pdo->inTransaction()) {
        $this->pdo->rollBack();
      }
      throw $e;
    }
  }

  /**
   * Replace membership for a team with the provided unit ids.
   * This does not automatically update formation; call setFormationCell() as needed.
   *
   * @param array<int,int> $unitInstanceIds
   */
  public function setTeamUnits(int $userId, int $teamId, array $unitInstanceIds): void
  {
    $unitInstanceIds = array_values(array_unique(array_map(static fn($v): int => (int)$v, $unitInstanceIds)));

    try {
      $this->pdo->beginTransaction();

      $this->assertTeamOwnedByUserForUpdate($userId, $teamId);

      // Validate ownership for all units (lock rows)
      foreach ($unitInstanceIds as $uid) {
        $this->assertUnitOwnedByUserForUpdate($userId, $uid);
      }

      // Clear membership
      $stmt = $this->pdo->prepare('DELETE FROM `team_units` WHERE `team_id` = ?');
      $stmt->execute([$teamId]);

      if (count($unitInstanceIds) > 0) {
        $valuesSql = [];
        $params = [];
        foreach ($unitInstanceIds as $uid) {
          $valuesSql[] = '(?, ?)';
          $params[] = $teamId;
          $params[] = $uid;
        }

        $sql = 'INSERT INTO `team_units` (`team_id`, `unit_instance_id`) VALUES ' . implode(',', $valuesSql);
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
      }

      $this->pdo->commit();
    } catch (Throwable $e) {
      if ($this->pdo->inTransaction()) {
        $this->pdo->rollBack();
      }
      throw $e;
    }
  }

  /**
   * Set a formation cell to a unit (or null to clear).
   *
   * Behavior:
   * - Ensures team is owned by user.
   * - If unit is non-null, ensures unit is owned by user.
   * - Ensures within the same team, the unit appears in at most one cell by clearing any existing placement.
   * - Upserts the (team_id, cell) row.
   */
  public function setFormationCell(int $userId, int $teamId, string $cell, ?int $unitInstanceId): void
  {
    $cell = strtoupper(trim($cell));
    if ($cell === '' || mb_strlen($cell) > 2) {
      throw new RuntimeException('Invalid formation cell.');
    }

    try {
      $this->pdo->beginTransaction();

      $this->assertTeamOwnedByUserForUpdate($userId, $teamId);

      if ($unitInstanceId !== null) {
        $this->assertUnitOwnedByUserForUpdate($userId, $unitInstanceId);

        // Ensure unit is not placed in multiple cells within the same team.
        $stmt = $this->pdo->prepare('
          UPDATE `team_formation`
          SET `unit_instance_id` = NULL
          WHERE `team_id` = ? AND `unit_instance_id` = ?
        ');
        $stmt->execute([$teamId, $unitInstanceId]);
      }

      // Upsert cell assignment
      $stmt = $this->pdo->prepare('
        INSERT INTO `team_formation` (`team_id`, `cell`, `unit_instance_id`)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE
          `unit_instance_id` = VALUES(`unit_instance_id`)
      ');
      $stmt->execute([$teamId, $cell, $unitInstanceId]);

      $this->pdo->commit();
    } catch (Throwable $e) {
      if ($this->pdo->inTransaction()) {
        $this->pdo->rollBack();
      }
      throw $e;
    }
  }

  /**
   * Clear all formation cells for a team.
   */
  public function clearFormation(int $userId, int $teamId): void
  {
    try {
      $this->pdo->beginTransaction();

      $this->assertTeamOwnedByUserForUpdate($userId, $teamId);

      $stmt = $this->pdo->prepare('DELETE FROM `team_formation` WHERE `team_id` = ?');
      $stmt->execute([$teamId]);

      $this->pdo->commit();
    } catch (Throwable $e) {
      if ($this->pdo->inTransaction()) {
        $this->pdo->rollBack();
      }
      throw $e;
    }
  }

  // -----------------------------
  // Internals
  // -----------------------------

  private function assertTeamOwnedByUserForUpdate(int $userId, int $teamId): void
  {
    $stmt = $this->pdo->prepare('
      SELECT 1
      FROM `teams`
      WHERE `id` = ? AND `user_id` = ?
      LIMIT 1
      FOR UPDATE
    ');
    $stmt->execute([$teamId, $userId]);

    if (!(bool)$stmt->fetchColumn()) {
      throw new RuntimeException('Team not found or not owned by user.');
    }
  }

  private function assertUnitOwnedByUserForUpdate(int $userId, int $unitInstanceId): void
  {
    $stmt = $this->pdo->prepare('
      SELECT 1
      FROM `unit_instances`
      WHERE `id` = ? AND `user_id` = ?
      LIMIT 1
      FOR UPDATE
    ');
    $stmt->execute([$unitInstanceId, $userId]);

    if (!(bool)$stmt->fetchColumn()) {
      throw new RuntimeException('Unit not found or not owned by user.');
    }
  }
}
