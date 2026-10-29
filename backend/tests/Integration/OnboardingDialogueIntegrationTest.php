<?php
declare(strict_types=1);

namespace DiceGoblins\Tests\Integration;

use DiceGoblins\Content\ContentRegistry;
use DiceGoblins\Controllers\DialogueController;
use DiceGoblins\Controllers\OnboardingController;
use DiceGoblins\Repositories\UserCodexRepository;
use DiceGoblins\Repositories\UserRepository;
use DiceGoblins\Tests\Support\IntegrationTestCase;

final class OnboardingDialogueIntegrationTest extends IntegrationTestCase
{
  public function testCurrentWhimConversationAdvancesThroughKnowledgeWithoutProvisioning(): void
  {
    $content = $this->content(); $flow = $content->onboardingFlow();
    $user = $this->user(); $other = $this->user();
    $onboarding = new OnboardingController($content);
    $dialogue = new DialogueController($content);
    $_SESSION['user_id'] = $user;
    $_SESSION['csrf_token'] = 'onboarding-csrf';
    $_SERVER['HTTP_X_CSRF_TOKEN'] = 'onboarding-csrf';

    $first = $this->visibleStep($content, $flow['steps'][0]);
    $second = $this->visibleStep($content, $flow['steps'][1]);
    $this->assertSame('required', $this->invoke(fn() => $onboarding->status())['body']['data']['status']);
    $firstRead = $this->invoke(fn() => $dialogue->read($first['dialogue_id']));
    $this->assertSame(200, $firstRead['status']);
    $this->assertSame(['id', 'title', 'body', 'choices'], array_keys($firstRead['body']['data']));
    $this->assertSame([$first['choice_id']], array_column($firstRead['body']['data']['choices'], 'id'));
    $this->assertSame(404, $this->invoke(fn() => $dialogue->read($second['dialogue_id']))['status']);
    $this->assertSame(404, $this->complete($dialogue, $second, 'future-step-1')['status']);
    $this->assertSame(0, (int)$this->scalar('SELECT COUNT(*) FROM user_codex_ownership WHERE user_id = ?', [$user]));
    $this->assertSame(1, (int)$this->scalar('SELECT player_revision FROM user_state WHERE user_id = ?', [$user]));

    $firstResult = $this->complete($dialogue, $first, 'first-step-1');
    $this->assertSame(200, $firstResult['status']);
    $this->assertSame($flow['steps'][0]['knowledge_id'], $firstResult['body']['data']['knowledge']['codex_id']);
    $this->assertSame('granted', $firstResult['body']['data']['knowledge']['outcome']);
    $this->assertSame(2, $firstResult['body']['data']['player_revision']);
    $this->assertSame($firstResult['body'], $this->complete($dialogue, $first, 'first-step-1')['body']);
    $this->assertSame(404, $this->complete($dialogue, $first, 'first-step-new-key')['status']);
    $next = $this->invoke(fn() => $onboarding->status())['body']['data'];
    $this->assertSame('in_progress', $next['status']);
    $this->assertSame($second['dialogue_id'], $next['dialogue_id']);
    $this->assertSame($next, $this->invoke(fn() => $onboarding->status())['body']['data']);
    $this->assertSame(404, $this->invoke(fn() => $dialogue->read($first['dialogue_id']))['status']);
    $this->assertSame(200, $this->invoke(fn() => $dialogue->read($second['dialogue_id']))['status']);

    $_SESSION['user_id'] = $other;
    $this->assertSame('required', $this->invoke(fn() => $onboarding->status())['body']['data']['status']);
    $this->assertSame(404, $this->complete($dialogue, $second, 'other-future-1')['status']);
    $this->assertSame(0, (int)$this->scalar('SELECT COUNT(*) FROM user_codex_ownership WHERE user_id = ?', [$other]));
    $_SESSION['user_id'] = $user;

    $final = $this->complete($dialogue, $second, 'final-step-1');
    $this->assertSame(200, $final['status']);
    $this->assertSame($flow['steps'][1]['knowledge_id'], $final['body']['data']['knowledge']['codex_id']);
    $this->assertSame('granted', $final['body']['data']['knowledge']['outcome']);
    $this->assertSame(3, $final['body']['data']['player_revision']);
    $this->assertSame($final['body'], $this->complete($dialogue, $second, 'final-step-1')['body']);
    $this->assertSame('ready_to_provision', $this->invoke(fn() => $onboarding->status())['body']['data']['status']);
    $this->assertSame(2, (int)$this->scalar('SELECT COUNT(*) FROM user_codex_ownership WHERE user_id = ?', [$user]));
    $this->assertSame(2, (int)$this->scalar('SELECT COUNT(*) FROM resolved_events WHERE user_id = ?', [$user]));
    $this->assertSame(2, (int)$this->scalar('SELECT COUNT(*) FROM idempotency_requests WHERE user_id = ?', [$user]));
    $this->assertSame(0, (int)$this->scalar('SELECT COUNT(*) FROM unit_instances WHERE user_id = ?', [$user]));
    $this->assertSame(0, (int)$this->scalar('SELECT COUNT(*) FROM user_unlocks WHERE user_id = ?', [$user]));
    $this->assertSame(3, (int)$this->scalar('SELECT player_revision FROM user_state WHERE user_id = ?', [$user]));
  }

