<?php
declare(strict_types=1);

namespace DiceGoblins\Services;

final class ObjectiveService
{
  /**
   * @param array<int,array{id:string,name:string,is_active:bool,unit_ids:array<int,string>}> $teams
   * @param array<int,array<string,mixed>> $units
   * @param array<int,array<string,mixed>> $regions
   * @param array<string,mixed>|null $activeRun
   * @return array<int,array{
   *   id:string,
   *   title:string,
   *   description:string,
   *   status:string,
   *   priority:int,
   *   progress_current:int,
   *   progress_target:int,
   *   route:string,
   *   meta:array<string,mixed>
   * }>
   */
  public function listProfileObjectives(
    array $teams,
    array $units,
    array $regions,
    int $squadUnitCap,
    ?array $activeRun
  ): array {
    $objectives = [
      $this->activeRunObjective($activeRun),
      $this->squadObjective($teams, $squadUnitCap),
      $this->diceObjective($units),
      $this->promotionObjective($units),
      $this->regionObjective($regions),
    ];

    usort(
      $objectives,
      static fn(array $left, array $right): int => [$left['status'] === 'complete' ? 1 : 0, $left['priority']]
        <=> [$right['status'] === 'complete' ? 1 : 0, $right['priority']]
    );

    return $objectives;
  }

  /**
   * @param array<string,mixed>|null $activeRun
   * @return array<string,mixed>
   */
  private function activeRunObjective(?array $activeRun): array
  {
    return [
      'id' => 'continue-active-run',
      'title' => $activeRun === null ? 'Start a run' : 'Continue the active run',
      'description' => $activeRun === null
        ? 'Choose an unlocked region and send the active squad into the field.'
        : 'Finish the current route before changing squads or starting another run.',
      'status' => 'active',
      'priority' => $activeRun === null ? 40 : 10,
      'progress_current' => $activeRun === null ? 0 : 1,
      'progress_target' => 1,
      'route' => $activeRun === null ? '/regions' : '/run/map',
      'meta' => [
        'region_slug' => isset($activeRun['region_slug']) ? (string)$activeRun['region_slug'] : null,
        'region_name' => isset($activeRun['region_name']) ? (string)$activeRun['region_name'] : null,
      ],
    ];
  }

  /**
   * @param array<int,array{id:string,name:string,is_active:bool,unit_ids:array<int,string>}> $teams
   * @return array<string,mixed>
   */
  private function squadObjective(array $teams, int $squadUnitCap): array
  {
    $activeSquad = null;
    foreach ($teams as $team) {
      if (($team['is_active'] ?? false) === true) {
        $activeSquad = $team;
        break;
      }
    }

    $assigned = count($activeSquad['unit_ids'] ?? []);
    $target = max(1, $squadUnitCap);

    return [
      'id' => 'ready-active-squad',
      'title' => 'Ready the active squad',
      'description' => 'Fill the active squad so the next run starts with enough bodies in formation.',
      'status' => $assigned > 0 ? 'complete' : 'active',
      'priority' => $assigned > 0 ? 90 : 20,
      'progress_current' => min($assigned, $target),
      'progress_target' => $target,
      'route' => '/warband',
      'meta' => [
        'active_squad_id' => isset($activeSquad['id']) ? (string)$activeSquad['id'] : null,
        'active_squad_name' => isset($activeSquad['name']) ? (string)$activeSquad['name'] : null,
      ],
    ];
  }

  /**
   * @param array<int,array<string,mixed>> $units
   * @return array<string,mixed>
   */
  private function diceObjective(array $units): array
  {
    $equippedDiceCount = 0;
    foreach ($units as $unit) {
      $equippedDice = is_array($unit['equipped_dice'] ?? null) ? $unit['equipped_dice'] : [];
      $abilityDice = is_array($unit['ability_dice'] ?? null) ? $unit['ability_dice'] : [];
      $equippedDiceCount += count($equippedDice) + count($abilityDice);
    }

    return [
      'id' => 'equip-first-die',
      'title' => 'Equip a die',
      'description' => 'Attach at least one die to a raider ability before pushing deeper.',
      'status' => $equippedDiceCount > 0 ? 'complete' : 'active',
      'priority' => $equippedDiceCount > 0 ? 100 : 30,
      'progress_current' => min($equippedDiceCount, 1),
      'progress_target' => 1,
      'route' => '/dice',
      'meta' => [],
    ];
  }

  /**
   * @param array<int,array<string,mixed>> $units
   * @return array<string,mixed>
   */
  private function promotionObjective(array $units): array
  {
    $readyUnit = null;
    $promotedCount = 0;
    foreach ($units as $unit) {
      if ((int)($unit['tier'] ?? 1) > 1) {
        $promotedCount++;
      }

      if ($readyUnit === null && (bool)($unit['promotion_eligible'] ?? false)) {
        $readyUnit = $unit;
      }
    }

    return [
      'id' => 'promote-first-unit',
      'title' => $readyUnit === null
        ? 'Promote a raider'
        : sprintf('Promote %s', (string)($readyUnit['name'] ?? 'a raider')),
      'description' => 'Use the Academy to turn earned levels into a stronger class path.',
      'status' => $promotedCount > 0 ? 'complete' : 'active',
      'priority' => $readyUnit === null ? 70 : 25,
      'progress_current' => min($promotedCount, 1),
      'progress_target' => 1,
      'route' => '/academy',
      'meta' => [
        'unit_instance_id' => isset($readyUnit['id']) ? (string)$readyUnit['id'] : null,
      ],
    ];
  }

  /**
   * @param array<int,array<string,mixed>> $regions
   * @return array<string,mixed>
   */
  private function regionObjective(array $regions): array
  {
    $enabledRegions = array_values(array_filter(
      $regions,
      static fn(array $region): bool => (bool)($region['is_enabled'] ?? false)
    ));
    $completedCount = count(array_filter(
      $enabledRegions,
      static fn(array $region): bool => (bool)($region['is_completed'] ?? false)
    ));
    $nextRegion = null;

    foreach ($enabledRegions as $region) {
      if ((bool)($region['is_unlocked'] ?? false) && !(bool)($region['is_completed'] ?? false)) {
        $nextRegion = $region;
        break;
      }
    }

    return [
      'id' => 'clear-next-region',
      'title' => $nextRegion === null
        ? 'Clear all unlocked regions'
        : sprintf('Clear %s', (string)$nextRegion['name']),
      'description' => 'Complete the next unlocked region to advance the map and unlock more systems.',
      'status' => $nextRegion === null && count($enabledRegions) > 0 ? 'complete' : 'active',
      'priority' => $nextRegion === null ? 110 : 50,
      'progress_current' => $completedCount,
      'progress_target' => max(1, count($enabledRegions)),
      'route' => '/regions',
      'meta' => [
        'region_slug' => isset($nextRegion['slug']) ? (string)$nextRegion['slug'] : null,
        'region_name' => isset($nextRegion['name']) ? (string)$nextRegion['name'] : null,
      ],
    ];
  }
}
