<?php
declare(strict_types=1);

namespace DiceGoblins\Tests\Unit;

use DiceGoblins\Content\ContentRegistry;
use DiceGoblins\RunGeneration\FixedGraphRunGenerator;
use DiceGoblins\RunGeneration\GeneratedRunGraphValidator;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class FixedGraphRunGeneratorTest extends TestCase
{
  public function testGeneratesDeterministicFixedGraphFromAuthoredNodesWithoutDatabaseIdentity(): void
  {
    $registry = ContentRegistry::load(dirname(__DIR__, 2) . '/content');
    $definition = $registry->runGenerationForRegion('region.the_farm');
    $generator = new FixedGraphRunGenerator();

    $first = $generator->generate($definition);
    $second = $generator->generate($definition);

    $this->assertSame($first, $second);
    $indexByKey = array_flip(array_column($definition['nodes'], 'key'));
    foreach ($definition['nodes'] as $index => $authored) {
      $this->assertSame([
        'node_index' => $index, 'node_type_id' => $authored['node_type_id'],
        'encounter_id' => $authored['encounter_id'] ?? null, 'event_id' => $authored['event_id'] ?? null,
        'status' => $authored['key'] === $definition['start_node_key'] ? 'available' : 'locked',
        'generated_metadata' => ['position' => $authored['position']],
      ], $first['nodes'][$index]);
    }
    $this->assertSame(array_map(static fn(array $edge): array => [
      'from_node_index' => $indexByKey[$edge['from']], 'to_node_index' => $indexByKey[$edge['to']],
      'generated_metadata' => null,
    ], $definition['edges']), $first['edges']);
    $encoded = json_encode($first, JSON_THROW_ON_ERROR);
    $this->assertStringNotContainsString('run_id', $encoded);
    $this->assertStringNotContainsString('node_id', $encoded);
    $this->assertStringNotContainsString('encounter_template_id', $encoded);
  }

  public function testGeneratesExactDeterministicMountainsGraph(): void
  {
    $definition = ContentRegistry::load(dirname(__DIR__, 2) . '/content')
      ->runGenerationForRegion('region.mountains');
    $graph = (new FixedGraphRunGenerator())->generate($definition);

    $this->assertSame([0, 1, 2, 3, 4, 5, 6, 7, 8], array_column($graph['nodes'], 'node_index'));
    $this->assertSame([
      'run_node_type.combat', 'run_node_type.loot', 'run_node_type.combat', 'run_node_type.rest',
      'run_node_type.combat', 'run_node_type.boss', 'run_node_type.exit',
      'run_node_type.hazard', 'run_node_type.hazard',
    ], array_column($graph['nodes'], 'node_type_id'));
    $this->assertSame([
      'encounter.mountains_kobold_combat_1', null, 'encounter.mountains_kobold_combat_2',
      'encounter.mountains_rest', 'encounter.mountains_kobold_combat_3',
      'encounter.mountains_kobold_boss_1', null, 'encounter.mountains_loose_scree',
      'encounter.mountains_rockfall',
    ], array_column($graph['nodes'], 'encounter_id'));
    $this->assertSame([null, 'event.mountains_loot_completed', null, null, null,
      'event.mountains_boss_completed', null, null, null], array_column($graph['nodes'], 'event_id'));
    $this->assertSame(['available', 'locked', 'locked', 'locked', 'locked', 'locked', 'locked',
      'locked', 'locked'],
      array_column($graph['nodes'], 'status'));
    $this->assertSame([0, 1, 2, 3, 7, 2, 4, 5], array_column($graph['edges'], 'from_node_index'));
    $this->assertSame([1, 2, 3, 7, 4, 8, 5, 6], array_column($graph['edges'], 'to_node_index'));
  }

  /** @dataProvider invalidGraphProvider */
  public function testGeneratedGraphValidatorRejectsMalformedOutput(array $graph, string $message): void
  {
    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage($message);
    (new GeneratedRunGraphValidator())->assertValid($graph);
  }

  public function invalidGraphProvider(): array
  {
    $valid = $this->minimalGraph();
    return [
      'empty' => [['nodes' => [], 'edges' => []], 'must contain nodes'],
      'non-sequential index' => [$this->with($valid, fn(array &$graph) => $graph['nodes'][1]['node_index'] = 2), 'unique, sequential'],
      'invalid endpoint' => [$this->with($valid, fn(array &$graph) => $graph['edges'][0]['to_node_index'] = 9), 'invalid endpoint'],
      'self edge' => [$this->with($valid, fn(array &$graph) => $graph['edges'][0]['to_node_index'] = 0), 'self edge'],
      'duplicate edge' => [$this->with($valid, fn(array &$graph) => $graph['edges'][] = $graph['edges'][0]), 'duplicate edge'],
      'bad availability' => [$this->with($valid, fn(array &$graph) => $graph['nodes'][1]['status'] = 'available'), 'incoherent initial availability'],
      'bad event identity' => [$this->with($valid, fn(array &$graph) => $graph['nodes'][1]['event_id'] = 'reward_definition.nope'), 'event identity'],
      'unreachable exit' => [$this->with($valid, fn(array &$graph) => $graph['edges'] = []), 'exit is unreachable'],
      'disconnected required node' => [$this->with($valid, function(array &$graph): void {
        $graph['nodes'][] = ['node_index' => 2, 'node_type_id' => 'run_node_type.loot', 'encounter_id' => null, 'status' => 'locked', 'generated_metadata' => []];
      }), 'disconnected'],
    ];
  }

  /** @return array{nodes:list<array<string,mixed>>,edges:list<array<string,mixed>>} */
  private function minimalGraph(): array
  {
    return [
      'nodes' => [
        ['node_index' => 0, 'node_type_id' => 'run_node_type.combat', 'encounter_id' => null, 'status' => 'available', 'generated_metadata' => []],
        ['node_index' => 1, 'node_type_id' => 'run_node_type.exit', 'encounter_id' => null, 'status' => 'locked', 'generated_metadata' => []],
      ],
      'edges' => [['from_node_index' => 0, 'to_node_index' => 1, 'generated_metadata' => null]],
    ];
  }

  /** @param array<string,mixed> $graph */
  private function with(array $graph, callable $mutator): array
  {
    $mutator($graph);
    return $graph;
  }
}
