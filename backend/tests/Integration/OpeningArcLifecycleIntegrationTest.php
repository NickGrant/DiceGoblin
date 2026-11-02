<?php
declare(strict_types=1);

namespace DiceGoblins\Tests\Integration;

use DateTimeImmutable;
use DateTimeZone;
use DiceGoblins\Application\Commands\RunStartException;
use DiceGoblins\Combat\Vnext\CombatInput;
use DiceGoblins\Combat\Vnext\CombatResolver;
use DiceGoblins\Combat\Vnext\CombatResult;
use DiceGoblins\Combat\Vnext\PlaybackRecorder;
use DiceGoblins\Content\ContentRegistry;
use DiceGoblins\Controllers\AuthController;
use DiceGoblins\Controllers\ControllerServiceFactory;
use DiceGoblins\Controllers\DialogueController;
use DiceGoblins\Controllers\GameBootstrapController;
use DiceGoblins\Controllers\OnboardingController;
use DiceGoblins\RunGeneration\FixedGraphRunGenerator;
use DiceGoblins\Tests\Support\IntegrationTestCase;

final class OpeningArcLifecycleIntegrationTest extends IntegrationTestCase
{
  public function testFreshAccountTraversesOpeningArcWithoutM13Access(): void
  {
    $content = ContentRegistry::load(dirname(__DIR__, 2) . '/tests/Fixtures/content');
    $this->setJsonBody([
      'email' => 'opening-arc-' . bin2hex(random_bytes(5)) . '@example.test',
      'password' => 'opening-arc-password', 'display_name' => 'New Warchief',
    ]);
    $registration = $this->invoke(fn() => (new AuthController())->localRegister());
    $this->assertSame(201, $registration['status']);
    $this->assertTrue($registration['body']['data']['authenticated']);
    $userId = (int)$_SESSION['user_id'];
    $this->trackUserId($userId);
    $_SERVER['HTTP_X_CSRF_TOKEN'] = (string)$_SESSION['csrf_token'];
    $publicBootstrap = $this->invoke(fn() => (new GameBootstrapController())->bootstrap());
    $this->assertSame(200, $publicBootstrap['status']);
    $this->assertSame((string)$userId, $publicBootstrap['body']['data']['account']['id']);
    $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
    $services = $this->services($content);
    $initial = $services['gameBootstrapQuery']->execute($userId, $now);
    $this->assertNull($initial['active_squad']);
    $this->assertSame([], $initial['progression']['available_region_ids']);
    $this->assertSame([], $initial['progression']['unlocked_region_ids']);
    $this->assertSame('required', $this->onboardingStatus($content)['status']);
    $this->assertLocked($services, $userId, 'region.the_farm', 'farm-before-onboarding');

    $onboarding = new OnboardingController($content);
    foreach ($content->onboardingFlow()['steps'] as $index => $step) {
      $dialogueId = $content->encounter($step['encounter_id'])['dialogue_id'];
      $dialogue = $content->dialogue($dialogueId);
      $this->assertSame($dialogueId, $this->onboardingStatus($content)['dialogue_id']);
      $_SERVER['HTTP_IDEMPOTENCY_KEY'] = 'opening-whim-' . $index;
      $this->setJsonBody(['choice_id' => $dialogue['choices'][0]['id']]);
      $result = $this->invoke(fn() => (new DialogueController($content))->complete($dialogueId));
      $this->assertSame(200, $result['status']);
      $this->assertSame($step['knowledge_id'], $result['body']['data']['knowledge']['codex_id']);
      $this->assertSame($result['body']['data']['player_revision'], $this->revision($userId));
    }
    $this->assertSame('ready_to_provision', $this->onboardingStatus($content)['status']);
    $this->assertNoKnowledge($userId, 'codex.mystic_cave.wrong_machine');
    $this->assertNoKnowledge($userId, 'codex.camp.wrong_machine_search');
    $this->assertLocked($services, $userId, 'region.the_farm', 'farm-before-provision');
    $_SERVER['HTTP_IDEMPOTENCY_KEY'] = 'opening-provision';
    $this->setJsonBody([]);
    $provision = $this->invoke(fn() => $onboarding->provision());
    $this->assertSame(200, $provision['status']);
    $provisionRevision = $this->revision($userId);
    $this->setJsonBody([]);
    $this->assertSame($provision, $this->invoke(fn() => $onboarding->provision()));
    $this->assertSame($provisionRevision, $this->revision($userId));
    $services = $this->services($content);
    $ready = $services['gameBootstrapQuery']->execute($userId, $now);
    $this->assertSame('complete', $this->onboardingStatus($content)['status']);
    $this->assertCount(4, $ready['active_squad']['units']);
    $this->assertSame(['region.the_farm'], $ready['progression']['available_region_ids']);
    $this->assertContains('unlock.region.the_farm', $ready['progression']['unlock_ids']);
    $this->assertNotContains('unlock.capability.shop_access', $ready['progression']['unlock_ids']);
    $this->assertNoKnowledge($userId, 'codex.camp.wrong_machine_search');
    $this->assertLocked($services, $userId, 'region.mountains', 'mountains-before-farm');

    $farm = $services['startRunCommand']->execute($userId, ['region_id' => 'region.the_farm'], 'opening-farm-start');
    $farmRunId = (int)$farm['run']['id'];
    $farmNodes = $this->nodeIds($services, $userId);
    $this->resolve($services, $userId, $farmRunId, $farmNodes, 0, 'opening-farm-combat');
    $this->resolve($services, $userId, $farmRunId, $farmNodes, 1, 'opening-farm-loot');
    $this->resolve($services, $userId, $farmRunId, $farmNodes, 2, 'opening-farm-scout',
      ['encounter_id' => 'encounter.the_farm_scout', 'action_id' => 'hear_paths']);
    $this->resolve($services, $userId, $farmRunId, $farmNodes, 3, 'opening-farm-rest',
      ['encounter_id' => 'encounter.the_farm_rest', 'action_id' => 'rest']);
    $boss = $this->resolve($services, $userId, $farmRunId, $farmNodes, 4, 'opening-farm-boss');
    $this->assertSame('victory', $boss['battle']['outcome']);
    $this->assertSame([['outcome' => 'granted', 'unlock_id' => 'unlock.capability.shop_access']], $boss['rewards']['unlocks']);
    $this->assertReplay($services, $userId, $farmRunId, $farmNodes[4], 'opening-farm-boss', $boss);
    $pendingCollector = $this->services($content);
    $this->assertSame([(string)$farmNodes[5]], $pendingCollector['currentRunQuery']->execute($userId)['run']['available_node_ids']);
    $this->assertSame('encounter.the_farm_tooth_collector',
      $pendingCollector['currentRunQuery']->execute($userId)['run']['nodes'][5]['interaction']['encounter_id']);
    $collector = $this->resolve($pendingCollector, $userId, $farmRunId, $farmNodes, 5, 'opening-collector',
      ['encounter_id' => 'encounter.the_farm_tooth_collector', 'action_id' => 'hear_thanks']);
    $this->assertSame('codex.farm.tooth_collector_rescued', $collector['knowledge']['codex_id']);
    $this->resolve($pendingCollector, $userId, $farmRunId, $farmNodes, 6, 'opening-farm-exit');
    $postFarm = $this->services($content);
    $beforeWhim = $postFarm['gameBootstrapQuery']->execute($userId, $now);
    $this->assertSame('dialogue.camp.whim_machine_search', $beforeWhim['progression']['camp_whim_dialogue_id']);
    $this->assertSame(['region.the_farm'], $beforeWhim['progression']['available_region_ids']);
    $this->assertNoKnowledge($userId, 'codex.camp.wrong_machine_search');
    $this->assertLocked($postFarm, $userId, 'region.mountains', 'mountains-before-whim');

    $whim = $postFarm['completeDialogueCommand']->execute($userId, 'dialogue.camp.whim_machine_search',
      'follow_lead', 'opening-post-farm-whim');
    $this->assertSame('codex.camp.wrong_machine_search', $whim['knowledge']['codex_id']);
    $this->assertSame('unlock.region.mountains', $whim['unlock']['unlock_id']);
    $whimRevision = $this->revision($userId);
    $this->assertSame($whim, $postFarm['completeDialogueCommand']->execute($userId,
      'dialogue.camp.whim_machine_search', 'follow_lead', 'opening-post-farm-whim'));
    $this->assertSame($whimRevision, $this->revision($userId));
    $mountainsServices = $this->services($content);
    $this->assertNull($mountainsServices['gameBootstrapQuery']->execute($userId, $now)['progression']['camp_whim_dialogue_id']);
    $this->assertNoKnowledge($userId, 'codex.mountains_archivist');
    $this->assertNoKnowledge($userId, 'codex.mountains_machine_in_swamps');
    $mountains = $mountainsServices['startRunCommand']->execute($userId,
      ['region_id' => 'region.mountains'], 'opening-mountains-start');
    $runId = (int)$mountains['run']['id'];
    $nodes = $this->nodeIds($mountainsServices, $userId);
    $this->assertCount(12, $nodes);
    $encounters = $this->pdo?->prepare('SELECT `encounter_id` FROM `run_nodes` WHERE `run_id` = ? ORDER BY `node_index`');
    $encounters?->execute([$runId]);
    foreach ($encounters?->fetchAll(\PDO::FETCH_COLUMN) ?? [] as $encounterId) {
      if ($encounterId === null) continue;
      $encounter = $content->encounter($encounterId);
      if (in_array($encounter['kind'], ['combat', 'boss'], true)) {
        foreach ($encounter['combatants'] as $combatant) {
          $this->assertStringStartsWith('enemy_unit_type.kobold', $combatant['enemy_unit_type_id']);
        }
      }
    }
    $this->resolve($mountainsServices, $userId, $runId, $nodes, 0, 'opening-mountains-combat-1');
    $restoredTrail = $this->services($content);
    $this->assertSame([(string)$nodes[9]], $restoredTrail['currentRunQuery']->execute($userId)['run']['available_node_ids']);
    $trail = $this->resolve($restoredTrail, $userId, $runId, $nodes, 9, 'opening-mountains-trail',
      ['encounter_id' => 'encounter.mountains_kobold_trail', 'action_id' => 'follow_tracks']);
    $this->assertSame('codex.mountains_kobold_trail', $trail['knowledge']['codex_id']);
    $this->assertReplay($restoredTrail, $userId, $runId, $nodes[9], 'opening-mountains-trail', $trail,
      ['encounter_id' => 'encounter.mountains_kobold_trail', 'action_id' => 'follow_tracks']);
    $this->resolve($restoredTrail, $userId, $runId, $nodes, 1, 'opening-mountains-loot');
    $this->resolve($restoredTrail, $userId, $runId, $nodes, 2, 'opening-mountains-combat-2');
    $this->resolve($restoredTrail, $userId, $runId, $nodes, 8, 'opening-mountains-rockfall',
      ['encounter_id' => 'encounter.mountains_rockfall', 'action_id' => 'push_through']);
    $this->resolve($restoredTrail, $userId, $runId, $nodes, 3, 'opening-mountains-rest',
      ['encounter_id' => 'encounter.mountains_rest', 'action_id' => 'rest']);
    $this->resolve($restoredTrail, $userId, $runId, $nodes, 7, 'opening-mountains-scree',
      ['encounter_id' => 'encounter.mountains_loose_scree', 'action_id' => 'cross']);
    $this->resolve($restoredTrail, $userId, $runId, $nodes, 4, 'opening-mountains-combat-3');
    $restoredArchivist = $this->services($content);
    $this->assertSame([(string)$nodes[10]], $restoredArchivist['currentRunQuery']->execute($userId)['run']['available_node_ids']);
    $this->assertNoKnowledge($userId, 'codex.mountains_archivist');
    $archivist = $this->resolve($restoredArchivist, $userId, $runId, $nodes, 10, 'opening-archivist',
      ['encounter_id' => 'encounter.mountains_archivist', 'action_id' => 'ask_about_machine']);
    $this->assertSame('codex.mountains_archivist', $archivist['knowledge']['codex_id']);
    $this->assertNoKnowledge($userId, 'codex.mountains_machine_in_swamps');
    $this->assertSame(['region.the_farm', 'region.mountains'],
      $restoredArchivist['gameBootstrapQuery']->execute($userId, $now)['progression']['available_region_ids']);
    $mountainsBoss = $this->resolve($restoredArchivist, $userId, $runId, $nodes, 5, 'opening-mountains-boss');
    $this->assertSame('victory', $mountainsBoss['battle']['outcome']);
    $this->assertSame([['outcome' => 'granted', 'unlock_id' => 'unlock.region.swamps']], $mountainsBoss['rewards']['unlocks']);
    $this->assertSame([['outcome' => 'granted', 'codex_id' => 'codex.mountains_machine_in_swamps']],
      $mountainsBoss['rewards']['knowledge']);
    $this->assertReplay($restoredArchivist, $userId, $runId, $nodes[5], 'opening-mountains-boss', $mountainsBoss);
    $restoredChart = $this->services($content);
    $this->assertSame([(string)$nodes[11]], $restoredChart['currentRunQuery']->execute($userId)['run']['available_node_ids']);
    $this->assertSame('encounter.mountains_swamp_handoff',
      $restoredChart['currentRunQuery']->execute($userId)['run']['nodes'][11]['interaction']['encounter_id']);
    $this->resolve($restoredChart, $userId, $runId, $nodes, 11, 'opening-swamp-chart',
      ['encounter_id' => 'encounter.mountains_swamp_handoff', 'action_id' => 'follow_swamp_trail']);
    $this->resolve($restoredChart, $userId, $runId, $nodes, 6, 'opening-mountains-exit');

    $final = $this->services($content)['gameBootstrapQuery']->execute($userId, $now);
    $this->assertNull($final['active_run']);
    $this->assertSame(['region.the_farm', 'region.mountains'], $final['progression']['available_region_ids']);
    $this->assertSame(['region.the_farm', 'region.mountains', 'region.swamps'],
      $final['progression']['unlocked_region_ids']);
    $this->assertEqualsCanonicalizing([
      'unlock.region.the_farm', 'unlock.capability.shop_access', 'unlock.region.mountains', 'unlock.region.swamps',
    ], $final['progression']['unlock_ids']);
    $this->assertNotContains('unlock.capability.wrong_machine_access', $final['progression']['unlock_ids']);
    $this->assertNoKnowledge($userId, 'codex.mystic_cave.wrong_machine');
    $this->assertSame(1, (int)$this->scalar("SELECT COUNT(*) FROM `user_codex_ownership` WHERE `user_id` = ? AND `codex_id` = 'codex.mountains_machine_in_swamps'", [$userId]));
    $this->assertSame(0, (int)$this->scalar("SELECT COUNT(*) FROM `user_unlocks` WHERE `user_id` = ? AND `unlock_id` = 'unlock.capability.wrong_machine_access'", [$userId]));
    $this->assertSame(0, (int)$this->scalar("SELECT COUNT(*) FROM `unit_instances` WHERE `user_id` = ? AND `kin_id` = 'kin.frog'", [$userId]));
    $this->assertSame([], array_values(array_filter($content->definitionsOfType('run_generation'),
      static fn(array $definition): bool => $definition['id'] === 'run_generation.swamps')));
    $this->assertSame([], array_values(array_filter($content->definitionsOfType('encounter'),
      static fn(array $definition): bool => ($definition['region_id'] ?? null) === 'region.swamps')));
    $this->assertSame([], array_values(array_filter($content->definitionsOfType('enemy_unit_type'),
      static fn(array $definition): bool => str_contains($definition['id'], 'frog') || str_contains($definition['id'], 'order'))));
    $this->assertSame([], array_values(array_filter($content->definitionsOfType('reconstruction_recipe'),
      static fn(array $definition): bool => str_contains($definition['kin_id'], 'frog'))));
    $runsBefore = (int)$this->scalar('SELECT COUNT(*) FROM `runs` WHERE `user_id` = ?', [$userId]);
    $revisionBefore = $this->revision($userId);
    $energyBefore = (int)$this->scalar('SELECT `energy_current` FROM `user_state` WHERE `user_id` = ?', [$userId]);
    try {
      $this->services($content)['startRunCommand']->execute($userId, ['region_id' => 'region.swamps'], 'opening-swamp-start');
      $this->fail('Swamp run started before M13 content.');
    } catch (RunStartException $e) { $this->assertSame('run_region_unsupported', $e->errorCode); }
    $this->assertSame($runsBefore, (int)$this->scalar('SELECT COUNT(*) FROM `runs` WHERE `user_id` = ?', [$userId]));
    $this->assertSame($revisionBefore, $this->revision($userId));
    $this->assertSame($energyBefore, (int)$this->scalar('SELECT `energy_current` FROM `user_state` WHERE `user_id` = ?', [$userId]));
    $finalHttp = $this->invoke(fn() => (new GameBootstrapController())->bootstrap());
    $this->assertSame(200, $finalHttp['status']);
    $this->assertSame($final['progression'], $finalHttp['body']['data']['progression']);

    $repeatServices = $this->services($content);
    $repeat = $repeatServices['startRunCommand']->execute($userId,
      ['region_id' => 'region.mountains'], 'opening-mountains-repeat-start');
    $repeatRunId = (int)$repeat['run']['id'];
    $repeatNodes = $this->nodeIds($repeatServices, $userId);
    $this->resolve($repeatServices, $userId, $repeatRunId, $repeatNodes, 0, 'opening-repeat-combat-1');
    $repeatTrail = $this->resolve($repeatServices, $userId, $repeatRunId, $repeatNodes, 9, 'opening-repeat-trail',
      ['encounter_id' => 'encounter.mountains_kobold_trail', 'action_id' => 'follow_tracks']);
    $this->assertSame('already_owned', $repeatTrail['knowledge']['outcome']);
    $this->resolve($repeatServices, $userId, $repeatRunId, $repeatNodes, 1, 'opening-repeat-loot');
    $this->resolve($repeatServices, $userId, $repeatRunId, $repeatNodes, 2, 'opening-repeat-combat-2');
    $this->resolve($repeatServices, $userId, $repeatRunId, $repeatNodes, 8, 'opening-repeat-rockfall',
      ['encounter_id' => 'encounter.mountains_rockfall', 'action_id' => 'push_through']);
    $this->resolve($repeatServices, $userId, $repeatRunId, $repeatNodes, 3, 'opening-repeat-rest',
      ['encounter_id' => 'encounter.mountains_rest', 'action_id' => 'rest']);
    $this->resolve($repeatServices, $userId, $repeatRunId, $repeatNodes, 7, 'opening-repeat-scree',
      ['encounter_id' => 'encounter.mountains_loose_scree', 'action_id' => 'cross']);
    $this->resolve($repeatServices, $userId, $repeatRunId, $repeatNodes, 4, 'opening-repeat-combat-3');
    $repeatArchivist = $this->resolve($repeatServices, $userId, $repeatRunId, $repeatNodes, 10, 'opening-repeat-archivist',
      ['encounter_id' => 'encounter.mountains_archivist', 'action_id' => 'ask_about_machine']);
    $this->assertSame('already_owned', $repeatArchivist['knowledge']['outcome']);
    $repeatBoss = $this->resolve($repeatServices, $userId, $repeatRunId, $repeatNodes, 5, 'opening-repeat-boss');
    $this->assertSame([['outcome' => 'already_owned', 'unlock_id' => 'unlock.region.swamps']], $repeatBoss['rewards']['unlocks']);
    $this->assertSame([['outcome' => 'already_owned', 'codex_id' => 'codex.mountains_machine_in_swamps']],
      $repeatBoss['rewards']['knowledge']);
    $this->assertReplay($repeatServices, $userId, $repeatRunId, $repeatNodes[5], 'opening-repeat-boss', $repeatBoss);
    $this->assertSame(1, (int)$this->scalar("SELECT COUNT(*) FROM `user_unlocks` WHERE `user_id` = ? AND `unlock_id` = 'unlock.region.swamps'", [$userId]));
    $this->assertSame(1, (int)$this->scalar("SELECT COUNT(*) FROM `user_codex_ownership` WHERE `user_id` = ? AND `codex_id` = 'codex.mountains_machine_in_swamps'", [$userId]));
  }

