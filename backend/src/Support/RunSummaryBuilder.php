<?php
declare(strict_types=1);

namespace DiceGoblins\Support;

use PDO;

final class RunSummaryBuilder
{
  /** @var array<string,string> */
  private const RARITY_TO_MATERIAL = [
    'common' => 'cardboard',
    'uncommon' => 'wood',
    'rare' => 'bone',
    'epic' => 'metal',
    'legendary' => 'gemstone',
  ];

  public function __construct(
    private readonly PDO $pdo,
  ) {}

  /**
   * @param array<string,mixed> $rewards
   * @return array{new_unit_labels:array<int,string>,new_dice_labels:array<int,string>}
   */
  public function buildBattleRewardLabels(int $userId, array $rewards): array
  {
    return [
      'new_unit_labels' => $this->extractUnitRewardLabels($userId, $rewards),
      'new_dice_labels' => $this->extractDiceRewardLabels($userId, $rewards),
    ];
  }

  /**
   * @param array<int,array<string,mixed>>|null $terminalRunState
   * @return array{
   *   rewards:array<int,string>,
   *   progression:array<int,string>,
   *   survivors:array<int,string>,
   *   defeated:array<int,string>
   * }
   */
  public function buildRunSummary(int $userId, int $runId, ?array $terminalRunState = null): array
  {
    $battleRows = $this->loadClaimedBattleRows($userId, $runId);
    $teethTotal = 0;
    $unitRewardCounts = [];
    $diceRewardCounts = [];
    $xpByUnitId = [];

    foreach ($battleRows as $battle) {
      $teethTotal += max(0, (int)($battle['currency_soft'] ?? 0));
      $rewards = is_array($battle['rewards']) ? $battle['rewards'] : [];

      foreach ($this->extractUnitRewardLabels($userId, $rewards) as $label) {
        $unitRewardCounts[$label] = ($unitRewardCounts[$label] ?? 0) + 1;
      }
      foreach ($this->extractDiceRewardLabels($userId, $rewards) as $label) {
        $diceRewardCounts[$label] = ($diceRewardCounts[$label] ?? 0) + 1;
      }

      $claimSnapshot = $rewards['claim_snapshot'] ?? null;
      if (!is_array($claimSnapshot)) {
        continue;
      }
      $xp = $claimSnapshot['xp'] ?? null;
      if (!is_array($xp)) {
        continue;
      }
      $awardPerUnit = max(0, (int)($xp['award_per_unit'] ?? 0));
      if ($awardPerUnit <= 0) {
        continue;
      }
      $appliedIds = is_array($xp['applied_unit_instance_ids'] ?? null)
        ? $xp['applied_unit_instance_ids']
        : [];
      foreach ($appliedIds as $rawUnitId) {
        $unitId = (string)$rawUnitId;
        if ($unitId === '') {
          continue;
        }
        $xpByUnitId[$unitId] = ($xpByUnitId[$unitId] ?? 0) + $awardPerUnit;
      }
    }

    $rewardLines = [];
    if ($teethTotal > 0) {
      $rewardLines[] = sprintf('Teeth +%d', $teethTotal);
    }
    if (count($unitRewardCounts) > 0) {
      $rewardLines[] = 'New Units: ' . $this->formatCountList($unitRewardCounts);
    }
    if (count($diceRewardCounts) > 0) {
      $rewardLines[] = 'New Dice: ' . $this->formatCountList($diceRewardCounts);
    }

    $progressionLines = [];
    if (count($xpByUnitId) > 0) {
      $unitLabels = $this->loadUnitProgressLabels($userId, array_keys($xpByUnitId));
      uasort($xpByUnitId, static fn(int $a, int $b): int => $b <=> $a);
      foreach ($xpByUnitId as $unitId => $xpGained) {
        $progressionLines[] = sprintf(
          '%s +%d XP',
          $unitLabels[$unitId] ?? ('Unit ' . $unitId),
          $xpGained
        );
      }
    }

    $runState = is_array($terminalRunState) && count($terminalRunState) > 0
      ? $terminalRunState
      : $this->loadRunUnitState($userId, $runId);
    [$survivors, $defeated] = $this->formatRunStateLists($userId, $runState);

    return [
      'rewards' => $rewardLines,
      'progression' => $progressionLines,
      'survivors' => $survivors,
      'defeated' => $defeated,
    ];
  }

  /**
   * @return array<int,array{currency_soft:int,rewards:array<string,mixed>}>
   */
  private function loadClaimedBattleRows(int $userId, int $runId): array
  {
    $stmt = $this->pdo->prepare('
      SELECT br.`currency_soft`, br.`rewards_json`
      FROM `battles` b
      JOIN `battle_rewards` br ON br.`battle_id` = b.`id`
      WHERE b.`user_id` = ? AND b.`run_id` = ? AND b.`status` = \'claimed\'
      ORDER BY b.`id` ASC
    ');
    $stmt->execute([$userId, $runId]);

    $rows = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
      $decoded = json_decode((string)($row['rewards_json'] ?? ''), true);
      $rows[] = [
        'currency_soft' => max(0, (int)($row['currency_soft'] ?? 0)),
        'rewards' => is_array($decoded) ? $decoded : [],
      ];
    }

    return $rows;
  }

