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

  /** @var list<array{slug:string,primitive:string,regions:list<string>,weight:int,title:string,result_copy:string,favor:string,currency_min:int,currency_max:int}> */
  private const SHRINE_EFFECTS = [
    ['slug' => 'shrine_bone_whisper', 'primitive' => 'small_reward', 'regions' => ['the_farm', 'mountains', 'swamps'], 'weight' => 4, 'title' => 'Bone Whisper', 'result_copy' => 'The bones clatter into a useful omen.', 'favor' => 'bone_whisper', 'currency_min' => 4, 'currency_max' => 8],
    ['slug' => 'shrine_rust_blessing', 'primitive' => 'small_reward', 'regions' => ['the_farm', 'mountains', 'swamps'], 'weight' => 4, 'title' => 'Rust Blessing', 'result_copy' => 'The shrine leaves a dull glint of favor behind.', 'favor' => 'rust_blessing', 'currency_min' => 4, 'currency_max' => 8],
    ['slug' => 'shrine_bog_luck', 'primitive' => 'small_reward', 'regions' => ['swamps'], 'weight' => 4, 'title' => 'Bog Luck', 'result_copy' => 'The swamp gives back just enough to worry about why.', 'favor' => 'bog_luck', 'currency_min' => 4, 'currency_max' => 8],
    ['slug' => 'shrine_clean_water', 'primitive' => 'cleansing', 'regions' => ['the_farm', 'swamps'], 'weight' => 2, 'title' => 'Clean Water', 'result_copy' => 'A clean sip steadies the warband.', 'favor' => 'clean_water', 'currency_min' => 3, 'currency_max' => 6],
    ['slug' => 'shrine_crooked_bargain', 'primitive' => 'bargain', 'regions' => ['mountains', 'swamps'], 'weight' => 2, 'title' => 'Crooked Bargain', 'result_copy' => 'The offering feels uneven, but useful.', 'favor' => 'crooked_bargain', 'currency_min' => 5, 'currency_max' => 9],
    ['slug' => 'shrine_hidden_footpath', 'primitive' => 'reroute', 'regions' => ['mountains', 'swamps'], 'weight' => 2, 'title' => 'Hidden Footpath', 'result_copy' => 'A safer path shows itself for a moment.', 'favor' => 'hidden_footpath', 'currency_min' => 2, 'currency_max' => 5],
    ['slug' => 'shrine_cracked_lantern', 'primitive' => 'controlled_risk', 'regions' => ['mountains'], 'weight' => 2, 'title' => 'Cracked Lantern', 'result_copy' => 'The light burns wrong, but it still burns.', 'favor' => 'cracked_lantern', 'currency_min' => 6, 'currency_max' => 10],
    ['slug' => 'shrine_seed_cache', 'primitive' => 'small_reward', 'regions' => ['the_farm'], 'weight' => 3, 'title' => 'Seed Cache', 'result_copy' => 'Something useful was buried here before you arrived.', 'favor' => 'seed_cache', 'currency_min' => 4, 'currency_max' => 7],
    ['slug' => 'shrine_mirror_mud', 'primitive' => 'controlled_risk', 'regions' => ['swamps'], 'weight' => 2, 'title' => 'Mirror Mud', 'result_copy' => 'The reflection makes a promise it may not keep.', 'favor' => 'mirror_mud', 'currency_min' => 5, 'currency_max' => 9],
    ['slug' => 'shrine_old_goblin_mark', 'primitive' => 'cleansing', 'regions' => ['the_farm', 'mountains', 'swamps'], 'weight' => 2, 'title' => 'Old Goblin Mark', 'result_copy' => 'An old mark remembers the shape of safe passage.', 'favor' => 'old_goblin_mark', 'currency_min' => 3, 'currency_max' => 6],
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
        'small_reward',
        'cleansing',
        'bargain',
        'reroute',
        'controlled_risk',
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
  public function resolveNodeEffect(string $nodeType, callable $nextInt, ?string $effectSlug = null): array
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
      $definition = $this->shrineEffectBySlug($effectSlug ?? '') ?? $this->pickWeightedShrineEffect($nextInt);
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
   * @return list<array{slug:string,primitive:string,regions:list<string>,weight:int,title:string,result_copy:string,favor:string,currency_min:int,currency_max:int}>
   */
  public function shrineCatalog(): array
  {
    return self::SHRINE_EFFECTS;
  }

  /**
   * @return array{slug:string,primitive:string,regions:list<string>,weight:int,title:string,result_copy:string,favor:string,currency_min:int,currency_max:int}
   */
  private function pickWeightedShrineEffect(callable $nextInt): array
  {
    $total = array_sum(array_map(static fn(array $effect): int => max(0, (int)$effect['weight']), self::SHRINE_EFFECTS));
    $cursor = $nextInt(max(1, $total));
    foreach (self::SHRINE_EFFECTS as $effect) {
      $weight = max(0, (int)$effect['weight']);
      if ($weight <= 0) {
        continue;
      }
      if ($cursor < $weight) {
        return $effect;
      }
      $cursor -= $weight;
    }

    return self::SHRINE_EFFECTS[0];
  }

  /**
   * @return array{slug:string,primitive:string,regions:list<string>,weight:int,title:string,result_copy:string,favor:string,currency_min:int,currency_max:int}|null
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
