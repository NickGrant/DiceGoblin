<?php
declare(strict_types=1);

namespace DiceGoblins\Tests\Unit;

use DiceGoblins\Services\ObjectiveService;
use PHPUnit\Framework\TestCase;

final class ObjectiveServiceTest extends TestCase
{
  public function testItPrioritizesActiveRunGuidance(): void
  {
    $objectives = $this->service()->listProfileObjectives(
      $this->teams(['u1']),
      $this->units(),
      $this->regions(),
      4,
      [
        'run_id' => 'run-7',
        'region_slug' => 'the_farm',
        'region_name' => 'The Farm',
      ]
    );

    $this->assertSame('continue-active-run', $objectives[0]['id']);
    $this->assertSame('/run/map', $objectives[0]['route']);
    $this->assertSame('The Farm', $objectives[0]['meta']['region_name']);
  }

  public function testItPrioritizesEmptySquadBeforeGeneralRunGuidance(): void
  {
    $objectives = $this->service()->listProfileObjectives(
      $this->teams([]),
      $this->units(),
      $this->regions(),
      4,
      null
    );

    $this->assertSame('ready-active-squad', $objectives[0]['id']);
    $this->assertSame('active', $objectives[0]['status']);
    $this->assertSame(0, $objectives[0]['progress_current']);
    $this->assertSame(4, $objectives[0]['progress_target']);
  }

  public function testItMarksEquippedDiceAndPromotionProgress(): void
  {
    $objectives = $this->service()->listProfileObjectives(
      $this->teams(['u1']),
      [
        [
          'id' => 'u1',
          'name' => 'Fang',
          'tier' => 2,
          'promotion_eligible' => false,
          'equipped_dice' => [['dice_instance_id' => 'd1']],
        ],
      ],
      $this->regions(),
      4,
      null
    );

    $objectiveById = [];
    foreach ($objectives as $objective) {
      $objectiveById[$objective['id']] = $objective;
    }

    $this->assertSame('complete', $objectiveById['equip-first-die']['status']);
    $this->assertSame(1, $objectiveById['equip-first-die']['progress_current']);
    $this->assertSame('complete', $objectiveById['promote-first-unit']['status']);
  }

  public function testItUsesGameplayFactsForBattleAndRunProgress(): void
  {
    $objectives = $this->service()->listProfileObjectives(
      $this->teams(['u1']),
      $this->units(),
      $this->completedRegions(),
      4,
      null,
      [
        'started_runs' => 3,
        'completed_runs' => 2,
        'claimed_victory_battles' => 5,
      ]
    );

    $objectiveById = [];
    foreach ($objectives as $objective) {
      $objectiveById[$objective['id']] = $objective;
    }

    $this->assertSame('complete', $objectiveById['continue-active-run']['status']);
    $this->assertSame(1, $objectiveById['continue-active-run']['progress_current']);
    $this->assertSame('complete', $objectiveById['claim-first-victory']['status']);
    $this->assertSame(1, $objectiveById['claim-first-victory']['progress_current']);
    $this->assertSame('complete', $objectiveById['complete-first-run']['status']);
    $this->assertSame(1, $objectiveById['complete-first-run']['progress_current']);
  }

  private function service(): ObjectiveService
  {
    return new ObjectiveService();
  }

  /**
   * @param array<int,string> $unitIds
   * @return array<int,array{id:string,name:string,is_active:bool,unit_ids:array<int,string>}>
   */
  private function teams(array $unitIds): array
  {
    return [
      [
        'id' => 'team-1',
        'name' => 'Alpha',
        'is_active' => true,
        'unit_ids' => $unitIds,
      ],
    ];
  }

  /**
   * @return array<int,array<string,mixed>>
   */
  private function units(): array
  {
    return [
      [
        'id' => 'u1',
        'name' => 'Fang',
        'tier' => 1,
        'promotion_eligible' => true,
        'equipped_dice' => [],
      ],
    ];
  }

  /**
   * @return array<int,array<string,mixed>>
   */
  private function regions(): array
  {
    return [
      [
        'id' => 'region-1',
        'slug' => 'the_farm',
        'name' => 'The Farm',
        'is_enabled' => true,
        'is_unlocked' => true,
        'is_completed' => false,
      ],
    ];
  }

  /**
   * @return array<int,array<string,mixed>>
   */
  private function completedRegions(): array
  {
    return [
      [
        'id' => 'region-1',
        'slug' => 'the_farm',
        'name' => 'The Farm',
        'is_enabled' => true,
        'is_unlocked' => true,
        'is_completed' => true,
      ],
    ];
  }
}
