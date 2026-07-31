<?php
declare(strict_types=1);

/**
 * File: C:\xampp\htdocs\dice-goblin\backend\src\Combat\Engine\DeterministicRunNodeResolver.php
 * Purpose: Project PHP module.
 */

namespace DiceGoblins\Combat\Engine;

use DiceGoblins\Combat\Abilities\AbilityRegistry;
use DiceGoblins\Combat\Abilities\AbilityType;
use DiceGoblins\Services\EncounterPrimitiveCatalog;
use DiceGoblins\Services\RunCombatModifierService;
use DiceGoblins\Services\SpliceVariantService;
use DiceGoblins\Services\UnitProgressionService;
use DiceGoblins\Services\UserUnlockService;
use DiceGoblins\Support\FormationGeometry;
use PDO;
use RuntimeException;

final class DeterministicRunNodeResolver
{
  private const MAX_COMBAT_ROUNDS = 200;

  /** @var array<string,bool> */
  private array $schemaPresenceCache = [];

  public function __construct(private readonly PDO $pdo)
  {
  }

  /**
   * @param array{id:string,seed:string} $run
   * @param array{id:string,node_type:string,encounter_template_id:?string,meta_json?:?string} $node
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
    $nodeMeta = $this->decodeJsonObject($node['meta_json'] ?? null);
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

    if (in_array($nodeType, ['rest', 'loot', 'hazard', 'shrine'], true)) {
      $rounds = 0;
      $ticks = 0;
      $outcome = 'victory';
      $xpTotal = 0;
      $quality = isset($nodeMeta['node_quality_tier']) ? (string)$nodeMeta['node_quality_tier'] : 'good';
      $effectSlug = isset($nodeMeta['encounter_effect_slug']) ? (string)$nodeMeta['encounter_effect_slug'] : null;
      $hazardSeverity = isset($nodeMeta['encounter_severity']) ? (string)$nodeMeta['encounter_severity'] : $quality;
      $nodeEffect = (new EncounterPrimitiveCatalog())->resolveNodeEffect(
        $nodeType,
        fn(int $max): int => $this->nextInt($rngState, $max),
        $effectSlug,
        [
          'region_slug' => $this->regionSlugForRun((int)$run['region_id']),
          'quality' => $quality,
          'severity' => $hazardSeverity,
        ]
      );
      $currencySoft = (int)$nodeEffect['currency_soft'];
      $events = [[
        'type' => 'node_effect',
        'round' => 0,
        'tick' => 0,
        'node_type' => $nodeType,
        'message' => (string)$nodeEffect['message'],
        'effect_slug' => (string)$nodeEffect['slug'],
        'primitive' => (string)$nodeEffect['primitive'],
        'quality' => $quality,
        'label' => match ($nodeType) {
          'hazard' => $this->humanizeId((string)$nodeEffect['slug']),
          'shrine' => $this->humanizeId((string)$nodeEffect['slug']),
          'rest' => 'Full Recovery',
          default => 'Path Cleared',
        },
        'detail' => match ($nodeType) {
          'hazard' => $this->describeNodeEffect($nodeType, $nodeEffect),
          'shrine' => $this->describeNodeEffect($nodeType, $nodeEffect),
          'rest' => 'The squad returns to full health.',
          default => 'The route opens without a fight.',
        },
        ...($nodeType === 'shrine' ? ['shrine_result' => $nodeEffect['result']] : []),
        ...($nodeType === 'hazard' ? ['hazard_result' => $nodeEffect['result']] : []),
      ]];
    } else {
      $difficulty = max(1, (int)$encounter['difficulty_rating']);

      $combatResult = $this->buildCombatEvents(
        $rngState,
        $ticksPerRound,
        $playerUnits,
        $enemyUnits
      );
      $events = $combatResult['events'];

      if ($combatResult['enemy_alive'] === false && $combatResult['player_alive'] === true) {
        $outcome = 'victory';
      } elseif ($combatResult['player_alive'] === false && $combatResult['enemy_alive'] === true) {
        $outcome = 'defeat';
      } else {
        throw new RuntimeException('combat_unresolved');
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
      'item_grants' => [],
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
    if ($nodeType === 'shrine') {
      $firstEvent = is_array($events[0] ?? null) ? $events[0] : [];
      $rewards['encounter_result'] = [
        'family' => 'shrine',
        'primitive' => (string)($firstEvent['primitive'] ?? ''),
        'effect_slug' => (string)($firstEvent['effect_slug'] ?? ''),
        'result' => is_array($firstEvent['shrine_result'] ?? null) ? $firstEvent['shrine_result'] : [],
      ];
    }
    if ($nodeType === 'hazard') {
      $firstEvent = is_array($events[0] ?? null) ? $events[0] : [];
      $rewards['encounter_result'] = [
        'family' => 'hazard',
        'primitive' => (string)($firstEvent['primitive'] ?? ''),
        'effect_slug' => (string)($firstEvent['effect_slug'] ?? ''),
        'result' => is_array($firstEvent['hazard_result'] ?? null) ? $firstEvent['hazard_result'] : [],
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
          $spliceVariantSlug = $this->pickSpliceVariantSlug($userId, $rngState);
          $rewards['unit_grants'][] = [
            'unit_type_slug' => $unitSlug,
            'splice_variant_slug' => $spliceVariantSlug,
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

      foreach ($this->progressionItemGrantsForVictory($nodeType, $enemyUnits) as $itemGrant) {
        $rewards['item_grants'][] = $itemGrant;
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
              'precision' => (int)($u['precision'] ?? 5),
              'resolve' => (int)($u['resolve'] ?? 5),
              'max_hp' => (int)$u['max_hp'],
              'current_hp' => (int)$u['current_hp'],
              'abilities' => $u['abilities'],
              'run_combat_modifiers' => is_array($u['run_combat_modifiers'] ?? null) ? $u['run_combat_modifiers'] : [],
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
              'precision' => (int)($u['precision'] ?? 5),
              'resolve' => (int)($u['resolve'] ?? 5),
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
   * @param array<int,array<string,mixed>> $enemyUnits
   * @return list<array{item_slug:string,quantity:int}>
   */
  private function progressionItemGrantsForVictory(string $nodeType, array $enemyUnits): array
  {
    if (!in_array($nodeType, ['combat', 'boss'], true)) {
      return [];
    }

    $enemySlugs = array_values(array_unique(array_map(
      static fn(array $unit): string => (string)($unit['template_slug'] ?? $unit['slug'] ?? $unit['id'] ?? ''),
      $enemyUnits
    )));
    $hasPigFamily = false;
    $hasMudking = false;
    foreach ($enemySlugs as $slug) {
      if ($slug === '') {
        continue;
      }
      if (str_contains($slug, 'pig') || str_contains($slug, 'boar') || $slug === 'mudking') {
        $hasPigFamily = true;
      }
      if ($slug === 'mudking') {
        $hasMudking = true;
      }
    }

    $grants = [];
    if ($hasPigFamily) {
      $grants[] = ['item_slug' => 'pig_ear', 'quantity' => $nodeType === 'boss' ? 2 : 1];
    }
    if ($hasMudking && $nodeType === 'boss') {
      $grants[] = ['item_slug' => 'mudking_crown_fragment', 'quantity' => 1];
    }

    return $grants;
  }

  private function regionSlugForRun(int $regionId): string
  {
    if ($regionId <= 0) {
      return '';
    }

    $stmt = $this->pdo->prepare('SELECT `slug` FROM `regions` WHERE `id` = ? LIMIT 1');
    $stmt->execute([$regionId]);
    $slug = $stmt->fetchColumn();
    return is_string($slug) ? $slug : '';
  }

