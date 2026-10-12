<?php
declare(strict_types=1);

namespace DiceGoblins\Tests\Unit;

use DiceGoblins\Content\ClientContentProjector;
use DiceGoblins\Content\ContentRegistry;
use DiceGoblins\Content\ContentValidationException;
use DiceGoblins\Content\ContentValidator;
use DiceGoblins\RunGeneration\FixedGraphRunGenerator;
use DiceGoblins\Tests\Support\BranchingRunDefinitionFixture;
use DiceGoblins\Tests\Support\ShrineContentFixture;
use PHPUnit\Framework\TestCase;

final class RunContentValidationTest extends TestCase
{
  public function testValidatedBranchingDefinitionGeneratesTwoOutgoingRoutesWithoutChangingCanonicalRegions(): void
  {
    $content = ContentRegistry::load($this->canonicalRoot());
    $definition = BranchingRunDefinitionFixture::definition($content);
    $definitions = $this->canonicalDefinitions();
    $definitions[] = $definition;
    (new ContentValidator())->validate([['path' => 'branching-test.json',
      'document' => ['definitions' => $definitions]]]);
    $graph = (new FixedGraphRunGenerator())->generate($definition);
    $this->assertSame([0, 0, 1, 2, 3, 4], array_column($graph['edges'], 'from_node_index'));
    $this->assertSame([1, 2, 3, 3, 4, 5], array_column($graph['edges'], 'to_node_index'));
    $this->assertSame(['available', 'locked', 'locked', 'locked', 'locked', 'locked'],
      array_column($graph['nodes'], 'status'));
  }
  public function testCanonicalRestEncountersAreServerPrivateAndBoundToBothRegions(): void
  {
    $registry = ContentRegistry::load($this->canonicalRoot());
    foreach (['region.the_farm' => 'encounter.the_farm_rest',
      'region.mountains' => 'encounter.mountains_rest'] as $regionId => $encounterId) {
      $generation = $registry->runGenerationForRegion($regionId);
      $rest = array_values(array_filter($generation['nodes'],
        static fn(array $node): bool => $node['node_type_id'] === 'run_node_type.rest'));
      $this->assertCount(1, $rest);
      $this->assertSame($encounterId, $rest[0]['encounter_id']);
      $encounter = $registry->encounter($encounterId);
      $this->assertSame('non_combat', $encounter['kind']);
      $this->assertSame('restore_participating_units_to_max', $encounter['actions'][0]['effect']['type']);
    }
    $projection = json_encode((new ClientContentProjector())->project($registry), JSON_THROW_ON_ERROR);
    $this->assertStringNotContainsString('encounter.the_farm_rest', $projection);
    $this->assertStringNotContainsString('restore_participating_units_to_max', $projection);
  }

  public function testMountainsHazardsAreAuthoredAndTheirEffectsStayServerPrivate(): void
  {
    $registry = ContentRegistry::load($this->canonicalRoot());
    $generation = $registry->runGenerationForRegion('region.mountains');
    $hazards = array_values(array_filter($generation['nodes'],
      static fn(array $node): bool => $node['node_type_id'] === 'run_node_type.hazard'));
    $this->assertSame(['encounter.mountains_loose_scree', 'encounter.mountains_rockfall'],
      array_column($hazards, 'encounter_id'));
    foreach ($hazards as $node) {
      $encounter = $registry->encounter($node['encounter_id']);
      $this->assertSame(['non_combat', 'region.mountains'], [$encounter['kind'], $encounter['region_id']]);
      $this->assertNotEmpty($encounter['actions']);
    }
    $farm = $registry->runGenerationForRegion('region.the_farm');
    $this->assertNotContains('run_node_type.hazard', array_column($farm['nodes'], 'node_type_id'));
    $projected = json_encode((new ClientContentProjector())->project($registry), JSON_THROW_ON_ERROR);
    $this->assertStringNotContainsString('encounter.mountains_loose_scree', $projected);
    $this->assertStringNotContainsString('damage_random_participating_unit', $projected);
    $this->assertStringNotContainsString('damage_all_participating_units', $projected);
  }

