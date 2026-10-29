<?php
declare(strict_types=1);

namespace DiceGoblins\Tests\Integration;

use DiceGoblins\Application\Objectives\ObjectiveProgressService;
use DiceGoblins\Application\Queries\ObjectiveQuery;
use DiceGoblins\Application\Rewards\RewardApplicationIntegrityException;
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
use PDO;

final class ObjectiveCompletionIntegrationTest extends IntegrationTestCase
{

  public function testOneFactCompletesMultipleObjectivesAndFinalizesRandomAndDuplicateRewardsExactlyOnce(): void
  {
    [$first, $other] = $this->users();
    $content = $this->content();
    $rolls = new ScriptedRewardRollSource([1000, 1, 9000, 1]);
    $service = $this->service($content, $rolls);
    $query = new ObjectiveQuery(new UserObjectiveRepository($this->pdo), $content);
    $this->pdo?->beginTransaction();
    $this->assertTrue($service->apply($first, 'complete_dialogue', 'dialogue.camp.farm_scout'));
    $this->assertTrue($service->finalizeSatisfied($first));
    $this->pdo?->commit();
    $states = array_column($query->execute($first)['objectives'], 'status', 'id');
    $this->assertSame(['objective.test.mountains_scout' => 'completed',
      'objective.test.no_reward' => 'completed', 'objective.test.second_scout' => 'completed'], $states);
    $this->assertSame('unstarted', $query->execute($other)['objectives'][0]['status']);
    $browserRead = json_encode($query->execute($first), JSON_THROW_ON_ERROR);
    foreach (['completion_event_id', 'event.test.objective_bonus', 'reward_definition',
      'probability_basis_points', 'target_id'] as $private) {
      $this->assertStringNotContainsString($private, $browserRead);
    }
    $this->assertSame('5', (string)$this->scalar('SELECT `teeth` FROM `user_state` WHERE `user_id` = ?', [$first]));
    $this->assertSame('1', (string)$this->scalar("SELECT COUNT(*) FROM `user_unlocks` WHERE `user_id` = ? AND `unlock_id` = 'unlock.region.mountains'", [$first]));
    $this->assertSame('2', (string)$this->scalar("SELECT COUNT(*) FROM `resolved_events` WHERE `user_id` = ? AND `source_type` = 'objective_completion'", [$first]));
    $rows = $this->rows('SELECT `source_id`, `result_json` FROM `resolved_events` WHERE `user_id` = ? AND `source_type` = ? ORDER BY `source_id`',
      [$first, 'objective_completion']);
    $firstReward = json_decode($rows[0]['result_json'], true, 512, JSON_THROW_ON_ERROR);
    $secondReward = json_decode($rows[1]['result_json'], true, 512, JSON_THROW_ON_ERROR);
    $this->assertSame(['granted', 'granted'], array_column($firstReward['entries'], 'outcome'));
    $this->assertSame(['not_rolled', 'already_owned'], array_column($secondReward['entries'], 'outcome'));
    $this->assertSame(4, $rolls->consumed());
    $this->pdo?->beginTransaction();
    $this->assertFalse($service->apply($first, 'complete_dialogue', 'dialogue.camp.farm_scout'));
    $this->assertFalse($service->finalizeSatisfied($first));
    $this->pdo?->commit();
    $this->assertSame(4, $rolls->consumed());
    $this->assertSame('5', (string)$this->scalar('SELECT `teeth` FROM `user_state` WHERE `user_id` = ?', [$first]));
  }

  public function testRewardFailureRollsBackProgressCompletionAndEvent(): void
  {
    [$first] = $this->users();
    $content = $this->content();
    $service = $this->service($content, new ScriptedRewardRollSource([10001]));
    $this->pdo?->beginTransaction();
    try {
      $service->apply($first, 'complete_dialogue', 'dialogue.camp.farm_scout');
      $service->finalizeSatisfied($first);
      $this->fail('Invalid reward roll should fail the owning transaction.');
    } catch (RewardApplicationIntegrityException) { $this->addToAssertionCount(1); }
    $this->pdo?->rollBack();
    $this->assertSame('0', (string)$this->scalar('SELECT COUNT(*) FROM `user_objectives` WHERE `user_id` = ?', [$first]));
    $this->assertSame('0', (string)$this->scalar('SELECT COUNT(*) FROM `resolved_events` WHERE `user_id` = ?', [$first]));
    $this->assertSame('0', (string)$this->scalar('SELECT `teeth` FROM `user_state` WHERE `user_id` = ?', [$first]));
  }

