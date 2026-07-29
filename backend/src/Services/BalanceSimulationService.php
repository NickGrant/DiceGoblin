<?php
declare(strict_types=1);

namespace DiceGoblins\Services;

use DiceGoblins\Combat\Engine\DeterministicRunNodeResolver;
use DiceGoblins\Repositories\EnergyRepository;
use DiceGoblins\Repositories\PlayerStateRepository;
use DiceGoblins\Repositories\RegionRepository;
use DiceGoblins\Repositories\RunRepository;
use DiceGoblins\Repositories\TeamRepository;
use PDO;
use RuntimeException;
use Throwable;

final class BalanceSimulationService
{
  private const DEFAULT_SAMPLE_COUNT = 25;
  private const MAX_SAMPLE_COUNT = 10000;
  private const DEFAULT_PROGRESSION_MAX_RUNS = 25;
  /** @var array<string,array<string,int|string>> */
  private const PROGRESSION_GOALS = [
    'first_promotion' => [
      'label' => 'First promotion-ready unit',
      'xp_total' => 1000,
    ],
    'next_region' => [
      'label' => 'Next region unlock',
      'completed_runs' => 1,
    ],
    'wrong_machine' => [
      'label' => 'Wrong Machine unlock',
      'completed_runs' => 1,
    ],
    'pig_kin' => [
      'label' => 'Pig Kin reconstruction',
      'completed_runs' => 1,
      'raw_chaos' => 5,
      'pig_ear' => 3,
      'mudking_crown_fragment' => 1,
    ],
  ];

  public function __construct(private readonly PDO $pdo)
  {
  }

  /**
   * @param array<string,mixed> $options
   * @return array<string,mixed>
   */
  public function run(array $options): array
  {
    $mode = $this->stringOption($options, 'mode', 'battle');
    $regionSlug = $this->stringOption($options, 'region', 'the_farm');
    $sampleCount = $this->intOption($options, 'runs', self::DEFAULT_SAMPLE_COUNT, 1, self::MAX_SAMPLE_COUNT);
    $seedBase = $this->stringOption($options, 'seed', 'balance-sim');
    $summaryOnly = $this->boolOption($options, 'summary-only', false);

    $report = match ($mode) {
      'battle' => $this->simulateBattle(
        $regionSlug,
        $this->stringOption($options, 'node', 'combat'),
        $sampleCount,
        $seedBase,
        $this->stringOption($options, 'profile', 'fresh_starter')
      ),
      'run' => $this->simulateRun(
        $regionSlug,
        $sampleCount,
        $seedBase,
        $this->stringOption($options, 'profile', 'fresh_starter')
      ),
      'progression' => $this->simulateProgression(
        $regionSlug,
        $this->stringOption($options, 'goal', 'all'),
        $sampleCount,
        $seedBase,
        $this->intOption($options, 'max-runs', self::DEFAULT_PROGRESSION_MAX_RUNS, 1, 250),
        $this->stringOption($options, 'profile', 'fresh_starter')
      ),
      default => throw new RuntimeException("Unsupported simulation mode '{$mode}'. Use battle, run, or progression."),
    };

    return $summaryOnly ? $this->withoutSamples($report) : $report;
  }

  /**
   * @return array<string,mixed>
   */
  public function simulateBattle(
    string $regionSlug,
    string $nodeType,
    int $sampleCount,
    string $seedBase = 'balance-sim',
    string $profile = 'fresh_starter'
  ): array
  {
    $this->assertSupportedProfile($profile);
    if (!in_array($nodeType, ['combat', 'boss', 'loot', 'hazard', 'shrine', 'rest'], true)) {
      throw new RuntimeException("Unsupported battle simulation node type '{$nodeType}'.");
    }

    $samples = [];
    for ($i = 0; $i < $sampleCount; $i++) {
      $samples[] = $this->simulateSample($regionSlug, [$nodeType], $seedBase, $i, $profile);
    }

    return $this->buildReport('battle', $regionSlug, ['node_type' => $nodeType, 'profile' => $profile], $samples);
  }