  private function services(ContentRegistry $content): array
  {
    $resolver = new class implements CombatResolver {
      public function resolve(CombatInput $input): CombatResult
      {
        $terminal = [];
        foreach ($input->combatants as $key => $unit) {
          $hp = $unit['side'] === 'enemy' ? 0 : $unit['current_hp'];
          $terminal[] = ['key' => $key, 'side' => $unit['side'], 'current_hp' => $hp,
            'max_hp' => $unit['max_hp'], 'is_defeated' => $hp === 0, 'statuses' => $unit['statuses']];
        }
        $events = new PlaybackRecorder();
        $events->add('battle_started', 0, 0, ['combatant_keys' => array_keys($input->combatants)]);
        $events->add('battle_ended', 1, 1, ['outcome' => 'victory']);
        return new CombatResult('victory', 1, 1, $terminal, $events->events());
      }
    };
    return ControllerServiceFactory::buildContentAware($this->pdo, null, $content, $resolver, new FixedGraphRunGenerator());
  }

  private function onboardingStatus(ContentRegistry $content): array
  {
    $response = $this->invoke(fn() => (new OnboardingController($content))->status());
    $this->assertSame(200, $response['status']);
    return $response['body']['data'];
  }

  private function nodeIds(array $services, int $userId): array
  {
    return array_map('intval', array_column($services['currentRunQuery']->execute($userId)['run']['nodes'], 'id'));
  }

