<?php
declare(strict_types=1);

namespace DiceGoblins\Tests\Integration;

use DateTimeImmutable;
use DateTimeZone;
use DiceGoblins\Application\Commands\DialogueCompletionException;
use DiceGoblins\Application\Commands\RunStartException;
use DiceGoblins\Application\Queries\DialogueUnavailableException;
use DiceGoblins\Content\ContentRegistry;
use DiceGoblins\Controllers\ControllerServiceFactory;
use DiceGoblins\Controllers\DialogueController;
use DiceGoblins\Repositories\UserCodexRepository;
use DiceGoblins\Repositories\UserUnlockRepository;
use DiceGoblins\Tests\Support\IntegrationTestCase;

final class PostFarmWhimProgressionIntegrationTest extends IntegrationTestCase
{
  public function testRescueKnowledgeOpensWhimAndCompletionGrantsSearchAndMountainsOnce(): void
  {
    $content = ContentRegistry::load(dirname(__DIR__, 2) . '/tests/Fixtures/content');
    $services = ControllerServiceFactory::buildContentAware($this->pdo, null, $content);
    $userId = $services['accountCreationService']->createLocal(
      'whim-search-' . bin2hex(random_bytes(4)) . '@example.test', password_hash('test-password', PASSWORD_DEFAULT),
      'Whim Search');
    $this->trackUserId($userId);
    $services['provisionWarbandFixtureCommand']->execute($userId);
    $services = ControllerServiceFactory::buildContentAware($this->pdo, null, $content);
    $dialogueId = 'dialogue.camp.whim_machine_search';
    $_SESSION['user_id'] = $userId;
    $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
    $bootstrap = $services['gameBootstrapQuery']->execute($userId, $now);
    $this->assertNull($bootstrap['progression']['camp_whim_dialogue_id']);
    $this->assertNotContains('region.mountains', $bootstrap['progression']['available_region_ids']);
    try { $services['dialogueQuery']->execute($userId, $dialogueId); $this->fail('Whim was readable before rescue.'); }
    catch (DialogueUnavailableException) { $this->addToAssertionCount(1); }
    $this->assertSame(404, $this->invoke(fn() => (new DialogueController($content))->read($dialogueId))['status']);

    $unlocks = new UserUnlockRepository($this->pdo);
    $this->assertTrue($unlocks->insertIfAbsent($userId, 'unlock.capability.shop_access'));
    $this->assertNull($services['gameBootstrapQuery']->execute($userId, $now)['progression']['camp_whim_dialogue_id']);
    try { $services['completeDialogueCommand']->execute($userId, $dialogueId, 'follow_lead', 'early-whim');
      $this->fail('Shop access alone permitted Whim completion.'); }
    catch (DialogueCompletionException $e) { $this->assertSame('dialogue_choice_unavailable', $e->errorCode); }

    $codex = new UserCodexRepository($this->pdo);
    $this->assertTrue($codex->insertIfAbsent($userId, 'codex.farm.tooth_collector_rescued'));
    $reloaded = ControllerServiceFactory::buildContentAware($this->pdo, null, $content);
    $pending = $reloaded['gameBootstrapQuery']->execute($userId, $now);
    $this->assertSame($dialogueId, $pending['progression']['camp_whim_dialogue_id']);
    $this->assertNotContains('region.mountains', $pending['progression']['available_region_ids']);
    $this->assertSame(['follow_lead'], array_column($reloaded['dialogueQuery']->execute($userId, $dialogueId)['choices'], 'id'));
    $this->assertSame(200, $this->invoke(fn() => (new DialogueController($content))->read($dialogueId))['status']);
    try { $reloaded['startRunCommand']->execute($userId, ['region_id' => 'region.mountains'], 'before-whim');
      $this->fail('Mountains started before the Whim beat.'); }
    catch (RunStartException $e) { $this->assertSame('run_region_locked', $e->errorCode); }

    $beforeRevision = $pending['player']['player_revision'];
    $result = $reloaded['completeDialogueCommand']->execute($userId, $dialogueId, 'follow_lead', 'whim-search-key');
    $this->assertEquals(['codex_id' => 'codex.camp.wrong_machine_search', 'outcome' => 'granted'], $result['knowledge']);
    $this->assertEquals(['unlock_id' => 'unlock.region.mountains', 'outcome' => 'granted'], $result['unlock']);
    $this->assertSame($beforeRevision + 1, $result['player_revision']);
    $this->assertSame($result, $reloaded['completeDialogueCommand']->execute($userId, $dialogueId,
      'follow_lead', 'whim-search-key'));
    $this->assertSame(1, (int)$this->scalar("SELECT COUNT(*) FROM user_codex_ownership WHERE user_id = ? AND codex_id = 'codex.camp.wrong_machine_search'", [$userId]));
    $this->assertSame(1, (int)$this->scalar("SELECT COUNT(*) FROM user_unlocks WHERE user_id = ? AND unlock_id = 'unlock.region.mountains'", [$userId]));
    $this->assertSame(0, (int)$this->scalar("SELECT COUNT(*) FROM user_unlocks WHERE user_id = ? AND unlock_id = 'unlock.capability.wrong_machine_access'", [$userId]));
    $this->assertSame(1, (int)$this->scalar("SELECT COUNT(*) FROM resolved_events WHERE user_id = ? AND event_id = 'event.camp.whim_machine_search'", [$userId]));

    $after = ControllerServiceFactory::buildContentAware($this->pdo, null, $content);
    $durable = $after['gameBootstrapQuery']->execute($userId, $now);
    $this->assertNull($durable['progression']['camp_whim_dialogue_id']);
    $this->assertContains('region.mountains', $durable['progression']['available_region_ids']);
    $this->assertSame($result['player_revision'], $durable['player']['player_revision']);
    try { $after['dialogueQuery']->execute($userId, $dialogueId); $this->fail('Completed Whim remained pending.'); }
    catch (DialogueUnavailableException) { $this->addToAssertionCount(1); }
    $this->assertSame(404, $this->invoke(fn() => (new DialogueController($content))->read($dialogueId))['status']);
    try { $after['completeDialogueCommand']->execute($userId, $dialogueId, 'follow_lead', 'new-whim-key');
      $this->fail('A new key replayed completed Whim progression.'); }
    catch (DialogueCompletionException $e) { $this->assertSame('dialogue_choice_unavailable', $e->errorCode); }
    $run = $after['startRunCommand']->execute($userId, ['region_id' => 'region.mountains'], 'after-whim');
    $this->assertSame('region.mountains', $run['run']['region_id']);
  }
}