  /**
   * @param array<string,mixed> $rewards
   * @return array<int,string>
   */
  private function extractUnitRewardLabels(int $userId, array $rewards): array
  {
    $unitGrants = is_array($rewards['unit_grants'] ?? null) ? $rewards['unit_grants'] : [];
    if (count($unitGrants) > 0) {
      $slugs = [];
      foreach ($unitGrants as $grant) {
        if (!is_array($grant)) {
          continue;
        }
        $slug = trim((string)($grant['unit_type_slug'] ?? ''));
        if ($slug !== '') {
          $slugs[] = $slug;
        }
      }

      $namesBySlug = $this->loadUnitTypeNamesBySlug($slugs);
      $labels = [];
      foreach ($unitGrants as $grant) {
        if (!is_array($grant)) {
          continue;
        }
        $slug = trim((string)($grant['unit_type_slug'] ?? ''));
        if ($slug === '') {
          continue;
        }
        $labels[] = $namesBySlug[$slug] ?? $this->prettifyId($slug);
      }
      if (count($labels) > 0) {
        return $labels;
      }
    }

    $instanceIds = is_array($rewards['new_unit_instance_ids'] ?? null)
      ? array_values(array_filter(array_map('strval', $rewards['new_unit_instance_ids']), static fn(string $id): bool => $id !== ''))
      : [];
    if (count($instanceIds) === 0) {
      return [];
    }

    return array_values($this->loadUnitTypeLabelsForInstances($userId, $instanceIds));
  }

  /**
   * @param array<string,mixed> $rewards
   * @return array<int,string>
   */
  private function extractDiceRewardLabels(int $userId, array $rewards): array
  {
    $diceGrants = is_array($rewards['dice_grants'] ?? null) ? $rewards['dice_grants'] : [];
    if (count($diceGrants) > 0) {
      $labels = [];
      foreach ($diceGrants as $grant) {
        if (!is_array($grant)) {
          continue;
        }
        $labels[] = $this->formatDiceTypeLabel(
          (string)($grant['rarity'] ?? 'common'),
          max(2, (int)($grant['sides'] ?? 6))
        );
      }
      if (count($labels) > 0) {
        return $labels;
      }
    }

    $instanceIds = is_array($rewards['new_dice_instance_ids'] ?? null)
      ? array_values(array_filter(array_map('strval', $rewards['new_dice_instance_ids']), static fn(string $id): bool => $id !== ''))
      : [];
    if (count($instanceIds) === 0) {
      return [];
    }

    return array_values($this->loadDiceTypeLabelsForInstances($userId, $instanceIds));
  }

  /**
   * @param array<int,string> $slugs
   * @return array<string,string>
   */
  private function loadUnitTypeNamesBySlug(array $slugs): array
  {
    $slugs = array_values(array_unique(array_filter(array_map('strval', $slugs), static fn(string $slug): bool => $slug !== '')));
    if (count($slugs) === 0) {
      return [];
    }

    $placeholders = implode(',', array_fill(0, count($slugs), '?'));
    $stmt = $this->pdo->prepare("
      SELECT `slug`, `name`
      FROM `unit_types`
      WHERE `slug` IN ($placeholders)
    ");
    $stmt->execute($slugs);

    $map = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
      $map[(string)$row['slug']] = (string)$row['name'];
    }

    return $map;
  }

  /**
   * @param array<int,string> $instanceIds
   * @return array<string,string>
   */
  private function loadUnitTypeLabelsForInstances(int $userId, array $instanceIds): array
  {
    $instanceIds = array_values(array_unique(array_filter(array_map('strval', $instanceIds), static fn(string $id): bool => $id !== '')));
    if (count($instanceIds) === 0) {
      return [];
    }

    $placeholders = implode(',', array_fill(0, count($instanceIds), '?'));
    $params = array_merge([$userId], $instanceIds);
    $stmt = $this->pdo->prepare("
      SELECT ui.`id`, ut.`name`
      FROM `unit_instances` ui
      JOIN `unit_types` ut ON ut.`id` = ui.`unit_type_id`
      WHERE ui.`user_id` = ? AND ui.`id` IN ($placeholders)
    ");
    $stmt->execute($params);

    $map = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
      $map[(string)$row['id']] = (string)$row['name'];
    }

    return $map;
  }

