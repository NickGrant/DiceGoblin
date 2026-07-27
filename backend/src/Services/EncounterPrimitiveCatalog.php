<?php
declare(strict_types=1);

namespace DiceGoblins\Services;

final class EncounterPrimitiveCatalog
{
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
  public function resolveNodeEffect(string $nodeType, callable $nextInt): array
  {
    if ($nodeType === 'hazard') {
      return [
        'family' => 'hazard',
        'slug' => 'hazard_cautious_footing',
        'primitive' => 'route_pressure',
        'message' => 'hazard_avoided',
        'currency_soft' => 0,
        'result' => [
          'effect' => 'cautious_footing',
          'pressure' => 'route',
        ],
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
}