  private function resolve(array $services, int $userId, int $runId, array $nodes, int $index,
    string $key, ?array $action = null): array
  {
    return $services['resolveRunNodeCommand']->execute($userId, $runId, $nodes[$index], $key, $action);
  }

  private function assertReplay(array $services, int $userId, int $runId, int $nodeId,
    string $key, array $original, ?array $action = null): void
  {
    $revision = $this->revision($userId);
    $this->assertSame($original, $services['resolveRunNodeCommand']->execute($userId, $runId, $nodeId, $key, $action));
    $this->assertSame($revision, $this->revision($userId));
  }

  private function assertLocked(array $services, int $userId, string $regionId, string $key): void
  {
    try {
      $services['startRunCommand']->execute($userId, ['region_id' => $regionId], $key);
      $this->fail($regionId . ' started before its durable unlock.');
    } catch (RunStartException $e) { $this->assertSame('run_region_locked', $e->errorCode); }
  }

  private function assertNoKnowledge(int $userId, string $codexId): void
  {
    $this->assertSame(0, (int)$this->scalar('SELECT COUNT(*) FROM `user_codex_ownership` WHERE `user_id` = ? AND `codex_id` = ?',
      [$userId, $codexId]));
  }

  private function revision(int $userId): int
  {
    return (int)$this->scalar('SELECT `player_revision` FROM `user_state` WHERE `user_id` = ?', [$userId]);
  }
}
