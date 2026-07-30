<?php
declare(strict_types=1);

namespace DiceGoblins\Services;

final class EncounterPrimitiveCatalog
{
  /** @var list<array{slug:string,primitive:string,regions:list<string>,min_depth:int,weight:int,result:array<string,mixed>}> */
  private const HAZARD_EFFECTS = [
    [
      'slug' => 'hazard_cautious_footing',
      'primitive' => 'route_pressure',
      'regions' => ['the_farm', 'mountains', 'swamps'],
      'min_depth' => 3,
      'weight' => 5,
      'result' => ['effect' => 'cautious_footing', 'pressure' => 'route'],
    ],
    [
      'slug' => 'hazard_mud_slick',
      'primitive' => 'temporary_modifier',
      'regions' => ['the_farm'],
      'min_depth' => 3,
      'weight' => 3,
      'result' => ['effect' => 'mud_slick', 'pressure' => 'temporary_modifier', 'stat' => 'precision'],
    ],
    [
      'slug' => 'hazard_broken_fence',
      'primitive' => 'route_pressure',
      'regions' => ['the_farm'],
      'min_depth' => 4,
      'weight' => 2,
      'result' => ['effect' => 'broken_fence', 'pressure' => 'route'],
    ],
    [
      'slug' => 'hazard_loose_scree',
      'primitive' => 'hp_attrition',
      'regions' => ['mountains'],
      'min_depth' => 4,
      'weight' => 3,
      'result' => ['effect' => 'loose_scree', 'pressure' => 'hp_attrition'],
    ],
    [
      'slug' => 'hazard_thin_air',
      'primitive' => 'temporary_modifier',
      'regions' => ['mountains'],
      'min_depth' => 5,
      'weight' => 2,
      'result' => ['effect' => 'thin_air', 'pressure' => 'temporary_modifier', 'stat' => 'resolve'],
    ],
    [
      'slug' => 'hazard_toll_cairn',
      'primitive' => 'currency_pressure',
      'regions' => ['mountains'],
      'min_depth' => 4,
      'weight' => 2,
      'result' => ['effect' => 'toll_cairn', 'pressure' => 'currency'],
    ],
    [
      'slug' => 'hazard_rust_thicket',
      'primitive' => 'item_pressure',
      'regions' => ['mountains', 'swamps'],
      'min_depth' => 5,
      'weight' => 2,
      'result' => ['effect' => 'rust_thicket', 'pressure' => 'item'],
    ],
    [
      'slug' => 'hazard_bog_mire',
      'primitive' => 'kin_mitigation',
      'regions' => ['swamps'],
      'min_depth' => 4,
      'weight' => 3,
      'result' => ['effect' => 'bog_mire', 'pressure' => 'kin_mitigation', 'mitigated_by' => ['pig_kin']],
    ],
    [
      'slug' => 'hazard_biting_reeds',
      'primitive' => 'hp_attrition',
      'regions' => ['swamps'],
      'min_depth' => 3,
      'weight' => 3,
      'result' => ['effect' => 'biting_reeds', 'pressure' => 'hp_attrition'],
    ],
    [
      'slug' => 'hazard_sinking_cache',
      'primitive' => 'item_pressure',
      'regions' => ['swamps'],
      'min_depth' => 5,
      'weight' => 2,
      'result' => ['effect' => 'sinking_cache', 'pressure' => 'item'],
    ],
    [
      'slug' => 'hazard_wrong_turn',
      'primitive' => 'route_pressure',
      'regions' => ['mountains', 'swamps'],
      'min_depth' => 6,
      'weight' => 1,
      'result' => ['effect' => 'wrong_turn', 'pressure' => 'route'],
    ],
  ];

  /** @var list<array{slug:string,primitive:string,regions:list<string>,qualities:list<string>,weights:array<string,int>,title:string,result_copy:string,favor:string,currency_min:int,currency_max:int,effect:array<string,mixed>,cost:array<string,mixed>}> */
  private const SHRINE_EFFECTS = [
    ['slug' => 'shrine_bone_whisper', 'primitive' => 'grant_teeth', 'regions' => ['the_farm', 'mountains', 'swamps'], 'qualities' => ['poor', 'good', 'great'], 'weights' => ['poor' => 8, 'good' => 5, 'great' => 3], 'title' => 'Bone Whisper', 'result_copy' => 'The bones clatter into a useful omen.', 'favor' => 'bone_whisper', 'currency_min' => 4, 'currency_max' => 8, 'effect' => ['type' => 'grant_teeth'], 'cost' => []],
    ['slug' => 'shrine_rust_blessing', 'primitive' => 'grant_teeth', 'regions' => ['the_farm', 'mountains', 'swamps'], 'qualities' => ['poor', 'good', 'great'], 'weights' => ['poor' => 7, 'good' => 5, 'great' => 3], 'title' => 'Rust Blessing', 'result_copy' => 'The shrine leaves a dull glint of favor behind.', 'favor' => 'rust_blessing', 'currency_min' => 4, 'currency_max' => 8, 'effect' => ['type' => 'grant_teeth'], 'cost' => []],
    ['slug' => 'shrine_clean_water', 'primitive' => 'heal_random_unit', 'regions' => ['the_farm', 'swamps'], 'qualities' => ['poor', 'good', 'great'], 'weights' => ['poor' => 4, 'good' => 6, 'great' => 5], 'title' => 'Clean Water', 'result_copy' => 'A clean sip steadies one wounded goblin.', 'favor' => 'clean_water', 'currency_min' => 0, 'currency_max' => 0, 'effect' => ['type' => 'heal_random_unit', 'amount_pct' => 35], 'cost' => []],
    ['slug' => 'shrine_old_goblin_mark', 'primitive' => 'squad_damage_next_combat', 'regions' => ['the_farm', 'mountains', 'swamps'], 'qualities' => ['good', 'great'], 'weights' => ['good' => 4, 'great' => 6], 'title' => 'Old Goblin Mark', 'result_copy' => 'An old mark sharpens the next fight.', 'favor' => 'old_goblin_mark', 'currency_min' => 0, 'currency_max' => 0, 'effect' => ['type' => 'squad_damage_next_combat', 'damage_multiplier' => 1.10], 'cost' => []],
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
      ],
      'shrine' => [
        'grant_teeth',
        'heal_random_unit',
        'drain_highest_life_heal_rest',
        'squad_damage_next_combat',
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
      $definition = $this->hazardEffectBySlug($effectSlug ?? '') ?? self::HAZARD_EFFECTS[0];
      return [
        'family' => 'hazard',
        'slug' => (string)$definition['slug'],
        'primitive' => (string)$definition['primitive'],
        'message' => 'hazard_avoided',
        'currency_soft' => 0,
        'result' => $definition['result'],
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
   * @return list<array{slug:string,primitive:string,regions:list<string>,min_depth:int,weight:int,result:array<string,mixed>}>
   */
  public function hazardEffectsForRegion(string $regionSlug, int $depth): array
  {
    return array_values(array_filter(
      self::HAZARD_EFFECTS,
      static fn(array $effect): bool => in_array($regionSlug, $effect['regions'], true)
        && $depth >= (int)$effect['min_depth']
        && (int)$effect['weight'] > 0
    ));
  }

  /**
   * @return list<array{slug:string,primitive:string,regions:list<string>,min_depth:int,weight:int,result:array<string,mixed>}>
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

  /**
   * @return array{slug:string,primitive:string,regions:list<string>,min_depth:int,weight:int,result:array<string,mixed>}|null
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
