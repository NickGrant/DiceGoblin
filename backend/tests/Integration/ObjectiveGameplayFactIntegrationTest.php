<?php
declare(strict_types=1);

namespace DiceGoblins\Tests\Integration;

use DiceGoblins\Application\Commands\ProvisionWarbandFixtureCommand;
use DiceGoblins\Application\Commands\RunNodeResolutionIntegrityException;
use DiceGoblins\Application\Commands\DialogueCompletionIntegrityException;
use DiceGoblins\Application\Rewards\RewardApplicationIntegrityException;
use DiceGoblins\Application\Objectives\ObjectiveProgressService;
use DiceGoblins\Application\Queries\ObjectiveQuery;
use DiceGoblins\Combat\Vnext\CombatInput;
use DiceGoblins\Combat\Vnext\CombatResolver;
use DiceGoblins\Combat\Vnext\CombatResult;
use DiceGoblins\Combat\Vnext\PlaybackRecorder;
use DiceGoblins\Content\ContentRegistry;
use DiceGoblins\Controllers\ControllerServiceFactory;
use DiceGoblins\Repositories\UserObjectiveRepository;
use DiceGoblins\Repositories\UserCodexRepository;
use DiceGoblins\Repositories\UserUnlockRepository;
use DiceGoblins\Repositories\WarbandFixtureRepository;
use DiceGoblins\RunGeneration\FixedGraphRunGenerator;
use DiceGoblins\Tests\Support\IntegrationTestCase;
use DiceGoblins\Tests\Support\ObjectiveContentFixture;

final class ObjectiveGameplayFactIntegrationTest extends IntegrationTestCase
{
  protected function supportsVnextBaseline(): bool { return true; }

  public function testFarmCombatExitAndDialogueFactsAdvanceOnceWithinTheirReceipts(): void
  {
    $objective = ObjectiveContentFixture::definition();
    $objective['requirements'][0] = ['id' => 'mudwrestler', 'label' => 'Defeat Mudwrestlers',
      'kind' => 'defeat_enemy_unit_type', 'target_id' => 'enemy_unit_type.mudwrestler', 'needed' => 1];
    $objective['requirements'][1] = ['id' => 'farm', 'label' => 'Complete Farm',
      'kind' => 'complete_region_run', 'target_id' => 'region.the_farm', 'needed' => 1];
    $objective['requirements'][2]['needed'] = 2;
    $objective['completion_event_id'] = 'event.dialogue_farm_scout_paths';
    $second = $objective;
    $second['id'] = 'objective.test.second_scout';
    unset($second['completion_event_id']);
    $second['requirements'] = [
      ['id' => 'mudwrestler', 'label' => 'Defeat Mudwrestlers', 'kind' => 'defeat_enemy_unit_type',
        'target_id' => 'enemy_unit_type.mudwrestler', 'needed' => 1],
    ];
    $content = ObjectiveContentFixture::registry([$second], $objective);
    $user = $this->account($content);
    $services = ControllerServiceFactory::buildContentAware($this->pdo, null, $content,
      $this->fixedResolver('victory'), new FixedGraphRunGenerator());
    $query = new ObjectiveQuery(new UserObjectiveRepository($this->pdo), $content);
    $run = $services['startRunCommand']->execute($user, ['region_id' => 'region.the_farm'], 'objective-run-start');
    $runId = (int)$run['run']['id'];
    $ids = array_map('intval', array_column($services['currentRunQuery']->execute($user)['run']['nodes'], 'id'));
    $command = $services['resolveRunNodeCommand'];
    $combat = $command->execute($user, $runId, $ids[0], 'objective-combat');
    $this->assertSame('victory', $combat['battle']['outcome']);
    $this->assertSame([1, 0, 0], $this->counts($query, $user));
    $this->assertSame(1, $query->execute($user)['objectives'][1]['requirements'][0]['current']);
    $this->assertSame($combat, $command->execute($user, $runId, $ids[0], 'objective-combat'));
    $this->assertSame([1, 0, 0], $this->counts($query, $user));
    $command->execute($user, $runId, $ids[1], 'objective-loot');
    $command->execute($user, $runId, $ids[2], 'objective-rest',
      ['encounter_id' => 'encounter.the_farm_rest', 'action_id' => 'rest']);
    $command->execute($user, $runId, $ids[3], 'objective-boss');
    $this->assertSame([1, 0, 0], $this->counts($query, $user));
    $exit = $command->execute($user, $runId, $ids[4], 'objective-exit');
    $this->assertSame('completed', $exit['run']['status']);
    $this->assertSame([1, 1, 0], $this->counts($query, $user));
    $this->assertSame($exit, $command->execute($user, $runId, $ids[4], 'objective-exit'));
    $this->assertSame([1, 1, 0], $this->counts($query, $user));
    $dialogue = $services['completeDialogueCommand'];
    $first = $dialogue->execute($user, 'dialogue.camp.farm_scout', 'hear_paths', 'objective-dialogue-one');
    $this->assertSame('granted', $first['knowledge']['outcome']);
    $this->assertSame([1, 1, 1], $this->counts($query, $user));
    $this->assertSame($first, $dialogue->execute($user, 'dialogue.camp.farm_scout', 'hear_paths', 'objective-dialogue-one'));
    $this->assertSame([1, 1, 1], $this->counts($query, $user));
    $secondDialogue = $dialogue->execute($user, 'dialogue.camp.farm_scout', 'hear_paths', 'objective-dialogue-two');
    $this->assertSame('already_owned', $secondDialogue['knowledge']['outcome']);
    $this->assertSame($first['player_revision'] + 1, $secondDialogue['player_revision']);
    $this->assertSame([1, 1, 2], $this->counts($query, $user));
    $this->assertSame('completed', $query->execute($user)['objectives'][0]['status']);
    $this->assertSame('1', (string)$this->scalar("SELECT COUNT(*) FROM `resolved_events` WHERE `user_id` = ? AND `source_type` = 'objective_completion'", [$user]));
    $third = $dialogue->execute($user, 'dialogue.camp.farm_scout', 'hear_paths', 'objective-dialogue-three');
    $this->assertSame($secondDialogue['player_revision'], $third['player_revision']);
    $this->assertSame([1, 1, 2], $this->counts($query, $user));
  }

