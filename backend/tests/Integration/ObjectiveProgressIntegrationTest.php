<?php
declare(strict_types=1);

namespace DiceGoblins\Tests\Integration;

use DiceGoblins\Application\Objectives\ObjectiveProgressService;
use DiceGoblins\Application\Queries\ObjectiveQuery;
use DiceGoblins\Application\Rewards\RewardApplicationService;
use DiceGoblins\Content\ContentRegistry;
use DiceGoblins\Domain\Rewards\RewardFinalizer;
use DiceGoblins\Repositories\PlayerStateRepository;
use DiceGoblins\Repositories\ResolvedEventRepository;
use DiceGoblins\Repositories\UserCodexRepository;
use DiceGoblins\Repositories\UserObjectiveRepository;
use DiceGoblins\Repositories\UserRepository;
use DiceGoblins\Repositories\UserUnlockRepository;
use DiceGoblins\Repositories\WarbandUnitRepository;
use DiceGoblins\Tests\Support\IntegrationTestCase;
use DiceGoblins\Tests\Support\ObjectiveContentFixture;
use DiceGoblins\Tests\Support\ScriptedRewardRollSource;
use RuntimeException;

final class ObjectiveProgressIntegrationTest extends IntegrationTestCase
{

  public function testAllFactsInitializeAdvanceSaturateEveryMatchAndRespectOwnershipAndCompletion(): void
  {
    [$first, $second] = $this->users();
    $other = ObjectiveContentFixture::definition();
    unset($other['completion_event_id']);
    $other['id'] = 'objective.test.second_scout';
    $other['requirements'] = [
      ['id' => 'kobolds', 'label' => 'Defeat kobolds', 'kind' => 'defeat_enemy_unit_type',
        'target_id' => 'enemy_unit_type.kobold_skirmisher', 'needed' => 3],
      ['id' => 'more_kobolds', 'label' => 'Defeat more kobolds', 'kind' => 'defeat_enemy_unit_type',
        'target_id' => 'enemy_unit_type.kobold_skirmisher', 'needed' => 4],
      ['id' => 'dialogue', 'label' => 'Speak with the scout', 'kind' => 'complete_dialogue',
        'target_id' => 'dialogue.camp.farm_scout', 'needed' => 2],
    ];
    $primary = ObjectiveContentFixture::definition();
    unset($primary['completion_event_id']);
    $content = ObjectiveContentFixture::registry([$other], $primary);
    $repo = new UserObjectiveRepository($this->pdo);
    $service = $this->service($repo, $content);
    $query = new ObjectiveQuery($repo, $content);
    $this->assertSame('0', (string)$this->scalar('SELECT COUNT(*) FROM `user_objectives` WHERE `user_id` = ?', [$first]));
    $this->pdo?->beginTransaction();
    $this->assertFalse($service->apply($first, 'defeat_enemy_unit_type', 'enemy_unit_type.mudking', 4));
    $this->assertTrue($service->apply($first, 'defeat_enemy_unit_type', 'enemy_unit_type.kobold_skirmisher', 2));
    $this->assertTrue($service->apply($first, 'defeat_enemy_unit_type', 'enemy_unit_type.kobold_skirmisher', 20));
    $this->assertFalse($service->apply($first, 'defeat_enemy_unit_type', 'enemy_unit_type.kobold_skirmisher'));
    $this->assertTrue($service->apply($first, 'complete_region_run', 'region.mountains'));
    $this->assertFalse($service->apply($first, 'complete_region_run', 'region.the_farm'));
    $this->assertTrue($service->apply($first, 'complete_dialogue', 'dialogue.camp.farm_scout'));
    $this->assertTrue($service->apply($first, 'complete_dialogue', 'dialogue.camp.farm_scout'));
    $this->assertTrue($service->finalizeSatisfied($first));
    $this->pdo?->commit();
    $entries = $query->execute($first)['objectives'];
    $this->assertSame([10, 1, 1], array_column($entries[0]['requirements'], 'current'));
    $this->assertSame([3, 4, 2], array_column($entries[1]['requirements'], 'current'));
    $this->assertSame('completed', $entries[1]['status']);
    $this->assertSame('unstarted', $query->execute($second)['objectives'][0]['status']);
    $this->assertSame('1', (string)$this->scalar('SELECT `player_revision` FROM `user_state` WHERE `user_id` = ?', [$first]));

    $this->pdo?->beginTransaction();
    $this->assertFalse($service->apply($first, 'complete_dialogue', 'dialogue.camp.farm_scout'));
    $this->assertFalse($service->finalizeSatisfied($first));
    $this->pdo?->commit();
    $this->assertSame('completed', $query->execute($first)['objectives'][1]['status']);
  }

  public function testRollbackAndCorruptStateFailWithoutCommittingProgress(): void
  {
    [$user] = $this->users();
    $content = ObjectiveContentFixture::registry();
    $repo = new UserObjectiveRepository($this->pdo);
    $service = $this->service($repo, $content);
    try { $service->apply($user, 'complete_dialogue', 'dialogue.camp.farm_scout');
      $this->fail('Progress outside the owning transaction should fail.');
    } catch (RuntimeException) { $this->addToAssertionCount(1); }
    $this->pdo?->beginTransaction();
    $this->assertTrue($service->apply($user, 'complete_dialogue', 'dialogue.camp.farm_scout'));
    $this->pdo?->rollBack();
    $this->assertSame('unstarted', (new ObjectiveQuery($repo, $content))->execute($user)['objectives'][0]['status']);
    $repo->insertActiveIfAbsent($user, ObjectiveContentFixture::ID);
    $repo->setCount($user, ObjectiveContentFixture::ID, 'unknown', 1);
    $this->pdo?->beginTransaction();
    try { $service->apply($user, 'complete_dialogue', 'dialogue.camp.farm_scout');
      $this->fail('Corrupt objective state should abort.');
    } catch (RuntimeException) { $this->addToAssertionCount(1); }
    $this->pdo?->rollBack();
    $this->assertSame('0', (string)$this->scalar("SELECT COUNT(*) FROM `user_objective_requirements` WHERE `user_id` = ? AND `requirement_id` = 'scout'", [$user]));
  }

  /** @return list<int> */
  private function users(): array
  {
    $repo = new UserRepository($this->pdo);
    $ids = [];
    for ($i = 0; $i < 2; $i++) {
      $id = $repo->createUser('Objective Progress ' . $i, null);
      $this->trackUserId($id);
      $this->pdo?->prepare('INSERT INTO `user_state` (`user_id`, `energy_current`) VALUES (?, 50)')->execute([$id]);
      $ids[] = $id;
    }
    return $ids;
  }

  private function service(UserObjectiveRepository $repo, ContentRegistry $content): ObjectiveProgressService
  {
    $players = new PlayerStateRepository($this->pdo);
    $units = new WarbandUnitRepository($this->pdo);
    $unlocks = new UserUnlockRepository($this->pdo);
    $rewards = new RewardApplicationService($this->pdo, $content,
      new RewardFinalizer(new ScriptedRewardRollSource([])), new ResolvedEventRepository($this->pdo),
      $players, $units, $unlocks, new UserCodexRepository($this->pdo));
    return new ObjectiveProgressService($this->pdo, $repo, $content, $players, $units, $unlocks, $rewards);
  }
}
