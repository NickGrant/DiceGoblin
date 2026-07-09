<?php
declare(strict_types=1);

namespace DiceGoblins\Services;

use DiceGoblins\Repositories\RegionRepository;
use DiceGoblins\Repositories\RunNodeRepository;
use DiceGoblins\Repositories\RunRepository;
use DiceGoblins\Support\RunSummaryBuilder;
use PDO;
use Throwable;

final class RunLifecycleService
{
  public function __construct(
    private readonly PDO $pdo,
    private readonly RunRepository $runRepository,
    private readonly RegionRepository $regionRepository,
    private readonly RunNodeRepository $runNodeRepository,
  ) {}

  /**
   * @return array{run_id:string,status:string}
   */
  public function failRun(int $userId, int $runId): array
  {
    return $this->withinTransaction(function () use ($userId, $runId): array {
      $this->runRepository->applyRunEndCleanup($runId, $userId, true);
      $this->runRepository->endRun($userId, $runId, 'failed');

      return [
        'run_id' => (string)$runId,
        'status' => 'failed',
      ];
    });
  }

  /**
   * @return array{run_id:string,status:string,run_summary:array<string,mixed>}
   */
  public function abandonRun(int $userId, int $runId): array
  {
    return $this->withinTransaction(function () use ($userId, $runId): array {
      $runSummary = $this->buildRunSummary($userId, $runId);
      $this->runRepository->applyRunEndCleanup($runId, $userId, true);
      $this->runRepository->endRun($userId, $runId, 'abandoned');

      return [
        'run_id' => (string)$runId,
        'status' => 'abandoned',
        'run_summary' => $runSummary,
      ];
    });
  }

  /**
   * @return array{
   *   run_id:string,
   *   status:string,
   *   exit_node_id:?string,
   *   run_summary:array<string,mixed>
   * }
   */
  public function completeRun(int $userId, int $runId, int $regionId, ?int $exitNodeId = null): array
  {
    return $this->withinTransaction(function () use ($userId, $runId, $regionId, $exitNodeId): array {
      if ($exitNodeId !== null && $exitNodeId > 0) {
        $this->runNodeRepository->markCleared($runId, $exitNodeId);
      }

      $runSummary = $this->buildRunSummary($userId, $runId);
      $this->runRepository->applyRunEndCleanup($runId, $userId, false);
      $this->runRepository->endRun($userId, $runId, 'completed');
      $runSummary['meta'] = $this->unlockSuccessfulCompletionRewards($userId, $regionId);

      return [
        'run_id' => (string)$runId,
        'status' => 'completed',
        'exit_node_id' => $exitNodeId !== null && $exitNodeId > 0 ? (string)$exitNodeId : null,
        'run_summary' => $runSummary,
      ];
    });
  }

  /**
   * @template T
   * @param callable():T $callback
   * @return T
   */
  private function withinTransaction(callable $callback): mixed
  {
    $ownsTransaction = false;

    try {
      if (!$this->pdo->inTransaction()) {
        $this->pdo->beginTransaction();
        $ownsTransaction = true;
      }

      $result = $callback();

      if ($ownsTransaction) {
        $this->pdo->commit();
      }

      return $result;
    } catch (Throwable $throwable) {
      if ($ownsTransaction && $this->pdo->inTransaction()) {
        $this->pdo->rollBack();
      }

      throw $throwable;
    }
  }

  /**
   * @return array<string,mixed>
   */
  private function buildRunSummary(int $userId, int $runId): array
  {
    return (new RunSummaryBuilder($this->pdo))->buildRunSummary($userId, $runId);
  }

  /**
   * @return array{
   *   completed_region_slug:?string,
   *   completed_region_name:?string,
   *   new_feature_unlocks:array<int,string>,
   *   new_region_unlocks:array<int,string>
   * }
   */
  private function unlockSuccessfulCompletionRewards(int $userId, int $completedRegionId): array
  {
    if ($completedRegionId <= 0) {
      return [
        'completed_region_slug' => null,
        'completed_region_name' => null,
        'new_feature_unlocks' => [],
        'new_region_unlocks' => [],
      ];
    }

    $completedRegion = $this->regionRepository->getRegionById($completedRegionId);
    if ($completedRegion === null) {
      return [
        'completed_region_slug' => null,
        'completed_region_name' => null,
        'new_feature_unlocks' => [],
        'new_region_unlocks' => [],
      ];
    }

    $newFeatureUnlocks = [];
    $newRegionUnlocks = [];
    $completedRegionSlug = (string)$completedRegion['slug'];
    $unlockService = new UserUnlockService($this->pdo);

    if (
      $completedRegionSlug === 'the_farm'
      && !$unlockService->isUnlocked($userId, UserUnlockService::NAMESPACE_FEATURE, UserUnlockService::FEATURE_SHOP)
    ) {
      $unlockService->grant($userId, UserUnlockService::NAMESPACE_FEATURE, UserUnlockService::FEATURE_SHOP);
      $newFeatureUnlocks[] = UserUnlockService::FEATURE_SHOP;
    }

    $nextSlug = $this->regionRepository->getNextRegionSlug($completedRegionSlug);
    if ($nextSlug === null) {
      return [
        'completed_region_slug' => $completedRegionSlug,
        'completed_region_name' => (string)($completedRegion['name'] ?? ''),
        'new_feature_unlocks' => $newFeatureUnlocks,
        'new_region_unlocks' => $newRegionUnlocks,
      ];
    }

    $nextRegion = $this->regionRepository->getRegionBySlug($nextSlug);
    if ($nextRegion === null || !$nextRegion['is_enabled']) {
      return [
        'completed_region_slug' => $completedRegionSlug,
        'completed_region_name' => (string)($completedRegion['name'] ?? ''),
        'new_feature_unlocks' => $newFeatureUnlocks,
        'new_region_unlocks' => $newRegionUnlocks,
      ];
    }

    if (!$this->regionRepository->isRegionUnlocked($userId, (int)$nextRegion['id'])) {
      $newRegionUnlocks[] = (string)$nextRegion['slug'];
    }
    $this->regionRepository->unlockRegion($userId, (int)$nextRegion['id']);

    return [
      'completed_region_slug' => $completedRegionSlug,
      'completed_region_name' => (string)($completedRegion['name'] ?? ''),
      'new_feature_unlocks' => $newFeatureUnlocks,
      'new_region_unlocks' => $newRegionUnlocks,
    ];
  }
}
