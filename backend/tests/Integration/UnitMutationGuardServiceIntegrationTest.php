<?php
declare(strict_types=1);

namespace DiceGoblins\Tests\Integration;

use DiceGoblins\Repositories\RunRepository;
use DiceGoblins\Services\UnitMutationGuardService;
use DiceGoblins\Tests\Support\BattleFlowIntegrationCase;

final class UnitMutationGuardServiceIntegrationTest extends BattleFlowIntegrationCase
{
  protected function integrationSkipMessage(): string
  {
    return 'Set TEST_DB_DSN to run unit mutation guard integration tests.';
  }

  public function testGetLockedUnitIdsReturnsOnlyUnitsInActiveRunSnapshot(): void
  {
    $userId = $this->insertUser('qa_guard', 'QA Guard');
    $regionId = $this->insertRegion();
    $runId = $this->insertRun($userId, $regionId);

    [$unitTypeId, ] = $this->pickUnitTypeForProgressTest();
    $lockedUnitId = $this->insertUnit($userId, $unitTypeId, 1, 0);
    $freeUnitId = $this->insertUnit($userId, $unitTypeId, 1, 0);
    $this->insertRunUnitState($runId, $lockedUnitId, 10, false);

    $service = new UnitMutationGuardService($this->pdo, new RunRepository($this->pdo));
    $lockedIds = $service->getLockedUnitIdsForUser($userId, [$lockedUnitId, $freeUnitId]);

    $this->assertSame([$lockedUnitId], $lockedIds);
    $this->assertFalse($service->isUnitMutableForUser($userId, $lockedUnitId));
    $this->assertTrue($service->isUnitMutableForUser($userId, $freeUnitId));
  }
}
