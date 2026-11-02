<?php
declare(strict_types=1);

namespace DiceGoblins\Application;

use DiceGoblins\Application\Queries\OnboardingQuery;
use DiceGoblins\Content\ContentRegistry;
use DiceGoblins\Content\ContentValidationException;
use DiceGoblins\Repositories\UserCodexRepository;
use DiceGoblins\Repositories\UserUnlockRepository;

final class DialogueAvailabilityPolicy
{
  public const POST_FARM_WHIM_DIALOGUE_ID = 'dialogue.camp.whim_machine_search';

  public function __construct(
    private readonly OnboardingQuery $onboarding,
    private readonly ContentRegistry $content,
    private readonly UserCodexRepository $codex,
    private readonly UserUnlockRepository $unlocks,
  ) {}

  public function pendingCampWhimDialogueId(int $userId): ?string
  {
    return $this->codex->owns($userId, 'codex.farm.tooth_collector_rescued')
      && $this->unlocks->owns($userId, 'unlock.capability.shop_access')
      && !$this->codex->owns($userId, 'codex.camp.wrong_machine_search')
      && !$this->unlocks->owns($userId, 'unlock.region.mountains')
      ? self::POST_FARM_WHIM_DIALOGUE_ID : null;
  }

  public function allows(int $userId, string $dialogueId): bool
  {
    if ($dialogueId === self::POST_FARM_WHIM_DIALOGUE_ID) {
      return $this->pendingCampWhimDialogueId($userId) !== null;
    }
    foreach ($this->content->onboardingFlow()['steps'] as $step) {
      $encounter = $this->content->encounter($step['encounter_id']);
      if ($encounter['dialogue_id'] !== $dialogueId) continue;
      return $this->onboarding->execute($userId)['dialogue_id'] === $dialogueId;
    }
    try { $dialogue = $this->content->dialogue($dialogueId); }
    catch (ContentValidationException) { return true; }
    if (($dialogue['run_only'] ?? false) === true) return false;
    foreach ($dialogue['choices'] as $choice) {
      if (!isset($choice['event_id'])) continue;
      $event = $this->content->event($choice['event_id']);
      $reward = $this->content->rewardDefinition($event['reward_definition_id']);
      foreach ($reward['entries'] as $entry) {
        if ($entry['reward_type'] === 'unlock') return false;
      }
    }
    return true;
  }
}