  public function testMalformedHazardEffectsAndReferencesFailValidation(): void
  {
    foreach (['unknown_effect', 'missing_amount', 'invalid_amount', 'incompatible_effect',
      'unknown_family', 'wrong_family_reference', 'foreign_encounter'] as $case) {
      $definitions = $this->canonicalDefinitions();
      foreach ($definitions as &$definition) {
        if (($definition['id'] ?? null) === 'encounter.mountains_loose_scree') {
          if ($case === 'unknown_effect') $definition['actions'][0]['effect']['type'] = 'grant_whim';
          if ($case === 'missing_amount') unset($definition['actions'][0]['effect']['amount']);
          if ($case === 'invalid_amount') $definition['actions'][0]['effect']['amount'] = 0;
          if ($case === 'incompatible_effect') $definition['actions'][0]['effect'] = ['type' => 'restore_participating_units_to_max'];
          if ($case === 'unknown_family') $definition['family'] = 'unknown';
        }
        if (($definition['id'] ?? null) === 'run_generation.mountains' && $case === 'foreign_encounter') {
          $definition['nodes'][7]['encounter_id'] = 'encounter.the_farm_rest';
        }
        if (($definition['id'] ?? null) === 'run_generation.mountains' && $case === 'wrong_family_reference') {
          $definition['nodes'][7]['encounter_id'] = 'encounter.mountains_rest';
        }
      }
      unset($definition);
      try {
        (new ContentValidator())->validate([['path' => 'hazard-invalid.json',
          'document' => ['definitions' => $definitions]]]);
        $this->fail("Invalid Hazard case {$case} was accepted.");
      } catch (ContentValidationException) {
        $this->addToAssertionCount(1);
      }
    }
  }
  public function testTestOnlyShrineChoiceValidatesWithoutChangingProductionRegionsOrBrowserCatalog(): void
  {
    $canonical = ContentRegistry::load($this->canonicalRoot());
    foreach (['region.the_farm', 'region.mountains'] as $regionId) {
      $generation = $canonical->runGenerationForRegion($regionId);
      $this->assertNotContains('run_node_type.shrine', array_column($generation['nodes'], 'node_type_id'));
      $this->assertNotContains(ShrineContentFixture::ENCOUNTER_ID, array_column($generation['nodes'], 'encounter_id'));
    }
    $this->assertCount(5, $canonical->runGenerationForRegion('region.the_farm')['nodes']);
    $this->assertCount(9, $canonical->runGenerationForRegion('region.mountains')['nodes']);
    $registry = ShrineContentFixture::registry();
    $encounter = $registry->encounter(ShrineContentFixture::ENCOUNTER_ID);
    $this->assertSame(['take_teeth', 'take_chaos'], array_column($encounter['actions'], 'id'));
    $this->assertSame('run_node_type.shrine',
      $registry->runGeneration(ShrineContentFixture::GENERATION_ID)['nodes'][3]['node_type_id']);
    $projected = json_encode((new ClientContentProjector())->project($registry), JSON_THROW_ON_ERROR);
    $this->assertStringNotContainsString(ShrineContentFixture::ENCOUNTER_ID, $projected);
    $this->assertStringNotContainsString('grant_currency', $projected);
  }

