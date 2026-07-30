<?php
declare(strict_types=1);

namespace DiceGoblins\Services;

final class EncounterPrimitiveCatalog
{
  /** @var list<array{slug:string,primitive:string,regions:list<string>,severities:list<string>,weights:array<string,int>,min_depth:int,title:string,result_copy:string,result:array<string,mixed>}> */
  private const HAZARD_EFFECTS = [
    [
      'slug' => 'hazard_cautious_footing',
      'primitive' => 'route_pressure',
      'regions' => ['the_farm', 'mountains', 'swamps'],
      'severities' => ['minor', 'moderate'],
      'weights' => ['minor' => 8, 'moderate' => 4],
      'min_depth' => 3,
      'title' => 'Cautious Footing',
      'result_copy' => 'The squad slows down and loses momentum on the route.',
      'result' => ['effect' => ['type' => 'route_pressure', 'pressure' => 'route']],
    ],
    [
      'slug' => 'hazard_mud_slick',
      'primitive' => 'temporary_modifier',
      'regions' => ['the_farm'],
      'severities' => ['minor', 'moderate'],
      'weights' => ['minor' => 6, 'moderate' => 5],
      'min_depth' => 3,
      'title' => 'Mud Slick',
      'result_copy' => 'Mud gums up the squad before the next fight.',
      'result' => ['effect' => ['type' => 'run_stat_modifier_next_combat', 'stat_adders' => ['precision' => -2]]],
    ],
    [
      'slug' => 'hazard_broken_fence',
      'primitive' => 'route_pressure',
      'regions' => ['the_farm'],
      'severities' => ['minor'],
      'weights' => ['minor' => 4],
      'min_depth' => 4,
      'title' => 'Broken Fence',
      'result_copy' => 'The route snarls into a slow detour.',
      'result' => ['effect' => ['type' => 'route_pressure', 'pressure' => 'route']],
    ],
    [
      'slug' => 'hazard_splintered_trap',
      'primitive' => 'hp_attrition',
      'regions' => ['the_farm', 'mountains'],
      'severities' => ['minor', 'moderate'],
      'weights' => ['minor' => 5, 'moderate' => 3],
      'min_depth' => 3,
      'title' => 'Splintered Trap',
      'result_copy' => 'A crude trap bites one unlucky unit.',
      'result' => ['effect' => ['type' => 'damage_random_unit', 'damage' => 5]],
    ],
    [
      'slug' => 'hazard_bad_rations',
      'primitive' => 'temporary_modifier',
      'regions' => ['the_farm', 'swamps'],
      'severities' => ['minor', 'moderate'],
      'weights' => ['minor' => 4, 'moderate' => 4],
      'min_depth' => 3,
      'title' => 'Bad Rations',
      'result_copy' => 'Something disagrees with the squad before the next fight.',
      'result' => ['effect' => ['type' => 'run_stat_modifier_next_combat', 'stat_adders' => ['resolve' => -2]]],
    ],
    [
      'slug' => 'hazard_loose_scree',
      'primitive' => 'hp_attrition',
      'regions' => ['mountains'],
      'severities' => ['minor', 'moderate', 'severe'],
      'weights' => ['minor' => 4, 'moderate' => 6, 'severe' => 3],
      'min_depth' => 4,
      'title' => 'Loose Scree',
      'result_copy' => 'The slope gives way under the warband.',
      'result' => ['effect' => ['type' => 'damage_squad', 'damage' => 3]],
    ],
    [
      'slug' => 'hazard_thin_air',
      'primitive' => 'temporary_modifier',
      'regions' => ['mountains'],
      'severities' => ['moderate', 'severe'],
      'weights' => ['moderate' => 5, 'severe' => 4],
      'min_depth' => 5,
      'title' => 'Thin Air',
      'result_copy' => 'The climb steals breath before the next fight.',
      'result' => ['effect' => ['type' => 'run_stat_modifier_next_combat', 'stat_multipliers' => ['resolve' => 0.85]]],
    ],
    [
      'slug' => 'hazard_toll_cairn',
      'primitive' => 'currency_pressure',
      'regions' => ['mountains'],
      'severities' => ['minor', 'moderate'],
      'weights' => ['minor' => 3, 'moderate' => 5],
      'min_depth' => 4,
      'title' => 'Toll Cairn',
      'result_copy' => 'The stones take their old road-price.',
      'result' => ['effect' => ['type' => 'lose_teeth', 'amount' => 6]],
    ],
    [
      'slug' => 'hazard_rust_thicket',
      'primitive' => 'item_pressure',
      'regions' => ['mountains', 'swamps'],
      'severities' => ['moderate', 'severe'],
      'weights' => ['moderate' => 4, 'severe' => 3],
      'min_depth' => 5,
      'title' => 'Rust Thicket',
      'result_copy' => 'Rusty growth dulls the squad before the next fight.',
      'result' => ['effect' => ['type' => 'run_stat_modifier_next_combat', 'stat_multipliers' => ['attack' => 0.9]]],
    ],
    [
      'slug' => 'hazard_bog_mire',
      'primitive' => 'kin_mitigation',
      'regions' => ['swamps'],
      'severities' => ['minor', 'moderate'],
      'weights' => ['minor' => 5, 'moderate' => 4],
      'min_depth' => 4,
      'title' => 'Bog Mire',
      'result_copy' => 'The mire pulls at the squad until somebody hauls them loose.',
      'result' => ['effect' => ['type' => 'damage_random_unit', 'damage' => 4], 'mitigated_by' => ['pig_kin']],
    ],
    [
      'slug' => 'hazard_biting_reeds',
      'primitive' => 'hp_attrition',
      'regions' => ['swamps'],
      'severities' => ['minor', 'moderate', 'severe'],
      'weights' => ['minor' => 4, 'moderate' => 6, 'severe' => 3],
      'min_depth' => 3,
      'title' => 'Biting Reeds',
      'result_copy' => 'The reeds rake across every exposed ankle.',
      'result' => ['effect' => ['type' => 'damage_squad', 'damage' => 2]],
    ],
    [
      'slug' => 'hazard_sinking_cache',
      'primitive' => 'item_pressure',
      'regions' => ['swamps'],
      'severities' => ['moderate'],
      'weights' => ['moderate' => 3],
      'min_depth' => 5,
      'title' => 'Sinking Cache',
      'result_copy' => 'Salvage sinks into the muck before the squad can grab it.',
      'result' => ['effect' => ['type' => 'lose_teeth', 'amount' => 8]],
    ],
    [
      'slug' => 'hazard_wrong_turn',
      'primitive' => 'route_pressure',
      'regions' => ['mountains', 'swamps'],
      'severities' => ['moderate', 'severe'],
      'weights' => ['moderate' => 2, 'severe' => 3],
      'min_depth' => 6,
      'title' => 'Wrong Turn',
      'result_copy' => 'The wrong path leaves the squad exposed before the next fight.',
      'result' => ['effect' => ['type' => 'run_stat_modifier_next_combat', 'stat_multipliers' => ['defense' => 0.85]]],
    ],
    [
      'slug' => 'hazard_black_gnats',
      'primitive' => 'temporary_modifier',
      'regions' => ['swamps'],
      'severities' => ['minor', 'moderate'],
      'weights' => ['minor' => 5, 'moderate' => 4],
      'min_depth' => 3,
      'title' => 'Black Gnats',
      'result_copy' => 'A biting cloud breaks the squad focus.',
      'result' => ['effect' => ['type' => 'run_stat_modifier_next_combat', 'stat_adders' => ['precision' => -1, 'resolve' => -1]]],
    ],
    [
      'slug' => 'hazard_collapse_warning',
      'primitive' => 'choice_pressure',
      'regions' => ['mountains', 'swamps'],
      'severities' => ['severe'],
      'weights' => ['severe' => 0],
      'min_depth' => 6,
      'title' => 'Collapse Warning',
      'result_copy' => 'A bad passage demands a hard choice.',
      'result' => ['effect' => ['type' => 'choice_offer'], 'choices' => [['type' => 'damage_squad', 'damage' => 10], ['type' => 'lose_teeth', 'amount' => 10]]],
    ],
  ];

