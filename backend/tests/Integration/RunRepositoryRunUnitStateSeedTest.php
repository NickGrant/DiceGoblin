<?php
declare(strict_types=1);

namespace DiceGoblins\Tests\Integration;

use DiceGoblins\Repositories\RunRepository;
use DiceGoblins\Tests\Support\DatabaseTestCase;

final class RunRepositoryRunUnitStateSeedTest extends DatabaseTestCase
{
  public function testSeedRunUnitStateFromTeamSnapshotsTeamUnitsWithComputedMaxHp(): void
  {
    $userId = $this->insertUser();
    $regionId = $this->insertRegion();
    $teamId = $this->insertTeam($userId);

    [$unitTypeId, $baseMaxHp, $maxHpPerLevel] = $this->pickUnitTypeStats();
    $level = 3;
    $unitId = $this->insertUnit($userId, $unitTypeId, $level);
    $this->insertTeamUnit($teamId, $unitId);

    $runId = $this->insertRun($userId, $regionId);

    $repo = new RunRepository($this->testPdo);
    $repo->seedRunUnitStateFromTeam($runId, $userId, $teamId);

    $stmt = $this->testPdo?->prepare('SELECT `current_hp`, `is_defeated`, `cooldowns_json`, `status_effects_json` FROM `run_unit_state` WHERE `run_id` = ? AND `unit_instance_id` = ?');
    $stmt?->execute([$runId, $unitId]);
    $row = $stmt?->fetch();

    $this->assertIsArray($row);

    $expectedHp = $baseMaxHp + (($level - 1) * $maxHpPerLevel);
    $this->assertSame((string)$expectedHp, (string)($row['current_hp'] ?? '0'));
    $this->assertSame('0', (string)($row['is_defeated'] ?? '1'));
    $this->assertSame('{}', (string)($row['cooldowns_json'] ?? ''));
    $this->assertSame('[]', (string)($row['status_effects_json'] ?? ''));
  }

  private function insertUser(): int
  {
    $token = bin2hex(random_bytes(6));
    $stmt = $this->testPdo?->prepare('INSERT INTO `users` (`discord_id`, `display_name`) VALUES (?, ?)');
    $stmt?->execute(["qa_seed_$token", "QA Seed $token"]);
    return (int)$this->testPdo?->lastInsertId();
  }

  private function insertRegion(): int
  {
    $token = bin2hex(random_bytes(4));
    $stmt = $this->testPdo?->prepare('INSERT INTO `regions` (`slug`, `name`, `theme`, `recommended_level`, `energy_cost`, `is_enabled`) VALUES (?, ?, ?, 1, 5, 1)');
    $stmt?->execute(["qa-seed-region-$token", "QA Seed Region $token", 'qa_theme']);
    return (int)$this->testPdo?->lastInsertId();
  }

  private function insertTeam(int $userId): int
  {
    $stmt = $this->testPdo?->prepare('INSERT INTO `teams` (`user_id`, `name`, `is_active`) VALUES (?, ?, 1)');
    $stmt?->execute([$userId, 'QA Seed Squad']);
    return (int)$this->testPdo?->lastInsertId();
  }

  /** @return array{0:int,1:int,2:int} */
  private function pickUnitTypeStats(): array
  {
    $stmt = $this->testPdo?->query('SELECT `id`, `base_stats_json`, `max_hp_per_level` FROM `unit_types` ORDER BY `id` ASC LIMIT 1');
    $row = $stmt?->fetch();
    $this->assertIsArray($row);

    $baseStats = json_decode((string)$row['base_stats_json'], true);
    $this->assertIsArray($baseStats);

    return [
      (int)$row['id'],
      max(1, (int)($baseStats['max_hp'] ?? 1)),
      max(0, (int)$row['max_hp_per_level']),
    ];
  }

  private function insertUnit(int $userId, int $unitTypeId, int $level): int
  {
    $stmt = $this->testPdo?->prepare('INSERT INTO `unit_instances` (`user_id`, `unit_type_id`, `tier`, `level`, `xp`, `locked`) VALUES (?, ?, 1, ?, 0, 0)');
    $stmt?->execute([$userId, $unitTypeId, $level]);
    return (int)$this->testPdo?->lastInsertId();
  }

  private function insertTeamUnit(int $teamId, int $unitId): void
  {
    $stmt = $this->testPdo?->prepare('INSERT INTO `team_units` (`team_id`, `unit_instance_id`) VALUES (?, ?)');
    $stmt?->execute([$teamId, $unitId]);
  }

  private function insertRun(int $userId, int $regionId): int
  {
    $stmt = $this->testPdo?->prepare('INSERT INTO `region_runs` (`user_id`, `region_id`, `seed`, `status`) VALUES (?, ?, 123456, \'active\')');
    $stmt?->execute([$userId, $regionId]);
    return (int)$this->testPdo?->lastInsertId();
  }
}
