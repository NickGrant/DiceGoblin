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
      $favorRoll = $nextInt(3);
      $favor = ['bone_whisper', 'rust_blessing', 'bog_luck'][$favorRoll];
      $currencySoft = 4 + $nextInt(5);
      return [
        'family' => 'shrine',
        'slug' => "shrine_{$favor}",
        'primitive' => 'small_reward',
        'message' => 'shrine_favor_granted',
        'currency_soft' => $currencySoft,
        'result' => [
          'favor' => $favor,
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