  /** @var list<array{slug:string,primitive:string,regions:list<string>,qualities:list<string>,weights:array<string,int>,title:string,result_copy:string,favor:string,currency_min:int,currency_max:int,effect:array<string,mixed>,cost:array<string,mixed>}> */
  private const SHRINE_EFFECTS = [
    ['slug' => 'shrine_bone_whisper', 'primitive' => 'grant_teeth', 'regions' => ['the_farm', 'mountains', 'swamps'], 'qualities' => ['poor', 'good', 'great'], 'weights' => ['poor' => 8, 'good' => 5, 'great' => 3], 'title' => 'Bone Whisper', 'result_copy' => 'The bones clatter into a useful omen.', 'favor' => 'bone_whisper', 'currency_min' => 4, 'currency_max' => 8, 'effect' => ['type' => 'grant_teeth'], 'cost' => []],
    ['slug' => 'shrine_rust_blessing', 'primitive' => 'grant_teeth', 'regions' => ['the_farm', 'mountains', 'swamps'], 'qualities' => ['poor', 'good', 'great'], 'weights' => ['poor' => 7, 'good' => 5, 'great' => 3], 'title' => 'Rust Blessing', 'result_copy' => 'The shrine leaves a dull glint of favor behind.', 'favor' => 'rust_blessing', 'currency_min' => 4, 'currency_max' => 8, 'effect' => ['type' => 'grant_teeth'], 'cost' => []],
    ['slug' => 'shrine_clean_water', 'primitive' => 'heal_random_unit', 'regions' => ['the_farm', 'swamps'], 'qualities' => ['poor', 'good', 'great'], 'weights' => ['poor' => 4, 'good' => 6, 'great' => 5], 'title' => 'Clean Water', 'result_copy' => 'A clean sip steadies one wounded goblin.', 'favor' => 'clean_water', 'currency_min' => 0, 'currency_max' => 0, 'effect' => ['type' => 'heal_random_unit', 'amount_pct' => 35], 'cost' => []],
    ['slug' => 'shrine_old_goblin_mark', 'primitive' => 'squad_damage_next_combat', 'regions' => ['the_farm', 'mountains', 'swamps'], 'qualities' => ['good', 'great'], 'weights' => ['good' => 4, 'great' => 6], 'title' => 'Old Goblin Mark', 'result_copy' => 'An old mark sharpens the next fight.', 'favor' => 'old_goblin_mark', 'currency_min' => 0, 'currency_max' => 0, 'effect' => ['type' => 'squad_damage_next_combat', 'damage_multiplier' => 1.10], 'cost' => []],
    ['slug' => 'shrine_stone_hide', 'primitive' => 'run_stat_modifier_next_combat', 'regions' => ['mountains', 'swamps'], 'qualities' => ['good', 'great'], 'weights' => ['good' => 3, 'great' => 5], 'title' => 'Stone Hide', 'result_copy' => 'A slab-mark settles over the squad before the next fight.', 'favor' => 'stone_hide', 'currency_min' => 0, 'currency_max' => 0, 'effect' => ['type' => 'run_stat_modifier_next_combat', 'stat_multipliers' => ['defense' => 1.25]], 'cost' => []],
    ['slug' => 'shrine_clear_eye', 'primitive' => 'run_stat_modifier_next_combat', 'regions' => ['the_farm', 'mountains', 'swamps'], 'qualities' => ['good', 'great'], 'weights' => ['good' => 3, 'great' => 4], 'title' => 'Clear Eye', 'result_copy' => 'The next fight looks a little less crooked.', 'favor' => 'clear_eye', 'currency_min' => 0, 'currency_max' => 0, 'effect' => ['type' => 'run_stat_modifier_next_combat', 'stat_adders' => ['precision' => 2, 'resolve' => 2]], 'cost' => []],
    ['slug' => 'shrine_hidden_footpath', 'primitive' => 'clear_random_combat_node', 'regions' => ['mountains', 'swamps'], 'qualities' => ['good', 'great'], 'weights' => ['good' => 3, 'great' => 5], 'title' => 'Hidden Footpath', 'result_copy' => 'A hostile patrol gets lost before the warband reaches it.', 'favor' => 'hidden_footpath', 'currency_min' => 0, 'currency_max' => 0, 'effect' => ['type' => 'clear_random_combat_node'], 'cost' => []],
    ['slug' => 'shrine_bog_luck', 'primitive' => 'double_run_teeth', 'regions' => ['swamps'], 'qualities' => ['good', 'great'], 'weights' => ['good' => 2, 'great' => 6], 'title' => 'Bog Luck', 'result_copy' => 'The swamp doubles what the run has already shaken loose.', 'favor' => 'bog_luck', 'currency_min' => 0, 'currency_max' => 0, 'effect' => ['type' => 'double_run_teeth'], 'cost' => []],
    ['slug' => 'shrine_borrowed_future', 'primitive' => 'upgrade_run_unit_tier', 'regions' => ['the_farm', 'mountains', 'swamps'], 'qualities' => ['great'], 'weights' => ['great' => 4], 'title' => 'Borrowed Future', 'result_copy' => 'A newly found goblin comes back sharper than before.', 'favor' => 'borrowed_future', 'currency_min' => 0, 'currency_max' => 0, 'effect' => ['type' => 'upgrade_run_unit_tier', 'tier_increase' => 1, 'max_tier' => 3], 'cost' => []],
    ['slug' => 'shrine_crooked_bargain', 'primitive' => 'drain_highest_life_heal_rest', 'regions' => ['mountains', 'swamps'], 'qualities' => ['good', 'great'], 'weights' => ['good' => 2, 'great' => 5], 'title' => 'Crooked Bargain', 'result_copy' => 'The healthiest goblin pays for everyone else to stand tall.', 'favor' => 'crooked_bargain', 'currency_min' => 0, 'currency_max' => 0, 'effect' => ['type' => 'drain_highest_life_heal_rest', 'drain_pct' => 50], 'cost' => ['declineable' => true]],
  ];