  public function testMalformedShrineFamilyActionsEffectsAndReferencesFailValidation(): void
  {
    foreach (['family', 'duplicate_action', 'unknown_effect', 'missing_amount', 'invalid_amount',
      'invalid_currency', 'incompatible_effect', 'wrong_family_reference', 'invalid_region_reference'] as $case) {
      $definitions = ShrineContentFixture::definitions();
      foreach ($definitions as &$definition) {
        if ($definition['id'] === ShrineContentFixture::ENCOUNTER_ID) {
          if ($case === 'family') $definition['family'] = 'unknown';
          if ($case === 'duplicate_action') $definition['actions'][1]['id'] = 'take_teeth';
          if ($case === 'unknown_effect') $definition['actions'][0]['effect']['type'] = 'grant_whim';
          if ($case === 'missing_amount') unset($definition['actions'][0]['effect']['amount']);
          if ($case === 'invalid_amount') $definition['actions'][0]['effect']['amount'] = 0;
          if ($case === 'invalid_currency') $definition['actions'][0]['effect']['currency_id'] = 'energy';
          if ($case === 'incompatible_effect') $definition['actions'][0]['effect'] = ['type' => 'restore_participating_units_to_max'];
          if ($case === 'invalid_region_reference') $definition['region_id'] = 'region.missing';
        }
        if ($definition['id'] === ShrineContentFixture::GENERATION_ID && $case === 'wrong_family_reference') {
          $definition['nodes'][3]['encounter_id'] = 'encounter.the_farm_rest';
        }
      }
      unset($definition);
      try {
        (new ContentValidator())->validate([['path' => 'shrine-invalid.json',
          'document' => ['definitions' => $definitions]]]);
        $this->fail("Invalid Shrine case {$case} was accepted.");
      } catch (ContentValidationException) { $this->addToAssertionCount(1); }
    }
  }
  public function testOptionalDeadEndAndNonIndexOrderedAcyclicRoutesAreValid(): void
  {
    $content = ContentRegistry::load($this->canonicalRoot());
    $definition = BranchingRunDefinitionFixture::definition($content, true);
    $definitions = $this->canonicalDefinitions();
    $definitions[] = $definition;
    (new ContentValidator())->validate([['path' => 'branching-test.json',
      'document' => ['definitions' => $definitions]]]);
    $graph = (new FixedGraphRunGenerator())->generate($definition);
    $this->assertSame([0, 0, 1, 3, 4], array_column($graph['edges'], 'from_node_index'));

    // List order supplies stable generated IDs; edge direction supplies reachability.
    [$definition['nodes'][1], $definition['nodes'][3]] = [$definition['nodes'][3], $definition['nodes'][1]];
    $definitions[count($definitions) - 1] = $definition;
    (new ContentValidator())->validate([['path' => 'branching-test.json',
      'document' => ['definitions' => $definitions]]]);
    $graph = (new FixedGraphRunGenerator())->generate($definition);
    $this->assertContains([3, 1], array_map(static fn(array $edge): array =>
      [$edge['from_node_index'], $edge['to_node_index']], $graph['edges']));
  }

  public function testBranchingCycleIsRejected(): void
  {
    $definitions = $this->canonicalDefinitions();
    $definition = BranchingRunDefinitionFixture::definition(ContentRegistry::load($this->canonicalRoot()));
    $definition['edges'][] = ['from' => 'rest', 'to' => 'loot'];
    $definitions[] = $definition;
    $this->expectException(ContentValidationException::class);
    $this->expectExceptionMessage('cycle');
    (new ContentValidator())->validate([['path' => 'branching-test.json',
      'document' => ['definitions' => $definitions]]]);
  }
  public function testCanonicalFarmRunContentIsValidatedAndTyped(): void
  {
    $registry = ContentRegistry::load($this->canonicalRoot());
    $generation = $registry->runGenerationForRegion('region.the_farm');

    $this->assertSame('run_generation.the_farm', $generation['id']);
    $this->assertSame('fixed_graph_v1', $generation['algorithm']);
    $this->assertSame(
      ['run_node_type.combat', 'run_node_type.loot', 'run_node_type.rest', 'run_node_type.boss', 'run_node_type.exit'],
      array_column($generation['nodes'], 'node_type_id'),
    );
    $this->assertCount(7, $registry->definitionsOfType('run_node_type'));
    $this->assertSame('encounter.the_farm_mud_combat_1', $generation['nodes'][0]['encounter_id']);
    $this->assertSame('event.farm_loot_completed', $generation['nodes'][1]['event_id']);
    $this->assertSame('encounter.the_farm_mud_boss_1', $generation['nodes'][3]['encounter_id']);
    $this->assertSame('event.farm_boss_completed', $generation['nodes'][3]['event_id']);
    $this->assertSame('Combat', $registry->runNodeType('run_node_type.combat')['display_name']);
  }