  /**
   * @param array<int,string> $instanceIds
   * @return array<string,string>
   */
  private function loadDiceTypeLabelsForInstances(int $userId, array $instanceIds): array
  {
    $instanceIds = array_values(array_unique(array_filter(array_map('strval', $instanceIds), static fn(string $id): bool => $id !== '')));
    if (count($instanceIds) === 0) {
      return [];
    }

    $placeholders = implode(',', array_fill(0, count($instanceIds), '?'));
    $params = array_merge([$userId], $instanceIds);
    $stmt = $this->pdo->prepare("
      SELECT di.`id`, dd.`rarity`, dd.`sides`
      FROM `dice_instances` di
      JOIN `dice_definitions` dd ON dd.`id` = di.`dice_definition_id`
      WHERE di.`user_id` = ? AND di.`id` IN ($placeholders)
    ");
    $stmt->execute($params);

    $map = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
      $map[(string)$row['id']] = $this->formatDiceTypeLabel(
        (string)($row['rarity'] ?? 'common'),
        max(2, (int)($row['sides'] ?? 6))
      );
    }

    return $map;
  }

  /**
   * @param array<int,string> $unitIds
   * @return array<string,string>
   */
  private function loadUnitProgressLabels(int $userId, array $unitIds): array
  {
    $unitIds = array_values(array_unique(array_filter(array_map('strval', $unitIds), static fn(string $id): bool => $id !== '')));
    if (count($unitIds) === 0) {
      return [];
    }

    $placeholders = implode(',', array_fill(0, count($unitIds), '?'));
    $params = array_merge([$userId], $unitIds);
    $stmt = $this->pdo->prepare("
      SELECT ui.`id`, ui.`display_name`, ut.`name`
      FROM `unit_instances` ui
      JOIN `unit_types` ut ON ut.`id` = ui.`unit_type_id`
      WHERE ui.`user_id` = ? AND ui.`id` IN ($placeholders)
    ");
    $stmt->execute($params);

    $map = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
      $displayName = trim((string)($row['display_name'] ?? ''));
      $map[(string)$row['id']] = $displayName !== '' ? $displayName : (string)$row['name'];
    }

    return $map;
  }

  /**
   * @return array<int,array{unit_instance_id:string,hp:int,is_defeated:bool}>
   */
  private function loadRunUnitState(int $userId, int $runId): array
  {
    $stmt = $this->pdo->prepare('
      SELECT rus.`unit_instance_id`, rus.`current_hp`, rus.`is_defeated`
      FROM `run_unit_state` rus
      JOIN `unit_instances` ui ON ui.`id` = rus.`unit_instance_id`
      WHERE rus.`run_id` = ? AND ui.`user_id` = ?
      ORDER BY rus.`unit_instance_id` ASC
    ');
    $stmt->execute([$runId, $userId]);

    $rows = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
      $rows[] = [
        'unit_instance_id' => (string)$row['unit_instance_id'],
        'hp' => max(0, (int)($row['current_hp'] ?? 0)),
        'is_defeated' => (int)($row['is_defeated'] ?? 0) === 1,
      ];
    }

    return $rows;
  }

  /**
   * @param array<int,array<string,mixed>> $runState
   * @return array{0:array<int,string>,1:array<int,string>}
   */
  private function formatRunStateLists(int $userId, array $runState): array
  {
    $unitIds = [];
    foreach ($runState as $row) {
      if (!is_array($row)) {
        continue;
      }
      $unitId = trim((string)($row['unit_instance_id'] ?? ''));
      if ($unitId !== '') {
        $unitIds[] = $unitId;
      }
    }
    $labels = $this->loadUnitProgressLabels($userId, $unitIds);

    $survivors = [];
    $defeated = [];
    foreach ($runState as $row) {
      if (!is_array($row)) {
        continue;
      }
      $unitId = trim((string)($row['unit_instance_id'] ?? ''));
      if ($unitId === '') {
        continue;
      }
      $label = $labels[$unitId] ?? ('Unit ' . $unitId);
      $isDefeated = !empty($row['is_defeated']) || (int)($row['hp'] ?? 0) <= 0;
      if ($isDefeated) {
        $defeated[] = $label;
        continue;
      }
      $survivors[] = $label;
    }

    sort($survivors, SORT_NATURAL | SORT_FLAG_CASE);
    sort($defeated, SORT_NATURAL | SORT_FLAG_CASE);

    return [$survivors, $defeated];
  }

  /**
   * @param array<string,int> $counts
   */
  private function formatCountList(array $counts): string
  {
    ksort($counts, SORT_NATURAL | SORT_FLAG_CASE);
    $parts = [];
    foreach ($counts as $label => $count) {
      $parts[] = $count > 1 ? sprintf('%s x%d', $label, $count) : $label;
    }
    return implode(', ', $parts);
  }

  private function formatDiceTypeLabel(string $rarity, int $sides): string
  {
    $material = self::RARITY_TO_MATERIAL[strtolower(trim($rarity))] ?? 'cardboard';
    return sprintf('%s d%d', $material, max(2, $sides));
  }

  private function prettifyId(string $value): string
  {
    return preg_replace('/\s+/', ' ', ucwords(str_replace(['_', '-'], ' ', trim($value)))) ?: $value;
  }
}