  public function testNextAuthoritativeProgressEvaluationRecoversSaturatedActiveState(): void
  {
    [$first] = $this->users();
    $objective = ObjectiveContentFixture::definition();
    $objective['requirements'] = [['id' => 'dialogue', 'label' => 'Speak with scout',
      'kind' => 'complete_dialogue', 'target_id' => 'dialogue.camp.farm_scout', 'needed' => 1]];
    unset($objective['completion_event_id']);
    $content = ObjectiveContentFixture::registry([], $objective);
    $repo = new UserObjectiveRepository($this->pdo);
    $repo->insertActiveIfAbsent($first, ObjectiveContentFixture::ID);
    $repo->setCount($first, ObjectiveContentFixture::ID, 'dialogue', 1);
    $this->assertSame('active', (new ObjectiveQuery($repo, $content))->execute($first)['objectives'][0]['status']);
    $service = $this->service($content, new ScriptedRewardRollSource([]));
    $this->pdo?->beginTransaction();
    $this->assertFalse($service->apply($first, 'complete_dialogue', 'dialogue.camp.farm_scout'));
    $this->assertTrue($service->finalizeSatisfied($first));
    $this->pdo?->commit();
    $this->assertSame('completed', (new ObjectiveQuery($repo, $content))->execute($first)['objectives'][0]['status']);
    $this->assertSame('0', (string)$this->scalar('SELECT COUNT(*) FROM `resolved_events` WHERE `user_id` = ?', [$first]));
  }

  private function content(): ContentRegistry
  {
    $first = ObjectiveContentFixture::definition();
    $first['requirements'] = [['id' => 'dialogue', 'label' => 'Speak with scout',
      'kind' => 'complete_dialogue', 'target_id' => 'dialogue.camp.farm_scout', 'needed' => 1]];
    $first['completion_event_id'] = 'event.test.objective_bonus';
    $second = $first;
    $second['id'] = 'objective.test.second_scout';
    $noReward = $first;
    $noReward['id'] = 'objective.test.no_reward';
    unset($noReward['completion_event_id']);
    return ObjectiveContentFixture::registry([$second, $noReward], $first, [
      ['id' => 'event.test.objective_bonus', 'type' => 'event',
        'reward_definition_id' => 'reward_definition.test.objective_bonus'],
      ['id' => 'reward_definition.test.objective_bonus', 'type' => 'reward_definition', 'entries' => [
        ['key' => 'teeth', 'probability_basis_points' => 5000, 'reward_type' => 'currency',
          'config' => ['currency_id' => 'teeth', 'amount' => 5]],
        ['key' => 'mountains', 'probability_basis_points' => 10000, 'reward_type' => 'unlock',
          'config' => ['unlock_id' => 'unlock.region.mountains']],
      ]],
    ]);
  }

  private function service(ContentRegistry $content, ScriptedRewardRollSource $rolls): ObjectiveProgressService
  {
    $players = new PlayerStateRepository($this->pdo);
    $units = new WarbandUnitRepository($this->pdo);
    $unlocks = new UserUnlockRepository($this->pdo);
    $rewards = new RewardApplicationService($this->pdo, $content,
      new RewardFinalizer($rolls), new ResolvedEventRepository($this->pdo), $players, $units, $unlocks,
      new UserCodexRepository($this->pdo));
    return new ObjectiveProgressService($this->pdo, new UserObjectiveRepository($this->pdo), $content,
      $players, $units, $unlocks, $rewards);
  }

  /** @param list<int|string> $params @return list<array<string,mixed>> */
  private function rows(string $sql, array $params): array
  {
    $stmt = $this->pdo?->prepare($sql);
    $stmt?->execute($params);
    return $stmt?->fetchAll(PDO::FETCH_ASSOC) ?: [];
  }

  /** @return list<int> */
  private function users(): array
  {
    $repo = new UserRepository($this->pdo);
    $ids = [];
    for ($i = 0; $i < 2; $i++) {
      $id = $repo->createUser('Objective Completion ' . $i, null);
      $this->trackUserId($id);
      $this->pdo?->prepare('INSERT INTO `user_state` (`user_id`, `energy_current`) VALUES (?, 50)')->execute([$id]);
      $ids[] = $id;
    }
    return $ids;
  }
}
