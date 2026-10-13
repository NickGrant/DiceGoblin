<?php
declare(strict_types=1);

namespace DiceGoblins\Tests\Integration;

use DiceGoblins\Application\Commands\ProvisionWarbandFixtureCommand;
use DiceGoblins\Application\Commands\RunNodeResolutionException;
use DiceGoblins\Content\ContentRegistry;
use DiceGoblins\Combat\Vnext\DamageCalculator;
use DiceGoblins\Controllers\ControllerServiceFactory;
use DiceGoblins\Domain\CombatStats\BaseLevelStatResolver;
use DiceGoblins\Repositories\BattlePersistenceRepository;
use DiceGoblins\Repositories\RunPersistenceRepository;
use DiceGoblins\Repositories\WarbandFixtureRepository;
use DiceGoblins\RunGeneration\FixedGraphRunGenerator;
use DiceGoblins\Tests\Support\IntegrationTestCase;
use DiceGoblins\Tests\Support\RunModifierContentFixture;
use PDO;

final class RunModifierLifecycleTest extends IntegrationTestCase
{
  protected function supportsVnextBaseline(): bool { return true; }

  public function testSourceReloadUnrelatedLootCombatConsumptionAndExactReplays(): void
  {
    [$userId, $runId, $ids, $content] = $this->readyRun(true);
    $services = ControllerServiceFactory::buildContentAware($this->pdo, null, $content);
    $command = $services['resolveRunNodeCommand'];
    $request = ['encounter_id' => RunModifierContentFixture::ENCOUNTER_ID, 'action_id' => 'focus'];
    $source = $command->execute($userId, $runId, $ids[3], 'focus-source', $request);
    $this->assertSame('shrine', $source['resolution_type']);
    $this->assertSame(1, $source['created_modifier']['remaining_combats']);
    $this->assertSame($source, $command->execute($userId, $runId, $ids[3], 'focus-source', $request));
    $this->assertSame(1, $this->countModifiers($runId));
    $reloaded = new PDO((string)getenv('TEST_DB_DSN'), (string)getenv('TEST_DB_USER'),
      (string)getenv('TEST_DB_PASS'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $reloadedQuery = ControllerServiceFactory::buildContentAware($reloaded, null, $content)['currentRunQuery'];
    $active = $reloadedQuery->execute($userId)['run'];
    $this->assertSame([['id' => $source['created_modifier']['id'],
      'modifier_id' => RunModifierContentFixture::MODIFIER_ID,
      'display_name' => 'Battle Focus', 'description' => 'Warband Attack rises in the next battle.',
      'remaining_combats' => 1]], $active['modifiers']);
    $visible = json_encode($active, JSON_THROW_ON_ERROR);
    foreach (['attack_bonus', 'create_run_modifier', 'remaining_combats":0'] as $hidden) {
      $this->assertStringNotContainsString($hidden, $visible);
    }
    $command->execute($userId, $runId, $ids[2], 'other-loot');
    $this->assertSame(1, $this->countModifiers($runId));
    $combat = $command->execute($userId, $runId, $ids[4], 'focus-combat');
    $this->assertSame([['id' => $source['created_modifier']['id'],
      'modifier_id' => RunModifierContentFixture::MODIFIER_ID, 'remaining_combats' => 0]],
      $combat['applied_modifiers']);
    $battle = (new BattlePersistenceRepository($this->pdo))->findForRunNode($runId, $ids[4]);
    $this->assertNotNull($battle);
    $participant = $battle->battle->manifestArray()[0];
    $combatant = $battle->battle->combatInput->combatants[$participant['combatant_key']];
    $unit = $this->row('SELECT `unit_type_id`, `level` FROM `unit_instances` WHERE `id` = ?', [$participant['unit_id']]);
    $type = $content->unitType((string)$unit['unit_type_id']);
    $base = (new BaseLevelStatResolver())->resolve($type['base_stats'], $type['growth_per_level'], (int)$unit['level']);
    $this->assertSame($base->attack + 2, $combatant['stats']['attack']);
    $enemy = current(array_filter($battle->battle->combatInput->combatants,
      static fn(array $row): bool => $row['side'] === 'enemy'));
    $this->assertIsArray($enemy);
    $ability = $combatant['active_abilities'][0];
    $baselineActor = $combatant;
    $baselineActor['stats']['attack'] -= 2;
    $calculator = new DamageCalculator();
    $baselineDamage = $calculator->calculate($baselineActor, $enemy, $ability, 4, false);
    $modifiedDamage = $calculator->calculate($combatant, $enemy, $ability, 4, false);
    $this->assertGreaterThan($baselineDamage['attack_component'], $modifiedDamage['attack_component']);
    $this->assertGreaterThan($baselineDamage['amount'], $modifiedDamage['amount']);
    $this->assertSame(0, $this->countModifiers($runId));
    $this->assertSame($combat, $command->execute($userId, $runId, $ids[4], 'focus-combat'));
    $this->assertSame(1, (int)$this->scalar('SELECT COUNT(*) FROM `battles` WHERE `run_id` = ? AND `run_node_id` = ?', [$runId, $ids[4]]));
    $this->assertArrayNotHasKey('modifiers', $reloadedQuery->execute($userId)['run']);
  }

  public function testRejectedCombatDoesNotConsumeAndOtherRunCannotSeeModifier(): void
  {
    [$userId, $runId, $ids, $content] = $this->readyRun(true);
    $services = ControllerServiceFactory::buildContentAware($this->pdo, null, $content);
    $command = $services['resolveRunNodeCommand'];
    $command->execute($userId, $runId, $ids[3], 'focus-source-2',
      ['encounter_id' => RunModifierContentFixture::ENCOUNTER_ID, 'action_id' => 'focus']);
    $this->pdo?->prepare("UPDATE `run_nodes` SET `status` = 'locked' WHERE `id` = ?")->execute([$ids[4]]);
    try {
      $command->execute($userId, $runId, $ids[4], 'locked-combat');
      $this->fail('Locked combat resolved.');
    } catch (RunNodeResolutionException $e) { $this->assertSame('run_node_unavailable', $e->errorCode); }
    $this->assertSame(1, $this->countModifiers($runId));
    $this->pdo?->prepare("UPDATE `runs` SET `status` = 'abandoned', `ended_at` = UTC_TIMESTAMP() WHERE `id` = ?")
      ->execute([$runId]);
    $this->assertNull($services['currentRunQuery']->execute($userId)['run']);
    $this->assertSame(1, $this->countModifiers($runId));
    $repository = new RunPersistenceRepository($this->pdo);
    $squadId = (int)$this->scalar('SELECT `squad_id` FROM `runs` WHERE `id` = ?', [$runId]);
    $laterRunId = $repository->createRun($userId, 'region.the_farm', $squadId);
    $graph = (new FixedGraphRunGenerator())->generate($content->runGeneration(RunModifierContentFixture::GENERATION_ID));
    $laterIds = $repository->insertNodes($laterRunId, $graph['nodes']);
    $repository->insertEdges($laterRunId, $graph['edges'], $laterIds);
    $repository->insertParticipatingUnits($laterRunId, array_map(static fn(array $row): array => [
      'unit_id' => (int)$row['unit_id'], 'current_hp' => (int)$row['current_hp'],
    ], $this->rows('SELECT `unit_id`, `current_hp` FROM `run_unit_state` WHERE `run_id` = ?', [$runId])));
    $this->assertSame(0, $this->countModifiers($laterRunId));
    $this->assertArrayNotHasKey('modifiers', $services['currentRunQuery']->execute($userId)['run']);
    $this->assertSame(1, $this->countModifiers($runId));
    $other = $services['accountCreationService']->createLocal('modifier-other-' . bin2hex(random_bytes(5)) . '@example.test',
      password_hash('test-password', PASSWORD_DEFAULT), 'Other Tester');
    $this->trackUserId($other);
    $this->assertNull($services['currentRunQuery']->execute($other)['run']);
  }

  public function testBossIsEligibleForTheSameNextCombatModifier(): void
  {
    [$userId, $runId, $ids, $content] = $this->readyRun(false);
    $command = ControllerServiceFactory::buildContentAware($this->pdo, null, $content)['resolveRunNodeCommand'];
    $source = $command->execute($userId, $runId, $ids[3], 'focus-before-boss',
      ['encounter_id' => RunModifierContentFixture::ENCOUNTER_ID, 'action_id' => 'focus']);
    $boss = $command->execute($userId, $runId, $ids[4], 'focused-boss');
    $this->assertSame('boss', $boss['resolution_type']);
    $this->assertSame([['id' => $source['created_modifier']['id'],
      'modifier_id' => RunModifierContentFixture::MODIFIER_ID, 'remaining_combats' => 0]],
      $boss['applied_modifiers']);
    $this->assertSame(0, $this->countModifiers($runId));
    $this->assertSame($boss, $command->execute($userId, $runId, $ids[4], 'focused-boss'));
  }

  /** @return array{0:int,1:int,2:array<int,int>,3:ContentRegistry} */
  private function readyRun(bool $nextCombat): array
  {
    $content = RunModifierContentFixture::registry($nextCombat);
    $services = ControllerServiceFactory::buildContentAware($this->pdo, null, $content);
    $userId = $services['accountCreationService']->createLocal('modifier-' . bin2hex(random_bytes(5)) . '@example.test',
      password_hash('test-password', PASSWORD_DEFAULT), 'Focus Tester');
    $this->trackUserId($userId);
    $fixture = (new ProvisionWarbandFixtureCommand($this->pdo, new WarbandFixtureRepository($this->pdo), $content))
      ->execute($userId);
    $graph = (new FixedGraphRunGenerator())->generate($content->runGeneration(RunModifierContentFixture::GENERATION_ID));
    $repository = new RunPersistenceRepository($this->pdo);
    $runId = $repository->createRun($userId, 'region.the_farm', (int)$fixture['active_squad_id']);
    $ids = $repository->insertNodes($runId, $graph['nodes']);
    $repository->insertEdges($runId, $graph['edges'], $ids);
    $participants = [];
    foreach ($this->rows('SELECT ui.`id`, ui.`unit_type_id`, ui.`level` FROM `squad_units` su
      JOIN `unit_instances` ui ON ui.`id` = su.`unit_id`
      WHERE su.`squad_id` = ? AND ui.`user_id` = ? ORDER BY su.`position`',
      [(int)$fixture['active_squad_id'], $userId]) as $unit) {
      $type = $content->unitType((string)$unit['unit_type_id']);
      $maximum = (new BaseLevelStatResolver())->resolve($type['base_stats'], $type['growth_per_level'], (int)$unit['level'])->hp;
      $participants[] = ['unit_id' => (int)$unit['id'], 'current_hp' => $maximum];
    }
    $repository->insertParticipatingUnits($runId, $participants);
    $this->pdo?->prepare("UPDATE `run_nodes` SET `status` = 'completed', `completed_at` = UTC_TIMESTAMP()
      WHERE `run_id` = ? AND `node_index` IN (0, 1)")->execute([$runId]);
    $this->pdo?->prepare("UPDATE `run_nodes` SET `status` = 'available' WHERE `run_id` = ? AND `node_index` IN (2, 3)")
      ->execute([$runId]);
    return [$userId, $runId, $ids, $content];
  }

  private function countModifiers(int $runId): int
  {
    return (int)$this->scalar('SELECT COUNT(*) FROM `run_modifiers` WHERE `run_id` = ?', [$runId]);
  }

  /** @return array<string,mixed> */
  private function row(string $sql, array $params): array
  {
    return $this->rows($sql, $params)[0];
  }

  /** @return list<array<string,mixed>> */
  private function rows(string $sql, array $params): array
  {
    $stmt = $this->pdo?->prepare($sql);
    $stmt?->execute($params);
    return $stmt?->fetchAll(PDO::FETCH_ASSOC) ?: [];
  }

}
