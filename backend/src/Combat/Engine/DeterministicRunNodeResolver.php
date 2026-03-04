<?php
declare(strict_types=1);

namespace DiceGoblins\Combat\Engine;

use PDO;

final class DeterministicRunNodeResolver
{
  public function __construct(private readonly PDO $pdo)
  {
  }

  /**
   * @param array{id:string,seed:string} $run
   * @param array{id:string,node_type:string,encounter_template_id:?string} $node
   * @return array{
   *   seed:int,
   *   outcome:string,
   *   rounds:int,
   *   ticks:int,
   *   xp_total:int,
   *   currency_soft:int,
   *   rewards:array<string,mixed>,
   *   log:array<string,mixed>
   * }
   */
  public function resolve(int $userId, int $teamId, array $run, array $node): array
  {
    $runId = (int)$run['id'];
    $nodeId = (int)$node['id'];
    $nodeType = (string)$node['node_type'];
    $encounterTemplateId = $node['encounter_template_id'] !== null ? (int)$node['encounter_template_id'] : null;

    $playerUnits = $this->loadPlayerUnits($userId, $teamId);
    $encounter = $this->loadEncounter($encounterTemplateId);
    $enemyUnits = $encounter['units'];

    ['seed' => $seed, 'rng_state' => $rngState] = $this->deriveSeedContext(
      $userId,
      $runId,
      (string)$run['seed'],
      $nodeId,
      $teamId,
      $encounterTemplateId
    );
    $ticksPerRound = 20;

    if ($nodeType === 'rest' || $nodeType === 'loot') {
      $rounds = 0;
      $ticks = 0;
      $outcome = 'victory';
      $xpTotal = 0;
      $currencySoft = $nodeType === 'loot' ? 5 : 0;
      $events = [[
        'type' => 'node_effect',
        'round' => 0,
        'tick' => 0,
        'node_type' => $nodeType,
        'message' => 'non_combat_resolution',
      ]];
    } else {
      $difficulty = max(1, (int)$encounter['difficulty_rating']);
      $rounds = 3 + $this->nextInt($rngState, 3); // 3-5 rounds
      $ticks = $rounds * $ticksPerRound;

      $playerPower = $this->sumPower($playerUnits);
      $enemyPower = $this->sumPower($enemyUnits) * (1.0 + (($difficulty - 1) * 0.07));

      // Deterministic variance avoids fixed outcomes with near-equal power.
      $variance = $this->nextInt($rngState, 21) - 10;
      $score = ($playerPower - $enemyPower) + ($variance * 0.4);
      $outcome = $score >= 0.0 ? 'victory' : 'defeat';

      $xpTotal = $this->computeXpTotal($enemyUnits, $difficulty, $outcome);
      $currencySoft = $outcome === 'victory' ? (3 * $difficulty) + $this->nextInt($rngState, 5) : 0;

      $events = $this->buildCombatEvents(
        $rngState,
        $rounds,
        $ticksPerRound,
        $playerUnits,
        $enemyUnits,
        $playerPower,
        $enemyPower
      );
      $events[] = [
        'type' => 'battle_end',
        'round' => $rounds,
        'tick' => $ticks,
        'outcome' => $outcome,
      ];
    }

    $rewards = [
      'new_dice_instance_ids' => [],
      'region_items' => [],
    ];

    return [
      'seed' => $seed,
      'outcome' => $outcome,
      'rounds' => $rounds,
      'ticks' => $ticks,
      'xp_total' => $xpTotal,
      'currency_soft' => $currencySoft,
      'rewards' => $rewards,
      'log' => [
        'meta' => [
          'ticksPerRound' => $ticksPerRound,
          'rng' => ['seed' => $seed],
          'seed_key_version' => 'v2',
          'createdAtIso' => gmdate('c'),
          'version' => 1,
          'engine' => 'deterministic_v1',
          'run_id' => $runId,
          'node_id' => $nodeId,
          'node_type' => $nodeType,
          'encounter_template_id' => $encounterTemplateId,
          'difficulty_rating' => (int)$encounter['difficulty_rating'],
          'participants' => [
            'player' => array_map(static fn(array $u): array => [
              'unit_instance_id' => (string)$u['id'],
              'attack' => (int)$u['attack'],
              'defense' => (int)$u['defense'],
              'max_hp' => (int)$u['max_hp'],
              'abilities' => $u['abilities'],
            ], $playerUnits),
            'enemy' => array_map(static fn(array $u): array => [
              'slug' => (string)$u['id'],
              'attack' => (int)$u['attack'],
              'defense' => (int)$u['defense'],
              'max_hp' => (int)$u['max_hp'],
              'abilities' => $u['abilities'],
            ], $enemyUnits),
          ],
        ],
        'events' => $events,
      ],
    ];
  }