  public function testMalformedAndCorruptSequenceFailsWithoutRewardOrReceipt(): void
  {
    $content = $this->content(); $flow = $content->onboardingFlow();
    $user = $this->user(); $_SESSION['user_id'] = $user;
    $_SESSION['csrf_token'] = 'onboarding-csrf'; $_SERVER['HTTP_X_CSRF_TOKEN'] = 'onboarding-csrf';
    $dialogue = new DialogueController($content);
    $first = $this->visibleStep($content, $flow['steps'][0]);
    $this->assertSame(404, $this->invoke(fn() => $dialogue->read('dialogue.missing'))['status']);
    $invalid = $this->complete($dialogue, ['dialogue_id' => $first['dialogue_id'], 'choice_id' => 'missing'], 'bad-choice-1');
    $this->assertSame(404, $invalid['status']);
    (new UserCodexRepository($this->pdo))->insertIfAbsent($user, $flow['steps'][1]['knowledge_id']);
    $this->assertSame(500, $this->invoke(fn() => $dialogue->read($first['dialogue_id']))['status']);
    $this->assertSame(500, $this->complete($dialogue, $first, 'corrupt-step-1')['status']);
    $this->assertSame(0, (int)$this->scalar('SELECT COUNT(*) FROM resolved_events WHERE user_id = ?', [$user]));
    $this->assertSame(0, (int)$this->scalar('SELECT COUNT(*) FROM idempotency_requests WHERE user_id = ?', [$user]));
  }

  /** @param array<string,mixed> $step @return array{dialogue_id:string,choice_id:string} */
  private function visibleStep(ContentRegistry $content, array $step): array
  {
    $encounter = $content->encounter($step['encounter_id']);
    $dialogue = $content->dialogue($encounter['dialogue_id']);
    return ['dialogue_id' => $dialogue['id'], 'choice_id' => $dialogue['choices'][0]['id']];
  }

  /** @param array{dialogue_id:string,choice_id:string} $step @return array{status:int,body:array<string,mixed>} */
  private function complete(DialogueController $controller, array $step, string $key): array
  {
    $_SERVER['HTTP_IDEMPOTENCY_KEY'] = $key;
    $this->setJsonBody(['choice_id' => $step['choice_id']]);
    return $this->invoke(fn() => $controller->complete($step['dialogue_id']));
  }

  private function user(): int
  {
    $id = (new UserRepository($this->pdo))->createUser('Whim Dialogue Test', null);
    $this->trackUserId($id);
    $this->pdo?->prepare('INSERT INTO user_state (user_id, energy_current) VALUES (?, 50)')->execute([$id]);
    return $id;
  }

  private function content(): ContentRegistry
  {
    return ContentRegistry::load(dirname(__DIR__, 2) . '/tests/Fixtures/content');
  }
}