  /**
   * @return array<string,mixed>
   */
  public function simulateRun(
    string $regionSlug,
    int $sampleCount,
    string $seedBase = 'balance-sim',
    string $profile = 'fresh_starter'
  ): array
  {
    $this->assertSupportedProfile($profile);
    $nodeTypes = ['combat', 'loot', 'hazard', 'shrine', 'boss'];
    $samples = [];
    for ($i = 0; $i < $sampleCount; $i++) {
      $samples[] = $this->simulateSample($regionSlug, $nodeTypes, $seedBase, $i, $profile);
    }

    return $this->buildReport('run', $regionSlug, ['node_types' => $nodeTypes, 'profile' => $profile], $samples);
  }

  /**
   * @return array<string,mixed>
   */
  public function simulateProgression(
    string $regionSlug,
    string $goal,
    int $sampleCount,
    string $seedBase = 'balance-sim',
    int $maxRuns = self::DEFAULT_PROGRESSION_MAX_RUNS,
    string $profile = 'fresh_starter'
  ): array {
    $this->assertSupportedProfile($profile);
    $goals = $this->selectedProgressionGoals($goal);
    $samples = [];
    for ($i = 0; $i < $sampleCount; $i++) {
      $samples[] = $this->simulateProgressionSample($regionSlug, $goals, $seedBase, $i, $maxRuns, $profile);
    }

    return $this->buildProgressionReport($regionSlug, $profile, $goals, $maxRuns, $samples);
  }

  /**
   * @param list<string> $goals
   * @return array<string,mixed>
   */
  private function simulateProgressionSample(
    string $regionSlug,
    array $goals,
    string $seedBase,
    int $sampleIndex,
    int $maxRuns,
    string $profile
  ): array
  {
    $progress = [
      'xp_total' => 0,
      'soft_currency' => 0,
      'raw_chaos' => 0,
      'completed_runs' => 0,
      'items' => [],
    ];
    $goalResults = [];

    for ($runNumber = 1; $runNumber <= $maxRuns; $runNumber++) {
      $runSample = $this->simulateSample(
        $regionSlug,
        ['combat', 'loot', 'hazard', 'shrine', 'boss'],
        "{$seedBase}|progression|{$sampleIndex}",
        $runNumber,
        $profile
      );
      $this->applyRunProgress($progress, $runSample);

      foreach ($goals as $goal) {
        if (isset($goalResults[$goal])) {
          continue;
        }

        $evaluation = $this->evaluateProgressionGoal($goal, $progress);
        if ($evaluation['achieved']) {
          $goalResults[$goal] = [
            'achieved' => true,
            'runs_to_goal' => $runNumber,
            'shortfalls' => [],
            'failure_reasons' => [],
          ];
        }
      }
    }

    foreach ($goals as $goal) {
      if (isset($goalResults[$goal])) {
        continue;
      }

      $evaluation = $this->evaluateProgressionGoal($goal, $progress);
      $goalResults[$goal] = [
        'achieved' => false,
        'runs_to_goal' => null,
        'shortfalls' => $evaluation['shortfalls'],
        'failure_reasons' => $evaluation['failure_reasons'],
      ];
    }

    ksort($goalResults);
    ksort($progress['items']);

    return [
      'sample_index' => $sampleIndex,
      'max_runs' => $maxRuns,
      'final_progress' => $progress,
      'goals' => $goalResults,
    ];
  }

