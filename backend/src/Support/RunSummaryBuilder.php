<?php
declare(strict_types=1);

namespace DiceGoblins\Support;

use PDO;
use DiceGoblins\Repositories\DiceRepository;
use DiceGoblins\Services\UnitProgressionService;

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
   * @param array<string,mixed> $rewards
   * @return array{
   *   units:array<int,array{
   *     unit_instance_id:string|null,
   *     name:string,
   *     unit_type_slug:string|null,
   *     unit_type_name:string,
   *     tier:int,
   *     level:int,
   *     total_attack:int,
   *     total_defense:int,
   *     total_precision:int,
   *     total_resolve:int,
   *     max_hp:int
   *   }>,
   *   dice:array<int,array{
   *     dice_instance_id:string|null,
   *     label:string,
   *     rarity:string,
   *     material:string,
   *     sides:int,
   *     affixes:array<int,array{
   *       affix_definition_id:string,
   *       affix_slug:string,
   *       name:string,
   *       rarity:string,
   *       kind:string,
   *       description:string,
   *       value:float
   *     }>
   *   }>
   * }
   */
  public function buildBattleRewardDetails(int $userId, array $rewards): array
  {
    return [
      'units' => $this->extractUnitRewardDetails($userId, $rewards),
      'dice' => $this->extractDiceRewardDetails($userId, $rewards),
    ];
  }

  /**
   * @param array<int,array<string,mixed>>|null $terminalRunState
   * @return array{
   *   rewards:array<int,string>,
   *   progression:array<int,string>,
   *   survivors:array<int,string>,
   *   defeated:array<int,string>,
   *   reward_detail:array{
   *     currency_soft:int,
   *     units:array<int,array{unit_instance_id:string|null,label:string}>,
   *     dice:array<int,array{dice_instance_id:string|null,label:string}>
   *   },
   *   progression_detail:array<int,array{
   *     unit_instance_id:string,
   *     label:string,
   *     xp_gained:int,
   *     is_defeated:bool,
   *     level_gain_count:int,
   *     final_level:int,
   *     final_xp:int,
   *     xp_to_next_level:int,
   *     tier:int,
   *     max_level:int,
   *     unit_type_name:string
   *   }>
   * }
   */
  public function buildRunSummary(int $userId, int $runId, ?array $terminalRunState = null): array
  {
    $battleRows = $this->loadClaimedBattleRows($userId, $runId);
    $teethTotal = 0;
    $unitRewardCounts = [];
    $diceRewardCounts = [];
    $xpByUnitId = [];
    $rewardUnits = [];
    $rewardDice = [];

    foreach ($battleRows as $battle) {
      $teethTotal += max(0, (int)($battle['currency_soft'] ?? 0));
      $rewards = is_array($battle['rewards']) ? $battle['rewards'] : [];

      foreach ($this->extractUnitRewardEntries($userId, $rewards) as $entry) {
        $rewardUnits[] = $entry;
      }
      foreach ($this->extractDiceRewardEntries($userId, $rewards) as $entry) {
        $rewardDice[] = $entry;
      }

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

    $runState = is_array($terminalRunState) && count($terminalRunState) > 0
      ? $terminalRunState
      : $this->loadRunUnitState($userId, $runId);
    [$survivors, $defeated] = $this->formatRunStateLists($userId, $runState);
    [$progressionLines, $progressionDetail] = $this->buildProgressionSummary($userId, $xpByUnitId, $runState);

    return [
      'rewards' => $rewardLines,
      'progression' => $progressionLines,
      'survivors' => $survivors,
      'defeated' => $defeated,
      'reward_detail' => [
        'currency_soft' => $teethTotal,
        'units' => $rewardUnits,
        'dice' => $rewardDice,
      ],
      'progression_detail' => $progressionDetail,
    ];
  }

  /**
   * @param array<string,int> $xpByUnitId
   * @param array<int,array<string,mixed>> $runState
   * @return array{
   *   0:array<int,string>,
   *   1:array<int,array{
   *     unit_instance_id:string,
   *     label:string,
   *     xp_gained:int,
   *     is_defeated:bool,
   *     level_gain_count:int,
   *     final_level:int,
   *     final_xp:int,
   *     xp_to_next_level:int,
   *     tier:int,
   *     max_level:int,
   *     unit_type_name:string
   *   }>
   * }
   */
  private function buildProgressionSummary(int $userId, array $xpByUnitId, array $runState): array
  {
    $runStateByUnitId = [];
    foreach ($runState as $row) {
      if (!is_array($row)) {
        continue;
      }
      $unitId = trim((string)($row['unit_instance_id'] ?? ''));
      if ($unitId === '') {
        continue;
      }

      $runStateByUnitId[$unitId] = [
        'hp' => max(0, (int)($row['hp'] ?? 0)),
        'is_defeated' => !empty($row['is_defeated']) || (int)($row['hp'] ?? 0) <= 0,
      ];
    }

    $unitIds = array_values(array_unique(array_merge(array_keys($runStateByUnitId), array_keys($xpByUnitId))));
    if (count($unitIds) === 0) {
      return [[], []];
    }

    $unitSnapshots = $this->loadUnitProgressSnapshots($userId, $unitIds);
    usort($unitIds, function (string $leftId, string $rightId) use ($xpByUnitId, $runStateByUnitId, $unitSnapshots): int {
      $leftXp = (int)($xpByUnitId[$leftId] ?? 0);
      $rightXp = (int)($xpByUnitId[$rightId] ?? 0);
      if ($leftXp !== $rightXp) {
        return $rightXp <=> $leftXp;
      }

      $leftDefeated = !empty($runStateByUnitId[$leftId]['is_defeated']);
      $rightDefeated = !empty($runStateByUnitId[$rightId]['is_defeated']);
      if ($leftDefeated !== $rightDefeated) {
        return $leftDefeated ? 1 : -1;
      }

      $leftLabel = (string)($unitSnapshots[$leftId]['label'] ?? ('Unit ' . $leftId));
      $rightLabel = (string)($unitSnapshots[$rightId]['label'] ?? ('Unit ' . $rightId));
      return strnatcasecmp($leftLabel, $rightLabel);
    });

    $progressionLines = [];
    $progressionDetail = [];
    foreach ($unitIds as $unitId) {
      $snapshot = $unitSnapshots[$unitId] ?? null;
      $label = is_array($snapshot) ? (string)$snapshot['label'] : ('Unit ' . $unitId);
      $xpGained = max(0, (int)($xpByUnitId[$unitId] ?? 0));
      $isDefeated = !empty($runStateByUnitId[$unitId]['is_defeated']);

      if ($xpGained > 0) {
        $progressionLines[] = sprintf('%s +%d XP', $label, $xpGained);
      }

      $progressionDetail[] = [
        'unit_instance_id' => $unitId,
        'label' => $label,
        'xp_gained' => $xpGained,
        'is_defeated' => $isDefeated,
        'level_gain_count' => is_array($snapshot)
          ? $this->estimateLevelGainCount(
            (int)$snapshot['final_level'],
            (int)$snapshot['final_xp'],
            $xpGained,
            (int)$snapshot['tier'],
          )
          : 0,
        'final_level' => is_array($snapshot) ? (int)$snapshot['final_level'] : 1,
        'final_xp' => is_array($snapshot) ? (int)$snapshot['final_xp'] : 0,
        'xp_to_next_level' => is_array($snapshot) ? (int)$snapshot['xp_to_next_level'] : 0,
        'tier' => is_array($snapshot) ? (int)$snapshot['tier'] : 1,
        'max_level' => is_array($snapshot) ? (int)$snapshot['max_level'] : 1,
        'unit_type_name' => is_array($snapshot) ? (string)$snapshot['unit_type_name'] : 'Unit',
      ];
    }

    return [$progressionLines, $progressionDetail];
  }

  /**
   * @param array<int,string> $unitIds
   * @return array<string,array{
   *   label:string,
   *   final_level:int,
   *   final_xp:int,
   *   xp_to_next_level:int,
   *   tier:int,
   *   max_level:int,
   *   unit_type_name:string
   * }>
   */
  private function loadUnitProgressSnapshots(int $userId, array $unitIds): array
  {
    $unitIds = array_values(array_unique(array_filter(array_map('strval', $unitIds), static fn(string $id): bool => $id !== '')));
    if (count($unitIds) === 0) {
      return [];
    }

    $placeholders = implode(',', array_fill(0, count($unitIds), '?'));
    $params = array_merge([$userId], $unitIds);
    $stmt = $this->pdo->prepare("
      SELECT ui.`id`, ui.`display_name`, ui.`level`, ui.`xp`, ui.`tier`, ut.`name`, ut.`max_level`
      FROM `unit_instances` ui
      JOIN `unit_types` ut ON ut.`id` = ui.`unit_type_id`
      WHERE ui.`user_id` = ? AND ui.`id` IN ($placeholders)
    ");
    $stmt->execute($params);

    $progression = new UnitProgressionService();
    $map = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
      $displayName = trim((string)($row['display_name'] ?? ''));
      $level = max(1, (int)($row['level'] ?? 1));
      $xp = max(0, (int)($row['xp'] ?? 0));
      $tier = max(1, (int)($row['tier'] ?? 1));
      $maxLevel = max(1, (int)($row['max_level'] ?? 1));
      $map[(string)$row['id']] = [
        'label' => $displayName !== '' ? $displayName : (string)$row['name'],
        'final_level' => $level,
        'final_xp' => $xp,
        'xp_to_next_level' => $progression->xpToNextLevel($tier, $level, $maxLevel, $xp),
        'tier' => $tier,
        'max_level' => $maxLevel,
        'unit_type_name' => (string)$row['name'],
      ];
    }

    return $map;
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
   * @return array<int,array{unit_instance_id:string|null,label:string}>
   */
  private function extractUnitRewardEntries(int $userId, array $rewards): array
  {
    $instanceIds = is_array($rewards['new_unit_instance_ids'] ?? null)
      ? array_values(array_filter(array_map('strval', $rewards['new_unit_instance_ids']), static fn(string $id): bool => $id !== ''))
      : [];

    if (count($instanceIds) > 0) {
      $labelsById = $this->loadUnitTypeLabelsForInstances($userId, $instanceIds);
      $entries = [];
      foreach ($instanceIds as $instanceId) {
        $entries[] = [
          'unit_instance_id' => $instanceId,
          'label' => $labelsById[$instanceId] ?? ('Unit ' . $instanceId),
        ];
      }
      return $entries;
    }

    return array_map(
      static fn(string $label): array => [
        'unit_instance_id' => null,
        'label' => $label,
      ],
      $this->extractUnitRewardLabels($userId, $rewards)
    );
  }

  /**
   * @param array<string,mixed> $rewards
   * @return array<int,array{
   *   unit_instance_id:string|null,
   *   name:string,
   *   unit_type_slug:string|null,
   *   unit_type_name:string,
   *   splice_variant_slug:string,
   *   splice_variant_name:string,
   *   splice_variant_description:string,
   *   splice_variant_passive_summary:string,
   *   tier:int,
   *   level:int,
   *   total_attack:int,
   *   total_defense:int,
   *   total_precision:int,
   *   total_resolve:int,
   *   max_hp:int
   * }>
   */
  private function extractUnitRewardDetails(int $userId, array $rewards): array
  {
    $instanceIds = is_array($rewards['new_unit_instance_ids'] ?? null)
      ? array_values(array_filter(array_map('strval', $rewards['new_unit_instance_ids']), static fn(string $id): bool => $id !== ''))
      : [];

    if (count($instanceIds) > 0) {
      return $this->loadUnitRewardDetailsForInstances($userId, $instanceIds);
    }

    $unitGrants = is_array($rewards['unit_grants'] ?? null) ? $rewards['unit_grants'] : [];
    if (count($unitGrants) === 0) {
      return [];
    }

    $slugs = [];
    $spliceSlugs = [];
    foreach ($unitGrants as $grant) {
      if (!is_array($grant)) {
        continue;
      }
      $slug = trim((string)($grant['unit_type_slug'] ?? ''));
      if ($slug !== '') {
        $slugs[] = $slug;
      }
      $spliceSlug = trim((string)($grant['splice_variant_slug'] ?? ''));
      if ($spliceSlug !== '') {
        $spliceSlugs[] = $spliceSlug;
      }
    }

    $unitTypesBySlug = $this->loadUnitTypesBySlug($slugs);
    $spliceVariantsBySlug = $this->loadSpliceVariantsBySlug($spliceSlugs);
    $details = [];
    foreach ($unitGrants as $grant) {
      if (!is_array($grant)) {
        continue;
      }

      $slug = trim((string)($grant['unit_type_slug'] ?? ''));
      if ($slug === '') {
        continue;
      }

      $unitType = $unitTypesBySlug[$slug] ?? null;
      $unitTypeName = (string)($unitType['name'] ?? $this->prettifyId($slug));
      $spliceSlug = trim((string)($grant['splice_variant_slug'] ?? 'basic_goblin'));
      $spliceVariant = $spliceVariantsBySlug[$spliceSlug] ?? $this->defaultSpliceVariant();
      $kinSlug = (string)$spliceVariant['slug'];
      $kinName = (string)$spliceVariant['name'];
      $kinDescription = (string)$spliceVariant['description'];
      $kinPassiveSummary = (string)$spliceVariant['passive_summary'];
      $details[] = [
        'unit_instance_id' => null,
        'name' => $unitTypeName,
        'unit_type_slug' => $slug,
        'unit_type_name' => $unitTypeName,
        'kin_slug' => $kinSlug,
        'kin_name' => $kinName,
        'kin_description' => $kinDescription,
        'kin_passive_summary' => $kinPassiveSummary,
        'splice_variant_slug' => $kinSlug,
        'splice_variant_name' => $kinName,
        'splice_variant_description' => $kinDescription,
        'splice_variant_passive_summary' => $kinPassiveSummary,
        'tier' => max(1, (int)($grant['tier'] ?? 1)),
        'level' => max(1, (int)($grant['level'] ?? 1)),
        ...$this->unitTypeStats($unitType['base_stats_json'] ?? null),
      ];
    }

    return $details;
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
   * @param array<string,mixed> $rewards
   * @return array<int,array{dice_instance_id:string|null,label:string}>
   */
  private function extractDiceRewardEntries(int $userId, array $rewards): array
  {
    $instanceIds = is_array($rewards['new_dice_instance_ids'] ?? null)
      ? array_values(array_filter(array_map('strval', $rewards['new_dice_instance_ids']), static fn(string $id): bool => $id !== ''))
      : [];

    if (count($instanceIds) > 0) {
      $labelsById = $this->loadDiceTypeLabelsForInstances($userId, $instanceIds);
      $entries = [];
      foreach ($instanceIds as $instanceId) {
        $entries[] = [
          'dice_instance_id' => $instanceId,
          'label' => $labelsById[$instanceId] ?? ('Die ' . $instanceId),
        ];
      }
      return $entries;
    }

    return array_map(
      static fn(string $label): array => [
        'dice_instance_id' => null,
        'label' => $label,
      ],
      $this->extractDiceRewardLabels($userId, $rewards)
    );
  }

  /**
   * @param array<string,mixed> $rewards
   * @return array<int,array{
   *   dice_instance_id:string|null,
   *   label:string,
   *   rarity:string,
   *   material:string,
   *   sides:int,
   *   affixes:array<int,array{
   *     affix_definition_id:string,
   *     affix_slug:string,
   *     name:string,
   *     rarity:string,
   *     kind:string,
   *     description:string,
   *     value:float
   *   }>
   * }>
   */
  private function extractDiceRewardDetails(int $userId, array $rewards): array
  {
    $instanceIds = is_array($rewards['new_dice_instance_ids'] ?? null)
      ? array_values(array_filter(array_map('strval', $rewards['new_dice_instance_ids']), static fn(string $id): bool => $id !== ''))
      : [];

    if (count($instanceIds) > 0) {
      return $this->loadDiceRewardDetailsForInstances($userId, $instanceIds);
    }

    $diceGrants = is_array($rewards['dice_grants'] ?? null) ? $rewards['dice_grants'] : [];
    $details = [];
    foreach ($diceGrants as $grant) {
      if (!is_array($grant)) {
        continue;
      }

      $rarity = strtolower(trim((string)($grant['rarity'] ?? 'common')));
      $sides = max(2, (int)($grant['sides'] ?? 6));
      $details[] = [
        'dice_instance_id' => null,
        'label' => $this->formatDiceTypeLabel($rarity, $sides),
        'rarity' => $rarity,
        'material' => $this->diceMaterial($rarity),
        'sides' => $sides,
        'affixes' => [],
      ];
    }

    return $details;
  }

  /**
   * @param array<int,string> $slugs
   * @return array<string,string>
   */
  private function loadUnitTypeNamesBySlug(array $slugs): array
  {
    $map = [];
    foreach ($this->loadUnitTypesBySlug($slugs) as $slug => $row) {
      $map[$slug] = (string)$row['name'];
    }

    return $map;
  }

  /**
   * @param array<int,string> $slugs
   * @return array<string,array{name:string,base_stats_json:mixed}>
   */
  private function loadUnitTypesBySlug(array $slugs): array
  {
    $slugs = array_values(array_unique(array_filter(array_map('strval', $slugs), static fn(string $slug): bool => $slug !== '')));
    if (count($slugs) === 0) {
      return [];
    }

    $placeholders = implode(',', array_fill(0, count($slugs), '?'));
    $stmt = $this->pdo->prepare("
      SELECT `slug`, `name`, `base_stats_json`
      FROM `unit_types`
      WHERE `slug` IN ($placeholders)
    ");
    $stmt->execute($slugs);

    $map = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
      $map[(string)$row['slug']] = [
        'name' => (string)$row['name'],
        'base_stats_json' => $row['base_stats_json'] ?? null,
      ];
    }

    return $map;
  }

  /**
   * @param array<int,string> $slugs
   * @return array<string,array{slug:string,name:string,description:string,passive_summary:string}>
   */
  private function loadSpliceVariantsBySlug(array $slugs): array
  {
    $slugs = array_values(array_unique(array_filter(array_map('strval', $slugs), static fn(string $slug): bool => $slug !== '')));
    if (count($slugs) === 0) {
      return [];
    }

    $placeholders = implode(',', array_fill(0, count($slugs), '?'));
    $stmt = $this->pdo->prepare("
      SELECT `slug`, `name`, `description`, `passive_summary`
      FROM `splice_variants`
      WHERE `slug` IN ($placeholders)
    ");
    $stmt->execute($slugs);

    $map = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
      $map[(string)$row['slug']] = [
        'slug' => (string)$row['slug'],
        'name' => (string)$row['name'],
        'description' => (string)$row['description'],
        'passive_summary' => (string)$row['passive_summary'],
      ];
    }

    return $map;
  }

  /**
   * @return array{slug:string,name:string,description:string,passive_summary:string}
   */
  private function defaultSpliceVariant(): array
  {
    return [
      'slug' => 'basic_goblin',
      'name' => 'Basic Goblin',
      'description' => 'Baseline goblin stock with no splice tendency.',
      'passive_summary' => 'No splice modifier.',
    ];
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
   * @return array<int,array{
   *   unit_instance_id:string,
   *   name:string,
   *   unit_type_slug:string,
   *   unit_type_name:string,
   *   splice_variant_slug:string,
   *   splice_variant_name:string,
   *   splice_variant_description:string,
   *   splice_variant_passive_summary:string,
   *   tier:int,
   *   level:int,
   *   total_attack:int,
   *   total_defense:int,
   *   total_precision:int,
   *   total_resolve:int,
   *   max_hp:int
   * }>
   */
  private function loadUnitRewardDetailsForInstances(int $userId, array $instanceIds): array
  {
    $instanceIds = array_values(array_unique(array_filter(array_map('strval', $instanceIds), static fn(string $id): bool => $id !== '')));
    if (count($instanceIds) === 0) {
      return [];
    }

    $placeholders = implode(',', array_fill(0, count($instanceIds), '?'));
    $params = array_merge([$userId], $instanceIds);
    $stmt = $this->pdo->prepare("
      SELECT
        ui.`id`,
        ui.`display_name`,
        ui.`tier`,
        ui.`level`,
        ui.`splice_variant_slug`,
        ut.`slug` AS `unit_type_slug`,
        ut.`name` AS `unit_type_name`,
        ut.`base_stats_json`,
        sv.`name` AS `splice_variant_name`,
        sv.`description` AS `splice_variant_description`,
        sv.`passive_summary` AS `splice_variant_passive_summary`
      FROM `unit_instances` ui
      JOIN `unit_types` ut ON ut.`id` = ui.`unit_type_id`
      LEFT JOIN `splice_variants` sv ON sv.`slug` = ui.`splice_variant_slug`
      WHERE ui.`user_id` = ? AND ui.`id` IN ($placeholders)
    ");
    $stmt->execute($params);

    $detailsById = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
      $displayName = trim((string)($row['display_name'] ?? ''));
      $unitTypeName = (string)($row['unit_type_name'] ?? '');
      $kinSlug = (string)($row['splice_variant_slug'] ?? 'basic_goblin');
      $kinName = (string)($row['splice_variant_name'] ?? 'Basic Goblin');
      $kinDescription = (string)($row['splice_variant_description'] ?? '');
      $kinPassiveSummary = (string)($row['splice_variant_passive_summary'] ?? '');
      $detailsById[(string)$row['id']] = [
        'unit_instance_id' => (string)$row['id'],
        'name' => $displayName !== '' ? $displayName : $unitTypeName,
        'unit_type_slug' => (string)($row['unit_type_slug'] ?? ''),
        'unit_type_name' => $unitTypeName,
        'kin_slug' => $kinSlug,
        'kin_name' => $kinName,
        'kin_description' => $kinDescription,
        'kin_passive_summary' => $kinPassiveSummary,
        'splice_variant_slug' => $kinSlug,
        'splice_variant_name' => $kinName,
        'splice_variant_description' => $kinDescription,
        'splice_variant_passive_summary' => $kinPassiveSummary,
        'tier' => max(1, (int)($row['tier'] ?? 1)),
        'level' => max(1, (int)($row['level'] ?? 1)),
        ...$this->unitTypeStats($row['base_stats_json'] ?? null),
      ];
    }

    $ordered = [];
    foreach ($instanceIds as $instanceId) {
      if (isset($detailsById[$instanceId])) {
        $ordered[] = $detailsById[$instanceId];
      }
    }

    return $ordered;
  }

  /**
   * @return array{total_attack:int,total_defense:int,total_precision:int,total_resolve:int,max_hp:int}
   */
  private function unitTypeStats(mixed $baseStatsJson): array
  {
    $stats = is_string($baseStatsJson) && $baseStatsJson !== ''
      ? json_decode($baseStatsJson, true)
      : [];
    $stats = is_array($stats) ? $stats : [];

    return [
      'total_attack' => max(0, (int)($stats['attack'] ?? 0)),
      'total_defense' => max(0, (int)($stats['defense'] ?? 0)),
      'total_precision' => max(0, (int)($stats['precision'] ?? 5)),
      'total_resolve' => max(0, (int)($stats['resolve'] ?? 5)),
      'max_hp' => max(1, (int)($stats['max_hp'] ?? 1)),
    ];
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
   * @param array<int,string> $instanceIds
   * @return array<int,array{
   *   dice_instance_id:string,
   *   label:string,
   *   rarity:string,
   *   material:string,
   *   sides:int,
   *   affixes:array<int,array{
   *     affix_definition_id:string,
   *     affix_slug:string,
   *     name:string,
   *     rarity:string,
   *     kind:string,
   *     description:string,
   *     value:float
   *   }>
   * }>
   */
  private function loadDiceRewardDetailsForInstances(int $userId, array $instanceIds): array
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

    $affixesByDice = (new DiceRepository($this->pdo))->getAffixesForDiceInstanceIds($instanceIds);
    $detailsById = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
      $diceId = (string)$row['id'];
      $rarity = strtolower(trim((string)($row['rarity'] ?? 'common')));
      $sides = max(2, (int)($row['sides'] ?? 6));
      $detailsById[$diceId] = [
        'dice_instance_id' => $diceId,
        'label' => $this->formatDiceTypeLabel($rarity, $sides),
        'rarity' => $rarity,
        'material' => $this->diceMaterial($rarity),
        'sides' => $sides,
        'affixes' => $affixesByDice[$diceId] ?? [],
      ];
    }

    $ordered = [];
    foreach ($instanceIds as $instanceId) {
      if (isset($detailsById[$instanceId])) {
        $ordered[] = $detailsById[$instanceId];
      }
    }

    return $ordered;
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
    return sprintf('%s d%d', $this->diceMaterial($rarity), max(2, $sides));
  }

  private function diceMaterial(string $rarity): string
  {
    return self::RARITY_TO_MATERIAL[strtolower(trim($rarity))] ?? 'cardboard';
  }

  private function prettifyId(string $value): string
  {
    return preg_replace('/\s+/', ' ', ucwords(str_replace(['_', '-'], ' ', trim($value)))) ?: $value;
  }

  private function estimateLevelGainCount(int $finalLevel, int $finalXp, int $xpGained, int $tier): int
  {
    $remaining = max(0, $xpGained);
    $level = max(1, $finalLevel);
    $xp = max(0, $finalXp);
    $resolvedTier = max(1, $tier);
    $levelGainCount = 0;

    if ($remaining <= $xp) {
      return 0;
    }

    $remaining -= $xp;
    while ($remaining > 0 && $level > 1) {
      $level--;
      $levelGainCount++;
      $threshold = $this->levelThreshold($level, $resolvedTier);
      if ($remaining <= $threshold) {
        break;
      }

      $remaining -= $threshold;
    }

    return $levelGainCount;
  }

  private function levelThreshold(int $level, int $tier): int
  {
    return max(1, max(1, $tier) * (max(1, $level) + 1) * 50);
  }
}
