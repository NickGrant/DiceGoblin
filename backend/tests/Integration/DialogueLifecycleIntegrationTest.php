<?php
declare(strict_types=1);

namespace DiceGoblins\Tests\Integration;

use DiceGoblins\Application\Commands\CompleteDialogueCommand;
use DiceGoblins\Application\Commands\DialogueCompletionException;
use DiceGoblins\Application\Commands\DialogueCompletionIntegrityException;
use DiceGoblins\Application\Commands\IdempotencyConflictException;
use DiceGoblins\Application\Queries\DialogueQuery;
use DiceGoblins\Application\Rewards\RewardApplicationService;
use DiceGoblins\Content\ContentRegistry;
use DiceGoblins\Controllers\DialogueController;
use DiceGoblins\Domain\Rewards\RewardFinalizer;
use DiceGoblins\Repositories\IdempotencyRequestRepository;
use DiceGoblins\Repositories\PlayerStateRepository;
use DiceGoblins\Repositories\ResolvedEventRepository;
use DiceGoblins\Repositories\UserCodexRepository;
use DiceGoblins\Repositories\UserRepository;
use DiceGoblins\Repositories\UserUnlockRepository;
use DiceGoblins\Repositories\WarbandUnitRepository;
use DiceGoblins\Tests\Support\IntegrationTestCase;
use DiceGoblins\Tests\Support\ScriptedRewardRollSource;

final class DialogueLifecycleIntegrationTest extends IntegrationTestCase
{
  protected function supportsVnextBaseline(): bool { return true; }

  public function testAuthoritativeChoiceProjectionAndForeignOwnershipIsolation(): void
  {
    [$first, $second] = $this->users();
    $content = $this->content();
    $query = new DialogueQuery(new UserCodexRepository($this->pdo), $content);
    $before = $query->execute($first, 'dialogue.camp.farm_scout');
    $this->assertSame(['hear_paths'], array_column($before['choices'], 'id'));
    $this->assertSame(['id', 'label'], array_keys($before['choices'][0]));
    (new UserCodexRepository($this->pdo))->insertIfAbsent($second, 'codex.farm.old_paths');
    $this->assertSame(['hear_paths', 'discuss_paths'],
      array_column($query->execute($second, 'dialogue.camp.farm_scout')['choices'], 'id'));
    $this->assertSame(['hear_paths'], array_column($query->execute($first, 'dialogue.camp.farm_scout')['choices'], 'id'));
    $this->assertStringNotContainsString('required_codex_ids', json_encode($before, JSON_THROW_ON_ERROR));
    $this->assertStringNotContainsString('event.dialogue_farm_scout_paths', json_encode($before, JSON_THROW_ON_ERROR));
    $this->assertStringNotContainsString('discuss_paths', json_encode($before, JSON_THROW_ON_ERROR));

    $controller = new DialogueController($content);
    $this->assertSame(401, $this->invoke(fn() => $controller->read('dialogue.camp.farm_scout'))['status']);
    $_SESSION['user_id'] = $first;
    $response = $this->invoke(fn() => $controller->read('dialogue.camp.farm_scout'));
    $this->assertSame(200, $response['status']);
    $this->assertSame($before, $response['body']['data']);
    $this->assertSame(404, $this->invoke(fn() => $controller->read('dialogue.unknown'))['status']);
  }