  /**
   * @return array<string,list<string>>
   */
  public function vocabulary(): array
  {
    return [
      'hazard' => [
        'hp_attrition',
        'temporary_modifier',
        'currency_pressure',
        'item_pressure',
        'route_pressure',
        'kin_mitigation',
        'choice_pressure',
      ],
      'shrine' => [
        'grant_teeth',
        'heal_random_unit',
        'drain_highest_life_heal_rest',
        'squad_damage_next_combat',
        'run_stat_modifier_next_combat',
        'double_run_teeth',
        'upgrade_run_unit_tier',
        'clear_random_combat_node',
      ],
    ];
  }

  /**
   * @param callable(int):int $nextInt
   * @return array{
   *   family:string,
   *   slug:string,
   *   primitive:string,
   *   message:string,
   *   currency_soft:int,
   *   result:array<string,mixed>
   * }
   */
  public function resolveNodeEffect(string $nodeType, callable $nextInt, ?string $effectSlug = null, array $context = []): array
  {
    if ($nodeType === 'hazard') {
      $regionSlug = trim((string)($context['region_slug'] ?? ''));
      $severity = $this->normalizeHazardSeverity((string)($context['severity'] ?? $context['quality'] ?? 'moderate'));
      $definition = $this->hazardEffectBySlug($effectSlug ?? '') ?? $this->pickWeightedHazardEffect($nextInt, $regionSlug, $severity);
      return [
        'family' => 'hazard',
        'slug' => (string)$definition['slug'],
        'primitive' => (string)$definition['primitive'],
        'message' => 'hazard_resolved',
        'currency_soft' => 0,
        'result' => [
          'title' => (string)$definition['title'],
          'result_copy' => (string)$definition['result_copy'],
          'severity' => $severity,
          ...$definition['result'],
        ],
      ];
    }

    if ($nodeType === 'shrine') {
      $regionSlug = trim((string)($context['region_slug'] ?? ''));
      $quality = $this->normalizeShrineQuality((string)($context['quality'] ?? 'good'));
      $allowDeclineable = !empty($context['allow_declineable']);
      $definition = $this->shrineEffectBySlug($effectSlug ?? '') ?? $this->pickWeightedShrineEffect($nextInt, $regionSlug, $quality, $allowDeclineable);
      $currencyMin = (int)$definition['currency_min'];
      $currencyMax = max($currencyMin, (int)$definition['currency_max']);
      $currencySoft = $currencyMin + $nextInt(($currencyMax - $currencyMin) + 1);
      return [
        'family' => 'shrine',
        'slug' => (string)$definition['slug'],
        'primitive' => (string)$definition['primitive'],
        'message' => 'shrine_favor_granted',
        'currency_soft' => $currencySoft,
        'result' => [
          'favor' => (string)$definition['favor'],
          'title' => (string)$definition['title'],
          'result_copy' => (string)$definition['result_copy'],
          'currency_soft' => $currencySoft,
          'quality' => $quality,
          'effect' => $definition['effect'],
          'cost' => $definition['cost'],
          'declineable' => !empty($definition['cost']['declineable']),
        ],
      ];
    }

    return [
      'family' => $nodeType,
      'slug' => "{$nodeType}_default",
      'primitive' => 'default_resolution',
      'message' => 'non_combat_resolution',
      'currency_soft' => $nodeType === 'loot' ? 8 : 0,
      'result' => [],
    ];
  }

