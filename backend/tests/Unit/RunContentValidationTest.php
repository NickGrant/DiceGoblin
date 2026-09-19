<?php
declare(strict_types=1);

namespace DiceGoblins\Tests\Unit;

use DiceGoblins\Content\ClientContentProjector;
use DiceGoblins\Content\ContentRegistry;
use DiceGoblins\Content\ContentValidationException;
use DiceGoblins\Content\ContentValidator;
use PHPUnit\Framework\TestCase;

final class RunContentValidationTest extends TestCase
{
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
    $this->assertCount(5, $registry->definitionsOfType('run_node_type'));
    $this->assertSame('encounter.the_farm_mud_combat_1', $generation['nodes'][0]['encounter_id']);
    $this->assertSame('event.farm_loot_completed', $generation['nodes'][1]['event_id']);
    $this->assertSame('encounter.the_farm_mud_boss_1', $generation['nodes'][3]['encounter_id']);
    $this->assertSame('event.farm_boss_completed', $generation['nodes'][3]['event_id']);
    $this->assertSame('Combat', $registry->runNodeType('run_node_type.combat')['display_name']);
  }

  public function testProjectionExposesOnlySafeNodeTypePresentation(): void
  {
    $registry = ContentRegistry::load($this->canonicalRoot());
    $projection = (new ClientContentProjector())->project($registry);
    $encoded = json_encode($projection, JSON_THROW_ON_ERROR);

    $this->assertSame(['run_energy_cost' => 10], $projection['content']['gameplay']);
    $this->assertSame([
      'id' => 'run_node_type.combat',
      'display_name' => 'Combat',
      'description' => 'Fight enemies guarding the path.',
      'icon_key' => 'icon_encounter_combat',
    ], $projection['content']['run_node_types']['run_node_type.combat']);
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
    $this->assertStringNotContainsString('Mudking', $encoded);
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
      'missing generation reference' => [fn(array &$definitions) => $this->mutate($definitions, 'region.the_farm', fn(array &$definition) => $definition['run_generation_id'] = 'run_generation.missing'), 'references missing run_generation'],
      'wrong generation namespace' => [fn(array &$definitions) => $this->mutate($definitions, 'region.the_farm', fn(array &$definition) => $definition['run_generation_id'] = 'run_node_type.combat'), 'run_generation. namespace'],
      'unsupported algorithm' => [fn(array &$definitions) => $this->mutate($definitions, 'run_generation.the_farm', fn(array &$definition) => $definition['algorithm'] = 'prototype_pattern_v2'), 'algorithm'],
      'missing node type reference' => [fn(array &$definitions) => $this->mutate($definitions, 'run_generation.the_farm', fn(array &$definition) => $definition['nodes'][0]['node_type_id'] = 'run_node_type.missing'), 'references missing run_node_type'],
      'malformed node event reference' => [fn(array &$definitions) => $this->mutate($definitions, 'run_generation.the_farm', fn(array &$definition) => $definition['nodes'][1]['event_id'] = 'reward_definition.farm_loot_completed'), 'event. namespace'],
      'missing node event reference' => [fn(array &$definitions) => $this->mutate($definitions, 'run_generation.the_farm', fn(array &$definition) => $definition['nodes'][1]['event_id'] = 'event.missing'), 'references missing event'],
      'wrong node type namespace' => [fn(array &$definitions) => $this->mutate($definitions, 'run_generation.the_farm', fn(array &$definition) => $definition['nodes'][0]['node_type_id'] = 'region.the_farm'), 'run_node_type. namespace'],
      'duplicate local key' => [fn(array &$definitions) => $this->mutate($definitions, 'run_generation.the_farm', fn(array &$definition) => $definition['nodes'][1]['key'] = 'combat'), 'duplicate local node key'],
      'invalid start key' => [fn(array &$definitions) => $this->mutate($definitions, 'run_generation.the_farm', fn(array &$definition) => $definition['start_node_key'] = 'missing'), 'start_node_key references missing local node'],
      'invalid endpoint' => [fn(array &$definitions) => $this->mutate($definitions, 'run_generation.the_farm', fn(array &$definition) => $definition['edges'][0]['to'] = 'missing'), 'unknown local node key'],
      'self edge' => [fn(array &$definitions) => $this->mutate($definitions, 'run_generation.the_farm', fn(array &$definition) => $definition['edges'][0] = ['from' => 'combat', 'to' => 'combat']), 'self edge'],
      'duplicate edge' => [fn(array &$definitions) => $this->mutate($definitions, 'run_generation.the_farm', fn(array &$definition) => $definition['edges'][1] = $definition['edges'][0]), 'duplicate edge'],
      'disconnected exit' => [fn(array &$definitions) => $this->mutate($definitions, 'run_generation.the_farm', fn(array &$definition) => array_pop($definition['edges'])), 'disconnected'],
      'missing boss' => [fn(array &$definitions) => $this->mutate($definitions, 'run_generation.the_farm', fn(array &$definition) => $definition['nodes'][3]['node_type_id'] = 'run_node_type.combat'), 'ordered combat, loot, rest, boss, exit'],
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
    foreach (['gameplay_config', 'region', 'kin', 'unit_type', 'enemy_unit_type', 'encounter', 'ability', 'dice_material', 'dice_aspect', 'dice_profile', 'run_node_type', 'run_generation', 'unlock', 'event', 'reward_definition'] as $type) {
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