  /**
   * @return array<int, array{
   *   id:string,
   *   pos:array{x:int,y:int},
   *   formation:array{w:int,h:int},
   *   attack:int,
   *   defense:int,
   *   precision:int,
   *   resolve:int,
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
    $abilityRegistry = new AbilityRegistry();
    $stmt = $this->pdo->prepare('
      SELECT
        ui.`id` AS unit_instance_id,
        ui.`unit_type_id`,
        ui.`splice_variant_slug`,
        sv.`stat_modifiers_json` AS `splice_stat_modifiers_json`,
        ui.`level`,
        ut.`base_stats_json`,
        ut.`ability_set_json`,
        ut.`attack_per_level`,
        ut.`defense_per_level`,
        ut.`max_hp_per_level`,
        ut.`precision_per_level`,
        ut.`resolve_per_level`,
        rus.`current_hp` AS `run_current_hp`,
        rus.`status_effects_json` AS `run_status_effects_json`
      FROM `team_units` tu
      JOIN `unit_instances` ui ON ui.`id` = tu.`unit_instance_id`
      JOIN `unit_types` ut ON ut.`id` = ui.`unit_type_id`
      LEFT JOIN `splice_variants` sv ON sv.`slug` = ui.`splice_variant_slug`
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
      $baseStats = $this->applySpliceStatModifiers(
        $this->decodeJsonObject($row['base_stats_json']),
        $this->decodeJsonObject($row['splice_stat_modifiers_json'] ?? null)
      );
      $abilitySet = $this->decodeJsonObject($row['ability_set_json']);
      $level = max(1, (int)$row['level']);

      $attack = $progression->totalAttackForLevel($baseStats, $level, (int)$row['attack_per_level']);
      $defense = $progression->totalDefenseForLevel($baseStats, $level, (int)$row['defense_per_level']);
      $maxHp = $progression->maxHpForLevel($baseStats, $level, (int)$row['max_hp_per_level']);
      $precision = $progression->totalPrecisionForLevel($baseStats, $level, (int)$row['precision_per_level']);
      $resolve = $progression->totalResolveForLevel($baseStats, $level, (int)$row['resolve_per_level']);
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
        'unit_type_id' => (string)$row['unit_type_id'],
        'pos' => $pos,
        'formation' => $footprint,
        'attack' => $attack,
        'defense' => $defense,
        'precision' => $precision,
        'resolve' => $resolve,
        'max_hp' => $maxHp,
        'current_hp' => $currentHp,
        'abilities' => $this->flattenActiveAbilityIds($abilitySet),
        'passive_abilities' => $this->flattenPassiveAbilityIds($abilitySet),
        'combat_affixes' => [
          'damage_flat' => 0,
          'below_half_bonus' => 0.0,
        ],
        'run_status_effects' => $this->decodeJsonList($row['run_status_effects_json'] ?? null),
        'run_combat_modifiers' => [],
        'ability_dice' => [],
        'passive_dice' => [],
      ];
    }

    if (count($units) === 0) {
      return $units;
    }

    $unitIds = array_map(static fn(array $u): int => (int)$u['id'], $units);
    $placeholders = implode(',', array_fill(0, count($unitIds), '?'));
    $featureUnlocks = (new UserUnlockService($this->pdo))
      ->listUnlockedKeys($userId, UserUnlockService::NAMESPACE_FEATURE);
    $d4ExplosionUnlocked = in_array(UserUnlockService::FEATURE_D4_EXPLODE, $featureUnlocks, true);
    $unlockedPassiveAbilityIdsByUnit = $this->loadUnlockedPassiveAbilityIdsByUnit($unitIds, $abilityRegistry);
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
          if ($d4ExplosionUnlocked) {
            $this->applyGlobalD4ExplosionUnlockToDie($die);
          }
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
      $unit['passive_abilities'] = array_values(array_unique(array_merge(
        (array)($unit['passive_abilities'] ?? []),
        $unlockedPassiveAbilityIdsByUnit[$unitId] ?? []
      )));

      $abilityDice = [];
      foreach ($diceByUnitAbility[$unitId] ?? [] as $abilityId => $slots) {
        foreach ($slots as $slotIndex => $diceEntries) {
          $abilityDice[(string)$abilityId][(int)$slotIndex] = array_values($diceEntries);
        }
      }

      $unit['ability_dice'] = $abilityDice;
      $unit['passive_dice'] = array_values($passiveDiceByUnitId[$unitId] ?? []);
      $this->applyPassiveAbilityAffixesToUnit($unit, $abilityRegistry);
      $this->applyPassiveDiceAffixesToUnit($unit);
      $unit = (new RunCombatModifierService())->applyModifiersToUnit($unit);
    }
    unset($unit);

    $this->applyFormationPassiveBonusesToUnits($units, $abilityRegistry);

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
     * @return array{difficulty_rating:int,description:string,reward_profile:array<string,mixed>,units:array<int,array{id:string,template_slug:string,pos:array{x:int,y:int},formation:array{w:int,h:int},attack:int,defense:int,max_hp:int,current_hp:int,abilities:array<int,string>,passive_abilities:array<int,string>,combat_affixes:array{damage_flat:int,below_half_bonus:float},ability_dice:array<string,array<int,array{kind:string,dice_instance_id:?string,sides:int,affixes:array<int,array{slug:string,value:float}>}>>,passive_dice:array<int,array{kind:string,dice_instance_id:?string,sides:int,affixes:array<int,array{slug:string,value:float}>}>,xp_reward:int}>}
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
    $abilityRegistry = new AbilityRegistry();
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
      $maxHp = max(1, (int)($baseStats['max_hp'] ?? 1));
      $precision = max(0, (int)($baseStats['precision'] ?? 5));
      $resolve = max(0, (int)($baseStats['resolve'] ?? 5));

      $units[] = [
        'id' => $instanceId,
        'template_slug' => $slug,
        'pos' => $pos,
        'formation' => $footprint,
        'attack' => max(1, (int)($baseStats['attack'] ?? 1)),
        'defense' => max(0, (int)($baseStats['defense'] ?? 0)),
        'precision' => $precision,
        'resolve' => $resolve,
        'max_hp' => $maxHp,
        'current_hp' => $maxHp,
        'abilities' => count($equippedAbilityIds) > 0 ? $equippedAbilityIds : $this->flattenActiveAbilityIds($abilitySet),
        'passive_abilities' => $this->flattenPassiveAbilityIds($abilitySet),
        'combat_affixes' => [
          'damage_flat' => 0,
          'below_half_bonus' => 0.0,
        ],
        'ability_dice' => [],
        'passive_dice' => [],
        'xp_reward' => max(0, (int)$row['xp_reward']),
      ];
    }

    foreach ($units as &$unit) {
      $this->applyPassiveAbilityAffixesToUnit($unit, $abilityRegistry);
      $this->applyPassiveDiceAffixesToUnit($unit);
    }
    unset($unit);

    $this->applyFormationPassiveBonusesToUnits($units, $abilityRegistry);

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
    int $ticksPerRound,
    array $playerUnits,
    array $enemyUnits,
  ): array {
    $events = [[
      'type' => 'battle_start',
      'round' => 0,
      'tick' => 0,
      'player_unit_count' => count($playerUnits),
      'enemy_unit_count' => count($enemyUnits),
      'max_rounds' => self::MAX_COMBAT_ROUNDS,
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
      $playerSchedules[$unitId] = $this->applyPassiveAbilityTargetingPreferencesToSchedule(
        $this->buildActiveAbilitySchedule((array)$unit['abilities'], $abilityRegistry),
        (array)($unit['passive_abilities'] ?? [])
      );
      $playerStatuses[$unitId] = [];
      $this->initializePassiveStatusesForCombat($playerStatuses, $unitId, (array)($unit['passive_abilities'] ?? []));
    }

    $enemyHp = [];
    $enemyById = [];
    $enemySchedules = [];
    $enemyStatuses = [];
    foreach ($enemyUnits as $unit) {
      $unitId = (string)$unit['id'];
      $enemyHp[$unitId] = max(0, min((int)$unit['max_hp'], (int)($unit['current_hp'] ?? $unit['max_hp'])));
      $enemyById[$unitId] = $unit;
      $enemySchedules[$unitId] = $this->applyPassiveAbilityTargetingPreferencesToSchedule(
        $this->buildActiveAbilitySchedule((array)$unit['abilities'], $abilityRegistry),
        (array)($unit['passive_abilities'] ?? [])
      );
      $enemyStatuses[$unitId] = [];
      $this->initializePassiveStatusesForCombat($enemyStatuses, $unitId, (array)($unit['passive_abilities'] ?? []));
    }

    $combatOver = false;
    $lastRound = 0;
    $lastTick = 0;
    $sleepBlockedUntilTick = [];
    $preferredTargetByActor = [];

    for ($round = 1; $round <= self::MAX_COMBAT_ROUNDS; $round++) {
      $playerDamagedThisRound = [];
      $enemyDamagedThisRound = [];
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
            $guardRedirected = false;
            $forcedTargetId = !$isSupportAbility
              ? $this->forcedNextAttackTargetId($playerStatuses, $playerActorId, $enemyHp)
              : null;
            if ($forcedTargetId !== null) {
              $aliveTargetIds = [$forcedTargetId];
            } elseif (!$isSupportAbility) {
              $guardTargetId = $this->forcedGuardTargetId($targetPoolHp, $enemyStatuses);
              if ($guardTargetId !== null) {
                $aliveTargetIds = [$guardTargetId];
                $guardRedirected = true;
              }
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
            $luckPassive = $this->applyTeamLuckPassives(
              $playerStatuses,
              $playerById,
              $playerHp,
              $dice,
              $round,
              $tick
            );
            $dice = $luckPassive['dice'];
            $stackResolution = $isSupportAbility
              ? ['damage_reduction' => 0, 'outcome' => null]
              : $this->consumeOneAttackDefenseStacks($enemyStatuses, $targetId);
            $outcome = $isSupportAbility
              ? $this->applySupportOutcomeActorPassives(
                $this->deriveSupportOutcome($abilityRegistry, $abilityId, $targetId, $playerHp, $targetUnit, $dice),
                $playerActor,
                $targetUnit,
                (int)($playerHp[$targetId] ?? (int)$targetUnit['max_hp'])
              )
              : $this->deriveActionOutcome(
                $state,
                $this->effectiveAttackWithStatuses((int)$playerActor['attack'], (array)($playerStatuses[$playerActorId] ?? [])),
                (int)$targetUnit['defense'],
                (int)($enemyHp[$targetId] ?? (int)$targetUnit['max_hp']),
                (int)$targetUnit['max_hp'],
                $abilityId,
                (int)$dice['dice_modifier'],
                $this->applyTeamDamagePassives(
                  (array)($playerActor['combat_affixes'] ?? ['damage_flat' => 0, 'below_half_bonus' => 0.0]),
                  $playerById,
                  $playerHp,
                  $targetId,
                  $enemyDamagedThisRound,
                  $targetStatuses
                ),
                $dice,
                $targetStatuses,
                (array)($playerActor['pos'] ?? ['x' => 1, 'y' => 1]),
                (array)($targetUnit['pos'] ?? ['x' => 1, 'y' => 1]),
                (array)($playerActor['formation'] ?? ['w' => 1, 'h' => 1]),
                (array)($targetUnit['formation'] ?? ['w' => 1, 'h' => 1]),
                $abilityRegistry,
                (int)$playerActor['attack'],
                (int)($stackResolution['damage_reduction'] ?? 0),
                (int)($playerActor['precision'] ?? 5),
                (int)($targetUnit['resolve'] ?? 5),
              );
            $outcome = $this->applySourceLinkedStatusParams($outcome, $playerActorId);
            if (!$isSupportAbility) {
              $outcome = $this->applyAllyProtectionPassives(
                $outcome,
                $targetId,
                $enemyById,
                $enemyHp,
                $enemyStatuses,
                $guardRedirected
              );
            }

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
            if ($isSupportAbility) {
              $this->applyOutcomeStatus(
                $playerStatuses,
                $targetId,
                $outcome,
                $round,
                $tick,
              );
              $passiveStatusAugment = null;
            } else {
              $this->applyOutcomeStatus(
                $enemyStatuses,
                $targetId,
                $outcome,
                $round,
                $tick,
              );
              $passiveStatusAugment = $this->applyAttackerPassiveStatusAugments(
                $enemyStatuses,
                $playerActor,
                $targetId,
                $outcome,
                $round,
                $tick
              );
            }
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
            if ($luckPassive['outcome'] !== null) {
              $events[count($events) - 1]['luck_passive_outcome'] = $luckPassive['outcome'];
            }
            if (!$isSupportAbility) {
              $this->consumeForcedNextAttackTarget($playerStatuses, $playerActorId);
            }
            if ($isSupportAbility) {
              $supportEchoOutcome = $this->applySupportEchoPassive(
                $state,
                $playerStatuses,
                $playerById,
                $playerHp,
                $targetId,
                $outcome,
                $round,
                $tick
              );
              if ($supportEchoOutcome !== null) {
                $events[count($events) - 1]['support_passive_outcome'] = $supportEchoOutcome;
              }
            }
            if (!$isSupportAbility) {
              $enemyHp[$targetId] = (int)$outcome['target_hp_after'];
              if ((int)($outcome['damage'] ?? 0) > 0) {
                $enemyDamagedThisRound[$targetId] = true;
              }
              $this->clearSleepOnDamage($enemyStatuses, $sleepBlockedUntilTick, $targetId, $tick, (int)$outcome['damage']);
              $preferredTargetByActor['player:' . $playerActorId] = $targetId;
              $defeatSupportOutcome = $this->applyPlayerDefeatTriggeredPassives(
                $playerUnits,
                $playerById,
                $playerHp,
                $playerStatuses,
                $enemyHp,
                $round,
                $tick,
                $playerActorId,
                $targetId,
                $outcome,
              );
              if ($defeatSupportOutcome !== null) {
                $events[count($events) - 1]['support_passive_outcome'] = $defeatSupportOutcome;
              }
              if ($passiveStatusAugment !== null) {
                $events[count($events) - 1]['status_augment_outcome'] = $passiveStatusAugment;
              }
              $multiTargetOutcome = $this->resolveAdditionalAbilityTargets(
                $events,
                $state,
                $abilityRegistry,
                'player',
                $playerActor,
                $playerActorId,
                $ability,
                $targetId,
                $enemyById,
                $enemyHp,
                $enemyStatuses,
                $playerStatuses,
                $dice,
                $round,
                $tick
              );
              if ($multiTargetOutcome !== null) {
                $events[count($events) - 1]['multi_target_outcome'] = $multiTargetOutcome;
              }
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
            $guardRedirected = false;
            $forcedTargetId = !$isSupportAbility
              ? $this->forcedNextAttackTargetId($enemyStatuses, $enemyActorId, $playerHp)
              : null;
            if ($forcedTargetId !== null) {
              $aliveTargetIds = [$forcedTargetId];
            } elseif (!$isSupportAbility) {
              $guardTargetId = $this->forcedGuardTargetId($targetPoolHp, $playerStatuses);
              if ($guardTargetId !== null) {
                $aliveTargetIds = [$guardTargetId];
                $guardRedirected = true;
              }
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
            $luckPassive = $this->applyTeamLuckPassives(
              $enemyStatuses,
              $enemyById,
              $enemyHp,
              $dice,
              $round,
              $tick
            );
            $dice = $luckPassive['dice'];
            $stackResolution = $isSupportAbility
              ? ['damage_reduction' => 0, 'outcome' => null]
              : $this->consumeOneAttackDefenseStacks($playerStatuses, $targetId);
            $outcome = $isSupportAbility
              ? $this->applySupportOutcomeActorPassives(
                $this->deriveSupportOutcome($abilityRegistry, $abilityId, $targetId, $enemyHp, $targetUnit, $dice),
                $enemyActor,
                $targetUnit,
                (int)($enemyHp[$targetId] ?? (int)$targetUnit['max_hp'])
              )
              : $this->deriveActionOutcome(
                $state,
                $this->effectiveAttackWithStatuses((int)$enemyActor['attack'], (array)($enemyStatuses[$enemyActorId] ?? [])),
                (int)$targetUnit['defense'],
                (int)($playerHp[$targetId] ?? (int)$targetUnit['max_hp']),
                (int)$targetUnit['max_hp'],
                $abilityId,
                (int)$dice['dice_modifier'],
                $this->applyTeamDamagePassives(
                  ['damage_flat' => 0, 'below_half_bonus' => 0.0],
                  $enemyById,
                  $enemyHp,
                  $targetId,
                  $playerDamagedThisRound,
                  $targetStatuses
                ),
                $dice,
                $targetStatuses,
                (array)($enemyActor['pos'] ?? ['x' => 1, 'y' => 1]),
                (array)($targetUnit['pos'] ?? ['x' => 1, 'y' => 1]),
                (array)($enemyActor['formation'] ?? ['w' => 1, 'h' => 1]),
                (array)($targetUnit['formation'] ?? ['w' => 1, 'h' => 1]),
                $abilityRegistry,
                (int)$enemyActor['attack'],
                (int)($stackResolution['damage_reduction'] ?? 0),
                (int)($enemyActor['precision'] ?? 5),
                (int)($targetUnit['resolve'] ?? 5),
              );
            $outcome = $this->applySourceLinkedStatusParams($outcome, $enemyActorId);
            if (!$isSupportAbility) {
              $outcome = $this->applyAllyProtectionPassives(
                $outcome,
                $targetId,
                $playerById,
                $playerHp,
                $playerStatuses,
                $guardRedirected
              );
            }

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
            if ($isSupportAbility) {
              $this->applyOutcomeStatus(
                $enemyStatuses,
                $targetId,
                $outcome,
                $round,
                $tick,
              );
              $passiveStatusAugment = null;
            } else {
              $this->applyOutcomeStatus(
                $playerStatuses,
                $targetId,
                $outcome,
                $round,
                $tick,
              );
              $passiveStatusAugment = $this->applyAttackerPassiveStatusAugments(
                $playerStatuses,
                $enemyActor,
                $targetId,
                $outcome,
                $round,
                $tick
              );
            }
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
            if ($luckPassive['outcome'] !== null) {
              $events[count($events) - 1]['luck_passive_outcome'] = $luckPassive['outcome'];
            }
            if (!$isSupportAbility) {
              $this->consumeForcedNextAttackTarget($enemyStatuses, $enemyActorId);
            }
            if ($isSupportAbility) {
              $supportEchoOutcome = $this->applySupportEchoPassive(
                $state,
                $enemyStatuses,
                $enemyById,
                $enemyHp,
                $targetId,
                $outcome,
                $round,
                $tick
              );
              if ($supportEchoOutcome !== null) {
                $events[count($events) - 1]['support_passive_outcome'] = $supportEchoOutcome;
              }
            }
            if (!$isSupportAbility) {
              $playerHp[$targetId] = (int)$outcome['target_hp_after'];
              if ((int)($outcome['damage'] ?? 0) > 0) {
                $playerDamagedThisRound[$targetId] = true;
              }
              $this->clearSleepOnDamage($playerStatuses, $sleepBlockedUntilTick, $targetId, $tick, (int)$outcome['damage']);
              $preferredTargetByActor['enemy:' . $enemyActorId] = $targetId;
              $survivalOutcome = $this->applyLastGoblinStandingIfNeeded(
                $playerHp,
                $playerStatuses,
                $playerById,
                $targetId,
                $round,
                $tick
              );
              $triggeredPassiveOutcome = null;
              $counterOutcome = null;
              if ((int)($outcome['damage'] ?? 0) > 0) {
                $triggeredPassiveOutcome = $this->applyTriggeredDefenderPassivesAfterHit(
                  $playerStatuses,
                  $playerById,
                  $targetId,
                  (int)($outcome['damage'] ?? 0),
                  $round,
                  $tick
                );
                $counterOutcome = $this->resolveCounterpunchRetaliation(
                  $events,
                  $state,
                  $abilityRegistry,
                  $enemyActor,
                  $playerById[$targetId] ?? null,
                  $enemyActorId,
                  $targetId,
                  $abilityId,
                  $playerHp,
                  $enemyHp,
                  $playerStatuses,
                  $enemyStatuses,
                  $round,
                  $tick
                );
              }
              if ($survivalOutcome !== null || $triggeredPassiveOutcome !== null || $counterOutcome !== null) {
                $events[count($events) - 1]['defender_passive_outcome'] = implode('; ', array_values(array_filter([
                  $survivalOutcome,
                  $triggeredPassiveOutcome,
                  $counterOutcome,
                ])));
              }
              if ($passiveStatusAugment !== null) {
                $events[count($events) - 1]['status_augment_outcome'] = $passiveStatusAugment;
              }
              $multiTargetOutcome = $this->resolveAdditionalAbilityTargets(
                $events,
                $state,
                $abilityRegistry,
                'enemy',
                $enemyActor,
                $enemyActorId,
                $ability,
                $targetId,
                $playerById,
                $playerHp,
                $playerStatuses,
                $enemyStatuses,
                $dice,
                $round,
                $tick
              );
              if ($multiTargetOutcome !== null) {
                $events[count($events) - 1]['multi_target_outcome'] = $multiTargetOutcome;
              }
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
      $this->tickStatusDurations($events, 'player', $round, $roundEndTick, $playerStatuses, $playerHp, $playerById, $sleepBlockedUntilTick);
      $this->tickStatusDurations($events, 'enemy', $round, $roundEndTick, $enemyStatuses, $enemyHp, $enemyById, $sleepBlockedUntilTick);
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
    $orderedActives = $this->normalizeOrderedActiveAbilityScheduleEntries($abilityIds, $registry);

    foreach ($orderedActives as $entry) {
      $speed = (int)$entry['speed'];
      if (($cumulativeTick + $speed) > 20) {
        break;
      }

      $cumulativeTick += $speed;
      $schedule[] = [
        'ability_id' => (string)$entry['ability_id'],
        'speed' => $speed,
        'target' => (string)$entry['target'],
        'trigger_tick' => $cumulativeTick,
        'equip_order' => (int)$entry['equip_order'],
      ];
    }

    if (count($schedule) === 0) {
      throw new RuntimeException('combat_ability_schedule_empty');
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
    int $attackerPrecision = 5,
    int $targetResolve = 5,
  ): array {
    $attackerPrecision = $this->normalizeCombatReliabilityStat($attackerPrecision);
    $targetResolve = $this->normalizeCombatReliabilityStat($targetResolve);
    $missChance = $this->precisionMissChancePercent($attackerPrecision);
    $missRoll = null;
    if ($missChance > 0) {
      $missRoll = $this->nextInt($state, 100) + 1;
      if ($missRoll <= $missChance) {
        return [
          'damage' => 0,
          'target_hp_after' => $targetHp,
          'outcome' => 'missed',
          'status_applied' => null,
          'status_duration_rounds' => null,
          'status_params' => [],
          'ability_outcome' => sprintf('attack missed (Precision %d)', $attackerPrecision),
          'affix_outcome' => null,
          'hit_outcome' => 'miss',
          'precision_roll' => $missRoll,
          'precision_target' => $missChance,
          'crit_roll' => null,
          'crit_target' => 0,
          'status_resisted' => false,
          'status_resist_roll' => null,
          'status_resist_target' => 0,
        ];
      }
    }

    $effectiveDefense = $this->effectiveDefenseWithStatuses($targetDefense, $targetStatuses);
    $ignoreDefenseFlat = max(0, (int)($combatAffixes['ignore_defense_flat'] ?? 0));
    if ($ignoreDefenseFlat > 0) {
      $effectiveDefense = max(0, $effectiveDefense - $ignoreDefenseFlat);
    }
    $variance = $this->nextInt($state, 5) - 2;
    $rawDamage = (int)floor(($attackerAttack * 0.65) - ($effectiveDefense * 0.35)) + $variance + $diceModifier;
    $rawDamage += max(0, (int)($combatAffixes['damage_flat'] ?? 0));

    $affixOutcomeParts = [];
    if ($ignoreDefenseFlat > 0) {
      $affixOutcomeParts[] = sprintf('ignored %d defense', $ignoreDefenseFlat);
    }
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

    $abilityTags = $abilityRegistry->has($abilityId)
      ? $abilityRegistry->get($abilityId)->tags
      : [];
    $isMelee = in_array('melee', $abilityTags, true);
    $isRanged = in_array('ranged', $abilityTags, true);
    $passiveDamageMultiplier = 1.0;
    if ($isMelee) {
      $passiveDamageMultiplier += max(0.0, (float)($combatAffixes['melee_damage_pct'] ?? 0.0));
    }
    if ($isRanged) {
      $passiveDamageMultiplier += max(0.0, (float)($combatAffixes['ranged_damage_pct'] ?? 0.0));
    }
    if ($abilityId === 'aimed_shot') {
      $passiveDamageMultiplier += max(0.0, (float)($combatAffixes['aimed_shot_bonus_pct'] ?? 0.0));
    }
    if ($this->isWounded($targetHp, $targetMaxHp)) {
      $passiveDamageMultiplier += max(0.0, (float)($combatAffixes['wounded_damage_pct'] ?? 0.0));
    }
    if ($isRanged && $this->isBackRow($targetPos, $targetFormation)) {
      $passiveDamageMultiplier += max(0.0, (float)($combatAffixes['backline_damage_pct'] ?? 0.0));
    }
    if ($passiveDamageMultiplier > 1.0) {
      $rawDamage = (int)floor($rawDamage * $passiveDamageMultiplier);
      $affixOutcomeParts[] = sprintf(
        'passive damage x%s',
        rtrim(rtrim(number_format($passiveDamageMultiplier, 2, '.', ''), '0'), '.')
      );
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

    $damageTakenMultiplier = $this->damageTakenMultiplierFromStatuses($targetStatuses, $isMelee);
    if (abs($damageTakenMultiplier - 1.0) > 0.0001) {
      $rawDamage = (int)floor($rawDamage * $damageTakenMultiplier);
      $affixOutcomeParts[] = sprintf('status damage x%s', rtrim(rtrim(number_format($damageTakenMultiplier, 2, '.', ''), '0'), '.'));
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

    $statusBonusTarget = trim((string)($combatAffixes['status_bonus_target'] ?? ''));
    $statusBonusPct = max(0.0, (float)($combatAffixes['status_bonus_pct'] ?? 0.0));
    if ($statusBonusTarget !== '' && $statusBonusPct > 0 && isset($targetStatuses[$statusBonusTarget])) {
      $rawDamage = (int)floor($rawDamage * (1 + $statusBonusPct));
      $affixOutcomeParts[] = sprintf(
        '%s x%s',
        $statusBonusTarget,
        rtrim(rtrim(number_format(1 + $statusBonusPct, 2, '.', ''), '0'), '.')
      );
    }

    $damagedEnemyBonusPct = max(0.0, (float)($combatAffixes['damaged_enemy_bonus_pct'] ?? 0.0));
    if ($damagedEnemyBonusPct > 0.0) {
      $rawDamage = (int)floor($rawDamage * (1 + $damagedEnemyBonusPct));
      $affixOutcomeParts[] = sprintf(
        'damaged target x%s',
        rtrim(rtrim(number_format(1 + $damagedEnemyBonusPct, 2, '.', ''), '0'), '.')
      );
    }

    $runDamageMultiplier = (float)($combatAffixes['run_damage_multiplier'] ?? 1.0);
    if (abs($runDamageMultiplier - 1.0) > 0.0001) {
      $rawDamage = (int)floor($rawDamage * max(0.1, $runDamageMultiplier));
      $affixOutcomeParts[] = sprintf(
        'run modifier damage x%s',
        rtrim(rtrim(number_format($runDamageMultiplier, 2, '.', ''), '0'), '.')
      );
    }

    $critChance = $this->precisionCritChancePercent($attackerPrecision);
    $critRoll = null;
    $isCritical = false;
    if ($critChance > 0) {
      $critRoll = $this->nextInt($state, 100) + 1;
      $isCritical = $critRoll <= $critChance;
      if ($isCritical) {
        $rawDamage = (int)floor($rawDamage * 1.5);
        $affixOutcomeParts[] = 'critical hit x1.5';
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
    $statusResistChance = $this->statusResistanceChancePercent(
      $attackerPrecision,
      $targetResolve,
      $statusApplication['params']
    );
    $statusResistRoll = null;
    $statusResisted = false;
    if ($status !== null && $statusResistChance > 0) {
      $statusResistRoll = $this->nextInt($state, 100) + 1;
      $statusResisted = $statusResistRoll <= $statusResistChance;
      if ($statusResisted) {
        $affixOutcomeParts[] = sprintf('%s resisted by Resolve %d', $status, $targetResolve);
        $status = null;
        $statusDuration = null;
        $statusApplication = ['duration_rounds' => null, 'params' => []];
      }
    }
    $outcome = $nextHp <= 0 ? 'defeated' : 'hit';

    $abilityOutcomeParts = [sprintf('%d damage dealt', $damage)];
    if ($status !== null) {
      if ($statusDuration !== null) {
        $abilityOutcomeParts[] = sprintf('%s applied for %d rounds', $status, $statusDuration);
      } else {
        $abilityOutcomeParts[] = sprintf('%s applied', $status);
      }
    }
    if ($statusResisted) {
      $abilityOutcomeParts[] = sprintf('status resisted by Resolve %d', $targetResolve);
    }
    if ($isCritical) {
      $abilityOutcomeParts[] = 'critical hit';
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
      'hit_outcome' => $isCritical ? 'critical' : 'hit',
      'precision_roll' => $missRoll,
      'precision_target' => $missChance,
      'crit_roll' => $critRoll,
      'crit_target' => $critChance,
      'status_resisted' => $statusResisted,
      'status_resist_roll' => $statusResistRoll,
      'status_resist_target' => $statusResistChance,
    ];
  }

  private function normalizeCombatReliabilityStat(int $value): int
  {
    return max(1, min(20, $value));
  }

  private function precisionMissChancePercent(int $precision): int
  {
    $precision = $this->normalizeCombatReliabilityStat($precision);
    if ($precision >= 5) {
      return 0;
    }

    return min(40, (5 - $precision) * 8);
  }

  private function precisionCritChancePercent(int $precision): int
  {
    $precision = $this->normalizeCombatReliabilityStat($precision);
    if ($precision <= 5) {
      return 0;
    }

    return min(30, ($precision - 5) * 5);
  }

  /**
   * @param array<string,mixed> $statusParams
   */
  private function statusResistanceChancePercent(int $attackerPrecision, int $targetResolve, array $statusParams): int
  {
    if ((bool)($statusParams['is_debuff'] ?? false) !== true) {
      return 0;
    }

    $attackerPrecision = $this->normalizeCombatReliabilityStat($attackerPrecision);
    $targetResolve = $this->normalizeCombatReliabilityStat($targetResolve);
    $advantage = $targetResolve - $attackerPrecision;
    if ($advantage <= 0) {
      return 0;
    }

    return min(45, $advantage * 8);
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
    if ($abilityId === 'taunting_guard') {
      $targetHp = (int)($currentHpByUnitId[$targetId] ?? (int)($targetUnit['max_hp'] ?? 0));
      $halfDie = $this->halfDieValue($this->diceRollTotal($diceContext));
      $statusDuration = 2;
      return [
        'damage' => 0,
        'target_hp_after' => $targetHp,
        'outcome' => 'buffed',
        'status_applied' => 'guard_stacks',
        'status_duration_rounds' => $statusDuration,
        'status_params' => [
          'stack_count' => max(1, $halfDie),
          'per_stack_damage_reduction' => 1,
          'consumes_on_next_attack' => true,
          'taunt_redirect' => true,
          'is_debuff' => false,
        ],
        'ability_outcome' => sprintf('guard_stacks applied for %d rounds', $statusDuration),
        'affix_outcome' => sprintf('gained %d guard stacks from half-die scaling', max(1, $halfDie)),
      ];
    }

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
   * @param array<string,int> $hpByUnitId
   * @param array<string,array<string,mixed>> $unitsById
   * @param array<string,int> $sleepBlockedUntilTick
   */
  private function tickStatusDurations(
    array &$events,
    string $side,
    int $round,
    int $tick,
    array &$statusMap,
    array &$hpByUnitId,
    array $unitsById,
    array &$sleepBlockedUntilTick
  ): void
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
          $this->applyStatusExpiryEffects(
            $events,
            $side,
            (string)$unitId,
            (string)$statusId,
            is_array($statusState) ? $statusState : [],
            $round,
            $tick,
            $hpByUnitId,
            $unitsById,
            $statusMap,
            $sleepBlockedUntilTick
          );
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
   * @param array<int,array<string,mixed>> $events
   * @param array<string,mixed> $statusState
   * @param array<string,int> $hpByUnitId
   * @param array<string,array<string,mixed>> $unitsById
   * @param array<string,array<string,array<string,mixed>>> $statusMap
   * @param array<string,int> $sleepBlockedUntilTick
   */
  private function applyStatusExpiryEffects(
    array &$events,
    string $side,
    string $unitId,
    string $statusId,
    array $statusState,
    int $round,
    int $tick,
    array &$hpByUnitId,
    array $unitsById,
    array &$statusMap,
    array &$sleepBlockedUntilTick
  ): void {
    if ($statusId !== 'fuse_lit') {
      return;
    }

    $currentHp = (int)($hpByUnitId[$unitId] ?? 0);
    if ($currentHp <= 0) {
      return;
    }

    $unit = $unitsById[$unitId] ?? null;
    if (!is_array($unit)) {
      return;
    }

    $statuses = $statusMap[$unitId] ?? [];
    $sourceAttack = max(1, (int)($statusState['params']['source_attack'] ?? 1));
    $damageRatio = max(0.0, (float)($statusState['params']['bomb_damage_ratio'] ?? 0.9));
    $damage = max(1, (int)floor($sourceAttack * $damageRatio));
    $damage = (int)floor($damage * $this->specialFrontTakenMultiplier($unit));
    $damage = (int)floor($damage * $this->damageTakenMultiplierFromStatuses($statuses));
    $damage = max(1, $damage);

    $nextHp = max(0, $currentHp - $damage);
    $hpByUnitId[$unitId] = $nextHp;
    $this->clearSleepOnDamage($statusMap, $sleepBlockedUntilTick, $unitId, $tick, $damage);

    $events[] = [
      'type' => 'status_tick',
      'round' => $round,
      'tick' => $tick,
      'side' => $side,
      $side === 'player' ? 'actor_unit_instance_id' : 'actor_enemy_slug' => $unitId,
      'status_id' => 'fuse_lit',
      'damage' => $damage,
      'target_hp_after' => $nextHp,
      'outcome' => $nextHp <= 0 ? 'defeated' : 'ticked',
      'ability_outcome' => sprintf('fuse_lit exploded for %d damage', $damage),
    ];
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
      $statusMap[$unitId][$statusId]['params'] = [
        'defense_pct' => $next,
        'attack_pct' => max(
          (float)($current['params']['attack_pct'] ?? 0.0),
          (float)($params['attack_pct'] ?? 0.0)
        ),
        'is_debuff' => false,
      ];
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
    $effectiveDefense = $bolsteredPct > 0
      ? max(0, (int)floor($targetDefense * (1 + $bolsteredPct)))
      : $targetDefense;

    $crackedArmorReduction = max(0, (int)($targetStatuses['cracked_armor']['params']['defense_reduction_flat'] ?? 0));
    if ($crackedArmorReduction > 0) {
      $effectiveDefense = max(0, $effectiveDefense - $crackedArmorReduction);
    }

    $shieldSetStacks = max(0, (int)($targetStatuses['shield_set']['params']['stack_count'] ?? 0));
    $shieldSetPerStack = max(0, (int)($targetStatuses['shield_set']['params']['defense_flat_per_stack'] ?? 0));
    if ($shieldSetStacks > 0 && $shieldSetPerStack > 0) {
      $effectiveDefense += ($shieldSetStacks * $shieldSetPerStack);
    }

    return $effectiveDefense;
  }

  /**
   * @param array<string,array<string,mixed>> $targetStatuses
   */
  private function damageTakenMultiplierFromStatuses(array $targetStatuses, bool $isMelee = false): float
  {
    $multiplier = 1.0;
    $multiplier += max(0.0, (float)($targetStatuses['bleeding']['params']['damage_taken_pct'] ?? 0.0));
    $multiplier += max(0.0, (float)($targetStatuses['marked']['params']['damage_taken_pct'] ?? 0.0));
    if ($isMelee) {
      $multiplier += max(0.0, (float)($targetStatuses['menaced']['params']['damage_taken_melee_pct'] ?? 0.0));
    }

    return $multiplier;
  }

  /**
   * @param array<string,array<string,mixed>> $attackerStatuses
   */
  private function effectiveAttackWithStatuses(int $baseAttack, array $attackerStatuses): int
  {
    $attack = $baseAttack;
    $attackMultiplier = 1.0;
    $attackMultiplier += max(0.0, (float)($attackerStatuses['bolstered']['params']['attack_pct'] ?? 0.0));
    $attackMultiplier += max(0.0, (float)($attackerStatuses['warcry']['params']['attack_pct'] ?? 0.0));
    $attackMultiplier -= max(0.0, (float)($attackerStatuses['cracked_skull']['params']['attack_reduction_pct'] ?? 0.0));
    $attackMultiplier -= max(0.0, (float)($attackerStatuses['disarmed']['params']['attack_reduction_pct'] ?? 0.0));
    $attackMultiplier -= max(0.0, (float)($attackerStatuses['poison']['params']['attack_reduction_pct'] ?? 0.0));
    $attack = max(1, (int)floor($attack * max(0.1, $attackMultiplier)));
    $attack += max(0, (int)($attackerStatuses['lucky']['params']['lucky_bonus_flat'] ?? 0));
    $attack += max(0, (int)($attackerStatuses['crowd_favorite']['params']['stack_count'] ?? 0))
      * max(0, (int)($attackerStatuses['crowd_favorite']['params']['damage_flat_per_stack'] ?? 0));
    return max(1, $attack);
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
          'attack_reduction_pct' => (float)($params['poison_attack_reduction_pct'] ?? 0.0),
          'status_speed' => (int)($params['status_speed'] ?? 5),
          'source_attack' => max(1, $sourceAttack),
          'is_debuff' => true,
        ],
      ],
      'marked' => [
        'duration_rounds' => $duration,
        'params' => [
          'damage_taken_pct' => (float)($params['damage_taken_pct'] ?? 0.15),
          'is_debuff' => true,
        ],
      ],
      'cracked_armor' => [
        'duration_rounds' => $duration,
        'params' => [
          'defense_reduction_flat' => (int)($params['defense_reduction_flat'] ?? 2),
          'is_debuff' => true,
        ],
      ],
      'cracked_skull', 'disarmed' => [
        'duration_rounds' => $duration,
        'params' => [
          'attack_reduction_pct' => (float)($params['attack_reduction_pct'] ?? 0.15),
          'is_debuff' => true,
        ],
      ],
      'warcry' => [
        'duration_rounds' => $duration,
        'params' => [
          'attack_pct' => (float)($params['attack_pct'] ?? 0.18) + ($rollTotal * 0.01),
          'is_debuff' => false,
        ],
      ],
      'lucky' => [
        'duration_rounds' => $duration,
        'params' => [
          'lucky_bonus_flat' => max(
            1,
            (int)($params['lucky_bonus_flat'] ?? 0) + max(0, $this->halfDieValue($rollTotal) - 1)
          ),
          'is_debuff' => false,
        ],
      ],
      'menaced' => [
        'duration_rounds' => $duration,
        'params' => [
          'damage_taken_melee_pct' => (float)($params['damage_taken_melee_pct'] ?? 0.12),
          'is_debuff' => true,
        ],
      ],
      'snared' => [
        'duration_rounds' => $duration,
        'params' => ['is_debuff' => true],
      ],
      'wrestled' => [
        'duration_rounds' => $duration,
        'params' => [
          'forced_target_id' => '',
          'consumes_on_hostile_action' => true,
          'is_debuff' => true,
        ],
      ],
      'fuse_lit' => [
        'duration_rounds' => $duration,
        'params' => [
          'bomb_damage_ratio' => (float)($params['bomb_damage_ratio'] ?? 0.9),
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
   *     contribution:int,
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

      $slotContribution = $rollTotal;
      $modifier += $slotContribution;
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
        'contribution' => $slotContribution,
        'modifier' => $slotContribution,
        'empty_slot' => (string)$diceUsed[$index]['kind'] === 'empty_slot',
      ];
      $slotTraceParts[] = sprintf(
        'slot%d=%s => %s (contribution %+d)',
        $index + 1,
        $slotLabel,
        $rollLabel,
        $slotContribution
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
    $unit['combat_affixes'] = array_replace(
      (array)($unit['combat_affixes'] ?? []),
      [
        'damage_flat' => max((int)($unit['combat_affixes']['damage_flat'] ?? 0), $damageFlat),
        'below_half_bonus' => max((float)($unit['combat_affixes']['below_half_bonus'] ?? 0.0), $belowHalfBonus),
      ]
    );
  }

  /**
   * @param array{kind:string,dice_instance_id:?string,sides:int,affixes:array<int,array{slug:string,value:float}>} $die
   */
  private function applyGlobalD4ExplosionUnlockToDie(array &$die): void
  {
    if ((int)($die['sides'] ?? 0) !== 4) {
      return;
    }

    foreach ((array)($die['affixes'] ?? []) as $affix) {
      if (strtolower(trim((string)($affix['slug'] ?? ''))) === 'explode_once') {
        return;
      }
    }

    $die['affixes'][] = [
      'slug' => 'explode_once',
      'value' => 1.0,
    ];
  }

  /**
   * @param array{
   *   attack:int,
   *   defense:int,
   *   max_hp:int,
   *   current_hp:int,
   *   passive_abilities?:array<int,string>,
   *   combat_affixes?:array<string,mixed>
   * } $unit
   */
  private function applyPassiveAbilityAffixesToUnit(array &$unit, AbilityRegistry $abilityRegistry): void
  {
    $attackFlat = 0;
    $defenseFlat = 0;
    $maxHpFlat = 0;
    $combatAffixes = (array)($unit['combat_affixes'] ?? []);

    foreach ((array)($unit['passive_abilities'] ?? []) as $abilityId) {
      $id = trim((string)$abilityId);
      if ($id === '' || !$abilityRegistry->has($id)) {
        continue;
      }

      $definition = $abilityRegistry->get($id);
      if ($definition->type !== AbilityType::Passive) {
        continue;
      }

      $params = (array)$definition->defaultParams;
      $attackFlat += (int)($params['attack_flat'] ?? 0);
      $defenseFlat += (int)($params['defense_flat'] ?? 0);
      $maxHpFlat += (int)($params['max_hp_flat'] ?? 0);

      foreach ([
        'melee_damage_pct',
        'ranged_damage_pct',
        'wounded_damage_pct',
        'aimed_shot_bonus_pct',
        'backline_damage_pct',
        'damaged_enemy_bonus_pct',
        'status_potency_pct',
        'poison_damage_pct',
        'status_bonus_pct',
      ] as $floatKey) {
        if (isset($params[$floatKey])) {
          $combatAffixes[$floatKey] = ((float)($combatAffixes[$floatKey] ?? 0.0)) + (float)$params[$floatKey];
        }
      }

      foreach ([
        'ignore_defense_flat',
        'bonus_damage_per_debuff_type',
        'debuff_type_cap',
      ] as $intKey) {
        if (isset($params[$intKey])) {
          $combatAffixes[$intKey] = max((int)($combatAffixes[$intKey] ?? 0), (int)$params[$intKey]);
        }
      }

      if (isset($params['status_bonus_target'])) {
        $combatAffixes['status_bonus_target'] = (string)$params['status_bonus_target'];
      }
    }

    $unit['attack'] = max(1, (int)$unit['attack'] + $attackFlat);
    $unit['defense'] = max(0, (int)$unit['defense'] + $defenseFlat);
    $unit['max_hp'] = max(1, (int)$unit['max_hp'] + $maxHpFlat);
    $unit['current_hp'] = min((int)$unit['max_hp'], (int)$unit['current_hp']);
    $unit['combat_affixes'] = array_replace((array)($unit['combat_affixes'] ?? []), $combatAffixes);
  }

  /**
   * @param array<string,array<string,array<string,mixed>>> $statusMap
   * @param array<int,string> $passiveAbilityIds
   */
  private function initializePassiveStatusesForCombat(array &$statusMap, string $unitId, array $passiveAbilityIds): void
  {
    if (in_array('spiteful_reflex', $passiveAbilityIds, true)) {
      $this->applyStatusState(
        $statusMap,
        $unitId,
        'spiteful_reflex',
        99,
        ['is_debuff' => false, 'last_trigger_round' => 0],
        1,
        0
      );
    }

    if (in_array('counterpunch', $passiveAbilityIds, true)) {
      $this->applyStatusState(
        $statusMap,
        $unitId,
        'counterpunch_ready',
        99,
        ['is_debuff' => false, 'last_trigger_round' => 0],
        1,
        0
      );
    }

    if (in_array('dumb_luck', $passiveAbilityIds, true)) {
      $this->applyStatusState(
        $statusMap,
        $unitId,
        'dumb_luck_ready',
        99,
        ['is_debuff' => false, 'used' => false],
        1,
        0
      );
    }

    if (in_array('last_goblin_standing', $passiveAbilityIds, true)) {
      $this->applyStatusState(
        $statusMap,
        $unitId,
        'last_goblin_standing_ready',
        99,
        ['is_debuff' => false, 'used' => false],
        1,
        0
      );
    }
  }

  /**
   * @param array<int,array<string,mixed>> $units
   */
  private function applyFormationPassiveBonusesToUnits(array &$units, AbilityRegistry $abilityRegistry): void
  {
    $unitsById = [];
    foreach ($units as $unit) {
      $unitsById[(string)$unit['id']] = $unit;
    }

    foreach ($units as &$unit) {
      $passives = (array)($unit['passive_abilities'] ?? []);
      if (in_array('hold_the_line', $passives, true)) {
        $this->applyHoldTheLineBonus($unit, $unitsById, $abilityRegistry);
      }
      if (in_array('vantage_point', $passives, true)) {
        $this->applyVantagePointBonus($unit, $unitsById, $abilityRegistry);
      }
    }
    unset($unit);
  }

  /**
   * @param array<string,mixed> $unit
   * @param array<string,array<string,mixed>> $unitsById
   */
  private function applyHoldTheLineBonus(array &$unit, array $unitsById, AbilityRegistry $abilityRegistry): void
  {
    if (!$abilityRegistry->has('hold_the_line')) {
      return;
    }

    $params = (array)$abilityRegistry->get('hold_the_line')->defaultParams;
    if (!$this->isFrontRow((array)($unit['pos'] ?? ['x' => 1, 'y' => 1]), (array)($unit['formation'] ?? ['w' => 1, 'h' => 1]))) {
      return;
    }

    $bonus = max(0, (int)($params['front_row_defense_flat'] ?? 0));
    $unitProfile = $this->combatPositionProfile($unit);
    foreach ($unitsById as $allyId => $ally) {
      if ($allyId === (string)$unit['id']) {
        continue;
      }
      $allyProfile = $this->combatPositionProfile($ally);
      if ($allyProfile['front_x'] < 2) {
        continue;
      }
      if (abs($allyProfile['top_y'] - $unitProfile['top_y']) <= 1) {
        $bonus += max(0, (int)($params['front_row_adjacent_bonus_flat'] ?? 0));
        break;
      }
    }

    $unit['defense'] = max(0, (int)$unit['defense'] + $bonus);
  }

  /**
   * @param array<string,mixed> $unit
   * @param array<string,array<string,mixed>> $unitsById
   */
  private function applyVantagePointBonus(array &$unit, array $unitsById, AbilityRegistry $abilityRegistry): void
  {
    if (!$abilityRegistry->has('vantage_point')) {
      return;
    }

    $params = (array)$abilityRegistry->get('vantage_point')->defaultParams;
    $profile = $this->combatPositionProfile($unit);
    $rowsAhead = 0;
    foreach ($unitsById as $allyId => $ally) {
      if ($allyId === (string)$unit['id']) {
        continue;
      }
      $allyProfile = $this->combatPositionProfile($ally);
      if ($allyProfile['front_x'] > $profile['front_x']) {
        $rowsAhead += 1;
      }
    }

    if ($rowsAhead <= 0) {
      return;
    }

    $unit['combat_affixes']['ranged_damage_pct'] = ((float)($unit['combat_affixes']['ranged_damage_pct'] ?? 0.0))
      + ($rowsAhead * max(0.0, (float)($params['ranged_damage_pct_per_row_ahead'] ?? 0.0)));
  }

  /**
   * @param array<string,int> $hpByUnitId
   * @param array<string,array<string,array<string,mixed>>> $statusMap
   * @param array<string,array<string,mixed>> $unitsById
   */
  private function applyLastGoblinStandingIfNeeded(
    array &$hpByUnitId,
    array &$statusMap,
    array $unitsById,
    string $unitId,
    int $round,
    int $tick
  ): ?string {
    if (($hpByUnitId[$unitId] ?? 0) > 0) {
      return null;
    }

    $unit = $unitsById[$unitId] ?? null;
    if (!is_array($unit) || !in_array('last_goblin_standing', (array)($unit['passive_abilities'] ?? []), true)) {
      return null;
    }

    $ready = (array)($statusMap[$unitId]['last_goblin_standing_ready']['params'] ?? []);
    if ((bool)($ready['used'] ?? false) === true) {
      return null;
    }

    $hpByUnitId[$unitId] = 1;
    $this->applyStatusState(
      $statusMap,
      $unitId,
      'last_goblin_standing_ready',
      99,
      ['is_debuff' => false, 'used' => true],
      $round,
      $tick
    );

    return 'last_goblin_standing kept unit at 1 HP';
  }

  /**
   * @param array<string,array<string,array<string,mixed>>> $statusMap
   * @param array<string,array<string,mixed>> $unitsById
   */
  private function applyTriggeredDefenderPassivesAfterHit(
    array &$statusMap,
    array $unitsById,
    string $unitId,
    int $damageTaken,
    int $round,
    int $tick
  ): ?string {
    if ($damageTaken <= 0) {
      return null;
    }

    $unit = $unitsById[$unitId] ?? null;
    if (!is_array($unit)) {
      return null;
    }

    $passives = (array)($unit['passive_abilities'] ?? []);
    $outcomes = [];
    $registry = new AbilityRegistry();

    if (in_array('brawl_hardened', $passives, true) && $registry->has('brawl_hardened')) {
      $params = (array)$registry->get('brawl_hardened')->defaultParams;
      $this->applyOneAttackDefenseStacks(
        $statusMap,
        $unitId,
        'brawl_hardened_stacks',
        1,
        max(0, (int)($params['damage_reduction_per_stack'] ?? 1)),
        max(1, (int)($params['stack_cap'] ?? 3)),
        $tick
      );
      $outcomes[] = 'brawl_hardened gained 1 stack';
    }

    if (in_array('shield_set', $passives, true) && $registry->has('shield_set')) {
      $params = (array)$registry->get('shield_set')->defaultParams;
      $stackCap = max(1, (int)($params['stack_cap'] ?? 3));
      if (in_array('wall_of_scrap', $passives, true) && $registry->has('wall_of_scrap')) {
        $stackCap += max(0, (int)($registry->get('wall_of_scrap')->defaultParams['stack_cap_bonus'] ?? 0));
      }
      $existing = (array)($statusMap[$unitId]['shield_set']['params'] ?? []);
      $stackCount = min($stackCap, max(0, (int)($existing['stack_count'] ?? 0)) + 1);
      $this->applyStatusState(
        $statusMap,
        $unitId,
        'shield_set',
        1,
        [
          'stack_count' => $stackCount,
          'defense_flat_per_stack' => max(0, (int)($params['defense_flat_per_stack'] ?? 1)),
          'is_debuff' => false,
        ],
        $round,
        $tick
      );
      $outcomes[] = sprintf('shield_set increased to %d stacks', $stackCount);
    }

    if (in_array('crowd_favorite', $passives, true) && $registry->has('crowd_favorite')) {
      $params = (array)$registry->get('crowd_favorite')->defaultParams;
      $existing = (array)($statusMap[$unitId]['crowd_favorite']['params'] ?? []);
      $stackCount = min(
        max(1, (int)($params['stack_cap'] ?? 5)),
        max(0, (int)($existing['stack_count'] ?? 0)) + 1
      );
      $this->applyStatusState(
        $statusMap,
        $unitId,
        'crowd_favorite',
        99,
        [
          'stack_count' => $stackCount,
          'damage_flat_per_stack' => max(0, (int)($params['damage_flat_per_stack'] ?? 1)),
          'is_debuff' => false,
        ],
        $round,
        $tick
      );
      $outcomes[] = sprintf('crowd_favorite increased to %d stacks', $stackCount);
    }

    return count($outcomes) > 0 ? implode(', ', $outcomes) : null;
  }

  /**
   * @param array<int,array<string,mixed>> $events
   * @param array<string,int> $playerHp
   * @param array<string,int> $enemyHp
   * @param array<string,array<string,array<string,mixed>>> $playerStatuses
   * @param array<string,array<string,array<string,mixed>>> $enemyStatuses
   * @param array<string,mixed>|null $defenderUnit
   */
  private function resolveCounterpunchRetaliation(
    array &$events,
    string &$state,
    AbilityRegistry $abilityRegistry,
    array $enemyActor,
    ?array $defenderUnit,
    string $attackerId,
    string $defenderId,
    string $enemyAbilityId,
    array &$playerHp,
    array &$enemyHp,
    array &$playerStatuses,
    array &$enemyStatuses,
    int $round,
    int $tick
  ): ?string {
    if (!is_array($defenderUnit) || !$abilityRegistry->has($enemyAbilityId)) {
      return null;
    }

    if (!in_array('counterpunch', (array)($defenderUnit['passive_abilities'] ?? []), true)) {
      return null;
    }

    if (!in_array('melee', $abilityRegistry->get($enemyAbilityId)->tags, true)) {
      return null;
    }

    $counterState = (array)($playerStatuses[$defenderId]['counterpunch_ready']['params'] ?? []);
    if ((int)($counterState['last_trigger_round'] ?? 0) === $round) {
      return 'counterpunch already used this round';
    }

    $playerStatuses[$defenderId]['counterpunch_ready']['params']['last_trigger_round'] = $round;
    $counterRatio = (float)($abilityRegistry->get('counterpunch')->defaultParams['counter_ratio'] ?? 0.7);
    $counterAttack = max(
      1,
      (int)floor(
        $this->effectiveAttackWithStatuses((int)$defenderUnit['attack'], (array)($playerStatuses[$defenderId] ?? []))
        * max(0.1, $counterRatio)
      )
    );
    $counterOutcome = $this->deriveActionOutcome(
      $state,
      $counterAttack,
      (int)($enemyActor['defense'] ?? 0),
      (int)($enemyHp[$attackerId] ?? (int)($enemyActor['max_hp'] ?? 1)),
      (int)($enemyActor['max_hp'] ?? 1),
      'basic_attack_melee',
      0,
      (array)($defenderUnit['combat_affixes'] ?? []),
      ['dice_used' => [], 'dice_rolls' => [], 'dice_outcome' => 'counterpunch', 'dice_modifier' => 0, 'explode_triggered' => false],
      (array)($enemyStatuses[$attackerId] ?? []),
      (array)($defenderUnit['pos'] ?? ['x' => 1, 'y' => 1]),
      (array)($enemyActor['pos'] ?? ['x' => 1, 'y' => 1]),
      (array)($defenderUnit['formation'] ?? ['w' => 1, 'h' => 1]),
      (array)($enemyActor['formation'] ?? ['w' => 1, 'h' => 1]),
      $abilityRegistry,
      (int)$defenderUnit['attack'],
      0,
      (int)($defenderUnit['precision'] ?? 5),
      (int)($enemyActor['resolve'] ?? 5)
    );
    $enemyHp[$attackerId] = (int)($counterOutcome['target_hp_after'] ?? ($enemyHp[$attackerId] ?? 0));

    $events[] = [
      'type' => 'reaction',
      'round' => $round,
      'tick' => $tick,
      'side' => 'player',
      'actor_unit_instance_id' => $defenderId,
      'target_enemy_slug' => $attackerId,
      'ability_id' => 'counterpunch',
      ...$counterOutcome,
    ];

    return sprintf('counterpunch retaliated for %d damage', (int)($counterOutcome['damage'] ?? 0));
  }

  /**
   * @param array<int,array<string,mixed>> $playerUnits
   * @param array<string,array<string,mixed>> $playerById
   * @param array<string,int> $playerHp
   * @param array<string,array<string,array<string,mixed>>> $playerStatuses
   * @param array<string,int> $enemyHp
   * @param array{outcome?:string} $outcome
   */
  private function applyPlayerDefeatTriggeredPassives(
    array $playerUnits,
    array $playerById,
    array &$playerHp,
    array &$playerStatuses,
    array $enemyHp,
    int $round,
    int $tick,
    string $actorId,
    string $targetId,
    array $outcome
  ): ?string {
    if ((string)($outcome['outcome'] ?? '') !== 'defeated') {
      return null;
    }

    $messages = [];
    foreach ($playerUnits as $unit) {
      $unitId = (string)$unit['id'];
      $passives = (array)($unit['passive_abilities'] ?? []);

      if (in_array('battle_tempo', $passives, true) && isset($playerStatuses[$actorId]['warcry'])) {
        $allyTargetId = $this->lowestHpAllyId($playerHp, $playerById, $unitId);
        if ($allyTargetId !== null) {
          $this->applyStatusState(
            $playerStatuses,
            $allyTargetId,
            'bolstered',
            1,
            ['defense_pct' => 0.12, 'is_debuff' => false],
            $round,
            $tick
          );
          $messages[] = sprintf('battle_tempo bolstered %s', $allyTargetId);
        }
      }

      if (in_array('morale_goblin', $passives, true)) {
        $allyTargetId = $this->lowestHpAllyId($playerHp, $playerById, $unitId);
        if ($allyTargetId !== null) {
          $heal = 2;
          $playerHp[$allyTargetId] = min(
            (int)($playerById[$allyTargetId]['max_hp'] ?? $playerHp[$allyTargetId]),
            (int)$playerHp[$allyTargetId] + $heal
          );
          $messages[] = sprintf('morale_goblin healed %s for %d', $allyTargetId, $heal);
        }
      }
    }

    return count($messages) > 0 ? implode(', ', $messages) : null;
  }

  /**
   * @param array<string,int> $hpByUnitId
   * @param array<string,array<string,mixed>> $unitsById
   */
  private function lowestHpAllyId(array $hpByUnitId, array $unitsById, string $excludeUnitId = ''): ?string
  {
    $bestId = null;
    $bestRatio = 2.0;
    foreach ($hpByUnitId as $unitId => $hp) {
      if ((int)$hp <= 0 || $unitId === $excludeUnitId || !isset($unitsById[$unitId])) {
        continue;
      }
      $maxHp = max(1, (int)($unitsById[$unitId]['max_hp'] ?? 1));
      $ratio = (int)$hp / $maxHp;
      if ($ratio < $bestRatio) {
        $bestRatio = $ratio;
        $bestId = (string)$unitId;
      }
    }

    return $bestId;
  }

  /**
   * @param array<string,array<string,array<string,mixed>>> $statusMap
   * @param array<string,mixed> $attackerUnit
   * @param array{status_applied?:mixed,status_duration_rounds?:mixed,status_params?:mixed} $outcome
   */
  private function applyAttackerPassiveStatusAugments(
    array &$statusMap,
    array $attackerUnit,
    string $targetId,
    array $outcome,
    int $round,
    int $tick
  ): ?string {
    $passives = (array)($attackerUnit['passive_abilities'] ?? []);
    if (count($passives) === 0) {
      return null;
    }

    $statusId = trim((string)($outcome['status_applied'] ?? ''));
    if ($statusId === '') {
      return null;
    }

    $messages = [];
    $duration = max(1, (int)($outcome['status_duration_rounds'] ?? 1));
    $params = is_array($outcome['status_params'] ?? null) ? $outcome['status_params'] : [];

    if ($statusId === 'marked' && in_array('barbed_mark', $passives, true)) {
      $this->applyStatusState(
        $statusMap,
        $targetId,
        'snared',
        2,
        ['is_debuff' => true],
        $round,
        $tick
      );
      $messages[] = 'barbed_mark applied snared';
    }

    $statusPotencyPct = 0.0;
    if (in_array('toxic_tools', $passives, true)) {
      $statusPotencyPct = max($statusPotencyPct, 0.15);
    }
    $statusPotencyPct += max(0.0, (float)($attackerUnit['combat_affixes']['status_potency_pct'] ?? 0.0));
    if ($statusPotencyPct > 0.0 && $this->isDebuffStatus($statusId, ['params' => $params])) {
      $params = $this->applyStatusPotencyBonus($params, $statusPotencyPct);
      $this->applyStatusState($statusMap, $targetId, $statusId, $duration, $params, $round, $tick);
      $messages[] = sprintf('toxic_tools strengthened %s', $statusId);
    }

    if (isset($params['attack_reduction_pct']) && in_array('brutal_suppression', $passives, true)) {
      $params['attack_reduction_pct'] = (float)$params['attack_reduction_pct'] + 0.08;
      $this->applyStatusState($statusMap, $targetId, $statusId, $duration, $params, $round, $tick);
      $messages[] = 'brutal_suppression strengthened attack reduction';
    }

    if ($statusId === 'disarmed' && in_array('disabling_hit', $passives, true)) {
      $params['attack_reduction_pct'] = (float)($params['attack_reduction_pct'] ?? 0.0) + 0.08;
      $this->applyStatusState($statusMap, $targetId, $statusId, $duration, $params, $round, $tick);
      $messages[] = 'disabling_hit strengthened disarm';
    }

    if ($statusId === 'cracked_armor' && in_array('shatter_plate', $passives, true)) {
      $params['defense_reduction_flat'] = (int)($params['defense_reduction_flat'] ?? 0) + 1;
      $this->applyStatusState($statusMap, $targetId, $statusId, $duration, $params, $round, $tick);
      $messages[] = 'shatter_plate strengthened cracked armor';
    }

    if ($statusId === 'poison' && in_array('lingering_cloud', $passives, true)) {
      $params['poison_damage_ratio'] = (float)($params['poison_damage_ratio'] ?? 0.2) * 1.15;
      $duration += 1;
      $this->applyStatusState($statusMap, $targetId, $statusId, $duration, $params, $round, $tick);
      $messages[] = 'lingering_cloud extended poison';
    }

    if ($statusId === 'poison' && in_array('sickly_weakness', $passives, true)) {
      $params['counts_as_extra_debuff_type'] = max(1, (int)($params['counts_as_extra_debuff_type'] ?? 0) + 1);
      $this->applyStatusState($statusMap, $targetId, $statusId, $duration, $params, $round, $tick);
      $messages[] = 'sickly_weakness increased poison debuff weight';
    }

    return count($messages) > 0 ? implode(', ', $messages) : null;
  }

  /**
   * @param array<string,mixed> $outcome
   * @param array<string,mixed> $actorUnit
   * @param array<string,mixed> $targetUnit
   * @return array<string,mixed>
   */
  private function applySupportOutcomeActorPassives(array $outcome, array $actorUnit, array $targetUnit, int $targetHpBefore): array
  {
    $passives = (array)($actorUnit['passive_abilities'] ?? []);
    $statusId = trim((string)($outcome['status_applied'] ?? ''));
    $params = is_array($outcome['status_params'] ?? null) ? $outcome['status_params'] : [];
    $messages = [];

    if ($statusId === 'bolstered' && in_array('rally_rhythm', $passives, true)) {
      $params['attack_pct'] = max((float)($params['attack_pct'] ?? 0.0), 0.10);
      $messages[] = 'rally_rhythm added attack boost';
    }

    if ($statusId === 'warcry' && in_array('chant_of_violence', $passives, true)) {
      $params['attack_pct'] = (float)($params['attack_pct'] ?? 0.0) + 0.08;
      $messages[] = 'chant_of_violence strengthened warcry';
    }

    if (
      $statusId === 'bolstered'
      && in_array('patch_job', $passives, true)
      && $this->isWounded($targetHpBefore, (int)($targetUnit['max_hp'] ?? 1))
    ) {
      $heal = 2;
      $outcome['target_hp_after'] = min((int)($targetUnit['max_hp'] ?? $targetHpBefore), $targetHpBefore + $heal);
      $messages[] = sprintf('patch_job healed %d', $heal);
    }

    if (count($messages) === 0) {
      return $outcome;
    }

    $outcome['status_params'] = $params;
    $existingAffix = trim((string)($outcome['affix_outcome'] ?? ''));
    $outcome['affix_outcome'] = implode(', ', array_values(array_filter([
      $existingAffix !== '' ? $existingAffix : null,
      implode(', ', $messages),
    ])));

    return $outcome;
  }

  /**
   * @param array<string,array<string,array<string,mixed>>> $statusMap
   * @param array<string,array<string,mixed>> $unitsById
   * @param array<string,int> $hpByUnitId
   * @param array<string,mixed> $outcome
   */
  private function applySupportEchoPassive(
    string &$state,
    array &$statusMap,
    array $unitsById,
    array $hpByUnitId,
    string $targetId,
    array $outcome,
    int $round,
    int $tick
  ): ?string {
    $targetUnit = $unitsById[$targetId] ?? null;
    if (!is_array($targetUnit) || !in_array('attention_hog', (array)($targetUnit['passive_abilities'] ?? []), true)) {
      return null;
    }

    $statusId = trim((string)($outcome['status_applied'] ?? ''));
    $params = is_array($outcome['status_params'] ?? null) ? $outcome['status_params'] : [];
    $duration = max(1, (int)($outcome['status_duration_rounds'] ?? 1));
    if ($statusId === '' || count($params) === 0) {
      return null;
    }

    $candidates = array_values(array_filter(
      $this->aliveUnitIds($hpByUnitId),
      static fn(string $unitId): bool => $unitId !== $targetId
    ));
    if (count($candidates) === 0) {
      return null;
    }

    $allyTargetId = (string)$candidates[$this->nextInt($state, count($candidates))];
    $this->applyStatusState(
      $statusMap,
      $allyTargetId,
      $statusId,
      $duration,
      $this->scaleSupportStatusParams($statusId, $params, 0.5),
      $round,
      $tick
    );

    return sprintf('attention_hog echoed %s to %s', $statusId, $allyTargetId);
  }

  /**
   * @param array<string,mixed> $params
   * @return array<string,mixed>
   */
  private function scaleSupportStatusParams(string $statusId, array $params, float $scale): array
  {
    if ($statusId === 'guard_stacks') {
      $params['stack_count'] = max(1, (int)ceil(max(0, (int)($params['stack_count'] ?? 1)) * $scale));
      return $params;
    }

    foreach (['defense_pct', 'attack_pct', 'damage_taken_pct', 'damage_taken_melee_pct', 'poison_damage_ratio', 'attack_reduction_pct'] as $floatKey) {
      if (isset($params[$floatKey])) {
        $params[$floatKey] = round(max(0.0, (float)$params[$floatKey]) * $scale, 4);
      }
    }

    foreach (['lucky_bonus_flat', 'defense_reduction_flat'] as $intKey) {
      if (isset($params[$intKey])) {
        $params[$intKey] = max(1, (int)ceil(max(0, (int)$params[$intKey]) * $scale));
      }
    }

    return $params;
  }

  /**
   * @param array<string,mixed> $outcome
   * @param array<string,array<string,mixed>> $unitsById
   * @param array<string,int> $hpByUnitId
   * @param array<string,array<string,array<string,mixed>>> $statusMap
   * @return array<string,mixed>
   */
  private function applyAllyProtectionPassives(
    array $outcome,
    string $targetId,
    array $unitsById,
    array $hpByUnitId,
    array $statusMap,
    bool $guardRedirected = false
  ): array {
    $damage = max(0, (int)($outcome['damage'] ?? 0));
    if ($damage <= 0) {
      return $outcome;
    }

    $messages = [];
    $reductionPct = $this->bodyguardDamageReductionPct($targetId, $unitsById, $hpByUnitId);
    if ($reductionPct > 0.0) {
      $reduced = max(1, (int)floor($damage * (1 - $reductionPct)));
      if ($reduced < $damage) {
        $damage = $reduced;
        $messages[] = 'bodyguard reduced damage';
      }
    }

    if ($guardRedirected && in_array('unmoving', (array)($unitsById[$targetId]['passive_abilities'] ?? []), true)) {
      $damage = max(1, $damage - 2);
      $messages[] = 'unmoving reduced redirected hit';
    }

    if (count($messages) === 0) {
      return $outcome;
    }

    $targetHpBefore = max(0, (int)($hpByUnitId[$targetId] ?? 0));
    $targetHpAfter = max(0, $targetHpBefore - $damage);
    $outcome['damage'] = $damage;
    $outcome['target_hp_after'] = $targetHpAfter;
    $outcome['outcome'] = $targetHpAfter <= 0 ? 'defeated' : 'hit';
    $existingAffix = trim((string)($outcome['affix_outcome'] ?? ''));
    $outcome['affix_outcome'] = implode(', ', array_values(array_filter([
      $existingAffix !== '' ? $existingAffix : null,
      implode(', ', $messages),
    ])));

    return $outcome;
  }

  /**
   * @param array<string,array<string,mixed>> $unitsById
   * @param array<string,int> $hpByUnitId
   */
  private function bodyguardDamageReductionPct(string $targetId, array $unitsById, array $hpByUnitId): float
  {
    $lowestAllyId = $this->lowestHpAllyId($hpByUnitId, $unitsById);
    if ($lowestAllyId === null || $lowestAllyId !== $targetId) {
      return 0.0;
    }

    $bestReduction = 0.0;
    foreach ($unitsById as $unitId => $unit) {
      if (
        $unitId === $targetId
        || ($hpByUnitId[$unitId] ?? 0) <= 0
        || !in_array('bodyguard', (array)($unit['passive_abilities'] ?? []), true)
      ) {
        continue;
      }
      $bestReduction = max($bestReduction, 0.15);
    }

    return $bestReduction;
  }

  /**
   * @param array<string,array<string,array<string,mixed>>> $statusMap
   */
  private function forcedGuardTargetId(array $hpByUnitId, array $statusMap): ?string
  {
    $bestId = null;
    $bestTick = -1;
    foreach ($this->aliveUnitIds($hpByUnitId) as $unitId) {
      $guardState = $statusMap[$unitId]['guard_stacks'] ?? null;
      if (!is_array($guardState) || (bool)($guardState['params']['taunt_redirect'] ?? false) !== true) {
        continue;
      }
      $appliedTick = (int)($guardState['applied_tick'] ?? 0);
      if ($appliedTick > $bestTick) {
        $bestTick = $appliedTick;
        $bestId = $unitId;
      }
    }

    return $bestId;
  }

  /**
   * @param array<string,array<string,array<string,mixed>>> $statusMap
   * @param array<string,int> $hpByUnitId
   */
  private function forcedNextAttackTargetId(array $statusMap, string $actorId, array $hpByUnitId): ?string
  {
    $wrestled = $statusMap[$actorId]['wrestled'] ?? null;
    if (!is_array($wrestled)) {
      return null;
    }

    $forcedTargetId = trim((string)($wrestled['params']['forced_target_id'] ?? ''));
    if ($forcedTargetId === '') {
      return null;
    }

    return (($hpByUnitId[$forcedTargetId] ?? 0) > 0) ? $forcedTargetId : null;
  }

  /**
   * @param array<string,array<string,array<string,mixed>>> $statusMap
   */
  private function consumeForcedNextAttackTarget(array &$statusMap, string $actorId): void
  {
    if (!isset($statusMap[$actorId]['wrestled'])) {
      return;
    }

    unset($statusMap[$actorId]['wrestled']);
  }

  /**
   * @param array<string,mixed> $outcome
   * @return array<string,mixed>
   */
  private function applySourceLinkedStatusParams(array $outcome, string $sourceUnitId): array
  {
    if (trim((string)($outcome['status_applied'] ?? '')) !== 'wrestled') {
      return $outcome;
    }

    $params = is_array($outcome['status_params'] ?? null) ? $outcome['status_params'] : [];
    $params['forced_target_id'] = $sourceUnitId;
    $params['consumes_on_hostile_action'] = true;
    $params['is_debuff'] = true;
    $outcome['status_params'] = $params;

    return $outcome;
  }

  /**
   * @param array<string,mixed> $combatAffixes
   * @param array<string,array<string,mixed>> $unitsById
   * @param array<string,int> $hpByUnitId
   * @param array<string,bool> $damagedThisRound
   * @return array<string,mixed>
   */
  private function applyTeamDamagePassives(
    array $combatAffixes,
    array $unitsById,
    array $hpByUnitId,
    string $targetId,
    array $damagedThisRound,
    array $targetStatuses = []
  ): array {
    $bonusPct = 0.0;
    $breakOpenPct = 0.0;
    foreach ($unitsById as $unitId => $unit) {
      if (($hpByUnitId[$unitId] ?? 0) <= 0) {
        continue;
      }
      $passives = (array)($unit['passive_abilities'] ?? []);
      if (isset($damagedThisRound[$targetId]) && in_array('mob_mentality', $passives, true)) {
        $bonusPct = max($bonusPct, 0.12);
      }
      if (isset($targetStatuses['cracked_armor']) && in_array('break_open', $passives, true)) {
        $breakOpenPct = max($breakOpenPct, 0.12);
      }
    }

    if ($bonusPct > 0.0) {
      $combatAffixes['damaged_enemy_bonus_pct'] = max(
        (float)($combatAffixes['damaged_enemy_bonus_pct'] ?? 0.0),
        $bonusPct
      );
    }

    if ($breakOpenPct > 0.0) {
      $combatAffixes['status_bonus_target'] = 'cracked_armor';
      $combatAffixes['status_bonus_pct'] = max((float)($combatAffixes['status_bonus_pct'] ?? 0.0), $breakOpenPct);
    }

    return $combatAffixes;
  }

  /**
   * @param array<string,mixed> $params
   * @return array<string,mixed>
   */
  private function applyStatusPotencyBonus(array $params, float $potencyPct): array
  {
    foreach (['damage_taken_pct', 'damage_taken_melee_pct', 'attack_reduction_pct', 'poison_damage_ratio'] as $floatKey) {
      if (isset($params[$floatKey])) {
        $params[$floatKey] = round((float)$params[$floatKey] * (1 + $potencyPct), 4);
      }
    }

    if (isset($params['defense_reduction_flat'])) {
      $params['defense_reduction_flat'] = max(
        1,
        (int)ceil((int)$params['defense_reduction_flat'] * (1 + $potencyPct))
      );
    }

    return $params;
  }

  /**
   * @param array<string,array<string,array<string,mixed>>> $statusMap
   * @param array<string,array<string,mixed>> $unitsById
   * @param array<string,int> $hpByUnitId
   * @param array{dice_used:array<int,array{kind:string,dice_instance_id:?string,sides:int}>,dice_rolls:array<int,array{sides:int,roll:int}>,slot_traces?:array<int,array<string,mixed>>,slot_trace_summary?:string,dice_outcome:string,dice_modifier:int,explode_triggered:bool} $dice
   * @return array{dice:array<string,mixed>,outcome:?string}
   */
  private function applyTeamLuckPassives(
    array &$statusMap,
    array $unitsById,
    array $hpByUnitId,
    array $dice,
    int $round,
    int $tick
  ): array {
    if ($this->diceRollTotal($dice) > 2 || $this->diceRollTotal($dice) <= 0) {
      return ['dice' => $dice, 'outcome' => null];
    }

    foreach ($unitsById as $unitId => $unit) {
      if (($hpByUnitId[$unitId] ?? 0) <= 0 || !in_array('dumb_luck', (array)($unit['passive_abilities'] ?? []), true)) {
        continue;
      }

      $ready = (array)($statusMap[$unitId]['dumb_luck_ready']['params'] ?? []);
      if ((bool)($ready['used'] ?? false) === true) {
        continue;
      }

      $dice['dice_modifier'] = (int)($dice['dice_modifier'] ?? 0) + 2;
      $dice['dice_outcome'] = trim(((string)($dice['dice_outcome'] ?? '')) . ' + dumb_luck');
      $this->applyStatusState(
        $statusMap,
        $unitId,
        'dumb_luck_ready',
        99,
        ['is_debuff' => false, 'used' => true],
        $round,
        $tick
      );

      return [
        'dice' => $dice,
        'outcome' => sprintf('dumb_luck improved a low roll for %s', $unitId),
      ];
    }

    return ['dice' => $dice, 'outcome' => null];
  }

  /**
   * @param array<int,array<string,mixed>> $events
   * @param array<string,mixed> $actorUnit
   * @param array{ability_id?:mixed,target?:mixed,equip_order?:mixed} $ability
   * @param array<string,array<string,mixed>> $targetUnitsById
   * @param array<string,int> $targetHpById
   * @param array<string,array<string,array<string,mixed>>> $targetStatuses
   * @param array<string,array<string,array<string,mixed>>> $actorStatuses
   * @param array{dice_used:array<int,array{kind:string,dice_instance_id:?string,sides:int}>,dice_rolls:array<int,array{sides:int,roll:int}>,slot_traces?:array<int,array<string,mixed>>,dice_outcome:string,dice_modifier:int,explode_triggered:bool} $dice
   */
  private function resolveAdditionalAbilityTargets(
    array &$events,
    string &$state,
    AbilityRegistry $abilityRegistry,
    string $side,
    array $actorUnit,
    string $actorId,
    array $ability,
    string $primaryTargetId,
    array $targetUnitsById,
    array &$targetHpById,
    array &$targetStatuses,
    array &$actorStatuses,
    array $dice,
    int $round,
    int $tick
  ): ?string {
    $abilityId = trim((string)($ability['ability_id'] ?? ''));
    if ($abilityId === '' || !$abilityRegistry->has($abilityId)) {
      return null;
    }

    $definition = $abilityRegistry->get($abilityId);
    $targetCount = max(1, (int)($definition->defaultParams['target_count'] ?? 1));
    if ($targetCount <= 1) {
      return null;
    }

    $availableIds = array_values(array_filter(
      $this->aliveUnitIds($targetHpById),
      static fn(string $candidateId): bool => $candidateId !== $primaryTargetId
    ));
    if (count($availableIds) === 0) {
      return null;
    }

    $messages = [];
    $targetPreference = (string)($ability['target'] ?? 'enemy_back_prefer');
    $extraTargetsToResolve = min($targetCount - 1, count($availableIds));
    for ($index = 0; $index < $extraTargetsToResolve; $index++) {
      $selection = $this->chooseTargetSelection(
        $state,
        $availableIds,
        $targetUnitsById,
        $targetPreference,
        $actorId,
        $targetHpById,
        $targetStatuses,
        null
      );
      $targetId = (string)$selection['id'];
      $targetUnit = $targetUnitsById[$targetId] ?? null;
      if (!is_array($targetUnit)) {
        continue;
      }

      $outcome = $this->deriveActionOutcome(
        $state,
        $this->effectiveAttackWithStatuses((int)$actorUnit['attack'], (array)($actorStatuses[$actorId] ?? [])),
        (int)($targetUnit['defense'] ?? 0),
        (int)($targetHpById[$targetId] ?? (int)($targetUnit['max_hp'] ?? 1)),
        (int)($targetUnit['max_hp'] ?? 1),
        $abilityId,
        (int)($dice['dice_modifier'] ?? 0),
        (array)($actorUnit['combat_affixes'] ?? ['damage_flat' => 0, 'below_half_bonus' => 0.0]),
        $dice,
        (array)($targetStatuses[$targetId] ?? []),
        (array)($actorUnit['pos'] ?? ['x' => 1, 'y' => 1]),
        (array)($targetUnit['pos'] ?? ['x' => 1, 'y' => 1]),
        (array)($actorUnit['formation'] ?? ['w' => 1, 'h' => 1]),
        (array)($targetUnit['formation'] ?? ['w' => 1, 'h' => 1]),
        $abilityRegistry,
        (int)$actorUnit['attack'],
        0,
        (int)($actorUnit['precision'] ?? 5),
        (int)($targetUnit['resolve'] ?? 5)
      );
      $outcome = $this->applySourceLinkedStatusParams($outcome, $actorId);
      $this->applyOutcomeStatus($targetStatuses, $targetId, $outcome, $round, $tick);
      $augment = $this->applyAttackerPassiveStatusAugments($targetStatuses, $actorUnit, $targetId, $outcome, $round, $tick);
      $targetHpById[$targetId] = (int)($outcome['target_hp_after'] ?? ($targetHpById[$targetId] ?? 0));

      $events[] = [
        'type' => 'action_splash',
        'round' => $round,
        'tick' => $tick,
        'side' => $side,
        $side === 'player' ? 'actor_unit_instance_id' : 'actor_enemy_slug' => $actorId,
        $side === 'player' ? 'target_enemy_slug' : 'target_unit_instance_id' => $targetId,
        'ability_id' => $abilityId,
        'targeting_reason' => $selection['reason'],
        'targeting_weights' => $selection['weights'],
        'status_augment_outcome' => $augment,
        ...$outcome,
      ];

      $messages[] = sprintf('extra target %s took %d damage', $targetId, (int)($outcome['damage'] ?? 0));
      $availableIds = array_values(array_filter(
        $availableIds,
        static fn(string $candidateId): bool => $candidateId !== $targetId
      ));
      if (count($availableIds) === 0) {
        break;
      }
    }

    return count($messages) > 0 ? implode(', ', $messages) : null;
  }

  /**
   * @param array<int,array{ability_id:string,speed:int,target:string,trigger_tick:int,equip_order:int}> $schedule
   * @param array<int,string> $passiveAbilityIds
   * @return array<int,array{ability_id:string,speed:int,target:string,trigger_tick:int,equip_order:int}>
   */
  private function applyPassiveAbilityTargetingPreferencesToSchedule(array $schedule, array $passiveAbilityIds): array
  {
    $preferAimedShot = in_array('patient_aim', $passiveAbilityIds, true) || in_array('pick_your_mark', $passiveAbilityIds, true);
    if (!$preferAimedShot) {
      return $schedule;
    }

    foreach ($schedule as &$entry) {
      if ((string)$entry['ability_id'] === 'aimed_shot') {
        $entry['target'] = 'enemy_back_prefer_marked_wounded_preferred_previous_target';
      }
    }
    unset($entry);

    return $schedule;
  }

  /**
   * @param array<int,string> $abilityIds
   * @return array<int,array{ability_id:string,speed:int,target:string,equip_order:int}>
   */
  private function normalizeOrderedActiveAbilityScheduleEntries(array $abilityIds, AbilityRegistry $registry): array
  {
    $entries = [];
    foreach ($abilityIds as $abilityId) {
      $id = trim((string)$abilityId);
      if ($id === '') {
        continue;
      }
      if (!$registry->has($id)) {
        throw new RuntimeException('combat_unknown_ability');
      }

      $def = $registry->get($id);
      if ($def->type !== AbilityType::Active || $def->speed === null) {
        throw new RuntimeException('combat_unschedulable_ability');
      }

      $entries[] = [
        'ability_id' => $id,
        'speed' => (int)$def->speed,
        'target' => $def->defaultTarget?->value ?? 'enemy_front_prefer',
        'equip_order' => count($entries),
      ];
    }

    return $entries;
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

    return null;
  }

  private function pickStatusEffect(string &$state, string $abilityId): ?string
  {
    $registry = new AbilityRegistry();
    if ($registry->has($abilityId)) {
      $statusId = trim((string)($registry->get($abilityId)->defaultParams['status_id'] ?? ''));
      if ($statusId !== '') {
        return $statusId;
      }
    }

    return null;
  }

  private function supportStatusEffect(string $abilityId): ?string
  {
    $registry = new AbilityRegistry();
    if ($registry->has($abilityId)) {
      $statusId = trim((string)($registry->get($abilityId)->defaultParams['status_id'] ?? ''));
      if ($statusId !== '') {
        return $statusId;
      }
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
   * @return list<mixed>
   */
  private function decodeJsonList(mixed $raw): array
  {
    if (is_array($raw)) {
      return array_is_list($raw) ? $raw : [];
    }

    if (is_string($raw)) {
      $decoded = json_decode($raw, true);
      return is_array($decoded) && array_is_list($decoded) ? $decoded : [];
    }

    return [];
  }

  /**
   * @param array<string,mixed> $baseStats
   * @param array<string,mixed> $modifiers
   * @return array<string,mixed>
   */
  private function applySpliceStatModifiers(array $baseStats, array $modifiers): array
  {
    foreach (['attack', 'defense', 'max_hp', 'precision', 'resolve'] as $key) {
      $default = match ($key) {
        'max_hp' => 1,
        'precision', 'resolve' => 5,
        default => 0,
      };
      $baseStats[$key] = max(0, (int)($baseStats[$key] ?? $default) + (int)($modifiers[$key] ?? 0));
    }

    if (isset($baseStats['max_hp'])) {
      $baseStats['max_hp'] = max(1, (int)$baseStats['max_hp']);
    }

    return $baseStats;
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
   * @param array<string,mixed> $abilitySet
   * @return array<int,string>
   */
  private function flattenPassiveAbilityIds(array $abilitySet): array
  {
    $out = [];

    $bucket = $abilitySet['passives'] ?? [];
    if (is_array($bucket)) {
      foreach ($bucket as $abilityId) {
        $id = trim((string)$abilityId);
        if ($id !== '') {
          $out[] = $id;
        }
      }
    }

    return array_values(array_unique($out));
  }

  /**
   * @param array<int,int> $unitIds
   * @return array<string,array<int,string>>
   */
  private function loadUnlockedPassiveAbilityIdsByUnit(array $unitIds, AbilityRegistry $abilityRegistry): array
  {
    if (count($unitIds) === 0 || !$this->schemaHasTable('unit_instance_unlocked_abilities')) {
      return [];
    }

    $unitIds = array_values(array_unique(array_map(static fn($value): int => (int)$value, $unitIds)));
    $placeholders = implode(',', array_fill(0, count($unitIds), '?'));
    $stmt = $this->pdo->prepare("
      SELECT `unit_instance_id`, `ability_id`
      FROM `unit_instance_unlocked_abilities`
      WHERE `unit_instance_id` IN ($placeholders)
      ORDER BY `unit_instance_id` ASC, `created_at` ASC, `ability_id` ASC
    ");
    $stmt->execute($unitIds);

    $byUnit = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
      $abilityId = trim((string)($row['ability_id'] ?? ''));
      if ($abilityId === '' || !$abilityRegistry->has($abilityId)) {
        continue;
      }

      if ($abilityRegistry->get($abilityId)->type !== AbilityType::Passive) {
        continue;
      }

      $unitId = (string)$row['unit_instance_id'];
      $byUnit[$unitId] ??= [];
      if (!in_array($abilityId, $byUnit[$unitId], true)) {
        $byUnit[$unitId][] = $abilityId;
      }
    }

    return $byUnit;
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

  /**
   * @param array{slug:string,message:string,primitive:string,currency_soft:int,result:array<string,mixed>} $nodeEffect
   */
  private function describeNodeEffect(string $nodeType, array $nodeEffect): string
  {
    $result = is_array($nodeEffect['result'] ?? null) ? $nodeEffect['result'] : [];
    $currencySoft = (int)($nodeEffect['currency_soft'] ?? 0);
    $effectName = $this->humanizeId((string)($nodeEffect['slug'] ?? $nodeEffect['message'] ?? 'effect'));

    if ($nodeType === 'shrine') {
      $copy = trim((string)($result['result_copy'] ?? ''));
      if ($copy === '') {
        $copy = sprintf('%s settles over the squad.', $effectName);
      }
      return $currencySoft > 0
        ? sprintf('%s %s grants %d teeth.', $copy, $effectName, $currencySoft)
        : $copy;
    }

    if ($nodeType === 'hazard') {
      $copy = trim((string)($result['result_copy'] ?? ''));
      if ($copy !== '') {
        return $copy;
      }

      $effect = is_array($result['effect'] ?? null) ? $result['effect'] : [];
      $damage = (int)($result['damage_each'] ?? $result['damage'] ?? 0);
      $damage = max($damage, (int)($effect['damage'] ?? 0));
      if ($damage > 0) {
        return sprintf('%s deals %d damage across the squad.', $effectName, $damage);
      }

      return sprintf('%s leaves the squad worse off.', $effectName);
    }

    return sprintf('%s resolves without a fight.', $effectName);
  }

  private function humanizeId(string $value): string
  {
    $segments = preg_split('/[_#\s-]+/', trim($value)) ?: [];
    $words = array_map(
      static fn(string $segment): string => ucfirst($segment),
      array_filter($segments, static fn(string $segment): bool => $segment !== '')
    );

    return implode(' ', $words);
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

  private function pickSpliceVariantSlug(int $userId, string &$state): string
  {
    $spliceVariantService = new SpliceVariantService($this->pdo);
    $totalWeight = $spliceVariantService->totalEnabledWeightForUser($userId);
    if ($totalWeight <= 0) {
      return SpliceVariantService::BASIC_GOBLIN;
    }

    $roll = $this->nextInt($state, $totalWeight);
    return $spliceVariantService->rollVariantSlugForUser($userId, $roll);
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
