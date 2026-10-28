<?php
declare(strict_types=1);

namespace DiceGoblins\Tests\Integration;

use DiceGoblins\Application\Queries\ObjectiveQuery;
use DiceGoblins\Controllers\ObjectiveController;
use DiceGoblins\Repositories\UserObjectiveRepository;
use DiceGoblins\Repositories\UserRepository;
use DiceGoblins\Tests\Support\IntegrationTestCase;
use DiceGoblins\Tests\Support\ObjectiveContentFixture;
use RuntimeException;

final class ObjectiveReadIntegrationTest extends IntegrationTestCase
{
  protected function supportsVnextBaseline(): bool { return true; }

  public function testUnstartedProgressCompletedAndForeignPlayerStatesSurviveReload(): void
  {
    [$owner, $other] = $this->users();
    $query = $this->query();
    $initial = $query->execute($owner)['objectives'][0];
    $this->assertSame('unstarted', $initial['status']);
    $this->assertSame([0, 0, 0], array_column($initial['requirements'], 'current'));
    $this->assertSame([10, 2, 1], array_column($initial['requirements'], 'needed'));
    $this->assertSame('unstarted', $query->execute($other)['objectives'][0]['status']);
    $this->assertSame('0', (string)$this->scalar('SELECT COUNT(*) FROM `user_objectives` WHERE `user_id` = ?', [$owner]));

    $repo = new UserObjectiveRepository($this->pdo);
    $this->assertTrue($repo->insertActiveIfAbsent($owner, ObjectiveContentFixture::ID));
    $this->assertFalse($repo->insertActiveIfAbsent($owner, ObjectiveContentFixture::ID));
    $repo->setCount($owner, ObjectiveContentFixture::ID, 'kobolds', 6);
    $active = $this->query()->execute($owner)['objectives'][0];
    $this->assertSame('active', $active['status']);
    $this->assertSame([6, 0, 0], array_column($active['requirements'], 'current'));
    $this->assertSame('unstarted', $this->query()->execute($other)['objectives'][0]['status']);

    $repo->setCount($owner, ObjectiveContentFixture::ID, 'kobolds', 10);
    $repo->setCount($owner, ObjectiveContentFixture::ID, 'runs', 2);
    $repo->setCount($owner, ObjectiveContentFixture::ID, 'scout', 1);
    $this->assertSame('active', $this->query()->execute($owner)['objectives'][0]['status']);
    $this->assertTrue($repo->markCompleted($owner, ObjectiveContentFixture::ID));
    $this->assertFalse($repo->markCompleted($owner, ObjectiveContentFixture::ID));
    $completed = $this->query()->execute($owner)['objectives'][0];
    $this->assertSame('completed', $completed['status']);
    $this->assertSame([10, 2, 1], array_column($completed['requirements'], 'current'));
    $this->assertSame('0', (string)$this->scalar('SELECT COUNT(*) FROM `resolved_events` WHERE `user_id` = ?', [$owner]));
    $this->assertSame('1', (string)$this->scalar('SELECT `player_revision` FROM `user_state` WHERE `user_id` = ?', [$owner]));
  }

  public function testUnknownIdentityOverTargetAndPrematureCompletionFailClosed(): void
  {
    [$unknown, $requirement, $overTarget, $premature] = $this->users(4);
    $repo = new UserObjectiveRepository($this->pdo);
    $repo->insertActiveIfAbsent($unknown, 'objective.test.missing');
    $this->expectIntegrity($unknown);

    $repo->insertActiveIfAbsent($requirement, ObjectiveContentFixture::ID);
    $repo->setCount($requirement, ObjectiveContentFixture::ID, 'missing', 1);
    $this->expectIntegrity($requirement);

    $repo->insertActiveIfAbsent($overTarget, ObjectiveContentFixture::ID);
    $repo->setCount($overTarget, ObjectiveContentFixture::ID, 'kobolds', 11);
    $this->expectIntegrity($overTarget);

    $repo->insertActiveIfAbsent($premature, ObjectiveContentFixture::ID);
    $repo->markCompleted($premature, ObjectiveContentFixture::ID);
    $this->expectIntegrity($premature);
  }

  public function testAuthenticatedReadProjectsOnlySafeFactsAndUserCascadeDeletesState(): void
  {
    [$owner] = $this->users();
    $controller = new ObjectiveController(ObjectiveContentFixture::registry());
    $this->assertSame(401, $this->invoke(fn() => $controller->catalog())['status']);
    $_SESSION['user_id'] = $owner;
    $response = $this->invoke(fn() => $controller->catalog());
    $this->assertSame(200, $response['status']);
    $entry = $response['body']['data']['objectives'][0];
    $this->assertSame(['id', 'title', 'description', 'category', 'status', 'requirements'], array_keys($entry));
    $this->assertSame(['id', 'label', 'current', 'needed'], array_keys($entry['requirements'][0]));
    $encoded = json_encode($response['body'], JSON_THROW_ON_ERROR);
    foreach (['kind', 'target_id', 'completion_event_id', 'enemy_unit_type.kobold_skirmisher',
      'event.mountains_boss_completed'] as $private) {
      $this->assertStringNotContainsString($private, $encoded);
    }
    $repo = new UserObjectiveRepository($this->pdo);
    $repo->insertActiveIfAbsent($owner, ObjectiveContentFixture::ID);
    $repo->setCount($owner, ObjectiveContentFixture::ID, 'kobolds', 4);
    $this->pdo?->prepare('DELETE FROM `users` WHERE `id` = ?')->execute([$owner]);
    $this->assertSame('0', (string)$this->scalar('SELECT COUNT(*) FROM `user_objectives` WHERE `user_id` = ?', [$owner]));
    $this->assertSame('0', (string)$this->scalar('SELECT COUNT(*) FROM `user_objective_requirements` WHERE `user_id` = ?', [$owner]));
  }

  private function query(): ObjectiveQuery
  { return new ObjectiveQuery(new UserObjectiveRepository($this->pdo), ObjectiveContentFixture::registry()); }

  private function expectIntegrity(int $userId): void
  {
    try { $this->query()->execute($userId); $this->fail('Corrupt objective state should fail.'); }
    catch (RuntimeException) { $this->addToAssertionCount(1); }
  }

  /** @return list<int> */
  private function users(int $count = 2): array
  {
    $users = new UserRepository($this->pdo);
    $ids = [];
    for ($index = 0; $index < $count; $index++) {
      $id = $users->createUser('Objective Test ' . $index, null);
      $this->trackUserId($id);
      $this->pdo?->prepare('INSERT INTO `user_state` (`user_id`, `energy_current`) VALUES (?, 50)')->execute([$id]);
      $ids[] = $id;
    }
    return $ids;
  }
}
