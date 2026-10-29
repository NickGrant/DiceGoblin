<?php
declare(strict_types=1);

namespace DiceGoblins\Application\Queries;

use DiceGoblins\Content\ContentRegistry;
use DiceGoblins\Repositories\UserCodexRepository;
use DiceGoblins\Repositories\PlayerStateRepository;
use DiceGoblins\Repositories\WarbandUnitRepository;
use RuntimeException;

final class OnboardingQuery
{
  public function __construct(
    private readonly WarbandUnitRepository $units,
    private readonly UserCodexRepository $codex,
    private readonly PlayerStateRepository $playerState,
    private readonly ContentRegistry $content,
  ) {}

  /** @return array{status:string,region:array{id:string,display_name:string},dialogue_id:?string} */
  public function execute(int $userId): array
  {
    if ($this->playerState->getPlayerState($userId) === null) {
      throw new RuntimeException('Required player state is missing.');
    }
    $flow = $this->content->onboardingFlow();
    $region = $this->content->region($flow['region_id']);
    $visibleRegion = ['id' => $region['id'], 'display_name' => $region['display_name']];

    // Unit ownership is durable and monotonic for the current vNext lifecycle.
    // Returning players need no new onboarding marker or backfill.
    if ($this->units->hasAnyForUser($userId)) {
      return ['status' => 'complete', 'region' => $visibleRegion, 'dialogue_id' => null];
    }

    $owned = [];
    foreach ($this->codex->listIdsForUser($userId) as $codexId) {
      $this->content->codexEntry($codexId);
      $owned[$codexId] = true;
    }
    $firstMissing = null;
    foreach ($flow['steps'] as $index => $step) {
      $hasKnowledge = isset($owned[$step['knowledge_id']]);
      if (!$hasKnowledge && $firstMissing === null) $firstMissing = $index;
      if ($hasKnowledge && $firstMissing !== null) {
        throw new RuntimeException('Onboarding knowledge is out of sequence.');
      }
    }
    if ($firstMissing === null) {
      return ['status' => 'ready_to_provision', 'region' => $visibleRegion, 'dialogue_id' => null];
    }
    $encounter = $this->content->encounter($flow['steps'][$firstMissing]['encounter_id']);
    return ['status' => $firstMissing === 0 ? 'required' : 'in_progress',
      'region' => $visibleRegion, 'dialogue_id' => $encounter['dialogue_id']];
  }
}
