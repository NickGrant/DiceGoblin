<?php
declare(strict_types=1);

/**
 * File: C:\xampp\htdocs\dice-goblin\backend\src\Combat\Engine\DeterministicRunNodeResolver.php
 * Purpose: Project PHP module.
 */

namespace DiceGoblins\Combat\Engine;

use DiceGoblins\Combat\Abilities\AbilityRegistry;
use DiceGoblins\Combat\Abilities\AbilityType;
use PDO;
use RuntimeException;

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

    if (($nodeType === 'combat' || $nodeType === 'boss') && count($enemyUnits) === 0) {
      throw new RuntimeException('combat_no_enemies');
    }

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

      $combatResult = $this->buildCombatEvents(
        $rngState,
        $rounds,
        $ticksPerRound,
        $playerUnits,
        $enemyUnits,
        $playerPower,
        $enemyPower
      );
      $events = $combatResult['events'];

      $simulatedOutcome = null;
      if ($combatResult['enemy_alive'] === false && $combatResult['player_alive'] === true) {
        $simulatedOutcome = 'victory';
      } elseif ($combatResult['player_alive'] === false && $combatResult['enemy_alive'] === true) {
        $simulatedOutcome = 'defeat';
      }
      if ($simulatedOutcome !== null) {
        $outcome = $simulatedOutcome;
      }

      $rounds = max(1, (int)$combatResult['ended_round']);
      $ticks = max(1, (int)$combatResult['ended_tick']);
      $xpTotal = $this->computeXpTotal($enemyUnits, $difficulty, $outcome);
      $currencySoft = $outcome === 'victory' ? (3 * $difficulty) + $this->nextInt($rngState, 5) : 0;

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
              'pos' => [
                'x' => (int)$u['pos']['x'],
                'y' => (int)$u['pos']['y'],
              ],
              'attack' => (int)$u['attack'],
              'defense' => (int)$u['defense'],
              'max_hp' => (int)$u['max_hp'],
              'abilities' => $u['abilities'],
            ], $playerUnits),
            'enemy' => array_map(static fn(array $u): array => [
              'slug' => (string)$u['id'],
              'pos' => [
                'x' => (int)$u['pos']['x'],
                'y' => (int)$u['pos']['y'],
              ],
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
   * @return array<int, array{
   *   id:string,
   *   pos:array{x:int,y:int},
   *   attack:int,
   *   defense:int,
   *   max_hp:int,
   *   abilities:array<int,string>,
   *   dice_pool:array<int,array{kind:string,dice_instance_id:?string,sides:int}>
   * }>
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
        ut.`max_hp_per_level`,
        tf.`cell` AS `formation_cell`
      FROM `team_units` tu
      JOIN `unit_instances` ui ON ui.`id` = tu.`unit_instance_id`
      JOIN `unit_types` ut ON ut.`id` = ui.`unit_type_id`
      LEFT JOIN `team_formation` tf
        ON tf.`team_id` = tu.`team_id`
       AND tf.`unit_instance_id` = ui.`id`
      WHERE tu.`team_id` = ? AND ui.`user_id` = ?
      ORDER BY ui.`id` ASC
    ');
    $stmt->execute([$teamId, $userId]);

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $units = [];
    $fallbackIndex = 0;

    foreach ($rows as $row) {
      $baseStats = $this->decodeJsonObject($row['base_stats_json']);
      $abilitySet = $this->decodeJsonObject($row['ability_set_json']);
      $level = max(1, (int)$row['level']);
      $levelScale = $level - 1;

      $attack = max(1, (int)($baseStats['attack'] ?? 1) + ((int)$row['attack_per_level'] * $levelScale));
      $defense = max(0, (int)($baseStats['defense'] ?? 0) + ((int)$row['defense_per_level'] * $levelScale));
      $maxHp = max(1, (int)($baseStats['max_hp'] ?? 1) + ((int)$row['max_hp_per_level'] * $levelScale));
      $pos = $this->cellToPos((string)($row['formation_cell'] ?? ''));
      if (!is_array($pos)) {
        $pos = $this->defaultPosForIndex($fallbackIndex);
      }
      $fallbackIndex++;

      $units[] = [
        'id' => (string)$row['unit_instance_id'],
        'pos' => $pos,
        'attack' => $attack,
        'defense' => $defense,
        'max_hp' => $maxHp,
        'abilities' => $this->flattenActiveAbilityIds($abilitySet),
        'dice_pool' => [],
      ];
    }

    if (count($units) === 0) {
      return $units;
    }

    $unitIds = array_map(static fn(array $u): int => (int)$u['id'], $units);
    $placeholders = implode(',', array_fill(0, count($unitIds), '?'));
    $diceStmt = $this->pdo->prepare("\n      SELECT\n        ud.`unit_instance_id`,\n        ud.`dice_instance_id`,\n        dd.`sides`\n      FROM `unit_dice` ud\n      JOIN `dice_instances` di ON di.`id` = ud.`dice_instance_id`\n      JOIN `dice_definitions` dd ON dd.`id` = di.`dice_definition_id`\n      WHERE ud.`unit_instance_id` IN ($placeholders)\n      ORDER BY ud.`unit_instance_id` ASC, ud.`slot_index` ASC\n    ");
    $diceStmt->execute($unitIds);

    $diceByUnitId = [];
    foreach ($diceStmt->fetchAll(PDO::FETCH_ASSOC) as $diceRow) {
      $unitId = (string)$diceRow['unit_instance_id'];
      $diceByUnitId[$unitId] ??= [];
      $diceByUnitId[$unitId][] = [
        'kind' => 'unit',
        'dice_instance_id' => (string)$diceRow['dice_instance_id'],
        'sides' => max(2, (int)$diceRow['sides']),
      ];
    }

    foreach ($units as &$unit) {
      $unitId = (string)$unit['id'];
      $unit['dice_pool'] = $diceByUnitId[$unitId] ?? [[
        'kind' => 'fallback',
        'dice_instance_id' => null,
        'sides' => 6,
      ]];
    }
    unset($unit);

    return $units;
  }

  /**
    * @return array{difficulty_rating:int,units:array<int,array{id:string,pos:array{x:int,y:int},attack:int,defense:int,max_hp:int,abilities:array<int,string>,dice_pool:array<int,array{kind:string,dice_instance_id:?string,sides:int}>,xp_reward:int}>}
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
    $enemyEntries = [];
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
            $pos = $this->normalizeEncounterPos($unit['pos'] ?? null);
            $enemyEntries[] = [
              'slug' => $slug,
              'pos' => $pos,
            ];
          }
        }
      }
    }

    if (count($enemyEntries) === 0) {
      return [
        'difficulty_rating' => (int)$template['difficulty_rating'],
        'units' => [],
      ];
    }

    $slugs = array_map(static fn(array $entry): string => (string)$entry['slug'], $enemyEntries);
    $uniqueSlugs = array_values(array_unique($slugs));
    $placeholders = implode(',', array_fill(0, count($uniqueSlugs), '?'));

    $stmt = $this->pdo->prepare("\n      SELECT `slug`, `base_stats_json`, `ability_set_json`, `xp_reward`\n      FROM `enemy_templates`\n      WHERE `slug` IN ($placeholders)\n    ");
    $stmt->execute($uniqueSlugs);

    $enemyBySlug = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
      $enemyBySlug[(string)$row['slug']] = $row;
    }

    $units = [];
    $slugCounts = [];
    foreach ($enemyEntries as $entry) {
      $slug = (string)($entry['slug'] ?? '');
      $row = $enemyBySlug[$slug] ?? null;
      if (!is_array($row)) {
        continue;
      }

      $slugCount = (int)($slugCounts[$slug] ?? 0) + 1;
      $slugCounts[$slug] = $slugCount;
      $instanceId = $slugCount > 1 ? ($slug . '#' . $slugCount) : $slug;
      $pos = $this->normalizeEncounterPos($entry['pos'] ?? null);

      $baseStats = $this->decodeJsonObject($row['base_stats_json']);
      $abilitySet = $this->decodeJsonObject($row['ability_set_json']);

      $units[] = [
        'id' => $instanceId,
        'pos' => $pos,
        'attack' => max(1, (int)($baseStats['attack'] ?? 1)),
        'defense' => max(0, (int)($baseStats['defense'] ?? 0)),
        'max_hp' => max(1, (int)($baseStats['max_hp'] ?? 1)),
        'abilities' => $this->flattenActiveAbilityIds($abilitySet),
        'dice_pool' => [[
          'kind' => 'enemy_virtual',
          'dice_instance_id' => null,
          'sides' => 6,
        ]],
        'xp_reward' => max(0, (int)$row['xp_reward']),
      ];
    }

    return [
      'difficulty_rating' => max(1, (int)$template['difficulty_rating']),
      'units' => $units,
    ];
  }

  /**
   * @return array{x:int,y:int}|null
   */
  private function cellToPos(string $cell): ?array
  {
    $value = strtoupper(trim($cell));
    if (!preg_match('/^[ABC][123]$/', $value)) {
      return null;
    }

    $x = ord($value[0]) - ord('A');
    $y = ((int)$value[1]) - 1;
    if ($x < 0 || $x > 2 || $y < 0 || $y > 2) {
      return null;
    }

    return ['x' => $x, 'y' => $y];
  }

  /**
   * @return array{x:int,y:int}
   */
  private function defaultPosForIndex(int $index): array
  {
    $positions = [
      ['x' => 0, 'y' => 0],
      ['x' => 1, 'y' => 0],
      ['x' => 2, 'y' => 0],
      ['x' => 0, 'y' => 1],
      ['x' => 1, 'y' => 1],
      ['x' => 2, 'y' => 1],
      ['x' => 0, 'y' => 2],
      ['x' => 1, 'y' => 2],
      ['x' => 2, 'y' => 2],
    ];

    $safeIndex = $index % count($positions);
    return $positions[$safeIndex] ?? ['x' => 1, 'y' => 1];
  }

  /**
   * @return array{x:int,y:int}
   */
  private function normalizeEncounterPos(mixed $rawPos): array
  {
    $default = ['x' => 1, 'y' => 1];
    if (!is_array($rawPos)) {
      return $default;
    }

    $xRaw = $rawPos['x'] ?? null;
    $yRaw = $rawPos['y'] ?? null;
    $x = is_numeric($xRaw) ? (int)$xRaw : 1;
    $y = is_numeric($yRaw) ? (int)$yRaw : 1;

    return [
      'x' => max(0, min(2, $x)),
      'y' => max(0, min(2, $y)),
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
   * @param array<int, array{id:string,attack:int,defense:int,max_hp:int,abilities:array<int,string>,dice_pool:array<int,array{kind:string,dice_instance_id:?string,sides:int}>}> $playerUnits
   * @param array<int, array{id:string,attack:int,defense:int,max_hp:int,abilities:array<int,string>,dice_pool:array<int,array{kind:string,dice_instance_id:?string,sides:int}>}> $enemyUnits
   * @return array{events:array<int, array<string,mixed>>,ended_round:int,ended_tick:int,player_alive:bool,enemy_alive:bool}
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
      return [
        'events' => $events,
        'ended_round' => 0,
        'ended_tick' => 0,
        'player_alive' => count($playerUnits) > 0,
        'enemy_alive' => count($enemyUnits) > 0,
      ];
    }

    $state = $rngState;
    $abilityRegistry = new AbilityRegistry();

    $playerHp = [];
    $playerById = [];
    $playerSchedules = [];
    foreach ($playerUnits as $unit) {
      $unitId = (string)$unit['id'];
      $playerHp[$unitId] = (int)$unit['max_hp'];
      $playerById[$unitId] = $unit;
      $playerSchedules[$unitId] = $this->buildActiveAbilitySchedule((array)$unit['abilities'], $abilityRegistry);
    }

    $enemyHp = [];
    $enemyById = [];
    $enemySchedules = [];
    foreach ($enemyUnits as $unit) {
      $unitId = (string)$unit['id'];
      $enemyHp[$unitId] = (int)$unit['max_hp'];
      $enemyById[$unitId] = $unit;
      $enemySchedules[$unitId] = $this->buildActiveAbilitySchedule((array)$unit['abilities'], $abilityRegistry);
    }

    $combatOver = false;
    $lastRound = 0;
    $lastTick = 0;

    for ($round = 1; $round <= $rounds; $round++) {
      $roundStartTick = (($round - 1) * $ticksPerRound) + 1;
      $events[] = [
        'type' => 'phase_start',
        'round' => $round,
        'tick' => $roundStartTick,
        'phase' => 'round_start',
      ];
      $lastRound = $round;
      $lastTick = $roundStartTick;

      for ($tickOffset = 1; $tickOffset <= $ticksPerRound; $tickOffset++) {
        if ($this->countLivingUnits($playerHp) === 0 || $this->countLivingUnits($enemyHp) === 0) {
          $combatOver = true;
          break;
        }

        $tick = $roundStartTick + ($tickOffset - 1);

        foreach ($playerUnits as $playerActor) {
          $playerActorId = (string)$playerActor['id'];
          if (($playerHp[$playerActorId] ?? 0) <= 0) {
            continue;
          }

          foreach ($playerSchedules[$playerActorId] ?? [] as $ability) {
            $speed = (int)$ability['speed'];
            if ($speed <= 0 || ($tickOffset % $speed) !== 0) {
              continue;
            }

            $aliveEnemyIds = $this->aliveUnitIds($enemyHp);
            if (count($aliveEnemyIds) === 0) {
              $combatOver = true;
              break 3;
            }

            $enemyTargetId = $aliveEnemyIds[$this->nextInt($state, count($aliveEnemyIds))];
            $enemyTarget = $enemyById[$enemyTargetId] ?? null;
            if (!is_array($enemyTarget)) {
              continue;
            }

            $abilityId = (string)$ability['ability_id'];
            $dice = $this->rollActionDice(
              $state,
              (array)($playerActor['dice_pool'] ?? []),
              $abilityId,
              'player'
            );
            $outcome = $this->deriveActionOutcome(
              $state,
              (int)$playerActor['attack'],
              (int)$enemyTarget['defense'],
              (int)($enemyHp[$enemyTargetId] ?? (int)$enemyTarget['max_hp']),
              $abilityId,
              (int)$dice['dice_modifier'],
              $abilityRegistry,
            );

            $events[] = [
              'type' => 'action',
              'round' => $round,
              'tick' => $tick,
              'side' => 'player',
              'actor_unit_instance_id' => $playerActorId,
              'target_enemy_slug' => $enemyTargetId,
              'ability_id' => $abilityId,
              'dice_used' => $dice['dice_used'],
              'dice_rolls' => $dice['dice_rolls'],
              'dice_outcome' => $dice['dice_outcome'],
              ...$outcome,
            ];
            $enemyHp[$enemyTargetId] = (int)$outcome['target_hp_after'];
            $lastRound = $round;
            $lastTick = $tick;
            if ($this->countLivingUnits($enemyHp) === 0) {
              $combatOver = true;
              break 3;
            }
          }
        }

        foreach ($enemyUnits as $enemyActor) {
          $enemyActorId = (string)$enemyActor['id'];
          if (($enemyHp[$enemyActorId] ?? 0) <= 0) {
            continue;
          }

          foreach ($enemySchedules[$enemyActorId] ?? [] as $ability) {
            $speed = (int)$ability['speed'];
            if ($speed <= 0 || ($tickOffset % $speed) !== 0) {
              continue;
            }

            $alivePlayerIds = $this->aliveUnitIds($playerHp);
            if (count($alivePlayerIds) === 0) {
              $combatOver = true;
              break 3;
            }

            $playerTargetId = $alivePlayerIds[$this->nextInt($state, count($alivePlayerIds))];
            $playerTarget = $playerById[$playerTargetId] ?? null;
            if (!is_array($playerTarget)) {
              continue;
            }

            $abilityId = (string)$ability['ability_id'];
            $dice = $this->rollActionDice(
              $state,
              (array)($enemyActor['dice_pool'] ?? []),
              $abilityId,
              'enemy'
            );
            $outcome = $this->deriveActionOutcome(
              $state,
              (int)$enemyActor['attack'],
              (int)$playerTarget['defense'],
              (int)($playerHp[$playerTargetId] ?? (int)$playerTarget['max_hp']),
              $abilityId,
              (int)$dice['dice_modifier'],
              $abilityRegistry,
            );

            $events[] = [
              'type' => 'action',
              'round' => $round,
              'tick' => $tick,
              'side' => 'enemy',
              'actor_enemy_slug' => $enemyActorId,
              'target_unit_instance_id' => $playerTargetId,
              'ability_id' => $abilityId,
              'dice_used' => $dice['dice_used'],
              'dice_rolls' => $dice['dice_rolls'],
              'dice_outcome' => $dice['dice_outcome'],
              ...$outcome,
            ];
            $playerHp[$playerTargetId] = (int)$outcome['target_hp_after'];
            $lastRound = $round;
            $lastTick = $tick;
            if ($this->countLivingUnits($playerHp) === 0) {
              $combatOver = true;
              break 3;
            }
          }
        }
      }

      if ($combatOver) {
        break;
      }
    }

    return [
      'events' => $events,
      'ended_round' => $lastRound,
      'ended_tick' => $lastTick,
      'player_alive' => $this->countLivingUnits($playerHp) > 0,
      'enemy_alive' => $this->countLivingUnits($enemyHp) > 0,
    ];
  }

  /**
   * @param array<int,string> $abilityIds
   * @return array<int,array{ability_id:string,speed:int}>
   */
  private function buildActiveAbilitySchedule(array $abilityIds, AbilityRegistry $registry): array
  {
    $scheduleById = [];
    foreach ($abilityIds as $abilityId) {
      $id = trim((string)$abilityId);
      if ($id === '' || !$registry->has($id)) {
        continue;
      }

      $def = $registry->get($id);
      if ($def->type !== AbilityType::Active || $def->speed === null) {
        continue;
      }

      $scheduleById[$id] = [
        'ability_id' => $id,
        'speed' => (int)$def->speed,
      ];
    }

    if (count($scheduleById) === 0) {
      $scheduleById['basic_attack_melee'] = [
        'ability_id' => 'basic_attack_melee',
        'speed' => 4,
      ];
    }

    $schedule = array_values($scheduleById);
    usort($schedule, static function (array $a, array $b): int {
      $speedCmp = ((int)$a['speed']) <=> ((int)$b['speed']);
      if ($speedCmp !== 0) {
        return $speedCmp;
      }

      return strcmp((string)$a['ability_id'], (string)$b['ability_id']);
    });

    return $schedule;
  }

  /**
   * @param array<string,int> $hpByUnitId
   * @return array<int,string>
   */
  private function aliveUnitIds(array $hpByUnitId): array
  {
    $alive = [];
    foreach ($hpByUnitId as $unitId => $hp) {
      if ((int)$hp > 0) {
        $alive[] = (string)$unitId;
      }
    }

    return $alive;
  }

  /**
   * @param array<string,int> $hpByUnitId
   */
  private function countLivingUnits(array $hpByUnitId): int
  {
    $count = 0;
    foreach ($hpByUnitId as $hp) {
      if ((int)$hp > 0) {
        $count++;
      }
    }

    return $count;
  }

  /**
   * @return array{damage:int,target_hp_after:int,outcome:string,status_applied:?string,status_duration_rounds:?int,ability_outcome:string}
   */
  private function deriveActionOutcome(
    string &$state,
    int $attackerAttack,
    int $targetDefense,
    int $targetHp,
    string $abilityId,
    int $diceModifier,
    AbilityRegistry $abilityRegistry,
  ): array {
    $variance = $this->nextInt($state, 5) - 2;
    $rawDamage = (int)floor(($attackerAttack * 0.65) - ($targetDefense * 0.35)) + $variance + $diceModifier;
    $damage = max(1, $rawDamage);
    $nextHp = max(0, $targetHp - $damage);
    $status = $this->pickStatusEffect($state, $abilityId);
    $statusDuration = $this->deriveStatusDurationRounds($abilityRegistry, $abilityId, $status);
    $outcome = $nextHp <= 0 ? 'defeated' : 'hit';

    $abilityOutcomeParts = [sprintf('%d damage dealt', $damage)];
    if ($status !== null) {
      if ($statusDuration !== null) {
        $abilityOutcomeParts[] = sprintf('%s applied for %d rounds', $status, $statusDuration);
      } else {
        $abilityOutcomeParts[] = sprintf('%s applied', $status);
      }
    }
    if ($outcome === 'defeated') {
      $abilityOutcomeParts[] = 'target defeated';
    }

    return [
      'damage' => $damage,
      'target_hp_after' => $nextHp,
      'outcome' => $outcome,
      'status_applied' => $status,
      'status_duration_rounds' => $statusDuration,
      'ability_outcome' => implode(', ', $abilityOutcomeParts),
    ];
  }

  /**
   * @param array<int,array{kind:string,dice_instance_id:?string,sides:int}> $dicePool
   * @return array{
   *   dice_used:array<int,array{kind:string,dice_instance_id:?string,sides:int}>,
   *   dice_rolls:array<int,array{sides:int,roll:int}>,
   *   dice_outcome:string,
   *   dice_modifier:int
   * }
   */
  private function rollActionDice(string &$state, array $dicePool, string $abilityId, string $side): array
  {
    $pool = $dicePool;
    if (count($pool) === 0) {
      $pool = [[
        'kind' => $side === 'enemy' ? 'enemy_virtual' : 'fallback',
        'dice_instance_id' => null,
        'sides' => 6,
      ]];
    }

    $die = $pool[$this->nextInt($state, count($pool))];
    $sides = max(2, (int)($die['sides'] ?? 6));
    $roll = 1 + $this->nextInt($state, $sides);

    $diceUsed = [[
      'kind' => (string)($die['kind'] ?? 'unknown'),
      'dice_instance_id' => isset($die['dice_instance_id']) && $die['dice_instance_id'] !== '' ? (string)$die['dice_instance_id'] : null,
      'sides' => $sides,
    ]];

    $diceLabel = $diceUsed[0]['dice_instance_id'] !== null
      ? sprintf('dice#%s', $diceUsed[0]['dice_instance_id'])
      : sprintf('%s_%s_die', $side, $abilityId);

    return [
      'dice_used' => $diceUsed,
      'dice_rolls' => [[
        'sides' => $sides,
        'roll' => $roll,
      ]],
      'dice_outcome' => sprintf('%s rolled d%d = %d', $diceLabel, $sides, $roll),
      'dice_modifier' => $roll - (int)ceil($sides / 2),
    ];
  }

  private function deriveStatusDurationRounds(AbilityRegistry $abilityRegistry, string $abilityId, ?string $status): ?int
  {
    if ($status === null) {
      return null;
    }

    if ($abilityRegistry->has($abilityId)) {
      $def = $abilityRegistry->get($abilityId);
      $duration = $def->defaultParams['duration_rounds'] ?? null;
      if (is_int($duration) && $duration > 0) {
        return $duration;
      }
      if (is_numeric($duration) && (int)$duration > 0) {
        return (int)$duration;
      }
    }

    return match ($status) {
      'sleep' => 2,
      'poison' => 3,
      'bleed' => 2,
      'guard_up' => 2,
      default => null,
    };
  }

  private function pickStatusEffect(string &$state, string $abilityId): ?string
  {
    $ability = strtolower($abilityId);
    if (str_contains($ability, 'poison')) {
      return 'poison';
    }
    if (str_contains($ability, 'sleep')) {
      return 'sleep';
    }
    if (str_contains($ability, 'shield')) {
      return 'guard_up';
    }

    // Low deterministic chance to apply bleed on generic attacks.
    if ($this->nextInt($state, 10) === 0) {
      return 'bleed';
    }

    return null;
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
  private function flattenActiveAbilityIds(array $abilitySet): array
  {
    $out = [];

    $bucket = $abilitySet['actives'] ?? [];
    if (is_array($bucket)) {
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