  /**
   * @return array<int, array{id:string,attack:int,defense:int,max_hp:int,abilities:array<int,string>}>
   */
  private function loadPlayerUnits(int $userId, int $teamId): array
  {
    $stmt = $this->pdo->prepare('
      SELECT
        ui.`id` AS unit_instance_id,
        ui.`level`,
        ut.`base_stats_json`,
        ut.`ability_set_json`,
        ut.`attack_per_level`,
        ut.`defense_per_level`,
        ut.`max_hp_per_level`
      FROM `team_units` tu
      JOIN `unit_instances` ui ON ui.`id` = tu.`unit_instance_id`
      JOIN `unit_types` ut ON ut.`id` = ui.`unit_type_id`
      WHERE tu.`team_id` = ? AND ui.`user_id` = ?
      ORDER BY ui.`id` ASC
    ');
    $stmt->execute([$teamId, $userId]);

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $units = [];

    foreach ($rows as $row) {
      $baseStats = $this->decodeJsonObject($row['base_stats_json']);
      $abilitySet = $this->decodeJsonObject($row['ability_set_json']);
      $level = max(1, (int)$row['level']);
      $levelScale = $level - 1;

      $attack = max(1, (int)($baseStats['attack'] ?? 1) + ((int)$row['attack_per_level'] * $levelScale));
      $defense = max(0, (int)($baseStats['defense'] ?? 0) + ((int)$row['defense_per_level'] * $levelScale));
      $maxHp = max(1, (int)($baseStats['max_hp'] ?? 1) + ((int)$row['max_hp_per_level'] * $levelScale));

      $units[] = [
        'id' => (string)$row['unit_instance_id'],
        'attack' => $attack,
        'defense' => $defense,
        'max_hp' => $maxHp,
        'abilities' => $this->flattenAbilityIds($abilitySet),
      ];
    }

    return $units;
  }

  /**
   * @return array{difficulty_rating:int,units:array<int,array{id:string,attack:int,defense:int,max_hp:int,abilities:array<int,string>,xp_reward:int}>}
   */
  private function loadEncounter(?int $encounterTemplateId): array
  {
    if ($encounterTemplateId === null) {
      return [
        'difficulty_rating' => 1,
        'units' => [],
      ];
    }

    $stmt = $this->pdo->prepare('
      SELECT `difficulty_rating`, `enemy_set_json`
      FROM `encounter_templates`
      WHERE `id` = ?
      LIMIT 1
    ');
    $stmt->execute([$encounterTemplateId]);
    $template = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!is_array($template)) {
      return [
        'difficulty_rating' => 1,
        'units' => [],
      ];
    }

    $enemySet = $this->decodeJsonObject($template['enemy_set_json']);
    $slugs = [];
    $teams = $enemySet['teams'] ?? [];
    if (is_array($teams)) {
      foreach ($teams as $team) {
        if (!is_array($team)) {
          continue;
        }
        $units = $team['units'] ?? [];
        if (!is_array($units)) {
          continue;
        }
        foreach ($units as $unit) {
          if (!is_array($unit)) {
            continue;
          }
          $slug = (string)($unit['enemy_template_slug'] ?? '');
          if ($slug !== '') {
            $slugs[] = $slug;
          }
        }
      }
    }

    if (count($slugs) === 0) {
      return [
        'difficulty_rating' => (int)$template['difficulty_rating'],
        'units' => [],
      ];
    }

    $uniqueSlugs = array_values(array_unique($slugs));
    $placeholders = implode(',', array_fill(0, count($uniqueSlugs), '?'));

    $stmt = $this->pdo->prepare("\n      SELECT `slug`, `base_stats_json`, `ability_set_json`, `xp_reward`\n      FROM `enemy_templates`\n      WHERE `slug` IN ($placeholders)\n    ");
    $stmt->execute($uniqueSlugs);

    $enemyBySlug = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
      $enemyBySlug[(string)$row['slug']] = $row;
    }

    $units = [];
    foreach ($slugs as $slug) {
      $row = $enemyBySlug[$slug] ?? null;
      if (!is_array($row)) {
        continue;
      }

      $baseStats = $this->decodeJsonObject($row['base_stats_json']);
      $abilitySet = $this->decodeJsonObject($row['ability_set_json']);

      $units[] = [
        'id' => $slug,
        'attack' => max(1, (int)($baseStats['attack'] ?? 1)),
        'defense' => max(0, (int)($baseStats['defense'] ?? 0)),
        'max_hp' => max(1, (int)($baseStats['max_hp'] ?? 1)),
        'abilities' => $this->flattenAbilityIds($abilitySet),
        'xp_reward' => max(0, (int)$row['xp_reward']),
      ];
    }

    return [
      'difficulty_rating' => max(1, (int)$template['difficulty_rating']),
      'units' => $units,
    ];
  }

  /**
   * @param array<int, array{attack:int,defense:int,max_hp:int,abilities:array<int,string>}> $units
   */
  private function sumPower(array $units): float
  {
    $sum = 0.0;
    foreach ($units as $unit) {
      $sum += ((float)$unit['attack'] * 1.4)
        + ((float)$unit['defense'] * 1.1)
        + ((float)$unit['max_hp'] * 0.35)
        + ((float)count($unit['abilities']) * 1.25);
    }
    return $sum;
  }