  public function testCanonicalMountainsRunContentIncludesPathAndDeadEndHazards(): void
  {
    $registry = ContentRegistry::load($this->canonicalRoot());
    $generation = $registry->runGenerationForRegion('region.mountains');

    $this->assertSame('run_generation.mountains', $generation['id']);
    $this->assertSame('combat_1', $generation['start_node_key']);
    $this->assertSame(['combat_1', 'loot', 'combat_2', 'rest', 'combat_3', 'boss', 'exit', 'loose_scree', 'rockfall'],
      array_column($generation['nodes'], 'key'));
    $this->assertSame([
      'run_node_type.combat', 'run_node_type.loot', 'run_node_type.combat', 'run_node_type.rest',
      'run_node_type.combat', 'run_node_type.boss', 'run_node_type.exit', 'run_node_type.hazard', 'run_node_type.hazard',
    ], array_column($generation['nodes'], 'node_type_id'));
    $this->assertSame([0, 1, 2, 3, 5, 6, 7, 4, 3], array_column(array_column($generation['nodes'], 'position'), 'column'));
    $this->assertSame([1, 1, 1, 1, 1, 1, 1, 1, 2], array_column(array_column($generation['nodes'], 'position'), 'row'));
    $this->assertCount(8, $generation['edges']);
  }

  public function testProjectionExposesOnlySafeNodeTypePresentation(): void
  {
    $registry = ContentRegistry::load($this->canonicalRoot());
    $projection = (new ClientContentProjector())->project($registry);
    $encoded = json_encode($projection, JSON_THROW_ON_ERROR);

    $this->assertSame(['run_energy_cost' => 10], $projection['content']['gameplay']);
    $this->assertSame([
      'id' => 'run_node_type.combat',
      'encounter_kind' => 'combat',
      'display_name' => 'Combat',
      'description' => 'Fight enemies guarding the path.',
      'icon_key' => 'icon_encounter_combat',
    ], $projection['content']['run_node_types']['run_node_type.combat']);
    $this->assertSame('non_combat', $projection['content']['run_node_types']['run_node_type.rest']['encounter_kind']);
    $this->assertStringNotContainsString('restore_participating_units_to_max', $encoded);
    $this->assertStringNotContainsString('run_generation.the_farm', $encoded);
    $this->assertStringNotContainsString('fixed_graph_v1', $encoded);
    $this->assertStringNotContainsString('start_node_key', $encoded);
    $this->assertStringNotContainsString('run_generation_id', $encoded);
    $this->assertStringNotContainsString('starting_energy', $encoded);
    $this->assertStringNotContainsString('energy_normal_max', $encoded);
    $this->assertStringNotContainsString('energy_regeneration_per_hour', $encoded);
    $this->assertStringNotContainsString('starting_region_id', $encoded);
    $this->assertStringNotContainsString('Mudwrestler', $encoded);
    $this->assertStringNotContainsString('Mud Sling', $encoded);
    $this->assertStringNotContainsString('encounter.the_farm_mud_combat_1', $encoded);
    $this->assertStringNotContainsString('encounter.the_farm_mud_boss_1', $encoded);
    $this->assertStringNotContainsString('Mud Slam', $encoded);
    $this->assertStringNotContainsString('event.farm_loot_completed', $encoded);
  }

