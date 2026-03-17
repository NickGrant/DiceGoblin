<?php
declare(strict_types=1);

namespace DiceGoblins\Tests\Support;

use PDO;

abstract class BattleFlowIntegrationCase extends IntegrationTestCase
{
  protected function insertRunNode(int $runId, string $nodeType, string $status): int
  {
    $encounterTemplateId = $this->pickEncounterTemplateIdForNodeType($nodeType);

    $stmt = $this->pdo?->prepare('
      INSERT INTO `run_nodes` (`run_id`, `node_index`, `node_type`, `status`, `encounter_template_id`, `meta_json`)
      VALUES (?, ?, ?, ?, ?, NULL)
    ');
    $stmt?->execute([$runId, random_int(1, 9999), $nodeType, $status, $encounterTemplateId]);
    return (int)$this->pdo?->lastInsertId();
  }

  protected function insertRunEdge(int $runId, int $fromNodeId, int $toNodeId): void
  {
    $stmt = $this->pdo?->prepare('
      INSERT INTO `run_edges` (`run_id`, `from_node_id`, `to_node_id`)
      VALUES (?, ?, ?)
    ');
    $stmt?->execute([$runId, $fromNodeId, $toNodeId]);
  }

  /** @return array{0:int,1:int} */
  protected function pickUnitTypeForProgressTest(): array
  {
    $stmt = $this->pdo?->query('SELECT `id`, `max_level` FROM `unit_types` ORDER BY `id` ASC LIMIT 1');
    $row = $stmt?->fetch(PDO::FETCH_ASSOC);
    $this->assertIsArray($row, 'Expected seeded unit_types rows in test database.');
    return [(int)$row['id'], max(1, (int)$row['max_level'])];
  }

  protected function insertUnit(int $userId, int $unitTypeId, int $level, int $xp): int
  {
    $stmt = $this->pdo?->prepare('
      INSERT INTO `unit_instances` (`user_id`, `unit_type_id`, `tier`, `level`, `xp`, `locked`)
      VALUES (?, ?, 1, ?, ?, 0)
    ');
    $stmt?->execute([$userId, $unitTypeId, $level, $xp]);
    return (int)$this->pdo?->lastInsertId();
  }

  protected function pickAnyDiceDefinitionId(): int
  {
    $row = $this->rows('SELECT `id` FROM `dice_definitions` ORDER BY `id` ASC LIMIT 1', []);
    $this->assertCount(1, $row);
    return (int)$row[0]['id'];
  }

  protected function insertDiceInstance(int $userId, int $diceDefinitionId): int
  {
    $stmt = $this->pdo?->prepare('
      INSERT INTO `dice_instances` (`user_id`, `dice_definition_id`, `display_name`)
      VALUES (?, ?, NULL)
    ');
    $stmt?->execute([$userId, $diceDefinitionId]);
    return (int)$this->pdo?->lastInsertId();
  }

  protected function insertTeamUnit(int $teamId, int $unitId): void
  {
    $stmt = $this->pdo?->prepare('INSERT INTO `team_units` (`team_id`, `unit_instance_id`) VALUES (?, ?)');
    $stmt?->execute([$teamId, $unitId]);
  }

  protected function insertRunUnitState(int $runId, int $unitId, int $hp, bool $isDefeated): void
  {
    $stmt = $this->pdo?->prepare('
      INSERT INTO `run_unit_state` (`run_id`, `unit_instance_id`, `current_hp`, `is_defeated`, `cooldowns_json`, `status_effects_json`)
      VALUES (?, ?, ?, ?, ?, ?)
    ');
    $stmt?->execute([$runId, $unitId, $hp, $isDefeated ? 1 : 0, '{}', '[]']);
  }

  protected function insertBattle(
    int $userId,
    int $runId,
    int $nodeId,
    int $teamId,
    string $status,
    string $outcome,
    int $seed,
    int $ticks,
    int $rounds
  ): int {
    $stmt = $this->pdo?->prepare('
      INSERT INTO `battles` (`user_id`, `run_id`, `node_id`, `team_id`, `rules_version`, `seed`, `status`, `outcome`, `ticks`, `rounds`)
      VALUES (?, ?, ?, ?, \'combat_v1\', ?, ?, ?, ?, ?)
    ');
    $stmt?->execute([$userId, $runId, $nodeId, $teamId, $seed, $status, $outcome, $ticks, $rounds]);
    return (int)$this->pdo?->lastInsertId();
  }

  /**
   * @param array<string,mixed> $rewards
   */
  protected function insertBattleRewards(int $battleId, int $xpTotal, int $currencySoft, array $rewards): void
  {
    $stmt = $this->pdo?->prepare('
      INSERT INTO `battle_rewards` (`battle_id`, `xp_total`, `currency_soft`, `rewards_json`)
      VALUES (?, ?, ?, ?)
    ');
    $stmt?->execute([$battleId, $xpTotal, $currencySoft, json_encode($rewards, JSON_UNESCAPED_SLASHES)]);
  }

  /**
   * @param array<int,int|string> $params
   * @return array<int,array<string,mixed>>
   */
  protected function rows(string $sql, array $params): array
  {
    $stmt = $this->pdo?->prepare($sql);
    $stmt?->execute($params);
    $rows = $stmt?->fetchAll(PDO::FETCH_ASSOC);
    return is_array($rows) ? $rows : [];
  }

  /** @return array{0:int,1:int} */
  protected function battleRewardTuple(int $battleId): array
  {
    $row = $this->rows(
      'SELECT `xp_total`, `currency_soft` FROM `battle_rewards` WHERE `battle_id` = ? LIMIT 1',
      [$battleId]
    );
    $this->assertCount(1, $row);
    return [(int)$row[0]['xp_total'], (int)$row[0]['currency_soft']];
  }

  private function pickEncounterTemplateIdForNodeType(string $nodeType): ?int
  {
    $slugPattern = match ($nodeType) {
      'combat' => '%_combat_%',
      'boss' => '%_boss_%',
      'loot' => '%_loot_%',
      'rest' => '%_rest_%',
      default => null,
    };

    if ($slugPattern === null) {
      return null;
    }

    $stmt = $this->pdo?->prepare('SELECT `id` FROM `encounter_templates` WHERE `slug` LIKE ? ORDER BY `id` ASC LIMIT 1');
    $stmt?->execute([$slugPattern]);
    $value = $stmt?->fetchColumn();

    if ($value === false || $value === null || $value === '') {
      return null;
    }

    return (int)$value;
  }
}