  public function testDefeatedFarmRunDoesNotCountSuccessfulRegionCompletion(): void
  {
    $objective = ObjectiveContentFixture::definition();
    $objective['requirements'] = [['id' => 'farm', 'label' => 'Complete Farm',
      'kind' => 'complete_region_run', 'target_id' => 'region.the_farm', 'needed' => 1]];
    $content = ObjectiveContentFixture::registry([], $objective);
    $user = $this->account($content);
    $services = ControllerServiceFactory::buildContentAware($this->pdo, null, $content,
      $this->fixedResolver('defeat'), new FixedGraphRunGenerator());
    $run = $services['startRunCommand']->execute($user, ['region_id' => 'region.the_farm'], 'objective-failed-start');
    $runId = (int)$run['run']['id'];
    $nodeId = (int)$services['currentRunQuery']->execute($user)['run']['nodes'][0]['id'];
    $result = $services['resolveRunNodeCommand']->execute($user, $runId, $nodeId, 'objective-failed-combat');
    $this->assertSame('failed', $result['run']['status']);
    $this->assertSame('unstarted', (new ObjectiveQuery(new UserObjectiveRepository($this->pdo), $content))
      ->execute($user)['objectives'][0]['status']);
    $other = $this->account($content);
    $abandoned = $services['startRunCommand']->execute($other, ['region_id' => 'region.the_farm'], 'objective-abandon-start');
    $this->assertSame('abandoned', $services['abandonRunCommand']->execute($other, (int)$abandoned['run']['id'])['run']['status']);
    $this->assertSame('unstarted', (new ObjectiveQuery(new UserObjectiveRepository($this->pdo), $content))
      ->execute($other)['objectives'][0]['status']);
  }

  public function testMountainsBattleCountsTwoDefeatedCopiesAndExactReplayCountsNone(): void
  {
    $content = ObjectiveContentFixture::registry();
    $user = $this->account($content);
    (new UserUnlockRepository($this->pdo))->insertIfAbsent($user, 'unlock.region.mountains');
    $services = ControllerServiceFactory::buildContentAware($this->pdo, null, $content,
      $this->fixedResolver('victory'), new FixedGraphRunGenerator());
    $run = $services['startRunCommand']->execute($user, ['region_id' => 'region.mountains'], 'objective-mountains-start');
    $runId = (int)$run['run']['id'];
    $nodeId = (int)$services['currentRunQuery']->execute($user)['run']['nodes'][0]['id'];
    $command = $services['resolveRunNodeCommand'];
    $result = $command->execute($user, $runId, $nodeId, 'objective-mountains-combat');
    $this->assertSame('victory', $result['battle']['outcome']);
    $query = new ObjectiveQuery(new UserObjectiveRepository($this->pdo), $content);
    $this->assertSame([2, 0, 0], $this->counts($query, $user));
    $this->assertSame($result, $command->execute($user, $runId, $nodeId, 'objective-mountains-combat'));
    $this->assertSame([2, 0, 0], $this->counts($query, $user));
  }