  public function testPrivateGenerationChangesAffectTheGlobalRevisionWithoutLeakingTopology(): void
  {
    $canonicalRegistry = ContentRegistry::load($this->canonicalRoot());
    $definitions = $this->canonicalDefinitions();
    $this->mutate(
      $definitions,
      'run_generation.the_farm',
      fn(array &$definition) => $definition['nodes'][0]['position']['row'] = 2,
    );

    $temporaryRoot = sys_get_temp_dir() . '/dice-goblins-run-content-' . bin2hex(random_bytes(6));
    mkdir($temporaryRoot, 0777, true);
    $temporaryFile = $temporaryRoot . '/definitions.json';

    try {
      file_put_contents($temporaryFile, json_encode(['definitions' => $definitions], JSON_THROW_ON_ERROR));
      $changedRegistry = ContentRegistry::load($temporaryRoot);
      $projector = new ClientContentProjector();
      $canonicalProjection = $projector->project($canonicalRegistry);
      $changedProjection = $projector->project($changedRegistry);

      $this->assertNotSame($canonicalProjection['revision'], $changedProjection['revision']);
      $this->assertSame($canonicalProjection['content'], $changedProjection['content']);
    } finally {
      if (is_file($temporaryFile)) unlink($temporaryFile);
      if (is_dir($temporaryRoot)) rmdir($temporaryRoot);
    }
  }

  /** @dataProvider malformedDefinitionProvider */
  public function testMalformedRunDefinitionsAreRejected(callable $mutator, string $message): void
  {
    $definitions = $this->canonicalDefinitions();
    $mutator($definitions);

    $this->expectException(ContentValidationException::class);
    $this->expectExceptionMessage($message);
    (new ContentValidator())->validate([['path' => 'test.json', 'document' => ['definitions' => $definitions]]]);
  }