  /**
   * @param list<string> $nodeTypes
   * @return array<string,mixed>
   */
  private function simulateSample(
    string $regionSlug,
    array $nodeTypes,
    string $seedBase,
    int $sampleIndex,
    string $profile
  ): array
  {
    $userId = 0;

    try {
      $userId = $this->createSimulationUser($sampleIndex);
      $this->bootstrapSimulationUser($userId);
      $this->applySimulationProfile($userId, $profile);

      $region = (new RegionRepository($this->pdo))->getRegionBySlug($regionSlug);
      if ($region === null) {
        throw new RuntimeException("Unknown region '{$regionSlug}'.");
      }
      if (!$region['is_enabled']) {
        throw new RuntimeException("Region '{$regionSlug}' is disabled.");
      }

      $regionId = (int)$region['id'];
      $regionRepository = new RegionRepository($this->pdo);
      $regionRepository->unlockRegion($userId, $regionId);

      $team = (new TeamRepository($this->pdo))->getActiveTeamForUser($userId);
      if ($team === null) {
        throw new RuntimeException('Simulation user has no active team.');
      }

      $teamId = (int)$team['id'];
      $runRepository = new RunRepository($this->pdo);
      $runSeed = (string)$this->numericSeed($seedBase, $sampleIndex);
      $runId = $runRepository->createRun($userId, $regionId, $runSeed);
      $runRepository->seedRunUnitStateFromTeam($runId, $userId, $teamId);

      $resolver = new DeterministicRunNodeResolver($this->pdo);
      $nodes = [];
      $completed = true;
      foreach ($nodeTypes as $nodeIndex => $nodeType) {
        $nodeId = $this->insertSimulationNode($runId, $nodeIndex, $nodeType, $regionId);
        $node = [
          'id' => (string)$nodeId,
          'node_type' => $nodeType,
          'encounter_template_id' => $this->encounterTemplateIdForNodeType($regionId, $nodeType) !== null
            ? (string)$this->encounterTemplateIdForNodeType($regionId, $nodeType)
            : null,
        ];

        $result = $resolver->resolve($userId, $teamId, [
          'id' => (string)$runId,
          'seed' => $runSeed,
        ], $node);

        $nodes[] = $this->summarizeNode($nodeType, $result);
        if ((string)$result['outcome'] !== 'victory') {
          $completed = false;
          break;
        }
      }

      return [
        'sample_index' => $sampleIndex,
        'completed' => $completed,
        'nodes_resolved' => count($nodes),
        'nodes' => $nodes,
      ];
    } finally {
      if ($userId > 0) {
        $this->deleteSimulationUser($userId);
      }
    }
  }

