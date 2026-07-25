<?php
declare(strict_types=1);

namespace DiceGoblins\Services;

final class ProfileDtoMapper
{
  /**
   * @param array<int,array{
   *   id:string,
   *   name:string,
   *   is_active:bool,
   *   unit_ids:array<int,string>,
   *   formation:array<int,array{cell:string,unit_instance_id:?string}>
   * }> $squads
   * @param array<int,mixed> $units
   * @param array<int,mixed> $dice
   * @param array{soft:int,hard:int,raw_chaos?:int} $currency
   * @param array{current:int,max:int,regen_rate_per_hour:float,last_regen_at:string} $energy
   * @param int $squadUnitCap
   * @param array<int,string> $featureUnlocks
   * @param array<int,string> $unitTypeUnlocks
   * @param array<int,mixed> $lineageUnlocks
   * @param array<int,string> $seenDialogues
   * @param array<int,mixed> $regions
   * @param array<int,mixed> $regionUnlocks
   * @param array<int,mixed> $items
   * @param array<int,array{region_item_id:string,quantity:int}> $regionItems
   * @param array<string,mixed>|null $activeRun
   * @param array<int,mixed> $objectives
   * @return array<string,mixed>
   */
  public function mapProfilePayload(
    string $serverTimeIso,
    array $squads,
    array $units,
    array $dice,
    array $currency,
    array $energy,
    int $squadUnitCap,
    array $featureUnlocks,
    array $unitTypeUnlocks,
    array $lineageUnlocks,
    array $seenDialogues,
    array $regions,
    array $regionUnlocks,
    array $items,
    array $regionItems,
    ?array $activeRun,
    array $objectives = []
  ): array {
    return [
      'server_time_iso' => $serverTimeIso,
      'squads' => $this->mapSquads($squads),
      'units' => $units,
      'dice' => $dice,
      'currency' => $currency,
      'energy' => $energy,
      'squad_unit_cap' => max(1, $squadUnitCap),
      'feature_unlocks' => array_values(array_map(static fn(mixed $value): string => (string)$value, $featureUnlocks)),
      'unit_type_unlocks' => array_values(array_map(static fn(mixed $value): string => (string)$value, $unitTypeUnlocks)),
      'lineage_unlocks' => $lineageUnlocks,
      'seen_dialogues' => array_values(array_map(static fn(mixed $value): string => (string)$value, $seenDialogues)),
      'regions' => $regions,
      'region_unlocks' => $regionUnlocks,
      'items' => $items,
      'region_items' => $regionItems,
      'active_run' => $activeRun,
      'objectives' => $objectives,
    ];
  }

  /**
   * @param array<int,array{
   *   id:string,
   *   name:string,
   *   is_active:bool,
   *   unit_ids:array<int,string>,
   *   formation:array<int,array{cell:string,unit_instance_id:?string}>
   * }> $squads
   * @return array<int,array{
   *   id:string,
   *   name:string,
   *   is_active:bool,
   *   unit_ids:array<int,string>,
   *   formation:array<int,array{cell:string,unit_instance_id:?string}>
   * }>
   */
  private function mapSquads(array $squads): array
  {
    return array_map(
      static fn(array $squad): array => [
        'id' => (string)$squad['id'],
        'name' => (string)$squad['name'],
        'is_active' => (bool)$squad['is_active'],
        'unit_ids' => array_values(array_map(static fn($id): string => (string)$id, $squad['unit_ids'] ?? [])),
        'formation' => array_map(
          static fn(array $cell): array => [
            'cell' => (string)($cell['cell'] ?? ''),
            'unit_instance_id' => isset($cell['unit_instance_id']) && $cell['unit_instance_id'] !== null
              ? (string)$cell['unit_instance_id']
              : null,
          ],
          $squad['formation'] ?? []
        ),
      ],
      $squads
    );
  }
}