  /**
   * @return list<array{slug:string,primitive:string,regions:list<string>,severities:list<string>,weights:array<string,int>,min_depth:int,title:string,result_copy:string,result:array<string,mixed>}>
   */
  public function hazardEffectsForRegion(string $regionSlug, int $depth): array
  {
    return array_values(array_filter(
      self::HAZARD_EFFECTS,
      static fn(array $effect): bool => in_array($regionSlug, $effect['regions'], true)
        && $depth >= (int)$effect['min_depth']
        && array_sum(array_map('intval', $effect['weights'])) > 0
    ));
  }

  /**
   * @return list<array{slug:string,primitive:string,regions:list<string>,severities:list<string>,weights:array<string,int>,min_depth:int,title:string,result_copy:string,result:array<string,mixed>}>
   */
  public function hazardCatalog(): array
  {
    return self::HAZARD_EFFECTS;
  }

  /**
   * @return list<array{slug:string,primitive:string,regions:list<string>,qualities:list<string>,weights:array<string,int>,title:string,result_copy:string,favor:string,currency_min:int,currency_max:int,effect:array<string,mixed>,cost:array<string,mixed>}>
   */
  public function shrineCatalog(): array
  {
    return self::SHRINE_EFFECTS;
  }

  /**
   * @return array{slug:string,primitive:string,regions:list<string>,qualities:list<string>,weights:array<string,int>,title:string,result_copy:string,favor:string,currency_min:int,currency_max:int,effect:array<string,mixed>,cost:array<string,mixed>}
   */
  private function pickWeightedShrineEffect(callable $nextInt, string $regionSlug, string $quality, bool $allowDeclineable = false): array
  {
    $effects = array_values(array_filter(self::SHRINE_EFFECTS, static function (array $effect) use ($regionSlug, $quality, $allowDeclineable): bool {
      $regions = $effect['regions'];
      $qualities = $effect['qualities'];
      $cost = is_array($effect['cost'] ?? null) ? $effect['cost'] : [];
      return ($regionSlug === '' || in_array($regionSlug, $regions, true))
        && in_array($quality, $qualities, true)
        && ($allowDeclineable || empty($cost['declineable']))
        && max(0, (int)($effect['weights'][$quality] ?? 0)) > 0;
    }));
    if ($effects === []) {
      $effects = array_values(array_filter(self::SHRINE_EFFECTS, static function (array $effect) use ($allowDeclineable): bool {
        $cost = is_array($effect['cost'] ?? null) ? $effect['cost'] : [];
        return in_array('good', $effect['qualities'], true)
          && ($allowDeclineable || empty($cost['declineable']));
      }));
    }

    $total = array_sum(array_map(static fn(array $effect): int => max(0, (int)($effect['weights'][$quality] ?? $effect['weights']['good'] ?? 0)), $effects));
    $cursor = $nextInt(max(1, $total));
    foreach ($effects as $effect) {
      $weight = max(0, (int)($effect['weights'][$quality] ?? $effect['weights']['good'] ?? 0));
      if ($weight <= 0) {
        continue;
      }
      if ($cursor < $weight) {
        return $effect;
      }
      $cursor -= $weight;
    }

    return $effects[0];
  }

