<?php
declare(strict_types=1);

namespace DiceGoblins\Application;

use DiceGoblins\Application\Queries\OnboardingQuery;
use DiceGoblins\Content\ContentRegistry;

final class OnboardingDialogueGuard
{
  public function __construct(
    private readonly OnboardingQuery $onboarding,
    private readonly ContentRegistry $content,
  ) {}

  public function allows(int $userId, string $dialogueId): bool
  {
    foreach ($this->content->onboardingFlow()['steps'] as $step) {
      $encounter = $this->content->encounter($step['encounter_id']);
      if ($encounter['dialogue_id'] !== $dialogueId) continue;
      return $this->onboarding->execute($userId)['dialogue_id'] === $dialogueId;
    }
    return true;
  }
}