  public function testBossRewardIntegrityFailureRollsBackCombatAndObjectiveProgress(): void
  {
    $objective = ObjectiveContentFixture::definition();
    $objective['requirements'] = [['id' => 'mudking', 'label' => 'Defeat Mudking',
      'kind' => 'defeat_enemy_unit_type', 'target_id' => 'enemy_unit_type.mudking', 'needed' => 1]];
    $content = ObjectiveContentFixture::registry([], $objective);
    $user = $this->account($content);
    $services = ControllerServiceFactory::buildContentAware($this->pdo, null, $content,
      $this->fixedResolver('victory'), new FixedGraphRunGenerator());
    $run = $services['startRunCommand']->execute($user, ['region_id' => 'region.the_farm'], 'objective-rollback-start');
    $runId = (int)$run['run']['id'];
    $ids = array_map('intval', array_column($services['currentRunQuery']->execute($user)['run']['nodes'], 'id'));
    $command = $services['resolveRunNodeCommand'];
    $command->execute($user, $runId, $ids[0], 'objective-rollback-combat');
    $command->execute($user, $runId, $ids[1], 'objective-rollback-loot');
    $command->execute($user, $runId, $ids[2], 'objective-rollback-rest',
      ['encounter_id' => 'encounter.the_farm_rest', 'action_id' => 'rest']);
    $this->pdo?->prepare("UPDATE `run_nodes` SET `event_id` = 'event.missing' WHERE `id` = ?")->execute([$ids[3]]);
    try {
      $command->execute($user, $runId, $ids[3], 'objective-rollback-boss');
      $this->fail('Invalid Boss reward reference should fail the whole command.');
    } catch (RunNodeResolutionIntegrityException) { $this->addToAssertionCount(1); }
    $this->assertSame('0', (string)$this->scalar('SELECT COUNT(*) FROM `battles` WHERE `run_node_id` = ?', [$ids[3]]));
    $this->assertSame('0', (string)$this->scalar('SELECT COUNT(*) FROM `user_objectives` WHERE `user_id` = ?', [$user]));
    $this->assertSame('0', (string)$this->scalar("SELECT COUNT(*) FROM `idempotency_requests` WHERE `user_id` = ? AND `idempotency_key` = 'objective-rollback-boss'", [$user]));
  }

  public function testCombatCompletionRewardAndLaterLegitimateBattleStayExactlyOnce(): void
  {
    $objective = ObjectiveContentFixture::definition();
    $objective['requirements'] = [['id' => 'mudwrestler', 'label' => 'Defeat Mudwrestler',
      'kind' => 'defeat_enemy_unit_type', 'target_id' => 'enemy_unit_type.mudwrestler', 'needed' => 1]];
    $objective['completion_event_id'] = 'event.dialogue_farm_scout_paths';
    $content = ObjectiveContentFixture::registry([], $objective);
    $user = $this->account($content);
    $services = ControllerServiceFactory::buildContentAware($this->pdo, null, $content,
      $this->fixedResolver('victory'), new FixedGraphRunGenerator());
    $query = new ObjectiveQuery(new UserObjectiveRepository($this->pdo), $content);
    $start = $services['startRunCommand']->execute($user, ['region_id' => 'region.the_farm'], 'objective-completion-combat-start');
    $runId = (int)$start['run']['id'];
    $nodeId = (int)$services['currentRunQuery']->execute($user)['run']['nodes'][0]['id'];
    $command = $services['resolveRunNodeCommand'];
    $result = $command->execute($user, $runId, $nodeId, 'objective-completion-combat');
    $this->assertSame('completed', $query->execute($user)['objectives'][0]['status']);
    $this->assertTrue((new UserCodexRepository($this->pdo))->owns($user, 'codex.farm.old_paths'));
    $this->assertSame($result, $command->execute($user, $runId, $nodeId, 'objective-completion-combat'));
    $services['abandonRunCommand']->execute($user, $runId);
    $next = $services['startRunCommand']->execute($user, ['region_id' => 'region.the_farm'], 'objective-completion-combat-next-start');
    $nextId = (int)$next['run']['id'];
    $nextNode = (int)$services['currentRunQuery']->execute($user)['run']['nodes'][0]['id'];
    $command->execute($user, $nextId, $nextNode, 'objective-completion-combat-next');
    $this->assertSame('1', (string)$this->scalar("SELECT COUNT(*) FROM `resolved_events` WHERE `user_id` = ? AND `source_type` = 'objective_completion'", [$user]));
    $this->assertSame([1], $this->counts($query, $user));
  }