  /**
   * @return array{slug:string,primitive:string,regions:list<string>,severities:list<string>,weights:array<string,int>,min_depth:int,title:string,result_copy:string,result:array<string,mixed>}
   */
  private function pickWeightedHazardEffect(callable $nextInt, string $regionSlug, string $severity): array
  {
    $effects = array_values(array_filter(self::HAZARD_EFFECTS, static function (array $effect) use ($regionSlug, $severity): bool {
      return ($regionSlug === '' || in_array($regionSlug, $effect['regions'], true))
        && in_array($severity, $effect['severities'], true)
        && max(0, (int)($effect['weights'][$severity] ?? 0)) > 0;
    }));
    if ($effects === []) {
      $effects = array_values(array_filter(self::HAZARD_EFFECTS, static fn(array $effect): bool =>
        in_array('moderate', $effect['severities'], true)
          && max(0, (int)($effect['weights']['moderate'] ?? 0)) > 0
      ));
    }

    $total = array_sum(array_map(static fn(array $effect): int => max(0, (int)($effect['weights'][$severity] ?? $effect['weights']['moderate'] ?? 0)), $effects));
    $cursor = $nextInt(max(1, $total));
    foreach ($effects as $effect) {
      $weight = max(0, (int)($effect['weights'][$severity] ?? $effect['weights']['moderate'] ?? 0));
      if ($weight <= 0) {
        continue;
      }
      if ($cursor < $weight) {
        return $effect;
      }
      $cursor -= $weight;
    }

    return $effects[0];
  }