  /**
   * @param array<int, array{xp_reward:int}> $enemyUnits
   */
  private function computeXpTotal(array $enemyUnits, int $difficulty, string $outcome): int
  {
    $base = 0;
    foreach ($enemyUnits as $enemy) {
      $base += max(0, (int)$enemy['xp_reward']);
    }

    if ($base <= 0) {
      $base = 10 * max(1, $difficulty);
    }

    if ($outcome === 'defeat') {
      return (int)max(0, floor($base * 0.25));
    }

    return $base;
  }

  /**
   * @param array<int, array{id:string,abilities:array<int,string>}> $playerUnits
   * @param array<int, array{id:string,abilities:array<int,string>}> $enemyUnits
   * @return array<int, array<string,mixed>>
   */
  private function buildCombatEvents(
    string $rngState,
    int $rounds,
    int $ticksPerRound,
    array $playerUnits,
    array $enemyUnits,
    float $playerPower,
    float $enemyPower,
  ): array {
    $events = [[
      'type' => 'battle_start',
      'round' => 0,
      'tick' => 0,
      'player_unit_count' => count($playerUnits),
      'enemy_unit_count' => count($enemyUnits),
      'player_power' => round($playerPower, 2),
      'enemy_power' => round($enemyPower, 2),
    ]];

    if (count($playerUnits) === 0 || count($enemyUnits) === 0) {
      return $events;
    }

    $state = $rngState;

    for ($round = 1; $round <= $rounds; $round++) {
      $roundStartTick = (($round - 1) * $ticksPerRound) + 1;
      $events[] = [
        'type' => 'phase_start',
        'round' => $round,
        'tick' => $roundStartTick,
        'phase' => 'round_start',
      ];

      $playerActor = $playerUnits[$this->nextInt($state, count($playerUnits))];
      $enemyTarget = $enemyUnits[$this->nextInt($state, count($enemyUnits))];
      $playerAbility = $this->pickAbility($state, $playerActor['abilities']);

      $events[] = [
        'type' => 'action',
        'round' => $round,
        'tick' => $roundStartTick + 4,
        'side' => 'player',
        'actor_unit_instance_id' => (string)$playerActor['id'],
        'target_enemy_slug' => (string)$enemyTarget['id'],
        'ability_id' => $playerAbility,
      ];

      $enemyActor = $enemyUnits[$this->nextInt($state, count($enemyUnits))];
      $playerTarget = $playerUnits[$this->nextInt($state, count($playerUnits))];
      $enemyAbility = $this->pickAbility($state, $enemyActor['abilities']);

      $events[] = [
        'type' => 'action',
        'round' => $round,
        'tick' => $roundStartTick + 11,
        'side' => 'enemy',
        'actor_enemy_slug' => (string)$enemyActor['id'],
        'target_unit_instance_id' => (string)$playerTarget['id'],
        'ability_id' => $enemyAbility,
      ];
    }

    return $events;
  }

  /**
   * @param array<int,string> $abilities
   */
  private function pickAbility(string &$state, array $abilities): string
  {
    if (count($abilities) === 0) {
      return 'basic_attack_melee';
    }

    return $abilities[$this->nextInt($state, count($abilities))];
  }

  private function nextInt(string &$state, int $maxExclusive): int
  {
    if ($maxExclusive <= 1) {
      return 0;
    }

    $state = hash('sha256', $state);
    $slice = substr($state, 0, 8);
    $value = (int)base_convert($slice, 16, 10);

    return $value % $maxExclusive;
  }

  /**
   * @return array{seed:int,rng_state:string}
   */
  private function deriveSeedContext(
    int $userId,
    int $runId,
    string $runSeed,
    int $nodeId,
    int $teamId,
    ?int $encounterTemplateId
  ): array {
    $seedKey = sprintf(
      'seed_v2|user:%d|run:%d|run_seed:%s|node:%d|team:%d|enc:%s',
      $userId,
      $runId,
      $runSeed,
      $nodeId,
      $teamId,
      $encounterTemplateId !== null ? (string)$encounterTemplateId : 'none'
    );

    $rngState = hash('sha256', $seedKey);

    // Use first 15 hex chars (60 bits) for stable positive seed material.
    $seedHex = substr($rngState, 0, 15);
    $seed = (int)base_convert($seedHex, 16, 10);
    if ($seed <= 0) {
      $seed = 1;
    }

    return [
      'seed' => $seed,
      'rng_state' => $rngState,
    ];
  }

  /**
   * @return array<string,mixed>
   */
  private function decodeJsonObject(mixed $raw): array
  {
    if (is_array($raw)) {
      return $raw;
    }

    if (is_string($raw)) {
      $decoded = json_decode($raw, true);
      return is_array($decoded) ? $decoded : [];
    }

    return [];
  }

  /**
   * @param array<string,mixed> $abilitySet
   * @return array<int,string>
   */
  private function flattenAbilityIds(array $abilitySet): array
  {
    $out = [];

    foreach (['actives', 'passives'] as $key) {
      $bucket = $abilitySet[$key] ?? [];
      if (!is_array($bucket)) {
        continue;
      }

      foreach ($bucket as $abilityId) {
        $id = trim((string)$abilityId);
        if ($id !== '') {
          $out[] = $id;
        }
      }
    }

    $out = array_values(array_unique($out));
    return $out;
  }
}