  public function testSuccessfulExitFinalizesRegionObjectiveAndExactReplayDoesNotReapplyReward(): void
  {
    $objective = ObjectiveContentFixture::definition();
    $objective['requirements'] = [['id' => 'farm', 'label' => 'Complete Farm',
      'kind' => 'complete_region_run', 'target_id' => 'region.the_farm', 'needed' => 1]];
    $objective['completion_event_id'] = 'event.dialogue_farm_scout_paths';
    $content = ObjectiveContentFixture::registry([], $objective);
    $user = $this->account($content);
    $services = ControllerServiceFactory::buildContentAware($this->pdo, null, $content,
      $this->fixedResolver('victory'), new FixedGraphRunGenerator());
    $query = new ObjectiveQuery(new UserObjectiveRepository($this->pdo), $content);
    $start = $services['startRunCommand']->execute($user, ['region_id' => 'region.the_farm'], 'objective-completion-exit-start');
    $runId = (int)$start['run']['id'];
    $ids = array_map('intval', array_column($services['currentRunQuery']->execute($user)['run']['nodes'], 'id'));
    $command = $services['resolveRunNodeCommand'];
    $command->execute($user, $runId, $ids[0], 'objective-completion-exit-combat');
    $command->execute($user, $runId, $ids[1], 'objective-completion-exit-loot');
    $command->execute($user, $runId, $ids[2], 'objective-completion-exit-rest',
      ['encounter_id' => 'encounter.the_farm_rest', 'action_id' => 'rest']);
    $command->execute($user, $runId, $ids[3], 'objective-completion-exit-boss');
    $this->assertSame('unstarted', $query->execute($user)['objectives'][0]['status']);
    $result = $command->execute($user, $runId, $ids[4], 'objective-completion-exit');
    $this->assertSame('completed', $query->execute($user)['objectives'][0]['status']);
    $this->assertTrue((new UserCodexRepository($this->pdo))->owns($user, 'codex.farm.old_paths'));
    $this->assertSame($result, $command->execute($user, $runId, $ids[4], 'objective-completion-exit'));
    $this->assertSame('1', (string)$this->scalar("SELECT COUNT(*) FROM `resolved_events` WHERE `user_id` = ? AND `source_type` = 'objective_completion'", [$user]));
  }

  public function testObjectiveRewardFailureRollsBackOwningCombatAndDialogueMutations(): void
  {
    $combatContent = $this->overflowRewardContent('defeat_enemy_unit_type', 'enemy_unit_type.mudwrestler');
    $combatUser = $this->account($combatContent);
    $this->pdo?->prepare('UPDATE `user_state` SET `teeth` = 1 WHERE `user_id` = ?')->execute([$combatUser]);
    $combatServices = ControllerServiceFactory::buildContentAware($this->pdo, null, $combatContent,
      $this->fixedResolver('victory'), new FixedGraphRunGenerator());
    $start = $combatServices['startRunCommand']->execute($combatUser, ['region_id' => 'region.the_farm'], 'objective-overflow-start');
    $runId = (int)$start['run']['id'];
    $nodeId = (int)$combatServices['currentRunQuery']->execute($combatUser)['run']['nodes'][0]['id'];
    $revision = (int)$this->scalar('SELECT `player_revision` FROM `user_state` WHERE `user_id` = ?', [$combatUser]);
    try {
      $combatServices['resolveRunNodeCommand']->execute($combatUser, $runId, $nodeId, 'objective-overflow-combat');
      $this->fail('Objective reward overflow should roll back the combat command.');
    } catch (RewardApplicationIntegrityException) { $this->addToAssertionCount(1); }
    $this->assertSame('0', (string)$this->scalar('SELECT COUNT(*) FROM `battles` WHERE `run_node_id` = ?', [$nodeId]));
    $this->assertSame('0', (string)$this->scalar('SELECT COUNT(*) FROM `user_objectives` WHERE `user_id` = ?', [$combatUser]));
    $this->assertSame('available', (string)$this->scalar('SELECT `status` FROM `run_nodes` WHERE `id` = ?', [$nodeId]));
    $this->assertSame((string)$revision, (string)$this->scalar('SELECT `player_revision` FROM `user_state` WHERE `user_id` = ?', [$combatUser]));
    $this->assertSame('0', (string)$this->scalar("SELECT COUNT(*) FROM `idempotency_requests` WHERE `user_id` = ? AND `idempotency_key` = 'objective-overflow-combat'", [$combatUser]));

    $dialogueContent = $this->overflowRewardContent('complete_dialogue', 'dialogue.camp.farm_scout');
    $dialogueUser = $this->account($dialogueContent);
    $this->pdo?->prepare('UPDATE `user_state` SET `teeth` = 1 WHERE `user_id` = ?')->execute([$dialogueUser]);
    $dialogue = ControllerServiceFactory::buildContentAware($this->pdo, null, $dialogueContent)['completeDialogueCommand'];
    try {
      $dialogue->execute($dialogueUser, 'dialogue.camp.farm_scout', 'hear_paths', 'objective-overflow-dialogue');
      $this->fail('Objective reward overflow should roll back the dialogue command.');
    } catch (DialogueCompletionIntegrityException) { $this->addToAssertionCount(1); }
    $this->assertSame('0', (string)$this->scalar('SELECT COUNT(*) FROM `user_objectives` WHERE `user_id` = ?', [$dialogueUser]));
    $this->assertSame('0', (string)$this->scalar('SELECT COUNT(*) FROM `user_codex_ownership` WHERE `user_id` = ?', [$dialogueUser]));
    $this->assertSame('0', (string)$this->scalar('SELECT COUNT(*) FROM `resolved_events` WHERE `user_id` = ?', [$dialogueUser]));
    $this->assertSame('0', (string)$this->scalar('SELECT COUNT(*) FROM `idempotency_requests` WHERE `user_id` = ?', [$dialogueUser]));
  }