  /**
   * @return array{slug:string,primitive:string,regions:list<string>,qualities:list<string>,weights:array<string,int>,title:string,result_copy:string,favor:string,currency_min:int,currency_max:int,effect:array<string,mixed>,cost:array<string,mixed>}|null
   */
  private function shrineEffectBySlug(string $slug): ?array
  {
    foreach (self::SHRINE_EFFECTS as $effect) {
      if ((string)$effect['slug'] === $slug) {
        return $effect;
      }
    }

    return null;
  }

  private function normalizeShrineQuality(string $quality): string
  {
    return in_array($quality, ['poor', 'good', 'great'], true) ? $quality : 'good';
  }

  private function normalizeHazardSeverity(string $severity): string
  {
    return match ($severity) {
      'poor', 'minor' => 'minor',
      'great', 'severe' => 'severe',
      default => 'moderate',
    };
  }

  /**
   * @return array{slug:string,primitive:string,regions:list<string>,severities:list<string>,weights:array<string,int>,min_depth:int,title:string,result_copy:string,result:array<string,mixed>}|null
   */
  private function hazardEffectBySlug(string $slug): ?array
  {
    foreach (self::HAZARD_EFFECTS as $effect) {
      if ((string)$effect['slug'] === $slug) {
        return $effect;
      }
    }

    return null;
  }
}