  public function malformedDefinitionProvider(): array
  {
    return [
      'malformed node type' => [fn(array &$definitions) => $this->mutate($definitions, 'run_node_type.combat', fn(array &$definition) => $definition['description'] = ''), 'description'],
      'unsupported node encounter kind' => [fn(array &$definitions) => $this->mutate($definitions, 'run_node_type.rest', fn(array &$definition) => $definition['encounter_kind'] = 'secret_effect'), "field 'encounter_kind' must be one of"],
      'missing generation reference' => [fn(array &$definitions) => $this->mutate($definitions, 'region.the_farm', fn(array &$definition) => $definition['run_generation_id'] = 'run_generation.missing'), 'references missing run_generation'],
      'wrong generation namespace' => [fn(array &$definitions) => $this->mutate($definitions, 'region.the_farm', fn(array &$definition) => $definition['run_generation_id'] = 'run_node_type.combat'), 'run_generation. namespace'],
      'unsupported algorithm' => [fn(array &$definitions) => $this->mutate($definitions, 'run_generation.the_farm', fn(array &$definition) => $definition['algorithm'] = 'prototype_pattern_v2'), 'algorithm'],
      'missing node type reference' => [fn(array &$definitions) => $this->mutate($definitions, 'run_generation.the_farm', fn(array &$definition) => $definition['nodes'][0]['node_type_id'] = 'run_node_type.missing'), 'references missing run_node_type'],
      'malformed node event reference' => [fn(array &$definitions) => $this->mutate($definitions, 'run_generation.the_farm', fn(array &$definition) => $definition['nodes'][1]['event_id'] = 'reward_definition.farm_loot_completed'), 'event. namespace'],
      'missing node event reference' => [fn(array &$definitions) => $this->mutate($definitions, 'run_generation.the_farm', fn(array &$definition) => $definition['nodes'][1]['event_id'] = 'event.missing'), 'references missing event'],
      'missing Rest encounter' => [fn(array &$definitions) => $this->mutate($definitions, 'run_generation.mountains', function (array &$definition): void { unset($definition['nodes'][3]['encounter_id']); }), 'node requires an encounter_id'],
      'wrong Rest encounter kind' => [fn(array &$definitions) => $this->mutate($definitions, 'run_generation.mountains', fn(array &$definition) => $definition['nodes'][3]['encounter_id'] = 'encounter.mountains_kobold_combat_1'), 'encounter kind does not match'],
      'missing Rest action' => [fn(array &$definitions) => $this->mutate($definitions, 'encounter.the_farm_rest', fn(array &$definition) => $definition['actions'] = []), 'actions must be a non-empty list'],
      'unknown Rest effect' => [fn(array &$definitions) => $this->mutate($definitions, 'encounter.the_farm_rest', fn(array &$definition) => $definition['actions'][0]['effect']['type'] = 'grant_teeth'), "field 'type' must be one of"],
      'cross-region Mountains encounter' => [fn(array &$definitions) => $this->mutate($definitions,
        'encounter.mountains_kobold_combat_2', fn(array &$definition) => $definition['region_id'] = 'region.the_farm'),
        'is not compatible with region'],
      'wrong node type namespace' => [fn(array &$definitions) => $this->mutate($definitions, 'run_generation.the_farm', fn(array &$definition) => $definition['nodes'][0]['node_type_id'] = 'region.the_farm'), 'run_node_type. namespace'],
      'duplicate local key' => [fn(array &$definitions) => $this->mutate($definitions, 'run_generation.the_farm', fn(array &$definition) => $definition['nodes'][1]['key'] = 'combat'), 'duplicate local node key'],
      'invalid start key' => [fn(array &$definitions) => $this->mutate($definitions, 'run_generation.the_farm', fn(array &$definition) => $definition['start_node_key'] = 'missing'), 'start_node_key references missing local node'],
      'invalid endpoint' => [fn(array &$definitions) => $this->mutate($definitions, 'run_generation.the_farm', fn(array &$definition) => $definition['edges'][0]['to'] = 'missing'), 'unknown local node key'],
      'self edge' => [fn(array &$definitions) => $this->mutate($definitions, 'run_generation.the_farm', fn(array &$definition) => $definition['edges'][0] = ['from' => 'combat', 'to' => 'combat']), 'self edge'],
      'duplicate edge' => [fn(array &$definitions) => $this->mutate($definitions, 'run_generation.the_farm', fn(array &$definition) => $definition['edges'][1] = $definition['edges'][0]), 'duplicate edge'],
      'disconnected exit' => [fn(array &$definitions) => $this->mutate($definitions, 'run_generation.the_farm', fn(array &$definition) => array_pop($definition['edges'])), 'disconnected'],
      'missing boss' => [fn(array &$definitions) => $this->mutate($definitions, 'run_generation.the_farm', fn(array &$definition) => $definition['nodes'][3]['node_type_id'] = 'run_node_type.combat'), 'encounter kind does not match'],
      'missing exit' => [fn(array &$definitions) => $this->mutate($definitions, 'run_generation.the_farm', fn(array &$definition) => $definition['nodes'][4]['node_type_id'] = 'run_node_type.loot'), 'exactly one run_node_type.exit'],
      'non-linear Farm path' => [fn(array &$definitions) => $this->mutate($definitions, 'run_generation.the_farm', fn(array &$definition) => $definition['edges'][2] = ['from' => 'rest', 'to' => 'exit']), 'disconnected'],
      'bad coordinate' => [fn(array &$definitions) => $this->mutate($definitions, 'run_generation.the_farm', fn(array &$definition) => $definition['nodes'][0]['position']['column'] = 1.5), 'position.column'],
    ];
  }

  /** @return list<array<string,mixed>> */
  private function canonicalDefinitions(): array
  {
    $registry = ContentRegistry::load($this->canonicalRoot());
    $definitions = [];
    foreach (['gameplay_config', 'region', 'kin', 'unit_type', 'enemy_unit_type', 'encounter', 'ability', 'dice_material', 'dice_aspect', 'dice_profile', 'run_node_type', 'run_generation', 'unlock', 'capability', 'academy_upgrade', 'unit_promotion', 'reconstruction_recipe', 'event', 'reward_definition', 'item', 'shop_offer'] as $type) {
      foreach ($registry->definitionsOfType($type) as $definition) $definitions[] = $definition;
    }
    return $definitions;
  }

  /** @param list<array<string,mixed>> $definitions */
  private function mutate(array &$definitions, string $id, callable $mutator): void
  {
    foreach ($definitions as &$definition) {
      if (($definition['id'] ?? null) !== $id) continue;
      $mutator($definition);
      return;
    }
    $this->fail("Definition {$id} was not found.");
  }

  private function canonicalRoot(): string
  {
    return dirname(__DIR__, 2) . '/content';
  }
}