  public function testCompletionReplayDuplicateKnowledgeAndNoViewedState(): void
  {
    [$first, $second] = $this->users();
    $content = $this->content();
    $rolls = new ScriptedRewardRollSource([1, 1]);
    $command = $this->command($content, $rolls);
    $firstResult = $command->execute($first, 'dialogue.camp.farm_scout', 'hear_paths', 'dialogue-first-1');
    $this->assertSame('codex.farm.old_paths', $firstResult['knowledge']['codex_id']);
    $this->assertSame('granted', $firstResult['knowledge']['outcome']);
    $this->assertSame(2, $firstResult['player_revision']);
    $this->assertTrue((new UserCodexRepository($this->pdo))->owns($first, 'codex.farm.old_paths'));
    $this->assertSame('1', (string)$this->scalar('SELECT COUNT(*) FROM `resolved_events` WHERE `user_id` = ?', [$first]));
    $this->assertSame('1', (string)$this->scalar('SELECT COUNT(*) FROM `idempotency_requests` WHERE `user_id` = ?', [$first]));

    $replay = $this->command($content, new ScriptedRewardRollSource([]))
      ->execute($first, 'dialogue.camp.farm_scout', 'hear_paths', 'dialogue-first-1');
    $this->assertSame($firstResult, $replay);
    $this->assertSame('1', (string)$this->scalar('SELECT COUNT(*) FROM `resolved_events` WHERE `user_id` = ?', [$first]));
    $this->assertSame('2', (string)$this->scalar('SELECT `player_revision` FROM `user_state` WHERE `user_id` = ?', [$first]));

    $duplicate = $command->execute($first, 'dialogue.camp.farm_scout', 'hear_paths', 'dialogue-repeat-2');
    $this->assertSame('already_owned', $duplicate['knowledge']['outcome']);
    $this->assertSame(2, $duplicate['player_revision']);
    $this->assertSame('2', (string)$this->scalar('SELECT COUNT(*) FROM `resolved_events` WHERE `user_id` = ?', [$first]));
    $this->assertSame('1', (string)$this->scalar('SELECT COUNT(*) FROM `user_codex_ownership` WHERE `user_id` = ?', [$first]));
    $followup = $command->execute($first, 'dialogue.camp.farm_scout', 'discuss_paths', 'dialogue-followup-3');
    $this->assertNull($followup['knowledge']);
    $this->assertSame(2, $followup['player_revision']);
    $this->assertSame(['hear_paths', 'discuss_paths'], array_column(
      (new DialogueQuery(new UserCodexRepository($this->pdo), $content))
        ->execute($first, 'dialogue.camp.farm_scout')['choices'], 'id'));
    $this->assertSame('0', (string)$this->scalar('SELECT COUNT(*) FROM `user_codex_ownership` WHERE `user_id` = ?', [$second]));
    try {
      $command->execute($second, 'dialogue.camp.farm_scout', 'discuss_paths', 'dialogue-foreign-4');
      $this->fail('Foreign knowledge should not unlock the choice.');
    } catch (DialogueCompletionException $e) { $this->assertSame(404, $e->httpStatus); }
    $this->assertSame('0', (string)$this->scalar('SELECT COUNT(*) FROM `idempotency_requests` WHERE `user_id` = ?', [$second]));
    $this->assertSame('0', (string)$this->scalar("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name IN ('dialogue_history','user_dialogue_history','seen_dialogues')", []));
    try {
      $command->execute($first, 'dialogue.camp.farm_scout', 'discuss_paths', 'dialogue-first-1');
      $this->fail('Key reuse should conflict.');
    } catch (IdempotencyConflictException) { $this->addToAssertionCount(1); }
  }

  public function testCorruptOwnershipRollsBackAndEndpointEnforcesCsrf(): void
  {
    [$first] = $this->users();
    $content = $this->content();
    $controller = new DialogueController($content);
    $_SESSION['user_id'] = $first;
    $this->setJsonBody(['choice_id' => 'hear_paths']);
    $this->assertSame(403, $this->invoke(fn() => $controller->complete('dialogue.camp.farm_scout'))['status']);
    $this->pdo?->prepare('INSERT INTO `user_codex_ownership` (`user_id`, `codex_id`) VALUES (?, ?)')
      ->execute([$first, 'codex.missing']);
    try {
      $this->command($content, new ScriptedRewardRollSource([1]))
        ->execute($first, 'dialogue.camp.farm_scout', 'hear_paths', 'dialogue-corrupt-5');
      $this->fail('Corrupt persisted Codex identity should abort.');
    } catch (DialogueCompletionIntegrityException) { $this->addToAssertionCount(1); }
    $this->assertSame('0', (string)$this->scalar('SELECT COUNT(*) FROM `resolved_events` WHERE `user_id` = ?', [$first]));
    $this->assertSame('0', (string)$this->scalar('SELECT COUNT(*) FROM `idempotency_requests` WHERE `user_id` = ?', [$first]));
    $this->assertSame('1', (string)$this->scalar('SELECT `player_revision` FROM `user_state` WHERE `user_id` = ?', [$first]));
  }

  public function testAuthenticatedCompletionEndpointUsesCsrfAndReplaysFinalizedReceipt(): void
  {
    [$first] = $this->users();
    $controller = new DialogueController($this->content());
    $this->setJsonBody(['choice_id' => 'hear_paths']);
    $this->assertSame(401, $this->invoke(fn() => $controller->complete('dialogue.camp.farm_scout'))['status']);
    $_SESSION['user_id'] = $first;
    $_SESSION['csrf_token'] = 'dialogue-csrf';
    $_SERVER['HTTP_X_CSRF_TOKEN'] = 'dialogue-csrf';
    $_SERVER['HTTP_IDEMPOTENCY_KEY'] = 'dialogue-http-123';
    $this->setJsonBody(['choice_id' => 'hear_paths']);
    $firstResponse = $this->invoke(fn() => $controller->complete('dialogue.camp.farm_scout'));
    $this->assertSame(200, $firstResponse['status']);
    $this->assertSame('granted', $firstResponse['body']['data']['knowledge']['outcome']);
    $this->assertArrayNotHasKey('reward_definition_id', $firstResponse['body']['data']);
    $this->assertStringNotContainsString('probability_basis_points', json_encode($firstResponse['body'], JSON_THROW_ON_ERROR));
    $this->setJsonBody(['choice_id' => 'hear_paths']);
    $replay = $this->invoke(fn() => $controller->complete('dialogue.camp.farm_scout'));
    $this->assertSame($firstResponse['body'], $replay['body']);
    $this->assertSame('1', (string)$this->scalar('SELECT COUNT(*) FROM `resolved_events` WHERE `user_id` = ?', [$first]));
    $_SERVER['HTTP_IDEMPOTENCY_KEY'] = 'dialogue-http-456';
    $this->setJsonBody(['choice_id' => 'hidden_choice']);
    $this->assertSame(404, $this->invoke(fn() => $controller->complete('dialogue.camp.farm_scout'))['status']);
  }

