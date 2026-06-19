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
use DiceGoblins\Services\UserUnlockService;
use DiceGoblins\Support\FormationGeometry;
use PDO;
use RuntimeException;

final class DeterministicRunNodeResolver
{
  /** @var array<string,bool> */
  private array $schemaPresenceCache = [];

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
      $currencySoft = $nodeType === 'loot' ? 8 : 0;
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
      $currencySoft = $outcome === 'victory'
        ? (5 * $difficulty) + $this->nextInt($rngState, 6)
        : 0;

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
        $unitSlug = $this->pickUnitTypeSlug($userId, $rngState);
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
              'formation' => [
                'w' => (int)$u['formation']['w'],
                'h' => (int)$u['formation']['h'],
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
              'formation' => [
                'w' => (int)$u['formation']['w'],
                'h' => (int)$u['formation']['h'],
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
   *   formation:array{w:int,h:int},
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
        rus.`current_hp` AS `run_current_hp`
      FROM `team_units` tu
      JOIN `unit_instances` ui ON ui.`id` = tu.`unit_instance_id`
      JOIN `unit_types` ut ON ut.`id` = ui.`unit_type_id`
      LEFT JOIN `run_unit_state` rus
        ON rus.`run_id` = ?
       AND rus.`unit_instance_id` = ui.`id`
      WHERE tu.`team_id` = ? AND ui.`user_id` = ?
      ORDER BY ui.`id` ASC
    ');
    $stmt->execute([$runId, $teamId, $userId]);

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $formationCellsByUnit = $this->loadTeamFormationCellsByUnit($teamId);
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
      $footprint = FormationGeometry::footprintFromStats($baseStats);
      $currentHp = $row['run_current_hp'] !== null
        ? max(0, min($maxHp, (int)$row['run_current_hp']))
        : $maxHp;
      $anchorCell = FormationGeometry::anchorCellForCells(
        $formationCellsByUnit[(string)$row['unit_instance_id']] ?? []
      );
      $pos = $this->cellToPos((string)($anchorCell ?? ''));
      if (!is_array($pos)) {
        $pos = $this->defaultPosForIndex($fallbackIndex);
      }
      $fallbackIndex++;

      $units[] = [
        'id' => (string)$row['unit_instance_id'],
        'pos' => $pos,
        'formation' => $footprint,
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
    $equippedAbilityIdsByUnit = [];
    if ($this->schemaHasTable('unit_instance_equipped_abilities')) {
      $equippedAbilityStmt = $this->pdo->prepare("
        SELECT `unit_instance_id`, `ability_id`
        FROM `unit_instance_equipped_abilities`
        WHERE `unit_instance_id` IN ($placeholders)
        ORDER BY `unit_instance_id` ASC, `equip_order` ASC, `id` ASC
      ");
      $equippedAbilityStmt->execute($unitIds);

      foreach ($equippedAbilityStmt->fetchAll(PDO::FETCH_ASSOC) as $abilityRow) {
        $unitId = (string)$abilityRow['unit_instance_id'];
        $abilityId = trim((string)($abilityRow['ability_id'] ?? ''));
        if ($abilityId === '') {
          continue;
        }

        $equippedAbilityIdsByUnit[$unitId] ??= [];
        $equippedAbilityIdsByUnit[$unitId][] = $abilityId;
      }
    }

    $diceByUnitAbility = [];
    $passiveDiceByUnitId = [];
    if ($this->schemaHasTable('unit_ability_dice')) {
      $diceStmt = $this->pdo->prepare("\n        SELECT\n          uad.`unit_instance_id`,\n          uad.`ability_id`,\n          uad.`slot_index`,\n          uad.`dice_instance_id`,\n          dd.`sides`,\n          ad.`slug` AS `affix_slug`,\n          dia.`value` AS `affix_value`\n        FROM `unit_ability_dice` uad\n        JOIN `dice_instances` di ON di.`id` = uad.`dice_instance_id`\n        JOIN `dice_definitions` dd ON dd.`id` = di.`dice_definition_id`\n        LEFT JOIN `dice_instance_affixes` dia ON dia.`dice_instance_id` = di.`id`\n        LEFT JOIN `affix_definitions` ad ON ad.`id` = dia.`affix_definition_id`\n        WHERE uad.`unit_instance_id` IN ($placeholders)\n        ORDER BY uad.`unit_instance_id` ASC, uad.`ability_id` ASC, uad.`slot_index` ASC, ad.`id` ASC\n      ");
      $diceStmt->execute($unitIds);

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
   * @return array<string,list<string>>
   */
  private function loadTeamFormationCellsByUnit(int $teamId): array
  {
    $stmt = $this->pdo->prepare('
      SELECT `cell`, `unit_instance_id`
      FROM `team_formation`
      WHERE `team_id` = ? AND `unit_instance_id` IS NOT NULL
      ORDER BY `cell` ASC
    ');
    $stmt->execute([$teamId]);

    $cellsByUnit = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
      $unitId = (string)$row['unit_instance_id'];
      $cellsByUnit[$unitId] ??= [];
      $cellsByUnit[$unitId][] = (string)$row['cell'];
    }

    return $cellsByUnit;
  }

  /**
     * @return array{difficulty_rating:int,description:string,reward_profile:array<string,mixed>,units:array<int,array{id:string,pos:array{x:int,y:int},formation:array{w:int,h:int},attack:int,defense:int,max_hp:int,abilities:array<int,string>,dice_pool:array<int,array{kind:string,dice_instance_id:?string,sides:int}>,xp_reward:int}>}
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

    $equippedAbilitySelect = $this->schemaHasColumn('enemy_templates', 'equipped_abilities_json')
      ? '`equipped_abilities_json`'
      : 'NULL AS `equipped_abilities_json`';
    $stmt = $this->pdo->prepare("\n      SELECT `slug`, `base_stats_json`, `ability_set_json`, {$equippedAbilitySelect}, `xp_reward`\n      FROM `enemy_templates`\n      WHERE `slug` IN ($placeholders)\n    ");
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
      $footprint = FormationGeometry::footprintFromStats($baseStats);
      $abilitySet = $this->decodeJsonObject($row['ability_set_json']);
      $equippedAbilityIds = $this->decodeAbilityIdList($row['equipped_abilities_json'] ?? null);

      $units[] = [
        'id' => $instanceId,
        'pos' => $pos,
        'formation' => $footprint,
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
    $playerStatuses = [];
    foreach ($playerUnits as $unit) {
      $unitId = (string)$unit['id'];
      $playerHp[$unitId] = max(0, min((int)$unit['max_hp'], (int)$unit['current_hp']));
      $playerById[$unitId] = $unit;
      $playerSchedules[$unitId] = $this->buildActiveAbilitySchedule((array)$unit['abilities'], $abilityRegistry);
      $playerStatuses[$unitId] = [];
    }

    $enemyHp = [];
    $enemyById = [];
    $enemySchedules = [];
    $enemyStatuses = [];
    foreach ($enemyUnits as $unit) {
      $unitId = (string)$unit['id'];
      $enemyHp[$unitId] = (int)$unit['max_hp'];
      $enemyById[$unitId] = $unit;
      $enemySchedules[$unitId] = $this->buildActiveAbilitySchedule((array)$unit['abilities'], $abilityRegistry);
      $enemyStatuses[$unitId] = [];
    }

    $combatOver = false;
    $lastRound = 0;
    $lastTick = 0;
    $sleepBlockedUntilTick = [];
    $preferredTargetByActor = [];

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
        $this->processStatusPhase(
          $events,
          'player',
          $round,
          $tick,
          $playerHp,
          $playerById,
          $playerStatuses,
          $sleepBlockedUntilTick
        );
        $lastTick = $tick;
        if ($this->countLivingUnits($playerHp) === 0 || $this->countLivingUnits($enemyHp) === 0) {
          $combatOver = true;
          break;
        }

        $this->processStatusPhase(
          $events,
          'enemy',
          $round,
          $tick,
          $enemyHp,
          $enemyById,
          $enemyStatuses,
          $sleepBlockedUntilTick
        );
        $lastTick = $tick;
        if ($this->countLivingUnits($playerHp) === 0 || $this->countLivingUnits($enemyHp) === 0) {
          $combatOver = true;
          break;
        }

        foreach ($playerUnits as $playerActor) {
          $playerActorId = (string)$playerActor['id'];
          if (($playerHp[$playerActorId] ?? 0) <= 0) {
            continue;
          }
          if ($this->isUnitAsleepForTick($playerStatuses, $sleepBlockedUntilTick, $playerActorId, $tick)) {
            $events[] = [
              'type' => 'action_skipped',
              'round' => $round,
              'tick' => $tick,
              'side' => 'player',
              'actor_unit_instance_id' => $playerActorId,
              'reason' => 'sleep',
            ];
            continue;
          }

          foreach ($playerSchedules[$playerActorId] ?? [] as $ability) {
            $triggerTick = (int)($ability['trigger_tick'] ?? 0);
            if ($triggerTick <= 0 || $tickOffset !== $triggerTick) {
              continue;
            }

            $abilityId = (string)$ability['ability_id'];
            $targetPreference = (string)($ability['target'] ?? 'enemy_front_prefer');
            $isSupportAbility = $this->isSupportTargetPreference($targetPreference);
            $targetPoolHp = $isSupportAbility ? $playerHp : $enemyHp;
            $targetPoolUnits = $isSupportAbility ? $playerById : $enemyById;
            $targetKey = $isSupportAbility ? 'target_unit_instance_id' : 'target_enemy_slug';
            $aliveTargetIds = $this->aliveUnitIds($targetPoolHp);
            if (count($aliveTargetIds) === 0) {
              $combatOver = true;
              break 3;
            }

            $targetSelection = $this->chooseTargetSelection(
              $state,
              $aliveTargetIds,
              $targetPoolUnits,
              $targetPreference,
              $playerActorId,
              $targetPoolHp,
              $isSupportAbility ? $playerStatuses : $enemyStatuses,
              $preferredTargetByActor['player:' . $playerActorId] ?? null
            );
            $targetId = $targetSelection['id'];
            $targetUnit = $targetPoolUnits[$targetId] ?? null;
            if (!is_array($targetUnit)) {
              continue;
            }
            $targetStatuses = $isSupportAbility
              ? (array)($playerStatuses[$targetId] ?? [])
              : (array)($enemyStatuses[$targetId] ?? []);

            $dice = $this->rollActionDice(
              $state,
              $this->resolvePlayerActionDiceSlots($playerActor, $abilityId),
              $abilityId,
              'player'
            );
            $stackResolution = $isSupportAbility
              ? ['damage_reduction' => 0, 'outcome' => null]
              : $this->consumeOneAttackDefenseStacks($enemyStatuses, $targetId);
            $outcome = $isSupportAbility
              ? $this->deriveSupportOutcome($abilityRegistry, $abilityId, $targetId, $playerHp, $targetUnit, $dice)
              : $this->deriveActionOutcome(
                $state,
                (int)$playerActor['attack'],
                (int)$targetUnit['defense'],
                (int)($enemyHp[$targetId] ?? (int)$targetUnit['max_hp']),
                (int)$targetUnit['max_hp'],
                $abilityId,
                (int)$dice['dice_modifier'],
                (array)($playerActor['combat_affixes'] ?? ['damage_flat' => 0, 'below_half_bonus' => 0.0]),
                $dice,
                $targetStatuses,
                (array)($playerActor['pos'] ?? ['x' => 1, 'y' => 1]),
                (array)($targetUnit['pos'] ?? ['x' => 1, 'y' => 1]),
                (array)($playerActor['formation'] ?? ['w' => 1, 'h' => 1]),
                (array)($targetUnit['formation'] ?? ['w' => 1, 'h' => 1]),
                $abilityRegistry,
                (int)$playerActor['attack'],
                (int)($stackResolution['damage_reduction'] ?? 0),
              );

            $events[] = [
              'type' => 'action',
              'round' => $round,
              'tick' => $tick,
              'side' => 'player',
              'loadout_source' => 'equipped',
              'actor_unit_instance_id' => $playerActorId,
              $targetKey => $targetId,
              'ability_id' => $abilityId,
              'ability_instance_index' => ((int)($ability['equip_order'] ?? 0)) + 1,
              'dice_used' => $dice['dice_used'],
              'dice_rolls' => $dice['dice_rolls'],
              'slot_traces' => $dice['slot_traces'],
              'slot_trace_summary' => $dice['slot_trace_summary'],
              'dice_outcome' => $dice['dice_outcome'],
              'actor_hp_after' => (int)($playerHp[$playerActorId] ?? (int)$playerActor['max_hp']),
              'actor_max_hp' => (int)$playerActor['max_hp'],
              'target_max_hp' => (int)$targetUnit['max_hp'],
              'targeting_reason' => $targetSelection['reason'],
              'targeting_weights' => $targetSelection['weights'],
              'stack_outcome' => $stackResolution['outcome'],
              ...$outcome,
            ];
            $this->applyOutcomeStatus(
              $enemyStatuses,
              $targetId,
              $outcome,
              $round,
              $tick,
            );
            $reactionOutcome = $isSupportAbility
              ? null
              : $this->reflectDebuffToSourceIfNeeded(
                $enemyStatuses,
                $playerStatuses,
                $targetId,
                $playerActorId,
                $outcome,
                $round,
                $tick
              );
            if ($reactionOutcome !== null) {
              $events[count($events) - 1]['reaction_outcome'] = $reactionOutcome;
            }
            if (!$isSupportAbility) {
              $enemyHp[$targetId] = (int)$outcome['target_hp_after'];
              $this->clearSleepOnDamage($enemyStatuses, $sleepBlockedUntilTick, $targetId, $tick, (int)$outcome['damage']);
              $preferredTargetByActor['player:' . $playerActorId] = $targetId;
            }
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
          if ($this->isUnitAsleepForTick($enemyStatuses, $sleepBlockedUntilTick, $enemyActorId, $tick)) {
            $events[] = [
              'type' => 'action_skipped',
              'round' => $round,
              'tick' => $tick,
              'side' => 'enemy',
              'actor_enemy_slug' => $enemyActorId,
              'reason' => 'sleep',
            ];
            continue;
          }

          foreach ($enemySchedules[$enemyActorId] ?? [] as $ability) {
            $triggerTick = (int)($ability['trigger_tick'] ?? 0);
            if ($triggerTick <= 0 || $tickOffset !== $triggerTick) {
              continue;
            }

            $abilityId = (string)$ability['ability_id'];
            $targetPreference = (string)($ability['target'] ?? 'enemy_front_prefer');
            $isSupportAbility = $this->isSupportTargetPreference($targetPreference);
            $targetPoolHp = $isSupportAbility ? $enemyHp : $playerHp;
            $targetPoolUnits = $isSupportAbility ? $enemyById : $playerById;
            $targetKey = $isSupportAbility ? 'target_enemy_slug' : 'target_unit_instance_id';
            $aliveTargetIds = $this->aliveUnitIds($targetPoolHp);
            if (count($aliveTargetIds) === 0) {
              $combatOver = true;
              break 3;
            }

            $targetSelection = $this->chooseTargetSelection(
              $state,
              $aliveTargetIds,
              $targetPoolUnits,
              $targetPreference,
              $enemyActorId,
              $targetPoolHp,
              $isSupportAbility ? $enemyStatuses : $playerStatuses,
              $preferredTargetByActor['enemy:' . $enemyActorId] ?? null
            );
            $targetId = $targetSelection['id'];
            $targetUnit = $targetPoolUnits[$targetId] ?? null;
            if (!is_array($targetUnit)) {
              continue;
            }
            $targetStatuses = $isSupportAbility
              ? (array)($enemyStatuses[$targetId] ?? [])
              : (array)($playerStatuses[$targetId] ?? []);

            $dice = $this->rollActionDice(
              $state,
              $this->resolveEnemyActionDiceSlots($abilityId),
              $abilityId,
              'enemy'
            );
            $stackResolution = $isSupportAbility
              ? ['damage_reduction' => 0, 'outcome' => null]
              : $this->consumeOneAttackDefenseStacks($playerStatuses, $targetId);
            $outcome = $isSupportAbility
              ? $this->deriveSupportOutcome($abilityRegistry, $abilityId, $targetId, $enemyHp, $targetUnit, $dice)
              : $this->deriveActionOutcome(
                $state,
                (int)$enemyActor['attack'],
                (int)$targetUnit['defense'],
                (int)($playerHp[$targetId] ?? (int)$targetUnit['max_hp']),
                (int)$targetUnit['max_hp'],
                $abilityId,
                (int)$dice['dice_modifier'],
                ['damage_flat' => 0, 'below_half_bonus' => 0.0],
                $dice,
                $targetStatuses,
                (array)($enemyActor['pos'] ?? ['x' => 1, 'y' => 1]),
                (array)($targetUnit['pos'] ?? ['x' => 1, 'y' => 1]),
                (array)($enemyActor['formation'] ?? ['w' => 1, 'h' => 1]),
                (array)($targetUnit['formation'] ?? ['w' => 1, 'h' => 1]),
                $abilityRegistry,
                (int)$enemyActor['attack'],
                (int)($stackResolution['damage_reduction'] ?? 0),
              );

            $events[] = [
              'type' => 'action',
              'round' => $round,
              'tick' => $tick,
              'side' => 'enemy',
              'loadout_source' => 'enemy_authored',
              'actor_enemy_slug' => $enemyActorId,
              $targetKey => $targetId,
              'ability_id' => $abilityId,
              'ability_instance_index' => ((int)($ability['equip_order'] ?? 0)) + 1,
              'dice_used' => $dice['dice_used'],
              'dice_rolls' => $dice['dice_rolls'],
              'slot_traces' => $dice['slot_traces'],
              'slot_trace_summary' => $dice['slot_trace_summary'],
              'dice_outcome' => $dice['dice_outcome'],
              'actor_hp_after' => (int)($enemyHp[$enemyActorId] ?? (int)$enemyActor['max_hp']),
              'actor_max_hp' => (int)$enemyActor['max_hp'],
              'target_max_hp' => (int)$targetUnit['max_hp'],
              'targeting_reason' => $targetSelection['reason'],
              'targeting_weights' => $targetSelection['weights'],
              'stack_outcome' => $stackResolution['outcome'],
              ...$outcome,
            ];
            $this->applyOutcomeStatus(
              $playerStatuses,
              $targetId,
              $outcome,
              $round,
              $tick,
            );
            $reactionOutcome = $isSupportAbility
              ? null
              : $this->reflectDebuffToSourceIfNeeded(
                $playerStatuses,
                $enemyStatuses,
                $targetId,
                $enemyActorId,
                $outcome,
                $round,
                $tick
              );
            if ($reactionOutcome !== null) {
              $events[count($events) - 1]['reaction_outcome'] = $reactionOutcome;
            }
            if (!$isSupportAbility) {
              $playerHp[$targetId] = (int)$outcome['target_hp_after'];
              $this->clearSleepOnDamage($playerStatuses, $sleepBlockedUntilTick, $targetId, $tick, (int)$outcome['damage']);
              $preferredTargetByActor['enemy:' . $enemyActorId] = $targetId;
            }
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

      $roundEndTick = $roundStartTick + $ticksPerRound - 1;
      $this->tickStatusDurations($events, 'player', $round, $roundEndTick, $playerStatuses);
      $this->tickStatusDurations($events, 'enemy', $round, $roundEndTick, $enemyStatuses);
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
  private function chooseTargetId(
    string &$state,
    array $aliveIds,
    array $unitsById,
    string $targetPreference,
    ?string $actorId = null,
    array $currentHpByUnitId = []
  ): string
  {
    return $this->chooseTargetSelection(
      $state,
      $aliveIds,
      $unitsById,
      $targetPreference,
      $actorId,
      $currentHpByUnitId
    )['id'];
  }

  /**
   * @param array<int,string> $aliveIds
   * @param array<string,array<string,mixed>> $unitsById
   * @param array<string,int> $currentHpByUnitId
   * @param array<string,array<string,array<string,mixed>>> $statusMap
   * @return array{id:string,reason:?string,weights:array<int,array{id:string,score:int,reasons:array<int,string>}>}
   */
  private function chooseTargetSelection(
    string &$state,
    array $aliveIds,
    array $unitsById,
    string $targetPreference,
    ?string $actorId = null,
    array $currentHpByUnitId = [],
    array $statusMap = [],
    ?string $preferredTargetId = null
  ): array {
    if ($targetPreference === 'self' && $actorId !== null && in_array($actorId, $aliveIds, true)) {
      return [
        'id' => $actorId,
        'reason' => 'self target',
        'weights' => [[
          'id' => $actorId,
          'score' => 1000,
          'reasons' => ['self'],
        ]],
      ];
    }

    if (count($aliveIds) <= 1) {
      $onlyId = (string)($aliveIds[0] ?? '');
      return [
        'id' => $onlyId,
        'reason' => $onlyId === '' ? null : 'only valid target',
        'weights' => $onlyId === '' ? [] : [[
          'id' => $onlyId,
          'score' => 1,
          'reasons' => ['only_target'],
        ]],
      ];
    }

    if ($targetPreference === 'ally_lowest_hp_pct') {
      $bestId = (string)$aliveIds[0];
      $bestRatio = 2.0;
      foreach ($aliveIds as $unitId) {
        $unit = $unitsById[$unitId] ?? [];
        $currentHp = max(0, (int)($currentHpByUnitId[$unitId] ?? ($unit['current_hp'] ?? 0)));
        $maxHp = max(1, (int)($unit['max_hp'] ?? 1));
        $ratio = $currentHp / $maxHp;
        if ($ratio < $bestRatio) {
          $bestId = (string)$unitId;
          $bestRatio = $ratio;
        }
      }

      return [
        'id' => $bestId,
        'reason' => 'lowest ally hp percentage',
        'weights' => [[
          'id' => $bestId,
          'score' => 1000,
          'reasons' => ['lowest_hp_pct'],
        ]],
      ];
    }

    $weights = [];
    $preferFront = str_contains($targetPreference, 'front');
    $preferBack = str_contains($targetPreference, 'back');
    $preferLowestHp = str_contains($targetPreference, 'lowest_hp');
    $preferHighestThreat = str_contains($targetPreference, 'highest_threat');
    $preferWounded = str_contains($targetPreference, 'wounded');
    $preferMarked = str_contains($targetPreference, 'marked');
    $preferDebuffed = str_contains($targetPreference, 'debuffed');
    $preferMostDebuffed = str_contains($targetPreference, 'most_debuffed');
    $preferPreviousTarget = str_contains($targetPreference, 'preferred_previous')
      || str_contains($targetPreference, 'previous_target')
      || str_contains($targetPreference, 'pick_your_mark');

    $bestFrontX = null;
    $bestBackX = null;
    $lowestHp = null;
    $highestThreat = null;
    foreach ($aliveIds as $unitId) {
      $unit = $unitsById[$unitId] ?? [];
      $profile = $this->combatPositionProfile($unit);
      $currentHp = max(0, (int)($currentHpByUnitId[$unitId] ?? ($unit['current_hp'] ?? 0)));
      $threat = (int)($unit['attack'] ?? 0) + (int)($unit['defense'] ?? 0);
      $bestFrontX = $bestFrontX === null ? $profile['front_x'] : max($bestFrontX, $profile['front_x']);
      $bestBackX = $bestBackX === null ? $profile['back_x'] : min($bestBackX, $profile['back_x']);
      $lowestHp = $lowestHp === null ? $currentHp : min($lowestHp, $currentHp);
      $highestThreat = $highestThreat === null ? $threat : max($highestThreat, $threat);
    }

    foreach ($aliveIds as $unitId) {
      $unit = $unitsById[$unitId] ?? [];
      $profile = $this->combatPositionProfile($unit);
      $currentHp = max(0, (int)($currentHpByUnitId[$unitId] ?? ($unit['current_hp'] ?? 0)));
      $statuses = (array)($statusMap[$unitId] ?? []);
      $score = 0;
      $reasons = [];

      if ($preferFront && $bestFrontX !== null && $profile['front_x'] === $bestFrontX) {
        $score += 300;
        $reasons[] = 'frontline';
      }
      if ($preferBack && $bestBackX !== null && $profile['back_x'] === $bestBackX) {
        $score += 300;
        $reasons[] = 'backline';
      }
      if ($preferLowestHp && $lowestHp !== null && $currentHp === $lowestHp) {
        $score += 275;
        $reasons[] = 'low_hp';
      }
      if ($preferHighestThreat && $highestThreat !== null) {
        $threat = (int)($unit['attack'] ?? 0) + (int)($unit['defense'] ?? 0);
        if ($threat === $highestThreat) {
          $score += 275;
          $reasons[] = 'high_threat';
        }
      }
      if ($preferWounded && $this->isWounded($currentHp, (int)($unit['max_hp'] ?? 1))) {
        $score += 250;
        $reasons[] = 'wounded';
      }
      if ($preferMarked && isset($statuses['marked'])) {
        $score += 260;
        $reasons[] = 'marked';
      }

      $debuffTypes = $this->countDistinctDebuffTypes($statuses);
      if ($preferDebuffed && $debuffTypes > 0) {
        $score += 220 + ($debuffTypes * 10);
        $reasons[] = 'debuffed';
      }
      if ($preferMostDebuffed && $debuffTypes > 0) {
        $score += 200 + ($debuffTypes * 25);
        $reasons[] = sprintf('most_debuffed:%d', $debuffTypes);
      }
      if ($preferPreviousTarget && $preferredTargetId !== null && $unitId === $preferredTargetId) {
        $score += 290;
        $reasons[] = 'preferred_previous_target';
      }

      $weights[] = [
        'id' => (string)$unitId,
        'score' => $score,
        'reasons' => $reasons,
      ];
    }

    $maxScore = max(array_map(static fn(array $entry): int => (int)$entry['score'], $weights));
    $bestCandidates = array_values(array_filter(
      $weights,
      static fn(array $entry): bool => (int)$entry['score'] === $maxScore
    ));
    $winner = $bestCandidates[$this->nextInt($state, count($bestCandidates))];

    return [
      'id' => (string)$winner['id'],
      'reason' => count((array)$winner['reasons']) > 0 ? implode(', ', (array)$winner['reasons']) : 'deterministic tie-break',
      'weights' => $weights,
    ];
  }

  private function isSupportTargetPreference(string $targetPreference): bool
  {
    return $targetPreference === 'self' || str_starts_with($targetPreference, 'ally_');
  }

  private function isWounded(int $currentHp, int $maxHp): bool
  {
    return $maxHp > 0 && $currentHp <= (int)floor($maxHp * 0.3);
  }

  private function halfDieValue(int $dieValue): int
  {
    return (int)ceil(max(0, $dieValue) / 2);
  }

  /**
   * @param array<string,array<string,mixed>> $statuses
   */
  private function countDistinctDebuffTypes(array $statuses): int
  {
    $count = 0;
    foreach ($statuses as $statusId => $statusState) {
      if (!$this->isDebuffStatus((string)$statusId, is_array($statusState) ? $statusState : [])) {
        continue;
      }

      $count += 1;
      $bonusTypes = max(0, (int)($statusState['params']['counts_as_extra_debuff_type'] ?? 0));
      $count += $bonusTypes;
    }

    return $count;
  }

  /**
   * @param array<string,mixed> $statusState
   */
  private function isDebuffStatus(string $statusId, array $statusState = []): bool
  {
    if ((bool)($statusState['params']['is_debuff'] ?? false) === true) {
      return true;
    }

    return !in_array($statusId, [
      'bolstered',
      'guard_stacks',
      'brawl_hardened_stacks',
      'spiteful_reflex',
      'counterpunch_ready',
      'shield_set',
    ], true);
  }

  /**
   * @param array<string,array<string,array<string,mixed>>> $statusMap
   * @return array{damage_reduction:int,outcome:?string}
   */
  private function consumeOneAttackDefenseStacks(array &$statusMap, string $unitId): array
  {
    $statuses = (array)($statusMap[$unitId] ?? []);
    $consumedStatuses = [];
    $damageReduction = 0;

    foreach ($statuses as $statusId => $statusState) {
      $params = is_array($statusState['params'] ?? null) ? $statusState['params'] : [];
      if ((bool)($params['consumes_on_next_attack'] ?? false) !== true) {
        continue;
      }

      $stackCount = max(0, (int)($params['stack_count'] ?? 0));
      if ($stackCount <= 0) {
        continue;
      }

      $perStackReduction = max(0, (int)($params['per_stack_damage_reduction'] ?? 0));
      $damageReduction += $stackCount * $perStackReduction;
      $consumedStatuses[] = sprintf('%s x%d', $statusId, $stackCount);
      unset($statusMap[$unitId][$statusId]);
    }

    return [
      'damage_reduction' => $damageReduction,
      'outcome' => count($consumedStatuses) > 0
        ? sprintf('consumed %s for -%d damage', implode(', ', $consumedStatuses), $damageReduction)
        : null,
    ];
  }

  /**
   * @param array<string,array<string,array<string,mixed>>> $statusMap
   */
  private function applyOneAttackDefenseStacks(
    array &$statusMap,
    string $unitId,
    string $statusId,
    int $addedStacks,
    int $perStackDamageReduction,
    int $maxStacks,
    int $tick
  ): void {
    $existing = (array)($statusMap[$unitId][$statusId]['params'] ?? []);
    $stackCount = min(
      max(1, $maxStacks),
      max(0, (int)($existing['stack_count'] ?? 0)) + max(0, $addedStacks)
    );

    $this->applyStatusState(
      $statusMap,
      $unitId,
      $statusId,
      99,
      [
        'stack_count' => $stackCount,
        'per_stack_damage_reduction' => max(0, $perStackDamageReduction),
        'consumes_on_next_attack' => true,
        'is_debuff' => false,
      ],
      1,
      $tick
    );
  }

  /**
   * @param array<string,array<string,array<string,mixed>>> $defenderStatuses
   * @param array<string,array<string,array<string,mixed>>> $attackerStatuses
   * @param array{status_applied?:mixed,status_duration_rounds?:mixed,status_params?:mixed} $outcome
   */
  private function reflectDebuffToSourceIfNeeded(
    array &$defenderStatuses,
    array &$attackerStatuses,
    string $defenderId,
    string $attackerId,
    array $outcome,
    int $round,
    int $tick
  ): ?string {
    $statusId = trim((string)($outcome['status_applied'] ?? ''));
    if ($statusId === '') {
      return null;
    }

    $statusParams = is_array($outcome['status_params'] ?? null) ? $outcome['status_params'] : [];
    if (!$this->isDebuffStatus($statusId, ['params' => $statusParams])) {
      return null;
    }
    if ((bool)($statusParams['reflected'] ?? false) === true) {
      return null;
    }

    $reflexState = (array)($defenderStatuses[$defenderId]['spiteful_reflex'] ?? []);
    if ($reflexState === []) {
      return null;
    }

    $lastTriggerRound = (int)($reflexState['params']['last_trigger_round'] ?? 0);
    if ($lastTriggerRound === $round) {
      return 'spiteful_reflex ready but already triggered this round';
    }

    $defenderStatuses[$defenderId]['spiteful_reflex']['params']['last_trigger_round'] = $round;
    $this->applyStatusState(
      $attackerStatuses,
      $attackerId,
      $statusId,
      max(1, (int)($outcome['status_duration_rounds'] ?? 1)),
      array_replace($statusParams, ['reflected' => true, 'is_debuff' => true]),
      $round,
      $tick
    );

    return sprintf('spiteful_reflex reflected %s back to attacker', $statusId);
  }

  /**
   * @param array{x:int,y:int} $attackerPos
   * @param array{x:int,y:int} $targetPos
   * @param array{w:int,h:int} $attackerFormation
   * @param array{w:int,h:int} $targetFormation
   */
  private function resolvePositionMultiplier(
    string $abilityId,
    array $attackerPos,
    array $targetPos,
    array $attackerFormation,
    array $targetFormation,
    AbilityRegistry $abilityRegistry
  ): float {
    if (!$abilityRegistry->has($abilityId)) {
      return 1.0;
    }

    $def = $abilityRegistry->get($abilityId);
    $isMelee = in_array('melee', $def->tags, true);
    $multiplier = 1.0;

    if ($isMelee && $this->isFrontRow($attackerPos, $attackerFormation)) {
      $multiplier *= 1.10;
    }
    if ($this->isFrontRow($targetPos, $targetFormation)) {
      $multiplier *= 1.10;
    }
    if ($isMelee && $this->isBackRow($targetPos, $targetFormation)) {
      $multiplier *= 0.90;
    }

    return $multiplier;
  }

  /**
   * @param array{x:int,y:int} $pos
   * @param array{w:int,h:int} $formation
   */
  private function isFrontRow(array $pos, array $formation): bool
  {
    $profile = $this->combatPositionProfile([
      'pos' => $pos,
      'formation' => $formation,
    ]);
    return $profile['front_x'] >= 2;
  }

  /**
   * @param array{x:int,y:int} $pos
   * @param array{w:int,h:int} $formation
   */
  private function isBackRow(array $pos, array $formation): bool
  {
    $profile = $this->combatPositionProfile([
      'pos' => $pos,
      'formation' => $formation,
    ]);
    return $profile['back_x'] <= 0;
  }

  /**
   * @param array{damage_flat:int,below_half_bonus:float} $combatAffixes
   * @param array{dice_used:array<int,array{kind:string,dice_instance_id:?string,sides:int}>,dice_rolls:array<int,array{sides:int,roll:int}>,dice_outcome:string,dice_modifier:int,explode_triggered:bool} $diceContext
   * @param array{x:int,y:int} $attackerPos
   * @param array{x:int,y:int} $targetPos
   * @param array{w:int,h:int} $attackerFormation
   * @param array{w:int,h:int} $targetFormation
   * @param array<string,array<string,mixed>> $targetStatuses
   * @return array{damage:int,target_hp_after:int,outcome:string,status_applied:?string,status_duration_rounds:?int,ability_outcome:string,affix_outcome:?string,status_params?:array<string,mixed>}
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
    array $targetStatuses,
    array $attackerPos,
    array $targetPos,
    array $attackerFormation,
    array $targetFormation,
    AbilityRegistry $abilityRegistry,
    int $statusSourceAttack,
    int $flatDamageReduction = 0,
  ): array {
    $effectiveDefense = $this->effectiveDefenseWithStatuses($targetDefense, $targetStatuses);
    $variance = $this->nextInt($state, 5) - 2;
    $rawDamage = (int)floor(($attackerAttack * 0.65) - ($effectiveDefense * 0.35)) + $variance + $diceModifier;
    $rawDamage += max(0, (int)($combatAffixes['damage_flat'] ?? 0));

    $affixOutcomeParts = [];
    if ($effectiveDefense !== $targetDefense) {
      $affixOutcomeParts[] = sprintf('defense buffed to %d', $effectiveDefense);
    }
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

    $positionMultiplier = $this->resolvePositionMultiplier(
      $abilityId,
      $attackerPos,
      $targetPos,
      $attackerFormation,
      $targetFormation,
      $abilityRegistry
    );
    if (abs($positionMultiplier - 1.0) > 0.0001) {
      $rawDamage = (int)floor($rawDamage * $positionMultiplier);
      $affixOutcomeParts[] = sprintf('position x%s', rtrim(rtrim(number_format($positionMultiplier, 2, '.', ''), '0'), '.'));
    }

    $damageTakenMultiplier = $this->damageTakenMultiplierFromStatuses($targetStatuses);
    if (abs($damageTakenMultiplier - 1.0) > 0.0001) {
      $rawDamage = (int)floor($rawDamage * $damageTakenMultiplier);
      $affixOutcomeParts[] = sprintf('bleeding x%s', rtrim(rtrim(number_format($damageTakenMultiplier, 2, '.', ''), '0'), '.'));
    }

    $debuffBonusPerType = max(0, (int)($combatAffixes['bonus_damage_per_debuff_type'] ?? 0));
    if ($debuffBonusPerType > 0) {
      $debuffTypeCap = max(1, (int)($combatAffixes['debuff_type_cap'] ?? 3));
      $debuffTypes = min($debuffTypeCap, $this->countDistinctDebuffTypes($targetStatuses));
      if ($debuffTypes > 0) {
        $debuffBonus = $debuffTypes * $debuffBonusPerType;
        $rawDamage += $debuffBonus;
        $affixOutcomeParts[] = sprintf('+%d damage from %d debuff types', $debuffBonus, $debuffTypes);
      }
    }

    if ($flatDamageReduction > 0) {
      $rawDamage -= $flatDamageReduction;
      $affixOutcomeParts[] = sprintf('one-attack stacks reduced damage by %d', $flatDamageReduction);
    }

    $damage = max(1, $rawDamage);
    $nextHp = max(0, $targetHp - $damage);
    $status = $this->pickStatusEffect($state, $abilityId);
    $statusApplication = $this->deriveStatusApplication(
      $abilityRegistry,
      $abilityId,
      $status,
      $diceContext,
      $statusSourceAttack
    );
    $statusDuration = $statusApplication['duration_rounds'];
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
      'status_params' => $statusApplication['params'],
      'ability_outcome' => implode(', ', $abilityOutcomeParts),
      'affix_outcome' => count($affixOutcomeParts) > 0 ? implode(', ', $affixOutcomeParts) : null,
    ];
  }

  /**
   * @param array<string,int> $currentHpByUnitId
   * @param array<string,mixed> $targetUnit
   * @param array{dice_used:array<int,array{kind:string,dice_instance_id:?string,sides:int}>,dice_rolls:array<int,array{sides:int,roll:int}>,slot_traces?:array<int,array<string,mixed>>,dice_outcome:string,dice_modifier:int,explode_triggered:bool} $diceContext
   * @return array{damage:int,target_hp_after:int,outcome:string,status_applied:?string,status_duration_rounds:?int,ability_outcome:string,affix_outcome:?string,status_params?:array<string,mixed>}
   */
  private function deriveSupportOutcome(
    AbilityRegistry $abilityRegistry,
    string $abilityId,
    string $targetId,
    array $currentHpByUnitId,
    array $targetUnit,
    array $diceContext,
  ): array {
    $status = $this->supportStatusEffect($abilityId);
    $statusApplication = $this->deriveStatusApplication(
      $abilityRegistry,
      $abilityId,
      $status,
      $diceContext,
      0
    );
    $statusDuration = $statusApplication['duration_rounds'];
    $targetHp = (int)($currentHpByUnitId[$targetId] ?? (int)($targetUnit['max_hp'] ?? 0));

    $abilityOutcomeParts = [];
    if ($status !== null) {
      $abilityOutcomeParts[] = $statusDuration !== null
        ? sprintf('%s applied for %d rounds', $status, $statusDuration)
        : sprintf('%s applied', $status);
    } else {
      $abilityOutcomeParts[] = 'support effect applied';
    }

    return [
      'damage' => 0,
      'target_hp_after' => $targetHp,
      'outcome' => 'buffed',
      'status_applied' => $status,
      'status_duration_rounds' => $statusDuration,
      'status_params' => $statusApplication['params'],
      'ability_outcome' => implode(', ', $abilityOutcomeParts),
      'affix_outcome' => null,
    ];
  }

  /**
   * @param array<int,array<string,mixed>> $events
   * @param array<string,int> $hpByUnitId
   * @param array<string,array<string,mixed>> $unitsById
   * @param array<string,array<string,array<string,mixed>>> $statusMap
   * @param array<string,int> $sleepBlockedUntilTick
   */
  private function processStatusPhase(
    array &$events,
    string $side,
    int $round,
    int $tick,
    array &$hpByUnitId,
    array $unitsById,
    array &$statusMap,
    array &$sleepBlockedUntilTick
  ): void {
    $events[] = [
      'type' => 'phase_start',
      'round' => $round,
      'tick' => $tick,
      'phase' => sprintf('%s_status', $side),
    ];

    foreach ($hpByUnitId as $unitId => $currentHp) {
      if ((int)$currentHp <= 0) {
        continue;
      }

      $statuses = $statusMap[$unitId] ?? [];
      if (!isset($statuses['poison'])) {
        continue;
      }

      $poison = is_array($statuses['poison']) ? $statuses['poison'] : [];
      $statusSpeed = max(1, (int)($poison['params']['status_speed'] ?? 5));
      if ($tick % $statusSpeed !== 0) {
        continue;
      }

      $unit = $unitsById[$unitId] ?? null;
      if (!is_array($unit)) {
        continue;
      }

      $sourceAttack = max(1, (int)($poison['params']['source_attack'] ?? 1));
      $damageRatio = max(0.0, (float)($poison['params']['poison_damage_ratio'] ?? 0.2));
      $damage = max(1, (int)floor($sourceAttack * $damageRatio));
      $damage = (int)floor($damage * $this->specialFrontTakenMultiplier($unit));
      $damage = (int)floor($damage * $this->damageTakenMultiplierFromStatuses($statuses));
      $damage = max(1, $damage);

      $nextHp = max(0, (int)$currentHp - $damage);
      $hpByUnitId[$unitId] = $nextHp;
      $this->clearSleepOnDamage($statusMap, $sleepBlockedUntilTick, (string)$unitId, $tick, $damage);

      $events[] = [
        'type' => 'status_tick',
        'round' => $round,
        'tick' => $tick,
        'side' => $side,
        $side === 'player' ? 'actor_unit_instance_id' : 'actor_enemy_slug' => (string)$unitId,
        'status_id' => 'poison',
        'damage' => $damage,
        'target_hp_after' => $nextHp,
        'outcome' => $nextHp <= 0 ? 'defeated' : 'ticked',
        'ability_outcome' => sprintf('poison dealt %d damage', $damage),
      ];
    }
  }

  /**
   * @param array<int,array<string,mixed>> $events
   * @param array<string,array<string,array<string,mixed>>> $statusMap
   */
  private function tickStatusDurations(array &$events, string $side, int $round, int $tick, array &$statusMap): void
  {
    foreach ($statusMap as $unitId => &$statuses) {
      foreach ($statuses as $statusId => &$statusState) {
        $remaining = (int)($statusState['duration_rounds'] ?? 0);
        if ($remaining <= 0) {
          continue;
        }

        $remaining -= 1;
        $statusState['duration_rounds'] = $remaining;
        if ($remaining <= 0) {
          unset($statuses[$statusId]);
          $events[] = [
            'type' => 'status_expired',
            'round' => $round,
            'tick' => $tick,
            'side' => $side,
            $side === 'player' ? 'actor_unit_instance_id' : 'actor_enemy_slug' => (string)$unitId,
            'status_id' => $statusId,
          ];
        }
      }
      unset($statusState);
    }
    unset($statuses);
  }

  /**
   * @param array<string,array<string,array<string,mixed>>> $statusMap
   * @param array{status_applied?:mixed,status_duration_rounds?:mixed,status_params?:mixed} $outcome
   */
  private function applyOutcomeStatus(array &$statusMap, string $targetId, array $outcome, int $round, int $tick): void
  {
    $statusId = isset($outcome['status_applied']) ? (string)$outcome['status_applied'] : '';
    if ($statusId === '') {
      return;
    }

    $duration = (int)($outcome['status_duration_rounds'] ?? 0);
    $params = is_array($outcome['status_params'] ?? null) ? $outcome['status_params'] : [];
    $this->applyStatusState($statusMap, $targetId, $statusId, $duration, $params, $round, $tick);
  }

  /**
   * @param array<string,array<string,array<string,mixed>>> $statusMap
   * @param array<string,mixed> $params
   */
  private function applyStatusState(
    array &$statusMap,
    string $unitId,
    string $statusId,
    int $durationRounds,
    array $params,
    int $round,
    int $tick
  ): void {
    $durationRounds = max(1, $durationRounds);
    $current = $statusMap[$unitId][$statusId] ?? null;
    if (!is_array($current)) {
      $statusMap[$unitId][$statusId] = [
        'duration_rounds' => $durationRounds,
        'params' => $params,
        'last_trigger_round' => 0,
        'applied_tick' => $tick,
        'applied_round' => $round,
      ];
      return;
    }

    $statusMap[$unitId][$statusId]['duration_rounds'] = max($durationRounds, (int)($current['duration_rounds'] ?? 0));
    $statusMap[$unitId][$statusId]['applied_tick'] = $tick;
    $statusMap[$unitId][$statusId]['applied_round'] = $round;

    if ($statusId === 'bolstered') {
      $existing = (float)($current['params']['defense_pct'] ?? 0.0);
      $next = max($existing, (float)($params['defense_pct'] ?? 0.0));
      $statusMap[$unitId][$statusId]['params'] = ['defense_pct' => $next];
      return;
    }

    if ($statusId === 'bleeding') {
      $existing = (float)($current['params']['damage_taken_pct'] ?? 0.0);
      $next = max($existing, (float)($params['damage_taken_pct'] ?? 0.0));
      $statusMap[$unitId][$statusId]['params'] = ['damage_taken_pct' => $next];
      return;
    }

    $statusMap[$unitId][$statusId]['params'] = array_replace(
      is_array($current['params'] ?? null) ? $current['params'] : [],
      $params
    );
  }

  /**
   * @param array<string,array<string,array<string,mixed>>> $statusMap
   * @param array<string,int> $sleepBlockedUntilTick
   */
  private function clearSleepOnDamage(
    array &$statusMap,
    array &$sleepBlockedUntilTick,
    string $targetId,
    int $tick,
    int $damage
  ): void {
    if ($damage <= 0 || !isset($statusMap[$targetId]['sleep'])) {
      return;
    }

    unset($statusMap[$targetId]['sleep']);
    $sleepBlockedUntilTick[$targetId] = max($tick, (int)($sleepBlockedUntilTick[$targetId] ?? 0));
  }

  /**
   * @param array<string,array<string,array<string,mixed>>> $statusMap
   * @param array<string,int> $sleepBlockedUntilTick
   */
  private function isUnitAsleepForTick(array $statusMap, array $sleepBlockedUntilTick, string $unitId, int $tick): bool
  {
    if (((int)($sleepBlockedUntilTick[$unitId] ?? 0)) >= $tick) {
      return true;
    }

    return isset($statusMap[$unitId]['sleep']);
  }

  /**
   * @param array<string,array<string,mixed>> $targetStatuses
   */
  private function effectiveDefenseWithStatuses(int $targetDefense, array $targetStatuses): int
  {
    $bolsteredPct = (float)($targetStatuses['bolstered']['params']['defense_pct'] ?? 0.0);
    if ($bolsteredPct <= 0) {
      return $targetDefense;
    }

    return max(0, (int)floor($targetDefense * (1 + $bolsteredPct)));
  }

  /**
   * @param array<string,array<string,mixed>> $targetStatuses
   */
  private function damageTakenMultiplierFromStatuses(array $targetStatuses): float
  {
    return 1.0 + max(0.0, (float)($targetStatuses['bleeding']['params']['damage_taken_pct'] ?? 0.0));
  }

  /**
   * @param array<string,mixed> $unit
   */
  private function specialFrontTakenMultiplier(array $unit): float
  {
    $profile = $this->combatPositionProfile($unit);
    return $profile['front_x'] >= 2 ? 1.10 : 1.0;
  }

  /**
   * @param array{slot_traces?:array<int,array<string,mixed>>,dice_rolls?:array<int,array{sides:int,roll:int}>} $diceContext
   * @return array{duration_rounds:?int,params:array<string,mixed>}
   */
  private function deriveStatusApplication(
    AbilityRegistry $abilityRegistry,
    string $abilityId,
    ?string $status,
    array $diceContext,
    int $sourceAttack
  ): array {
    if ($status === null) {
      return ['duration_rounds' => null, 'params' => []];
    }

    $duration = $this->deriveStatusDurationRounds($abilityRegistry, $abilityId, $status);
    $params = $abilityRegistry->has($abilityId) ? (array)$abilityRegistry->get($abilityId)->defaultParams : [];
    $rollTotal = $this->diceRollTotal($diceContext);

    return match ($status) {
      'bolstered' => [
        'duration_rounds' => $duration,
        'params' => ['defense_pct' => 0.20 + ($rollTotal * 0.01), 'is_debuff' => false],
      ],
      'bleeding' => [
        'duration_rounds' => $duration,
        'params' => ['damage_taken_pct' => 0.20 + ($rollTotal * 0.01), 'is_debuff' => true],
      ],
      'poison' => [
        'duration_rounds' => $duration,
        'params' => [
          'poison_damage_ratio' => (float)($params['poison_damage_ratio'] ?? 0.2),
          'status_speed' => (int)($params['status_speed'] ?? 5),
          'source_attack' => max(1, $sourceAttack),
          'is_debuff' => true,
        ],
      ],
      'sleep' => [
        'duration_rounds' => $duration,
        'params' => ['is_debuff' => true],
      ],
      default => [
        'duration_rounds' => $duration,
        'params' => ['is_debuff' => true],
      ],
    };
  }

  /**
   * @param array{slot_traces?:array<int,array<string,mixed>>,dice_rolls?:array<int,array{sides:int,roll:int}>} $diceContext
   */
  private function diceRollTotal(array $diceContext): int
  {
    $slotTraces = is_array($diceContext['slot_traces'] ?? null) ? $diceContext['slot_traces'] : [];
    if (count($slotTraces) > 0) {
      $total = 0;
      foreach ($slotTraces as $trace) {
        $total += (int)($trace['roll_total'] ?? 0);
      }
      return max(0, $total);
    }

    $diceRolls = is_array($diceContext['dice_rolls'] ?? null) ? $diceContext['dice_rolls'] : [];
    $total = 0;
    foreach ($diceRolls as $entry) {
      $total += (int)($entry['roll'] ?? 0);
    }

    return max(0, $total);
  }

  /**
   * @param array<string,mixed> $unit
   * @return array{front_x:int,back_x:int,top_y:int,bottom_y:int}
   */
  private function combatPositionProfile(array $unit): array
  {
    $pos = is_array($unit['pos'] ?? null) ? $unit['pos'] : ['x' => 1, 'y' => 1];
    $formation = is_array($unit['formation'] ?? null) ? $unit['formation'] : ['w' => 1, 'h' => 1];
    $positions = $this->combatOccupiedPositions($pos, $formation);

    $frontX = max(array_map(static fn(array $cell): int => (int)$cell['x'], $positions));
    $backX = min(array_map(static fn(array $cell): int => (int)$cell['x'], $positions));
    $topY = min(array_map(static fn(array $cell): int => (int)$cell['y'], $positions));
    $bottomY = max(array_map(static fn(array $cell): int => (int)$cell['y'], $positions));

    return [
      'front_x' => $frontX,
      'back_x' => $backX,
      'top_y' => $topY,
      'bottom_y' => $bottomY,
    ];
  }

  /**
   * @param array{x:int,y:int} $pos
   * @param array{w:int,h:int} $formation
   * @return list<array{x:int,y:int}>
   */
  private function combatOccupiedPositions(array $pos, array $formation): array
  {
    $anchorX = max(0, min(2, (int)($pos['x'] ?? 1)));
    $anchorY = max(0, min(2, (int)($pos['y'] ?? 1)));
    $width = max(1, min(3, (int)($formation['w'] ?? 1)));
    $height = max(1, min(3, (int)($formation['h'] ?? 1)));
    $positions = [];

    for ($rowOffset = 0; $rowOffset < $height; $rowOffset += 1) {
      for ($colOffset = 0; $colOffset < $width; $colOffset += 1) {
        $x = $anchorX - $colOffset;
        $y = $anchorY + $rowOffset;
        if ($x < 0 || $x > 2 || $y < 0 || $y > 2) {
          continue;
        }
        $positions[] = ['x' => $x, 'y' => $y];
      }
    }

    if (count($positions) === 0) {
      return [['x' => $anchorX, 'y' => $anchorY]];
    }

    return $positions;
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
      'bleeding' => 2,
      'guard_up' => 2,
      default => null,
    };
  }

  private function pickStatusEffect(string &$state, string $abilityId): ?string
  {
    $ability = strtolower($abilityId);
    if (str_contains($ability, 'bolster') || str_contains($ability, 'shield')) {
      return 'bolstered';
    }
    if (str_contains($ability, 'poison')) {
      return 'poison';
    }
    if (str_contains($ability, 'sleep')) {
      return 'sleep';
    }

    // Low deterministic chance to apply bleeding on generic attacks.
    if ($this->nextInt($state, 10) === 0) {
      return 'bleeding';
    }

    return null;
  }

  private function supportStatusEffect(string $abilityId): ?string
  {
    $ability = strtolower($abilityId);
    if (str_contains($ability, 'bolster') || str_contains($ability, 'shield')) {
      return 'bolstered';
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

    if (array_key_exists('active', $abilityListRaw) || array_key_exists('actives', $abilityListRaw)) {
      $nested = $abilityListRaw['active'] ?? $abilityListRaw['actives'] ?? [];
      return $this->decodeAbilityIdList($nested);
    }

    $out = [];
    foreach ($abilityListRaw as $abilityId) {
      if (is_array($abilityId)) {
        foreach ($this->decodeAbilityIdList($abilityId) as $nestedId) {
          $out[] = $nestedId;
        }
        continue;
      }

      $id = trim((string)$abilityId);
      if ($id !== '') {
        $out[] = $id;
      }
    }

    return array_values(array_unique($out));
  }

  private function schemaHasTable(string $table): bool
  {
    $cacheKey = 'table:' . $table;
    if (array_key_exists($cacheKey, $this->schemaPresenceCache)) {
      return $this->schemaPresenceCache[$cacheKey];
    }

    $stmt = $this->pdo->prepare('
      SELECT COUNT(*)
      FROM INFORMATION_SCHEMA.TABLES
      WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = ?
    ');
    $stmt->execute([$table]);
    $exists = ((int)$stmt->fetchColumn()) > 0;
    $this->schemaPresenceCache[$cacheKey] = $exists;
    return $exists;
  }

  private function schemaHasColumn(string $table, string $column): bool
  {
    $cacheKey = 'column:' . $table . ':' . $column;
    if (array_key_exists($cacheKey, $this->schemaPresenceCache)) {
      return $this->schemaPresenceCache[$cacheKey];
    }

    $stmt = $this->pdo->prepare('
      SELECT COUNT(*)
      FROM INFORMATION_SCHEMA.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = ?
        AND COLUMN_NAME = ?
    ');
    $stmt->execute([$table, $column]);
    $exists = ((int)$stmt->fetchColumn()) > 0;
    $this->schemaPresenceCache[$cacheKey] = $exists;
    return $exists;
  }

  private function pickUnitTypeSlug(int $userId, string &$state): ?string
  {
    $unlockService = new UserUnlockService($this->pdo);
    $unlockedSlugs = $unlockService->listUnlockedKeys($userId, UserUnlockService::NAMESPACE_UNIT_TYPE);
    if (count($unlockedSlugs) === 0) {
      return null;
    }

    $placeholders = implode(',', array_fill(0, count($unlockedSlugs), '?'));
    $stmt = $this->pdo->prepare("SELECT `slug` FROM `unit_types` WHERE RIGHT(`slug`, 3) = '_t1' AND `slug` IN ($placeholders) ORDER BY `id` ASC");
    $stmt->execute($unlockedSlugs);
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
