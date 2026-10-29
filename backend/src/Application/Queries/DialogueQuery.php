<?php
declare(strict_types=1);

namespace DiceGoblins\Application\Queries;

use DiceGoblins\Application\OnboardingDialogueGuard;
use DiceGoblins\Content\ContentRegistry;
use DiceGoblins\Content\ContentValidationException;
use DiceGoblins\Repositories\UserCodexRepository;
use RuntimeException;

final class DialogueQuery
{
  public function __construct(
    private readonly UserCodexRepository $ownership,
    private readonly ContentRegistry $content,
    private readonly OnboardingDialogueGuard $onboarding,
  ) {}

  /** @return array{id:string,title:string,body:string,choices:list<array{id:string,label:string}>} */
  public function execute(int $userId, string $dialogueId): array
  {
    if (!$this->onboarding->allows($userId, $dialogueId)) {
      throw new DialogueUnavailableException('Dialogue is unavailable.');
    }
    try { $dialogue = $this->content->dialogue($dialogueId); }
    catch (ContentValidationException) { throw new DialogueUnavailableException('Dialogue is unavailable.'); }
    $owned = [];
    foreach ($this->ownership->listIdsForUser($userId) as $id) {
      try { $this->content->codexEntry($id); }
      catch (ContentValidationException $e) { throw new RuntimeException('Persisted Codex identity is unknown.', 0, $e); }
      $owned[$id] = true;
    }
    $choices = [];
    foreach ($dialogue['choices'] as $choice) {
      foreach ($choice['required_codex_ids'] as $required) {
        if (!isset($owned[$required])) continue 2;
      }
      $choices[] = ['id' => $choice['id'], 'label' => $choice['label']];
    }
    return ['id' => $dialogueId, 'title' => $dialogue['title'], 'body' => $dialogue['body'], 'choices' => $choices];
  }
}
