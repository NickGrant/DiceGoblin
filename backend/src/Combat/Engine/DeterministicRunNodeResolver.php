<?php
declare(strict_types=1);

/**
 * File: C:\xampp\htdocs\dice-goblin\backend\src\Combat\Engine\DeterministicRunNodeResolver.php
 * Purpose: Project PHP module.
 */

namespace DiceGoblins\Combat\Engine;

use DiceGoblins\Combat\Abilities\AbilityRegistry;
use DiceGoblins\Combat\Abilities\AbilityType;
use DiceGoblins\Services\UnitProgressionService;
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

    $playerUnits = $this->loadPlayerUnits($userId, $teamId, $runId);
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
      'new_unit_instance_ids' => [],
      'region_items' => [],
      'dice_grants' => [],
      'unit_grants' => [],
    ];
    if ($nodeType === 'loot') {
      $lootTableSlug = isset($encounter['reward_profile']['loot_table_slug'])
        ? (string)$encounter['reward_profile']['loot_table_slug']
        : '';
      $rolls = isset($encounter['reward_profile']['rolls'])
        ? max(1, (int)$encounter['reward_profile']['rolls'])
        : 1;
      $rewards['loot_node'] = [
        'loot_table_slug' => $lootTableSlug,
        'rolls' => $rolls,
        'currency_soft' => $currencySoft,
      ];
    }

    if ($outcome === 'victory' && in_array($nodeType, ['combat', 'boss', 'loot'], true)) {
      $unitRoll = $this->nextInt($rngState, 100);
      $diceRoll = $this->nextInt($rngState, 100);
      $grantUnit = $nodeType === 'loot' ? ($unitRoll < 55) : ($unitRoll < 20);
      $grantDice = $nodeType === 'loot' ? ($diceRoll < 80) : ($diceRoll < 35);

      if ($nodeType === 'loot' && !$grantUnit && !$grantDice) {
        $grantDice = true;
      }

      if ($grantUnit) {
        $unitSlug = $this->pickUnitTypeSlug($rngState);
        if ($unitSlug !== null) {
          $rewards['unit_grants'][] = [
            'unit_type_slug' => $unitSlug,
            'tier' => 1,
            'level' => 1,
          ];
        }
      }

      if ($grantDice) {
        $diceSpec = $this->pickDiceDefinitionSpec($rngState);
        if ($diceSpec !== null) {
          $rewards['dice_grants'][] = [
            'rarity' => (string)$diceSpec['rarity'],
            'sides' => (int)$diceSpec['sides'],
          ];
        }
      }
    }

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
          'encounter_description' => (string)($encounter['description'] ?? ''),
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
              'current_hp' => (int)$u['current_hp'],
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
   *   current_hp:int,
   *   abilities:array<int,string>,
   *   combat_affixes:array{damage_flat:int,below_half_bonus:float},
   *   ability_dice:array<string,array<int,array{
   *     kind:string,
   *     dice_instance_id:?string,
   *     sides:int,
   *     affixes:array<int,array{slug:string,value:float}>
   *   }>>,
   *   passive_dice:array<int,array{
   *     kind:string,
   *     dice_instance_id:?string,
   *     sides:int,
   *     affixes:array<int,array{slug:string,value:float}>
   *   }>
   * }>
   */
  private function loadPlayerUnits(int $userId, int $teamId, int $runId): array
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
        ut.`max_equipped_dice`,
        tf.`cell` AS `formation_cell`,
        rus.`current_hp` AS `run_current_hp`
      FROM `team_units` tu
      JOIN `unit_instances` ui ON ui.`id` = tu.`unit_instance_id`
      JOIN `unit_types` ut ON ut.`id` = ui.`unit_type_id`
      LEFT JOIN `team_formation` tf
        ON tf.`team_id` = tu.`team_id`
       AND tf.`unit_instance_id` = ui.`id`
      LEFT JOIN `run_unit_state` rus
        ON rus.`run_id` = ?
       AND rus.`unit_instance_id` = ui.`id`
      WHERE tu.`team_id` = ? AND ui.`user_id` = ?
      ORDER BY ui.`id` ASC
    ');
    $stmt->execute([$runId, $teamId, $userId]);

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $units = [];
    $fallbackIndex = 0;
    $progression = new UnitProgressionService();

    foreach ($rows as $row) {
      $baseStats = $this->decodeJsonObject($row['base_stats_json']);
      $abilitySet = $this->decodeJsonObject($row['ability_set_json']);
      $level = max(1, (int)$row['level']);

      $attack = $progression->totalAttackForLevel($baseStats, $level, (int)$row['attack_per_level']);
      $defense = $progression->totalDefenseForLevel($baseStats, $level, (int)$row['defense_per_level']);
      $maxHp = $progression->maxHpForLevel($baseStats, $level, (int)$row['max_hp_per_level']);
      $currentHp = $row['run_current_hp'] !== null
        ? max(0, min($maxHp, (int)$row['run_current_hp']))
        : $maxHp;
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
        'current_hp' => $currentHp,
        'abilities' => $this->flattenActiveAbilityIds($abilitySet),
        'combat_affixes' => [
          'damage_flat' => 0,
          'below_half_bonus' => 0.0,
        ],
        'ability_dice' => [],
        'passive_dice' => [],
      ];
    }

    if (count($units) === 0) {
      return $units;
    }

    $unitIds = array_map(static fn(array $u): int => (int)$u['id'], $units);
    $placeholders = implode(',', array_fill(0, count($unitIds), '?'));
    $equippedAbilityStmt = $this->pdo->prepare("
      SELECT `unit_instance_id`, `ability_id`
      FROM `unit_instance_equipped_abilities`
      WHERE `unit_instance_id` IN ($placeholders)
      ORDER BY `unit_instance_id` ASC, `equip_order` ASC, `id` ASC
    ");
    $equippedAbilityStmt->execute($unitIds);

    $equippedAbilityIdsByUnit = [];
    foreach ($equippedAbilityStmt->fetchAll(PDO::FETCH_ASSOC) as $abilityRow) {
      $unitId = (string)$abilityRow['unit_instance_id'];
      $abilityId = trim((string)($abilityRow['ability_id'] ?? ''));
      if ($abilityId === '') {
        continue;
      }

      $equippedAbilityIdsByUnit[$unitId] ??= [];
      $equippedAbilityIdsByUnit[$unitId][] = $abilityId;
    }

    $diceStmt = $this->pdo->prepare("\n      SELECT\n        uad.`unit_instance_id`,\n        uad.`ability_id`,\n        uad.`slot_index`,\n        uad.`dice_instance_id`,\n        dd.`sides`,\n        ad.`slug` AS `affix_slug`,\n        dia.`value` AS `affix_value`\n      FROM `unit_ability_dice` uad\n      JOIN `dice_instances` di ON di.`id` = uad.`dice_instance_id`\n      JOIN `dice_definitions` dd ON dd.`id` = di.`dice_definition_id`\n      LEFT JOIN `dice_instance_affixes` dia ON dia.`dice_instance_id` = di.`id`\n      LEFT JOIN `affix_definitions` ad ON ad.`id` = dia.`affix_definition_id`\n      WHERE uad.`unit_instance_id` IN ($placeholders)\n      ORDER BY uad.`unit_instance_id` ASC, uad.`ability_id` ASC, uad.`slot_index` ASC, ad.`id` ASC\n    ");
    $diceStmt->execute($unitIds);

    $diceByUnitAbility = [];
    $passiveDiceByUnitId = [];
    foreach ($diceStmt->fetchAll(PDO::FETCH_ASSOC) as $diceRow) {
      $unitId = (string)$diceRow['unit_instance_id'];
      $abilityId = trim((string)($diceRow['ability_id'] ?? ''));
      $slotIndex = (int)($diceRow['slot_index'] ?? 0);
      $diceInstanceId = (string)$diceRow['dice_instance_id'];
      $diceKey = $unitId . ':' . $abilityId . ':' . $slotIndex . ':' . $diceInstanceId;
      if (!isset($diceByUnitAbility[$unitId][$abilityId][$slotIndex][$diceKey])) {
        $die = [
          'kind' => 'unit',
          'dice_instance_id' => $diceInstanceId,
          'sides' => max(2, (int)$diceRow['sides']),
          'affixes' => [],
        ];
        $diceByUnitAbility[$unitId][$abilityId][$slotIndex][$diceKey] = $die;
        $passiveDiceByUnitId[$unitId][$diceInstanceId] = $die;
      }
      $affixSlug = trim((string)($diceRow['affix_slug'] ?? ''));
      if ($affixSlug !== '') {
        $affix = [
          'slug' => $affixSlug,
          'value' => (float)($diceRow['affix_value'] ?? 0),
        ];
        $diceByUnitAbility[$unitId][$abilityId][$slotIndex][$diceKey]['affixes'][] = $affix;
        $passiveDiceByUnitId[$unitId][$diceInstanceId]['affixes'][] = $affix;
      }
    }

    foreach ($units as &$unit) {
      $unitId = (string)$unit['id'];
      $equippedAbilityIds = $equippedAbilityIdsByUnit[$unitId] ?? [];
      if (count($equippedAbilityIds) > 0) {
        $unit['abilities'] = $equippedAbilityIds;
      }

      $abilityDice = [];
      foreach ($diceByUnitAbility[$unitId] ?? [] as $abilityId => $slots) {
        foreach ($slots as $slotIndex => $diceEntries) {
          $abilityDice[(string)$abilityId][(int)$slotIndex] = array_values($diceEntries);
        }
      }

      $unit['ability_dice'] = $abilityDice;
      $unit['passive_dice'] = array_values($passiveDiceByUnitId[$unitId] ?? []);
      $this->applyPassiveDiceAffixesToUnit($unit);
    }
    unset($unit);

    return $units;
  }

  /**
     * @return array{difficulty_rating:int,description:string,reward_profile:array<string,mixed>,units:array<int,array{id:string,pos:array{x:int,y:int},attack:int,defense:int,max_hp:int,abilities:array<int,string>,dice_pool:array<int,array{kind:string,dice_instance_id:?string,sides:int}>,xp_reward:int}>}
   */
  private function loadEncounter(?int $encounterTemplateId): array
  {
    if ($encounterTemplateId === null) {
      return [
        'difficulty_rating' => 1,
          'description' => '',
          'reward_profile' => [],
        'units' => [],
      ];
    }

    $stmt = $this->pdo->prepare('
        SELECT `difficulty_rating`, `description`, `enemy_set_json`, `reward_profile_json`
      FROM `encounter_templates`
      WHERE `id` = ?
      LIMIT 1
    ');
    $stmt->execute([$encounterTemplateId]);
    $template = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!is_array($template)) {
      return [
        'difficulty_rating' => 1,
        'description' => '',
        'reward_profile' => [],
        'units' => [],
      ];
    }

    $description = trim((string)($template['description'] ?? ''));
    $rewardProfile = $this->decodeJsonObject($template['reward_profile_json'] ?? null);

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
        'description' => $description,
        'reward_profile' => $rewardProfile,
        'units' => [],
      ];
    }

    $slugs = array_map(static fn(array $entry): string => (string)$entry['slug'], $enemyEntries);
    $uniqueSlugs = array_values(array_unique($slugs));
    $placeholders = implode(',', array_fill(0, count($uniqueSlugs), '?'));

    $stmt = $this->pdo->prepare("\n      SELECT `slug`, `base_stats_json`, `ability_set_json`, `equipped_abilities_json`, `xp_reward`\n      FROM `enemy_templates`\n      WHERE `slug` IN ($placeholders)\n    ");
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
      $equippedAbilityIds = $this->decodeAbilityIdList($row['equipped_abilities_json'] ?? null);

      $units[] = [
        'id' => $instanceId,
        'pos' => $pos,
        'attack' => max(1, (int)($baseStats['attack'] ?? 1)),
        'defense' => max(0, (int)($baseStats['defense'] ?? 0)),
        'max_hp' => max(1, (int)($baseStats['max_hp'] ?? 1)),
        'abilities' => count($equippedAbilityIds) > 0 ? $equippedAbilityIds : $this->flattenActiveAbilityIds($abilitySet),
        'dice_pool' => [],
        'xp_reward' => max(0, (int)$row['xp_reward']),
      ];
    }

    return [
      'difficulty_rating' => max(1, (int)$template['difficulty_rating']),
      'description' => $description,
      'reward_profile' => $rewardProfile,
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

    // Front depth is horizontal (right side). Cell digit 1 is the front-most slot.
    $x = 3 - (int)$value[1];
    $y = ord($value[0]) - ord('A');
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
      ['x' => 2, 'y' => 0],
      ['x' => 2, 'y' => 1],
      ['x' => 2, 'y' => 2],
      ['x' => 1, 'y' => 0],
      ['x' => 1, 'y' => 1],
      ['x' => 1, 'y' => 2],
      ['x' => 0, 'y' => 0],
      ['x' => 0, 'y' => 1],
      ['x' => 0, 'y' => 2],
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
  * @param array<int, array{id:string,attack:int,defense:int,max_hp:int,current_hp:int,abilities:array<int,string>,combat_affixes:array{damage_flat:int,below_half_bonus:float},ability_dice:array<string,array<int,array{kind:string,dice_instance_id:?string,sides:int,affixes:array<int,array{slug:string,value:float}>}>>,passive_dice:array<int,array{kind:string,dice_instance_id:?string,sides:int,affixes:array<int,array{slug:string,value:float}>}>}> $playerUnits
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
      $playerHp[$unitId] = max(0, min((int)$unit['max_hp'], (int)$unit['current_hp']));
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
            $triggerTick = (int)($ability['trigger_tick'] ?? 0);
            if ($triggerTick <= 0 || $tickOffset !== $triggerTick) {
              continue;
            }

            $aliveEnemyIds = $this->aliveUnitIds($enemyHp);
            if (count($aliveEnemyIds) === 0) {
              $combatOver = true;
              break 3;
            }

              $enemyTargetId = $this->chooseTargetId(
                $state,
                $aliveEnemyIds,
                $enemyById,
                (string)($ability['target'] ?? 'enemy_front_prefer')
              );
              $enemyTarget = $enemyById[$enemyTargetId] ?? null;
            if (!is_array($enemyTarget)) {
              continue;
            }

            $abilityId = (string)$ability['ability_id'];
            $dice = $this->rollActionDice(
              $state,
              $this->resolvePlayerActionDiceSlots($playerActor, $abilityId),
              $abilityId,
              'player'
            );
            $outcome = $this->deriveActionOutcome(
              $state,
              (int)$playerActor['attack'],
              (int)$enemyTarget['defense'],
              (int)($enemyHp[$enemyTargetId] ?? (int)$enemyTarget['max_hp']),
              (int)$enemyTarget['max_hp'],
                $abilityId,
                (int)$dice['dice_modifier'],
                (array)($playerActor['combat_affixes'] ?? ['damage_flat' => 0, 'below_half_bonus' => 0.0]),
                $dice,
                (array)($playerActor['pos'] ?? ['x' => 1, 'y' => 1]),
                (array)($enemyTarget['pos'] ?? ['x' => 1, 'y' => 1]),
                $abilityRegistry,
              );

            $events[] = [
              'type' => 'action',
              'round' => $round,
              'tick' => $tick,
              'side' => 'player',
              'loadout_source' => 'equipped',
              'actor_unit_instance_id' => $playerActorId,
              'target_enemy_slug' => $enemyTargetId,
              'ability_id' => $abilityId,
              'ability_instance_index' => ((int)($ability['equip_order'] ?? 0)) + 1,
              'dice_used' => $dice['dice_used'],
              'dice_rolls' => $dice['dice_rolls'],
              'slot_traces' => $dice['slot_traces'],
              'slot_trace_summary' => $dice['slot_trace_summary'],
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
            $triggerTick = (int)($ability['trigger_tick'] ?? 0);
            if ($triggerTick <= 0 || $tickOffset !== $triggerTick) {
              continue;
            }

            $alivePlayerIds = $this->aliveUnitIds($playerHp);
            if (count($alivePlayerIds) === 0) {
              $combatOver = true;
              break 3;
            }

              $playerTargetId = $this->chooseTargetId(
                $state,
                $alivePlayerIds,
                $playerById,
                (string)($ability['target'] ?? 'enemy_front_prefer')
              );
              $playerTarget = $playerById[$playerTargetId] ?? null;
            if (!is_array($playerTarget)) {
              continue;
            }

            $abilityId = (string)$ability['ability_id'];
            $dice = $this->rollActionDice(
              $state,
              $this->resolveEnemyActionDiceSlots($abilityId),
              $abilityId,
              'enemy'
            );
            $outcome = $this->deriveActionOutcome(
              $state,
              (int)$enemyActor['attack'],
              (int)$playerTarget['defense'],
              (int)($playerHp[$playerTargetId] ?? (int)$playerTarget['max_hp']),
              (int)$playerTarget['max_hp'],
                $abilityId,
                (int)$dice['dice_modifier'],
                ['damage_flat' => 0, 'below_half_bonus' => 0.0],
                $dice,
                (array)($enemyActor['pos'] ?? ['x' => 1, 'y' => 1]),
                (array)($playerTarget['pos'] ?? ['x' => 1, 'y' => 1]),
                $abilityRegistry,
              );

            $events[] = [
              'type' => 'action',
              'round' => $round,
              'tick' => $tick,
              'side' => 'enemy',
              'loadout_source' => 'enemy_authored',
              'actor_enemy_slug' => $enemyActorId,
              'target_unit_instance_id' => $playerTargetId,
              'ability_id' => $abilityId,
              'ability_instance_index' => ((int)($ability['equip_order'] ?? 0)) + 1,
              'dice_used' => $dice['dice_used'],
              'dice_rolls' => $dice['dice_rolls'],
              'slot_traces' => $dice['slot_traces'],
              'slot_trace_summary' => $dice['slot_trace_summary'],
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
   * @return array<int,array{ability_id:string,speed:int,target:string,trigger_tick:int,equip_order:int}>
   */
  private function buildActiveAbilitySchedule(array $abilityIds, AbilityRegistry $registry): array
  {
    $schedule = [];
    $cumulativeTick = 0;
    foreach ($abilityIds as $abilityId) {
      $id = trim((string)$abilityId);
      if ($id === '' || !$registry->has($id)) {
        continue;
      }

      $def = $registry->get($id);
      if ($def->type !== AbilityType::Active || $def->speed === null) {
        continue;
      }

      $cumulativeTick += (int)$def->speed;
      if ($cumulativeTick > 20) {
        continue;
      }

      $schedule[] = [
        'ability_id' => $id,
        'speed' => (int)$def->speed,
        'target' => $def->defaultTarget?->value ?? 'enemy_front_prefer',
        'trigger_tick' => $cumulativeTick,
        'equip_order' => count($schedule),
      ];
    }

    if (count($schedule) === 0) {
      $schedule[] = [
        'ability_id' => 'basic_attack_melee',
        'speed' => 4,
        'target' => 'enemy_front_prefer',
        'trigger_tick' => 4,
        'equip_order' => 0,
      ];
    }

    usort($schedule, static function (array $a, array $b): int {
      $tickCmp = ((int)$a['trigger_tick']) <=> ((int)$b['trigger_tick']);
      if ($tickCmp !== 0) {
        return $tickCmp;
      }

      return ((int)$a['equip_order']) <=> ((int)$b['equip_order']);
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
   * @param array<int,string> $aliveIds
   * @param array<string,array<string,mixed>> $unitsById
   */
  private function chooseTargetId(string &$state, array $aliveIds, array $unitsById, string $targetPreference): string
  {
    if (count($aliveIds) <= 1) {
      return (string)($aliveIds[0] ?? '');
    }

    $preferFront = str_contains($targetPreference, 'front');
    $preferBack = str_contains($targetPreference, 'back');
    if (!$preferFront && !$preferBack) {
      return (string)$aliveIds[$this->nextInt($state, count($aliveIds))];
    }

    usort($aliveIds, function (string $a, string $b) use ($unitsById, $preferFront): int {
      $unitA = $unitsById[$a] ?? [];
      $unitB = $unitsById[$b] ?? [];
      $posA = is_array($unitA['pos'] ?? null) ? $unitA['pos'] : ['x' => 1, 'y' => 1];
      $posB = is_array($unitB['pos'] ?? null) ? $unitB['pos'] : ['x' => 1, 'y' => 1];

      $cmpX = $preferFront
        ? ((int)$posB['x'] <=> (int)$posA['x'])
        : ((int)$posA['x'] <=> (int)$posB['x']);
      if ($cmpX !== 0) {
        return $cmpX;
      }

      $cmpY = ((int)$posA['y'] <=> (int)$posB['y']);
      if ($cmpY !== 0) {
        return $cmpY;
      }

      return strcmp($a, $b);
    });

    $firstUnit = $unitsById[$aliveIds[0]] ?? [];
    $firstPos = is_array($firstUnit['pos'] ?? null) ? $firstUnit['pos'] : ['x' => 1, 'y' => 1];
    $bestX = (int)$firstPos['x'];
    $preferredIds = array_values(array_filter($aliveIds, function (string $id) use ($unitsById, $bestX): bool {
      $unit = $unitsById[$id] ?? [];
      $pos = is_array($unit['pos'] ?? null) ? $unit['pos'] : ['x' => 1, 'y' => 1];
      return (int)$pos['x'] === $bestX;
    }));

    return (string)$preferredIds[$this->nextInt($state, count($preferredIds))];
  }

  /**
   * @param array{x:int,y:int} $attackerPos
   * @param array{x:int,y:int} $targetPos
   */
  private function resolvePositionMultiplier(
    string $abilityId,
    array $attackerPos,
    array $targetPos,
    AbilityRegistry $abilityRegistry
  ): float {
    if (!$abilityRegistry->has($abilityId)) {
      return 1.0;
    }

    $def = $abilityRegistry->get($abilityId);
    $isMelee = in_array('melee', $def->tags, true);
    $multiplier = 1.0;

    if ($isMelee && $this->isFrontRow($attackerPos)) {
      $multiplier *= 1.10;
    }
    if ($this->isFrontRow($targetPos)) {
      $multiplier *= 1.10;
    }
    if ($isMelee && $this->isBackRow($targetPos)) {
      $multiplier *= 0.90;
    }

    return $multiplier;
  }

  /**
   * @param array{x:int,y:int} $pos
   */
  private function isFrontRow(array $pos): bool
  {
    return ((int)($pos['x'] ?? 1)) >= 2;
  }

  /**
   * @param array{x:int,y:int} $pos
   */
  private function isBackRow(array $pos): bool
  {
    return ((int)($pos['x'] ?? 1)) <= 0;
  }

  /**
   * @param array{damage_flat:int,below_half_bonus:float} $combatAffixes
   * @param array{dice_used:array<int,array{kind:string,dice_instance_id:?string,sides:int}>,dice_rolls:array<int,array{sides:int,roll:int}>,dice_outcome:string,dice_modifier:int,explode_triggered:bool} $diceContext
   * @param array{x:int,y:int} $attackerPos
   * @param array{x:int,y:int} $targetPos
   * @return array{damage:int,target_hp_after:int,outcome:string,status_applied:?string,status_duration_rounds:?int,ability_outcome:string,affix_outcome:?string}
   */
  private function deriveActionOutcome(
    string &$state,
    int $attackerAttack,
    int $targetDefense,
    int $targetHp,
    int $targetMaxHp,
    string $abilityId,
    int $diceModifier,
    array $combatAffixes,
    array $diceContext,
    array $attackerPos,
    array $targetPos,
    AbilityRegistry $abilityRegistry,
  ): array {
    $variance = $this->nextInt($state, 5) - 2;
    $rawDamage = (int)floor(($attackerAttack * 0.65) - ($targetDefense * 0.35)) + $variance + $diceModifier;
    $rawDamage += max(0, (int)($combatAffixes['damage_flat'] ?? 0));

    $affixOutcomeParts = [];
    if (((int)($combatAffixes['damage_flat'] ?? 0)) > 0) {
      $affixOutcomeParts[] = sprintf('+%d attack damage', (int)$combatAffixes['damage_flat']);
    }
    if (($diceContext['explode_triggered'] ?? false) === true) {
      $affixOutcomeParts[] = 'explode triggered';
    }

    $belowHalfBonus = (float)($combatAffixes['below_half_bonus'] ?? 0.0);
    if ($belowHalfBonus > 0 && $targetMaxHp > 0 && $targetHp < (int)ceil($targetMaxHp / 2)) {
      $rawDamage = (int)floor($rawDamage * (1 + $belowHalfBonus));
      $affixOutcomeParts[] = sprintf('execute below half x%s', rtrim(rtrim(number_format(1 + $belowHalfBonus, 2, '.', ''), '0'), '.'));
    }

    $positionMultiplier = $this->resolvePositionMultiplier($abilityId, $attackerPos, $targetPos, $abilityRegistry);
    if (abs($positionMultiplier - 1.0) > 0.0001) {
      $rawDamage = (int)floor($rawDamage * $positionMultiplier);
      $affixOutcomeParts[] = sprintf('position x%s', rtrim(rtrim(number_format($positionMultiplier, 2, '.', ''), '0'), '.'));
    }

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
      'affix_outcome' => count($affixOutcomeParts) > 0 ? implode(', ', $affixOutcomeParts) : null,
    ];
  }

  /**
   * @param array{
   *   ability_dice?:array<string,array<int,array{kind:string,dice_instance_id:?string,sides:int,affixes:array<int,array{slug:string,value:float}>}>>
   * } $playerUnit
   * @return array<int,array{kind:string,dice_instance_id:?string,sides:int,affixes:array<int,array{slug:string,value:float}>}>
   */
  private function resolvePlayerActionDiceSlots(array $playerUnit, string $abilityId): array
  {
    $slotCount = $this->slotCountForAbility($abilityId);
    if ($slotCount <= 0) {
      return [];
    }

    $abilityDice = (array)($playerUnit['ability_dice'][$abilityId] ?? []);
    $slots = [];
    for ($slotIndex = 0; $slotIndex < $slotCount; $slotIndex++) {
      $die = $abilityDice[$slotIndex][0] ?? null;
      if (is_array($die)) {
        $slots[] = $die;
        continue;
      }

      $slots[] = [
        'kind' => 'empty_slot',
        'dice_instance_id' => null,
        'sides' => 1,
        'affixes' => [],
      ];
    }

    return $slots;
  }

  /**
   * @return array<int,array{kind:string,dice_instance_id:?string,sides:int,affixes:array<int,array{slug:string,value:float}>}>
   */
  private function resolveEnemyActionDiceSlots(string $abilityId): array
  {
    $slotCount = $this->slotCountForAbility($abilityId);
    if ($slotCount <= 0) {
      return [];
    }

    $slots = [];
    for ($slotIndex = 0; $slotIndex < $slotCount; $slotIndex++) {
      $slots[] = [
        'kind' => 'enemy_virtual',
        'dice_instance_id' => null,
        'sides' => 6,
        'affixes' => [],
      ];
    }

    return $slots;
  }

  private function slotCountForAbility(string $abilityId): int
  {
    $registry = new AbilityRegistry();
    if (!$registry->has($abilityId)) {
      return 0;
    }

    $definition = $registry->get($abilityId);
    return max(0, (int)($definition->diceCost ?? 0));
  }

  /**
   * @param array<int,array{kind:string,dice_instance_id:?string,sides:int,affixes?:array<int,array{slug:string,value:float}>}> $dicePool
   * @return array{
   *   dice_used:array<int,array{kind:string,dice_instance_id:?string,sides:int}>,
   *   dice_rolls:array<int,array{sides:int,roll:int}>,
   *   slot_traces:array<int,array{
   *     slot_index:int,
   *     kind:string,
   *     dice_instance_id:?string,
   *     sides:int,
   *     rolls:array<int,array{sides:int,roll:int}>,
   *     roll_total:int,
   *     modifier:int,
   *     empty_slot:bool
   *   }>,
   *   slot_trace_summary:string,
   *   dice_outcome:string,
   *   dice_modifier:int,
   *   explode_triggered:bool
   * }
   */
  private function rollActionDice(string &$state, array $dicePool, string $abilityId, string $side): array
  {
    $pool = $dicePool;
    if (count($pool) === 0) {
      return [
        'dice_used' => [],
        'dice_rolls' => [],
        'slot_traces' => [],
        'slot_trace_summary' => 'no slots used',
        'dice_outcome' => sprintf('%s_%s used no dice', $side, $abilityId),
        'dice_modifier' => 0,
        'explode_triggered' => false,
      ];
    }

    $diceUsed = [];
    $diceRolls = [];
    $slotTraces = [];
    $diceOutcomeParts = [];
    $slotTraceParts = [];
    $explodeTriggered = false;
    $modifier = 0;

    foreach (array_values($pool) as $index => $die) {
      $sides = max(1, (int)($die['sides'] ?? ($side === 'enemy' ? 6 : 1)));
      $roll = 1 + $this->nextInt($state, $sides);
      $rollEntries = [[
        'sides' => $sides,
        'roll' => $roll,
      ]];
      $rollTotal = $roll;

      foreach ((array)($die['affixes'] ?? []) as $affix) {
        $slug = strtolower(trim((string)($affix['slug'] ?? '')));
        if ($slug === 'explode_once' && $roll === $sides) {
          $explodeTriggered = true;
          $extraRoll = 1 + $this->nextInt($state, $sides);
          $rollEntries[] = [
            'sides' => $sides,
            'roll' => $extraRoll,
          ];
          $rollTotal += $extraRoll;
          break;
        }
      }

      $diceUsed[] = [
        'kind' => (string)($die['kind'] ?? 'unknown'),
        'dice_instance_id' => isset($die['dice_instance_id']) && $die['dice_instance_id'] !== '' ? (string)$die['dice_instance_id'] : null,
        'sides' => $sides,
      ];
      foreach ($rollEntries as $entry) {
        $diceRolls[] = $entry;
      }

      $slotModifier = $rollTotal - (int)ceil($sides / 2);
      $modifier += $slotModifier;
      $diceLabel = $diceUsed[$index]['dice_instance_id'] !== null
        ? sprintf('dice#%s', $diceUsed[$index]['dice_instance_id'])
        : sprintf('%s_%s_slot_%d', $side, $abilityId, $index + 1);
      $slotLabel = $diceUsed[$index]['dice_instance_id'] !== null
        ? sprintf('#%s(d%d)', $diceUsed[$index]['dice_instance_id'], $sides)
        : sprintf('%s(d%d)', (string)$diceUsed[$index]['kind'], $sides);
      $rollLabel = implode(' + ', array_map(
        static fn(array $entry): string => (string)$entry['roll'],
        $rollEntries
      ));
      $slotTraces[] = [
        'slot_index' => $index,
        'kind' => (string)$diceUsed[$index]['kind'],
        'dice_instance_id' => $diceUsed[$index]['dice_instance_id'],
        'sides' => $sides,
        'rolls' => $rollEntries,
        'roll_total' => $rollTotal,
        'modifier' => $slotModifier,
        'empty_slot' => (string)$diceUsed[$index]['kind'] === 'empty_slot',
      ];
      $slotTraceParts[] = sprintf(
        'slot%d=%s => %s (mod %+d)',
        $index + 1,
        $slotLabel,
        $rollLabel,
        $slotModifier
      );
      $diceOutcomeParts[] = count($rollEntries) > 1
        ? sprintf('%s rolled d%d = %s (explode => %d)', $diceLabel, $sides, $rollLabel, $rollTotal)
        : sprintf('%s rolled d%d = %d', $diceLabel, $sides, $roll);
    }

    return [
      'dice_used' => $diceUsed,
      'dice_rolls' => $diceRolls,
      'slot_traces' => $slotTraces,
      'slot_trace_summary' => implode('; ', $slotTraceParts),
      'dice_outcome' => implode('; ', $diceOutcomeParts),
      'dice_modifier' => $modifier,
      'explode_triggered' => $explodeTriggered,
    ];
  }

  /**
   * @param array{
   *   attack:int,
   *   defense:int,
   *   max_hp:int,
   *   current_hp:int,
   *   combat_affixes:array{damage_flat:int,below_half_bonus:float},
   *   passive_dice:array<int,array{kind:string,dice_instance_id:?string,sides:int,affixes:array<int,array{slug:string,value:float}>}>
   * } $unit
   */
  private function applyPassiveDiceAffixesToUnit(array &$unit): void
  {
    $attackFlat = 0;
    $defenseFlat = 0;
    $attackPct = 0.0;
    $defensePct = 0.0;
    $damageFlat = 0;
    $belowHalfBonus = 0.0;

    foreach ((array)($unit['passive_dice'] ?? []) as $die) {
      foreach ((array)($die['affixes'] ?? []) as $affix) {
        $slug = strtolower(trim((string)($affix['slug'] ?? '')));
        $value = (float)($affix['value'] ?? 0);
        switch ($slug) {
          case 'atk_plus':
            $attackFlat += (int)floor($value);
            $damageFlat += (int)floor($value);
            break;
          case 'guard_plus':
            $defenseFlat += (int)floor($value);
            break;
          case 'precision_plus':
            $attackPct += $value;
            break;
          case 'bulwark_plus':
            $defensePct += $value;
            break;
          case 'execute_below_half':
            $belowHalfBonus = max($belowHalfBonus, $value);
            break;
        }
      }
    }

    $unit['attack'] = max(1, (int)floor(((int)$unit['attack'] + $attackFlat) * (1 + $attackPct)));
    $unit['defense'] = max(0, (int)floor(((int)$unit['defense'] + $defenseFlat) * (1 + $defensePct)));
    $unit['combat_affixes'] = [
      'damage_flat' => $damageFlat,
      'below_half_bonus' => $belowHalfBonus,
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

  /**
   * @param mixed $abilityListRaw
   * @return array<int,string>
   */
  private function decodeAbilityIdList(mixed $abilityListRaw): array
  {
    if (is_string($abilityListRaw)) {
      $decoded = json_decode($abilityListRaw, true);
      $abilityListRaw = is_array($decoded) ? $decoded : [];
    }

    if (!is_array($abilityListRaw)) {
      return [];
    }

    $out = [];
    foreach ($abilityListRaw as $abilityId) {
      $id = trim((string)$abilityId);
      if ($id !== '') {
        $out[] = $id;
      }
    }

    return $out;
  }

  private function pickUnitTypeSlug(string &$state): ?string
  {
    $stmt = $this->pdo->query("SELECT `slug` FROM `unit_types` WHERE RIGHT(`slug`, 3) = '_t1' ORDER BY `id` ASC");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (count($rows) === 0) {
      return null;
    }

    $index = $this->nextInt($state, count($rows));
    $slug = (string)($rows[$index]['slug'] ?? '');
    return $slug !== '' ? $slug : null;
  }

  /**
   * @return array{rarity:string,sides:int}|null
   */
  private function pickDiceDefinitionSpec(string &$state): ?array
  {
    $stmt = $this->pdo->query('SELECT `rarity`, `sides` FROM `dice_definitions` ORDER BY `id` ASC');
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (count($rows) === 0) {
      return null;
    }

    $index = $this->nextInt($state, count($rows));
    $row = $rows[$index] ?? null;
    if (!is_array($row)) {
      return null;
    }

    return [
      'rarity' => (string)($row['rarity'] ?? 'common'),
      'sides' => max(2, (int)($row['sides'] ?? 6)),
    ];
  }
}
