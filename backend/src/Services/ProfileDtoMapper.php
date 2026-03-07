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
   * @param array{soft:int,hard:int} $currency
   * @param array{current:int,max:int,regen_rate_per_hour:float,last_regen_at:string} $energy
   * @param array<int,mixed> $regionUnlocks
   * @param array<int,array{region_item_id:string,quantity:int}> $regionItems
   * @param array<string,mixed>|null $activeRun
   * @return array<string,mixed>
   */
  public function mapProfilePayload(
    string $serverTimeIso,
    array $squads,
    array $units,
    array $dice,
    array $currency,
    array $energy,
    array $regionUnlocks,
    array $regionItems,
    ?array $activeRun
  ): array {
    return [
      'server_time_iso' => $serverTimeIso,
      'squads' => $this->mapSquads($squads),
      'units' => $units,
      'dice' => $dice,
      'currency' => $currency,
      'energy' => $energy,
      'region_unlocks' => $regionUnlocks,
      'region_items' => $regionItems,
      'active_run' => $activeRun,
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
