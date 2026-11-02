<?php
declare(strict_types=1);

namespace DiceGoblins\Tests\Integration;

use DateTimeImmutable;
use DateTimeZone;
use DiceGoblins\Application\Commands\ReconstructionException;
use DiceGoblins\Application\Commands\RunStartException;
use DiceGoblins\Content\ContentRegistry;
use DiceGoblins\Controllers\AuthController;
use DiceGoblins\Controllers\ControllerServiceFactory;
use DiceGoblins\Controllers\DialogueController;
use DiceGoblins\Controllers\OnboardingController;
use DiceGoblins\Repositories\UserCodexRepository;
use DiceGoblins\Repositories\UserRepository;
use DiceGoblins\Tests\Support\IntegrationTestCase;

final class OnboardingCoreLoopTransitionIntegrationTest extends IntegrationTestCase
{
  public function testWhimKnowledgeProvisioningAndNormalFarmStartRemainSeparate(): void
  {
    $content = $this->content();
    $userId = $this->registeredUser();
    $_SERVER['HTTP_X_CSRF_TOKEN'] = (string)$_SESSION['csrf_token'];
    $onboarding = new OnboardingController($content);
    $dialogue = new DialogueController($content);
    $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));

    $this->assertSame('required', $this->status($onboarding)['status']);
    $before = $this->services($content)['gameBootstrapQuery']->execute($userId, $now);
    $this->assertNull($before['active_squad']);
    $this->assertSame([], $before['progression']['available_region_ids']);
    try {
      $this->services($content)['startRunCommand']->execute($userId, ['region_id' => 'region.the_farm'], 'before-squad');
      $this->fail('Farm start must require an active squad.');
    } catch (RunStartException $e) {
      $this->assertSame('run_region_locked', $e->errorCode);
    }

    foreach ($content->onboardingFlow()['steps'] as $index => $step) {
      $encounter = $content->encounter($step['encounter_id']);
      $conversation = $content->dialogue($encounter['dialogue_id']);
      $this->assertSame($conversation['id'], $this->status($onboarding)['dialogue_id']);
      $_SERVER['HTTP_IDEMPOTENCY_KEY'] = 'transition-dialogue-' . $index;
      $this->setJsonBody(['choice_id' => $conversation['choices'][0]['id']]);
      $result = $this->invoke(fn() => $dialogue->complete($conversation['id']));
      $this->assertSame(200, $result['status']);
      $this->assertSame($step['knowledge_id'], $result['body']['data']['knowledge']['codex_id']);
      $this->assertSame('ready_to_provision', $this->status($onboarding)['status']);
    }
    $this->assertSame(0, (int)$this->scalar("SELECT COUNT(*) FROM user_codex_ownership WHERE user_id = ? AND codex_id = 'codex.mystic_cave.wrong_machine'", [$userId]));

    $this->assertNull($this->services($content)['gameBootstrapQuery']->execute($userId, $now)['active_squad']);
    $this->assertSame(0, (int)$this->scalar('SELECT COUNT(*) FROM user_unlocks WHERE user_id = ?', [$userId]));
    $this->assertReconstructionUnavailable($userId, $content);

    $_SERVER['HTTP_IDEMPOTENCY_KEY'] = 'transition-provision';
    $this->setJsonBody([]);
    $provision = $this->invoke(fn() => $onboarding->provision());
    $this->assertSame(200, $provision['status']);
    $this->assertSame('complete', $provision['body']['data']['onboarding_status']);
    $this->assertSame('complete', $this->status($onboarding)['status']);

    $bootstrap = $this->services($content)['gameBootstrapQuery']->execute($userId, $now);
    $this->assertSame($provision['body']['data']['active_squad_id'], $bootstrap['active_squad']['id']);
    $this->assertCount(4, $bootstrap['active_squad']['units']);
    $this->assertContains('region.the_farm', $bootstrap['progression']['available_region_ids']);
    $this->assertContains('unlock.region.the_farm', $bootstrap['progression']['unlock_ids']);
    $this->assertNotContains('unlock.capability.wrong_machine_access', $bootstrap['progression']['unlock_ids']);
    $this->assertSame(['codex.mystic_cave.whim_arrival'], (new UserCodexRepository($this->pdo))->listIdsForUser($userId));
    $this->assertSame($bootstrap, $this->services($content)['gameBootstrapQuery']->execute($userId, $now));
    $this->assertSame('complete', $this->status(new OnboardingController($content))['status']);
    $this->assertReconstructionUnavailable($userId, $content);

    $run = $this->services($content)['startRunCommand']->execute(
      $userId, ['region_id' => 'region.the_farm'], 'transition-farm-start',
    );
    $this->assertSame('active', $run['run']['status']);
    $this->assertSame('region.the_farm', $run['run']['region_id']);
  }

  public function testReturningUnitOwnerNeedsNoWhimKnowledgeBackfill(): void
  {
    $content = $this->content();
    $userId = $this->user();
    foreach ($content->onboardingFlow()['steps'] as $step) {
      (new UserCodexRepository($this->pdo))->insertIfAbsent($userId, $step['knowledge_id']);
    }
    $_SESSION['user_id'] = $userId;
    $_SESSION['csrf_token'] = 'returning-csrf';
    $_SERVER['HTTP_X_CSRF_TOKEN'] = 'returning-csrf';
    $_SERVER['HTTP_IDEMPOTENCY_KEY'] = 'returning-provision';
    $this->setJsonBody([]);
    $this->assertSame(200, $this->invoke(fn() => (new OnboardingController($content))->provision())['status']);
    $this->pdo?->prepare('DELETE FROM user_codex_ownership WHERE user_id = ?')->execute([$userId]);

    $this->assertSame('complete', $this->status(new OnboardingController($content))['status']);
    $bootstrap = $this->services($content)['gameBootstrapQuery']->execute(
      $userId, new DateTimeImmutable('now', new DateTimeZone('UTC')),
    );
    $this->assertNotNull($bootstrap['active_squad']);
    $this->assertContains('region.the_farm', $bootstrap['progression']['available_region_ids']);
    $this->assertSame(0, (int)$this->scalar('SELECT COUNT(*) FROM user_codex_ownership WHERE user_id = ?', [$userId]));
  }

  /** @return array<string,mixed> */
  private function status(OnboardingController $controller): array
  {
    $response = $this->invoke(fn() => $controller->status());
    $this->assertSame(200, $response['status']);
    return $response['body']['data'];
  }

  /** @return array<string,mixed> */
  private function services(ContentRegistry $content): array
  {
    return ControllerServiceFactory::buildContentAware(
      $this->pdo, ControllerServiceFactory::buildCore($this->pdo), $content,
    );
  }

  private function assertReconstructionUnavailable(int $userId, ContentRegistry $content): void
  {
    $recipe = $content->reconstructionRecipe('reconstruction_recipe.reconstruct_pig_kin');
    $request = [
      'recipe_id' => $recipe['id'],
      'expected_mode' => 'first_restoration',
      'expected_price' => $recipe['first_restoration']['price'],
      'expected_ingredients' => $recipe['first_restoration']['ingredients'],
    ];
    try {
      $this->services($content)['reconstructKinCommand']->execute($userId, $request, 'transition-reconstruct-' . $this->statusKey($userId));
      $this->fail('Mystic Cave knowledge must not permit reconstruction.');
    } catch (ReconstructionException $e) {
      $this->assertSame('reconstruction_unavailable', $e->errorCode);
    }
    $this->assertSame(0, (int)$this->scalar("SELECT COUNT(*) FROM user_unlocks WHERE user_id = ? AND unlock_id = 'unlock.capability.wrong_machine_access'", [$userId]));
  }

  private function statusKey(int $userId): string
  {
    return (string)$this->scalar('SELECT player_revision FROM user_state WHERE user_id = ?', [$userId]);
  }

  private function user(): int
  {
    $id = (new UserRepository($this->pdo))->createUser('Onboarding Transition Test', null);
    $this->trackUserId($id);
    $this->pdo?->prepare('INSERT INTO user_state (user_id, energy_current) VALUES (?, 50)')->execute([$id]);
    return $id;
  }

  private function registeredUser(): int
  {
    $this->setJsonBody([
      'email' => 'onboarding-' . bin2hex(random_bytes(6)) . '@example.test',
      'password' => 'onboarding-test-password',
      'display_name' => 'New Warchief',
    ]);
    $response = $this->invoke(fn() => (new AuthController())->localRegister());
    $this->assertSame(201, $response['status']);
    $id = (int)($_SESSION['user_id'] ?? 0);
    $this->assertGreaterThan(0, $id);
    $this->assertNotEmpty($_SESSION['csrf_token'] ?? null);
    $this->trackUserId($id);
    return $id;
  }

  private function content(): ContentRegistry
  {
    return ContentRegistry::load(dirname(__DIR__, 2) . '/tests/Fixtures/content');
  }
}
