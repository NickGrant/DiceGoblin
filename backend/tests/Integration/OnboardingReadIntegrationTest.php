<?php
declare(strict_types=1);

namespace DiceGoblins\Tests\Integration;

use DiceGoblins\Application\Queries\OnboardingQuery;
use DiceGoblins\Content\ContentRegistry;
use DiceGoblins\Controllers\OnboardingController;
use DiceGoblins\Repositories\UserCodexRepository;
use DiceGoblins\Repositories\PlayerStateRepository;
use DiceGoblins\Repositories\UserRepository;
use DiceGoblins\Repositories\WarbandUnitRepository;
use DiceGoblins\Tests\Support\IntegrationTestCase;
use RuntimeException;

final class OnboardingReadIntegrationTest extends IntegrationTestCase
{
  public function testDurableKnowledgeAndUnitOwnershipDetermineRecoverableStage(): void
  {
    $user = $this->user();
    $query = $this->query();
    $this->assertSame(['status' => 'required', 'region' => ['id' => 'region.mystic_cave',
      'display_name' => 'Mystic Cave'], 'dialogue_id' => 'dialogue.mystic_cave.whim_arrival'], $query->execute($user));

    $codex = new UserCodexRepository($this->pdo);
    $codex->insertIfAbsent($user, 'codex.mystic_cave.whim_arrival');
    $this->assertSame('in_progress', $query->execute($user)['status']);
    $this->assertSame('dialogue.mystic_cave.wrong_machine', $query->execute($user)['dialogue_id']);
    $this->assertSame('in_progress', $this->query()->execute($user)['status']);

    $codex->insertIfAbsent($user, 'codex.mystic_cave.wrong_machine');
    $this->assertSame('ready_to_provision', $this->query()->execute($user)['status']);
    $this->assertNull($this->query()->execute($user)['dialogue_id']);

    $this->pdo?->prepare("INSERT INTO `unit_instances` (`user_id`, `unit_type_id`, `kin_id`, `display_name`, `level`, `xp`, `lifecycle_status`)
      VALUES (?, 'unit_type.bruiser', 'kin.goblin', 'Veteran', 1, 0, 'active')")->execute([$user]);
    $this->assertSame('complete', $this->query()->execute($user)['status']);
  }

  public function testReturningUnitOwnerNeedsNoKnowledgeBackfillAndCorruptSequenceFails(): void
  {
    $returning = $this->user(); $corrupt = $this->user();
    $this->pdo?->prepare("INSERT INTO `unit_instances` (`user_id`, `unit_type_id`, `kin_id`, `display_name`, `level`, `xp`, `lifecycle_status`)
      VALUES (?, 'unit_type.bruiser', 'kin.goblin', 'Veteran', 1, 0, 'active')")->execute([$returning]);
    $this->assertSame('complete', $this->query()->execute($returning)['status']);
    (new UserCodexRepository($this->pdo))->insertIfAbsent($corrupt, 'codex.mystic_cave.wrong_machine');
    $this->expectException(RuntimeException::class);
    $this->query()->execute($corrupt);
  }

  public function testAuthenticatedEndpointProjectsOnlyCurrentStage(): void
  {
    $user = $this->user();
    $controller = new OnboardingController($this->content());
    $unauthorized = $this->invoke(fn() => $controller->status());
    $this->assertSame(401, $unauthorized['status']);
    $_SESSION['user_id'] = $user;
    $response = $this->invoke(fn() => $controller->status());
    $this->assertSame(200, $response['status']);
    $this->assertSame(['status', 'region', 'dialogue_id'], array_keys($response['body']['data']));
    $this->assertSame('required', $response['body']['data']['status']);
    $encoded = json_encode($response['body'], JSON_THROW_ON_ERROR);
    foreach (['knowledge_id', 'encounter_id', 'reward_definition', 'machine_capability_id', 'response'] as $private) {
      $this->assertStringNotContainsString($private, $encoded);
    }
  }

  public function testMissingPlayerStateAndUnknownKnowledgeFailClosed(): void
  {
    $missingState = $this->user();
    $this->pdo?->prepare('DELETE FROM `user_state` WHERE `user_id` = ?')->execute([$missingState]);
    try { $this->query()->execute($missingState); $this->fail('Expected missing player state to fail.'); }
    catch (RuntimeException) { $this->addToAssertionCount(1); }

    $unknownKnowledge = $this->user();
    (new UserCodexRepository($this->pdo))->insertIfAbsent($unknownKnowledge, 'codex.unknown');
    $response = $this->invoke(function () use ($unknownKnowledge): void {
      $_SESSION['user_id'] = $unknownKnowledge;
      (new OnboardingController($this->content()))->status();
    });
    $this->assertSame(500, $response['status']);
    $this->assertSame('onboarding_state_integrity_error', $response['body']['error']['code']);
  }

  private function user(): int
  {
    $id = (new UserRepository($this->pdo))->createUser('Onboarding Test', null);
    $this->trackUserId($id);
    $this->pdo?->prepare('INSERT INTO `user_state` (`user_id`, `energy_current`) VALUES (?, 50)')->execute([$id]);
    return $id;
  }

  private function content(): ContentRegistry
  {
    return ContentRegistry::load(dirname(__DIR__, 2) . '/tests/Fixtures/content');
  }

  private function query(): OnboardingQuery
  {
    return new OnboardingQuery(new WarbandUnitRepository($this->pdo), new UserCodexRepository($this->pdo),
      new PlayerStateRepository($this->pdo), $this->content());
  }
}