  public function testRandomizedObjectiveRewardIsStoredOnceAcrossDialogueReceiptReplay(): void
  {
    $objective = ObjectiveContentFixture::definition();
    $objective['requirements'] = [['id' => 'dialogue', 'label' => 'Speak with scout',
      'kind' => 'complete_dialogue', 'target_id' => 'dialogue.camp.farm_scout', 'needed' => 1]];
    $objective['completion_event_id'] = 'event.test.objective_random';
    $content = ObjectiveContentFixture::registry([], $objective, [
      ['id' => 'event.test.objective_random', 'type' => 'event',
        'reward_definition_id' => 'reward_definition.test.objective_random'],
      ['id' => 'reward_definition.test.objective_random', 'type' => 'reward_definition', 'entries' => [
        ['key' => 'teeth', 'probability_basis_points' => 5000, 'reward_type' => 'currency',
          'config' => ['currency_id' => 'teeth', 'amount' => 7]],
      ]],
    ]);
    $user = $this->account($content);
    $dialogue = ControllerServiceFactory::buildContentAware($this->pdo, null, $content)['completeDialogueCommand'];
    $first = $dialogue->execute($user, 'dialogue.camp.farm_scout', 'hear_paths', 'objective-random-dialogue');
    $event = (string)$this->scalar("SELECT `result_json` FROM `resolved_events` WHERE `user_id` = ? AND `source_type` = 'objective_completion'", [$user]);
    $wallet = (string)$this->scalar('SELECT `teeth` FROM `user_state` WHERE `user_id` = ?', [$user]);
    $this->assertSame($first, $dialogue->execute($user, 'dialogue.camp.farm_scout', 'hear_paths', 'objective-random-dialogue'));
    $this->assertSame($event, (string)$this->scalar("SELECT `result_json` FROM `resolved_events` WHERE `user_id` = ? AND `source_type` = 'objective_completion'", [$user]));
    $this->assertSame($wallet, (string)$this->scalar('SELECT `teeth` FROM `user_state` WHERE `user_id` = ?', [$user]));
    $later = $dialogue->execute($user, 'dialogue.camp.farm_scout', 'hear_paths', 'objective-random-dialogue-later');
    $this->assertSame($first['player_revision'], $later['player_revision']);
    $this->assertSame('1', (string)$this->scalar("SELECT COUNT(*) FROM `resolved_events` WHERE `user_id` = ? AND `source_type` = 'objective_completion'", [$user]));
    $this->assertSame('completed', (new ObjectiveQuery(new UserObjectiveRepository($this->pdo), $content))
      ->execute($user)['objectives'][0]['status']);
  }