  private function createSimulationUser(int $sampleIndex): int
  {
    $token = bin2hex(random_bytes(4));
    $stmt = $this->pdo->prepare('
      INSERT INTO `users` (`discord_id`, `display_name`)
      VALUES (?, ?)
    ');
    $stmt->execute([
      "sim_{$sampleIndex}_{$token}",
      "Simulation {$sampleIndex} {$token}",
    ]);

    return (int)$this->pdo->lastInsertId();
  }

  private function numericSeed(string $seedBase, int $sampleIndex): int
  {
    $hash = hash('sha256', $seedBase . '|' . $sampleIndex);
    $seed = (int)base_convert(substr($hash, 0, 12), 16, 10);
    return max(1, min(2147483647, $seed));
  }

  private function bootstrapSimulationUser(int $userId): void
  {
    $bootstrapper = new PlayerBootstrapper(
      new PlayerStateRepository($this->pdo),
      new EnergyRepository($this->pdo),
      new StarterPackProvisioningService()
    );
    $bootstrapper->ensureBaseline($userId);
  }

  private function applySimulationProfile(int $userId, string $profile): void
  {
    $this->assertSupportedProfile($profile);

    if ($profile === 'fresh_starter' || $profile === 'basic_goblin_starter') {
      $stmt = $this->pdo->prepare('
        UPDATE `unit_instances`
        SET `splice_variant_slug` = ?
        WHERE `user_id` = ?
      ');
      $stmt->execute([SpliceVariantService::BASIC_GOBLIN, $userId]);
      return;
    }

    if ($profile === 'pig_kin_starter') {
      (new LineageUnlockService($this->pdo))->grant($userId, LineageUnlockService::PIG_KIN);
      $stmt = $this->pdo->prepare('
        UPDATE `unit_instances`
        SET `splice_variant_slug` = ?
        WHERE `user_id` = ?
      ');
      $stmt->execute([LineageUnlockService::PIG_KIN, $userId]);
    }
  }

  private function assertSupportedProfile(string $profile): void
  {
    if (!in_array($profile, ['fresh_starter', 'basic_goblin_starter', 'pig_kin_starter'], true)) {
      throw new RuntimeException("Unsupported simulation profile '{$profile}'. Use fresh_starter, basic_goblin_starter, or pig_kin_starter.");
    }
  }

  private function insertSimulationNode(int $runId, int $nodeIndex, string $nodeType, int $regionId): int
  {
    $stmt = $this->pdo->prepare('
      INSERT INTO `run_nodes` (`run_id`, `node_index`, `node_type`, `status`, `encounter_template_id`, `meta_json`)
      VALUES (?, ?, ?, \'available\', ?, ?)
    ');
    $stmt->execute([
      $runId,
      $nodeIndex,
      $nodeType,
      $this->encounterTemplateIdForNodeType($regionId, $nodeType),
      json_encode(['simulation' => true], JSON_UNESCAPED_SLASHES),
    ]);

    return (int)$this->pdo->lastInsertId();
  }

  private function encounterTemplateIdForNodeType(int $regionId, string $nodeType): ?int
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

    $stmt = $this->pdo->prepare('
      SELECT `id`
      FROM `encounter_templates`
      WHERE `region_id` = ? AND `slug` LIKE ?
      ORDER BY `id` ASC
      LIMIT 1
    ');
    $stmt->execute([$regionId, $slugPattern]);
    $value = $stmt->fetchColumn();

    return $value === false || $value === null ? null : (int)$value;
  }

  /**
   * @param array<string,mixed> $result
   * @return array<string,mixed>
   */
  private function summarizeNode(string $nodeType, array $result): array
  {
    $hp = $this->playerHpSummary($result['log'] ?? []);
    $rewards = is_array($result['rewards'] ?? null) ? $result['rewards'] : [];

    return [
      'node_type' => $nodeType,
      'outcome' => (string)$result['outcome'],
      'rounds' => (int)$result['rounds'],
      'ticks' => (int)$result['ticks'],
      'xp_total' => (int)$result['xp_total'],
      'currency_soft' => (int)$result['currency_soft'],
      'currency_raw_chaos' => $this->extractRawChaos($rewards),
      'hp_remaining_pct' => $hp['remaining_pct'],
      'unit_defeats' => $hp['defeats'],
      'dice_grants' => count((array)($rewards['dice_grants'] ?? [])),
      'unit_grants' => count((array)($rewards['unit_grants'] ?? [])),
      'item_quantities' => $this->extractItemQuantities($rewards),
    ];
  }

  /**
   * @param array<string,mixed> $progress
   * @param array<string,mixed> $runSample
   */
  private function applyRunProgress(array &$progress, array $runSample): void
  {
    if (!empty($runSample['completed'])) {
      $progress['completed_runs'] = (int)$progress['completed_runs'] + 1;
    }

    foreach ((array)($runSample['nodes'] ?? []) as $node) {
      if (!is_array($node)) {
        continue;
      }

      $progress['xp_total'] = (int)$progress['xp_total'] + max(0, (int)($node['xp_total'] ?? 0));
      $progress['soft_currency'] = (int)$progress['soft_currency'] + max(0, (int)($node['currency_soft'] ?? 0));
      $progress['raw_chaos'] = (int)$progress['raw_chaos'] + max(0, (int)($node['currency_raw_chaos'] ?? 0));

      foreach ((array)($node['item_quantities'] ?? []) as $slug => $quantity) {
        $slug = (string)$slug;
        if ($slug === '') {
          continue;
        }
        $progress['items'][$slug] = ((int)($progress['items'][$slug] ?? 0)) + max(0, (int)$quantity);
      }
    }
  }

  /**
   * @param array<string,mixed> $progress
   * @return array{achieved:bool,shortfalls:array<string,int>,failure_reasons:list<string>}
   */
  private function evaluateProgressionGoal(string $goal, array $progress): array
  {
    $requirements = self::PROGRESSION_GOALS[$goal] ?? null;
    if ($requirements === null) {
      throw new RuntimeException("Unsupported progression goal '{$goal}'.");
    }

    $shortfalls = [];
    foreach ($requirements as $key => $required) {
      if ($key === 'label' || !is_int($required)) {
        continue;
      }

      $owned = match ($key) {
        'xp_total', 'completed_runs', 'raw_chaos' => (int)($progress[$key] ?? 0),
        default => (int)($progress['items'][$key] ?? 0),
      };

      if ($owned < $required) {
        $shortfalls[$key] = $required - $owned;
      }
    }

    return [
      'achieved' => $shortfalls === [],
      'shortfalls' => $shortfalls,
      'failure_reasons' => array_keys($shortfalls),
    ];
  }

  /**
   * @return list<string>
   */
  private function selectedProgressionGoals(string $goal): array
  {
    if ($goal === 'all') {
      return array_keys(self::PROGRESSION_GOALS);
    }
    if (!array_key_exists($goal, self::PROGRESSION_GOALS)) {
      throw new RuntimeException("Unsupported progression goal '{$goal}'. Use all, first_promotion, next_region, wrong_machine, or pig_kin.");
    }

    return [$goal];
  }

  /**
   * @param list<string> $goals
   * @param array<int,array<string,mixed>> $samples
   * @return array<string,mixed>
   */
  private function buildProgressionReport(string $regionSlug, string $profile, array $goals, int $maxRuns, array $samples): array
  {
    $goalSummaries = [];
    foreach ($goals as $goal) {
      $goalSamples = array_map(static fn(array $sample): array => (array)($sample['goals'][$goal] ?? []), $samples);
      $achieved = array_values(array_filter($goalSamples, static fn(array $sample): bool => !empty($sample['achieved'])));
      $runsToGoal = array_values(array_map(static fn(array $sample): int => (int)$sample['runs_to_goal'], $achieved));
      $failureReasons = [];
      $shortfallTotals = [];

      foreach ($goalSamples as $sample) {
        if (!empty($sample['achieved'])) {
          continue;
        }
        foreach ((array)($sample['failure_reasons'] ?? []) as $reason) {
          $reason = (string)$reason;
          $failureReasons[$reason] = ($failureReasons[$reason] ?? 0) + 1;
        }
        foreach ((array)($sample['shortfalls'] ?? []) as $key => $quantity) {
          $key = (string)$key;
          $shortfallTotals[$key] = ($shortfallTotals[$key] ?? 0) + (int)$quantity;
        }
      }

      ksort($failureReasons);
      ksort($shortfallTotals);
      $sampleCount = max(1, count($goalSamples));
      $goalSummaries[$goal] = [
        'label' => (string)self::PROGRESSION_GOALS[$goal]['label'],
        'requirements' => $this->progressionGoalRequirements($goal),
        'achievement_rate' => round(count($achieved) / $sampleCount, 4),
        'achieved_samples' => count($achieved),
        'runs_p50' => $this->percentile($runsToGoal, 0.50),
        'runs_p75' => $this->percentile($runsToGoal, 0.75),
        'runs_p90' => $this->percentile($runsToGoal, 0.90),
        'worst_observed_runs' => count($runsToGoal) > 0 ? max($runsToGoal) : null,
        'failure_reasons' => $failureReasons,
        'shortfall_totals' => $shortfallTotals,
      ];
    }

    return [
      'ok' => true,
      'generated_at' => gmdate('c'),
      'mode' => 'progression',
      'region' => $regionSlug,
      'config' => [
        'samples' => max(1, count($samples)),
        'max_runs' => $maxRuns,
        'profile' => $profile,
        'goals' => $goals,
      ],
      'assumptions' => [
        'strategy' => 'fresh starter account repeatedly runs the representative mini-path for the selected region',
        'profile_fixture' => $profile,
        'run_model' => ['combat', 'loot', 'hazard', 'shrine', 'boss'],
        'goal_requirements' => array_combine($goals, array_map(fn(string $goal): array => $this->progressionGoalRequirements($goal), $goals)),
      ],
      'summary' => $goalSummaries,
      'samples' => $samples,
    ];
  }

  /**
   * @return array<string,int>
   */
  private function progressionGoalRequirements(string $goal): array
  {
    $requirements = [];
    foreach (self::PROGRESSION_GOALS[$goal] ?? [] as $key => $value) {
      if (is_int($value)) {
        $requirements[$key] = $value;
      }
    }

    return $requirements;
  }

  /**
   * @param array<string,mixed> $log
   * @return array{remaining_pct:float,defeats:int}
   */
  private function playerHpSummary(array $log): array
  {
    $participants = $log['meta']['participants']['player'] ?? [];
    if (!is_array($participants) || count($participants) === 0) {
      return ['remaining_pct' => 1.0, 'defeats' => 0];
    }

    $hpByUnit = [];
    $maxTotal = 0;
    foreach ($participants as $participant) {
      if (!is_array($participant)) {
        continue;
      }
      $unitId = (string)($participant['unit_instance_id'] ?? '');
      if ($unitId === '') {
        continue;
      }

      $maxHp = max(1, (int)($participant['max_hp'] ?? 1));
      $currentHp = max(0, (int)($participant['current_hp'] ?? $maxHp));
      $hpByUnit[$unitId] = $currentHp;
      $maxTotal += $maxHp;
    }

    $events = $log['events'] ?? [];
    if (is_array($events)) {
      foreach ($events as $event) {
        if (!is_array($event) || !isset($event['target_unit_instance_id'], $event['target_hp_after'])) {
          continue;
        }
        $unitId = (string)$event['target_unit_instance_id'];
        if (array_key_exists($unitId, $hpByUnit)) {
          $hpByUnit[$unitId] = max(0, (int)$event['target_hp_after']);
        }
      }
    }

    $remaining = array_sum($hpByUnit);
    $defeats = count(array_filter($hpByUnit, static fn(int $hp): bool => $hp <= 0));

    return [
      'remaining_pct' => $maxTotal > 0 ? round($remaining / $maxTotal, 4) : 1.0,
      'defeats' => $defeats,
    ];
  }

  /**
   * @param array<string,mixed> $rewards
   * @return array<string,int>
   */
  private function extractItemQuantities(array $rewards): array
  {
    $items = [];
    foreach (['item_grants', 'items', 'region_items'] as $key) {
      $rows = $rewards[$key] ?? [];
      if (!is_array($rows)) {
        continue;
      }

      foreach ($rows as $row) {
        if (!is_array($row)) {
          continue;
        }
        $slug = (string)($row['item_slug'] ?? $row['region_item_slug'] ?? $row['slug'] ?? '');
        if ($slug === '') {
          continue;
        }
        $items[$slug] = ($items[$slug] ?? 0) + max(0, (int)($row['quantity'] ?? 1));
      }
    }

    ksort($items);
    return $items;
  }

  /**
   * @param array<string,mixed> $rewards
   */
  private function extractRawChaos(array $rewards): int
  {
    $total = max(0, (int)($rewards['raw_chaos_awarded'] ?? 0));
    $chaosBonus = is_array($rewards['chaos_bonus'] ?? null) ? $rewards['chaos_bonus'] : [];
    $currency = is_array($chaosBonus['currency'] ?? null) ? $chaosBonus['currency'] : [];
    return $total + max(0, (int)($currency['raw_chaos'] ?? 0));
  }

  /**
   * @param array<int,array<string,mixed>> $samples
   * @param array<string,mixed> $config
   * @return array<string,mixed>
   */
  private function buildReport(string $mode, string $regionSlug, array $config, array $samples): array
  {
    $nodeSummaries = [];
    foreach ($samples as $sample) {
      foreach ((array)($sample['nodes'] ?? []) as $node) {
        if (is_array($node)) {
          $nodeSummaries[] = $node;
        }
      }
    }

    $completed = count(array_filter($samples, static fn(array $sample): bool => !empty($sample['completed'])));
    $sampleCount = max(1, count($samples));
    $rounds = array_map(static fn(array $node): int => (int)$node['rounds'], $nodeSummaries);
    $hpRemaining = array_map(static fn(array $node): float => (float)$node['hp_remaining_pct'], $nodeSummaries);
    $wins = count(array_filter($nodeSummaries, static fn(array $node): bool => (string)$node['outcome'] === 'victory'));
    $currencySoft = array_sum(array_map(static fn(array $node): int => (int)$node['currency_soft'], $nodeSummaries));
    $xpTotal = array_sum(array_map(static fn(array $node): int => (int)$node['xp_total'], $nodeSummaries));
    $unitDefeats = array_sum(array_map(static fn(array $node): int => (int)$node['unit_defeats'], $nodeSummaries));
    $diceGrants = array_sum(array_map(static fn(array $node): int => (int)$node['dice_grants'], $nodeSummaries));
    $unitGrants = array_sum(array_map(static fn(array $node): int => (int)$node['unit_grants'], $nodeSummaries));

    $itemTotals = [];
    foreach ($nodeSummaries as $node) {
      foreach ((array)($node['item_quantities'] ?? []) as $slug => $quantity) {
        $itemTotals[(string)$slug] = ($itemTotals[(string)$slug] ?? 0) + (int)$quantity;
      }
    }
    ksort($itemTotals);

    return [
      'ok' => true,
      'generated_at' => gmdate('c'),
      'mode' => $mode,
      'region' => $regionSlug,
      'config' => [
        ...$config,
        'samples' => $sampleCount,
      ],
      'summary' => [
        'completion_rate' => round($completed / $sampleCount, 4),
        'node_win_rate' => count($nodeSummaries) > 0 ? round($wins / count($nodeSummaries), 4) : 0.0,
        'average_nodes_resolved' => round(array_sum(array_map(static fn(array $sample): int => (int)$sample['nodes_resolved'], $samples)) / $sampleCount, 2),
        'average_rounds' => $this->average($rounds),
        'rounds_p50' => $this->percentile($rounds, 0.50),
        'rounds_p90' => $this->percentile($rounds, 0.90),
        'average_hp_remaining_pct' => $this->average($hpRemaining),
        'unit_defeats_per_sample' => round($unitDefeats / $sampleCount, 2),
        'xp_total_per_sample' => round($xpTotal / $sampleCount, 2),
        'soft_currency_per_sample' => round($currencySoft / $sampleCount, 2),
        'dice_grants_per_sample' => round($diceGrants / $sampleCount, 2),
        'unit_grants_per_sample' => round($unitGrants / $sampleCount, 2),
        'item_totals' => $itemTotals,
      ],
      'samples' => $samples,
    ];
  }

  /** @param list<int|float> $values */
  private function average(array $values): float
  {
    if (count($values) === 0) {
      return 0.0;
    }

    return round(array_sum($values) / count($values), 4);
  }

  /** @param list<int|float> $values */
  private function percentile(array $values, float $percentile): float
  {
    if (count($values) === 0) {
      return 0.0;
    }

    sort($values);
    $index = (int)ceil((count($values) * $percentile)) - 1;
    $index = max(0, min(count($values) - 1, $index));
    return round((float)$values[$index], 4);
  }

  /**
   * @param array<string,mixed> $options
   */
  private function stringOption(array $options, string $key, string $default): string
  {
    $value = trim((string)($options[$key] ?? $default));
    return $value !== '' ? $value : $default;
  }

  /**
   * @param array<string,mixed> $options
   */
  private function intOption(array $options, string $key, int $default, int $min, int $max): int
  {
    $value = (int)($options[$key] ?? $default);
    return max($min, min($max, $value));
  }

  /**
   * @param array<string,mixed> $options
   */
  private function boolOption(array $options, string $key, bool $default): bool
  {
    if (!array_key_exists($key, $options)) {
      return $default;
    }

    $value = $options[$key];
    if (is_bool($value)) {
      return $value;
    }

    $normalized = strtolower(trim((string)$value));
    if (in_array($normalized, ['1', 'true', 'yes', 'on'], true)) {
      return true;
    }
    if (in_array($normalized, ['0', 'false', 'no', 'off'], true)) {
      return false;
    }

    return $default;
  }

  /**
   * @param array<string,mixed> $report
   * @return array<string,mixed>
   */
  private function withoutSamples(array $report): array
  {
    $config = is_array($report['config'] ?? null) ? $report['config'] : [];
    $report['config'] = [
      ...$config,
      'summary_only' => true,
    ];
    unset($report['samples']);

    return $report;
  }

  private function deleteSimulationUser(int $userId): void
  {
    $this->execDelete("DELETE br FROM `battle_rewards` br JOIN `battles` b ON b.`id` = br.`battle_id` WHERE b.`user_id` = ?", [$userId]);
    $this->execDelete("DELETE bl FROM `battle_logs` bl JOIN `battles` b ON b.`id` = bl.`battle_id` WHERE b.`user_id` = ?", [$userId]);
    $this->execDelete('DELETE FROM `battles` WHERE `user_id` = ?', [$userId]);
    $this->execDelete("DELETE re FROM `run_edges` re JOIN `region_runs` rr ON rr.`id` = re.`run_id` WHERE rr.`user_id` = ?", [$userId]);
    $this->execDelete("DELETE rus FROM `run_unit_state` rus JOIN `region_runs` rr ON rr.`id` = rus.`run_id` WHERE rr.`user_id` = ?", [$userId]);
    $this->execDelete("DELETE rn FROM `run_nodes` rn JOIN `region_runs` rr ON rr.`id` = rn.`run_id` WHERE rr.`user_id` = ?", [$userId]);
    $this->execDelete('DELETE FROM `region_runs` WHERE `user_id` = ?', [$userId]);
    $this->execDelete("DELETE tf FROM `team_formation` tf JOIN `teams` t ON t.`id` = tf.`team_id` WHERE t.`user_id` = ?", [$userId]);
    $this->execDelete("DELETE tu FROM `team_units` tu JOIN `teams` t ON t.`id` = tu.`team_id` WHERE t.`user_id` = ?", [$userId]);
    $this->execDelete("DELETE uad FROM `unit_ability_dice` uad JOIN `unit_instances` ui ON ui.`id` = uad.`unit_instance_id` WHERE ui.`user_id` = ?", [$userId]);
    $this->execDelete("DELETE uea FROM `unit_instance_equipped_abilities` uea JOIN `unit_instances` ui ON ui.`id` = uea.`unit_instance_id` WHERE ui.`user_id` = ?", [$userId]);
    $this->execDelete("DELETE uua FROM `unit_instance_unlocked_abilities` uua JOIN `unit_instances` ui ON ui.`id` = uua.`unit_instance_id` WHERE ui.`user_id` = ?", [$userId]);
    $this->execDelete("DELETE dia FROM `dice_instance_affixes` dia JOIN `dice_instances` di ON di.`id` = dia.`dice_instance_id` WHERE di.`user_id` = ?", [$userId]);
    $this->execDelete('DELETE FROM `shop_daily_deals` WHERE `user_id` = ?', [$userId]);
    $this->execDelete('DELETE FROM `user_bounties` WHERE `user_id` = ?', [$userId]);
    $this->execDelete('DELETE FROM `user_grants` WHERE `user_id` = ?', [$userId]);
    $this->execDelete('DELETE FROM `user_unlocks` WHERE `user_id` = ?', [$userId]);
    $this->execDelete('DELETE FROM `unit_promotions` WHERE `user_id` = ?', [$userId]);
    $this->execDelete('DELETE FROM `user_items` WHERE `user_id` = ?', [$userId]);
    $this->execDelete('DELETE FROM `user_region_items` WHERE `user_id` = ?', [$userId]);
    $this->execDelete('DELETE FROM `region_unlocks` WHERE `user_id` = ?', [$userId]);
    $this->execDelete('DELETE FROM `dice_instances` WHERE `user_id` = ?', [$userId]);
    $this->execDelete('DELETE FROM `unit_instances` WHERE `user_id` = ?', [$userId]);
    $this->execDelete('DELETE FROM `teams` WHERE `user_id` = ?', [$userId]);
    $this->execDelete('DELETE FROM `energy_state` WHERE `user_id` = ?', [$userId]);
    $this->execDelete('DELETE FROM `player_state` WHERE `user_id` = ?', [$userId]);
    $this->execDelete('DELETE FROM `users` WHERE `id` = ?', [$userId]);
  }

  /** @param list<mixed> $params */
  private function execDelete(string $sql, array $params): void
  {
    try {
      $stmt = $this->pdo->prepare($sql);
      $stmt->execute($params);
    } catch (Throwable) {
      // Cleanup is best-effort so schema drift in optional tables does not hide the real simulation result.
    }
  }
}