  public function testAuthoredDialogueKnowledgeChoicesGrantAndReplayAcrossConversations(): void
  {
    [$userId] = $this->users();
    $content = $this->content();
    $choices = [];
    foreach ($content->definitionsOfType('dialogue') as $dialogue) {
      foreach ($dialogue['choices'] as $choice) {
        if (!isset($choice['event_id']) || $choice['required_codex_ids'] !== []) continue;
        $event = $content->event($choice['event_id']);
        $reward = $content->rewardDefinition($event['reward_definition_id']);
        if (count($reward['entries']) !== 1 || $reward['entries'][0]['reward_type'] !== 'codex') continue;
        $choices[] = [$dialogue['id'], $choice['id'], $reward['entries'][0]['config']['codex_id']];
      }
    }
    $this->assertNotEmpty($choices);
    $command = $this->command($content, new ScriptedRewardRollSource(array_fill(0, count($choices), 1)));
    $codex = new UserCodexRepository($this->pdo);
    foreach ($choices as $index => [$dialogueId, $choiceId, $codexId]) {
      $alreadyOwned = $codex->owns($userId, $codexId);
      $revisionBefore = (int)$this->scalar('SELECT `player_revision` FROM `user_state` WHERE `user_id` = ?', [$userId]);
      $key = 'authored-dialogue-' . $index;
      $result = $command->execute($userId, $dialogueId, $choiceId, $key);
      $this->assertSame($codexId, $result['knowledge']['codex_id']);
      $this->assertSame($alreadyOwned ? 'already_owned' : 'granted', $result['knowledge']['outcome']);
      $this->assertTrue($codex->owns($userId, $codexId));
      $this->assertSame($revisionBefore + ($alreadyOwned ? 0 : 1), $result['player_revision']);
      $this->assertSame($result, $command->execute($userId, $dialogueId, $choiceId, $key));
    }
    $this->assertSame((string)count($choices), (string)$this->scalar(
      'SELECT COUNT(*) FROM `resolved_events` WHERE `user_id` = ?', [$userId]));
  }

  public function testFailedRewardFinalizationRollsBackReceiptAndKnowledge(): void
  {
    [$first] = $this->users();
    try {
      $this->command($this->content(), new ScriptedRewardRollSource([10001]))
        ->execute($first, 'dialogue.camp.farm_scout', 'hear_paths', 'dialogue-bad-roll-1');
      $this->fail('Invalid finalized reward roll should abort the transaction.');
    } catch (DialogueCompletionIntegrityException) { $this->addToAssertionCount(1); }
    $this->assertSame('0', (string)$this->scalar('SELECT COUNT(*) FROM `resolved_events` WHERE `user_id` = ?', [$first]));
    $this->assertSame('0', (string)$this->scalar('SELECT COUNT(*) FROM `idempotency_requests` WHERE `user_id` = ?', [$first]));
    $this->assertSame('0', (string)$this->scalar('SELECT COUNT(*) FROM `user_codex_ownership` WHERE `user_id` = ?', [$first]));
    $this->assertSame('1', (string)$this->scalar('SELECT `player_revision` FROM `user_state` WHERE `user_id` = ?', [$first]));
  }

  /** @return array{int,int} */
  private function users(): array
  {
    $users = new UserRepository($this->pdo);
    $first = $users->createUser('Dialogue First', null);
    $second = $users->createUser('Dialogue Second', null);
    $this->trackUserId($first); $this->trackUserId($second);
    $this->pdo?->prepare('INSERT INTO `user_state` (`user_id`, `energy_current`) VALUES (?, 50)')->execute([$first]);
    $this->pdo?->prepare('INSERT INTO `user_state` (`user_id`, `energy_current`) VALUES (?, 50)')->execute([$second]);
    return [$first, $second];
  }

  private function content(): ContentRegistry
  { return ContentRegistry::load(dirname(__DIR__, 2) . '/content'); }

  private function command(ContentRegistry $content, ScriptedRewardRollSource $rolls): CompleteDialogueCommand
  {
    $players = new PlayerStateRepository($this->pdo);
    $codex = new UserCodexRepository($this->pdo);
    $unlocks = new UserUnlockRepository($this->pdo);
    $receipts = new IdempotencyRequestRepository($this->pdo);
    $rewards = new RewardApplicationService($this->pdo, $content, new RewardFinalizer($rolls),
      new ResolvedEventRepository($this->pdo), $players, new WarbandUnitRepository($this->pdo), $unlocks, $codex);
    return new CompleteDialogueCommand($this->pdo, $players, $codex, $unlocks, $receipts, $rewards, $content);
  }
}