  public function testCombatObjectiveXpRewardUsesAuthoritativeRunParticipants(): void
  {
    $objective = ObjectiveContentFixture::definition();
    $objective['requirements'] = [['id' => 'mudwrestler', 'label' => 'Defeat Mudwrestler',
      'kind' => 'defeat_enemy_unit_type', 'target_id' => 'enemy_unit_type.mudwrestler', 'needed' => 1]];
    $content = ObjectiveContentFixture::registry([], $objective);
    $user = $this->account($content);
    $services = ControllerServiceFactory::buildContentAware($this->pdo, null, $content,
      $this->fixedResolver('victory'), new FixedGraphRunGenerator());
    $start = $services['startRunCommand']->execute($user, ['region_id' => 'region.the_farm'], 'objective-xp-start');
    $runId = (int)$start['run']['id'];
    $nodeId = (int)$services['currentRunQuery']->execute($user)['run']['nodes'][0]['id'];
    $before = (string)$this->scalar('SELECT SUM(`xp`) FROM `unit_instances` WHERE `user_id` = ?', [$user]);
    $result = $services['resolveRunNodeCommand']->execute($user, $runId, $nodeId, 'objective-xp-combat');
    $after = (string)$this->scalar('SELECT SUM(`xp`) FROM `unit_instances` WHERE `user_id` = ?', [$user]);
    $this->assertGreaterThan((int)$before, (int)$after);
    $this->assertSame('completed', (new ObjectiveQuery(new UserObjectiveRepository($this->pdo), $content))
      ->execute($user)['objectives'][0]['status']);
    $this->assertSame($result, $services['resolveRunNodeCommand']->execute($user, $runId, $nodeId, 'objective-xp-combat'));
    $this->assertSame($after, (string)$this->scalar('SELECT SUM(`xp`) FROM `unit_instances` WHERE `user_id` = ?', [$user]));
  }

  private function overflowRewardContent(string $kind, string $targetId): ContentRegistry
  {
    $objective = ObjectiveContentFixture::definition();
    $objective['requirements'] = [['id' => 'one', 'label' => 'One fact', 'kind' => $kind,
      'target_id' => $targetId, 'needed' => 1]];
    $objective['completion_event_id'] = 'event.test.objective_overflow';
    return ObjectiveContentFixture::registry([], $objective, [
      ['id' => 'event.test.objective_overflow', 'type' => 'event',
        'reward_definition_id' => 'reward_definition.test.objective_overflow'],
      ['id' => 'reward_definition.test.objective_overflow', 'type' => 'reward_definition', 'entries' => [
        ['key' => 'overflow', 'probability_basis_points' => 10000, 'reward_type' => 'currency',
          'config' => ['currency_id' => 'teeth', 'amount' => PHP_INT_MAX]],
      ]],
    ]);
  }

  private function account(ContentRegistry $content): int
  {
    $services = ControllerServiceFactory::buildContentAware($this->pdo, null, $content);
    $id = $services['accountCreationService']->createLocal('objective-facts-' . bin2hex(random_bytes(4)) . '@example.test',
      password_hash('test-password', PASSWORD_DEFAULT), 'Fighter');
    $this->trackUserId($id);
    (new ProvisionWarbandFixtureCommand($this->pdo, new WarbandFixtureRepository($this->pdo), $content))->execute($id);
    return $id;
  }

  /** @return list<int> */
  private function counts(ObjectiveQuery $query, int $userId): array
  { return array_column($query->execute($userId)['objectives'][0]['requirements'], 'current'); }

  private function fixedResolver(string $outcome): CombatResolver
  {
    return new class($outcome) implements CombatResolver {
      public function __construct(private readonly string $outcome) {}
      public function resolve(CombatInput $input): CombatResult
      {
        $terminal = [];
        foreach ($input->combatants as $key => $unit) {
          $hp = ($this->outcome === 'victory' && $unit['side'] === 'enemy')
            || ($this->outcome === 'defeat' && $unit['side'] === 'player') ? 0 : $unit['current_hp'];
          $terminal[] = ['key' => $key, 'side' => $unit['side'], 'current_hp' => $hp, 'max_hp' => $unit['max_hp'],
            'is_defeated' => $hp === 0, 'statuses' => $unit['statuses']];
        }
        $events = new PlaybackRecorder();
        $events->add('battle_started', 0, 0, ['combatant_keys' => array_keys($input->combatants)]);
        $events->add('battle_ended', 1, 1, ['outcome' => $this->outcome]);
        return new CombatResult($this->outcome, 1, 1, $terminal, $events->events());
      }
    };
  }
}
