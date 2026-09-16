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
  public function testGeneratesExactDeterministicFarmGraphWithoutDatabaseIdentity(): void
  {
    $registry = ContentRegistry::load(dirname(__DIR__, 2) . '/content');
    $definition = $registry->runGenerationForRegion('region.the_farm');
    $generator = new FixedGraphRunGenerator();

    $first = $generator->generate($definition);
    $second = $generator->generate($definition);

    $this->assertSame($first, $second);
    $this->assertSame([
      ['node_index' => 0, 'node_type_id' => 'run_node_type.combat', 'encounter_id' => 'encounter.the_farm_mud_combat_1', 'status' => 'available', 'generated_metadata' => ['position' => ['column' => 0, 'row' => 1]]],
      ['node_index' => 1, 'node_type_id' => 'run_node_type.loot', 'encounter_id' => null, 'status' => 'locked', 'generated_metadata' => ['position' => ['column' => 1, 'row' => 1]]],
      ['node_index' => 2, 'node_type_id' => 'run_node_type.rest', 'encounter_id' => null, 'status' => 'locked', 'generated_metadata' => ['position' => ['column' => 2, 'row' => 1]]],
      ['node_index' => 3, 'node_type_id' => 'run_node_type.boss', 'encounter_id' => null, 'status' => 'locked', 'generated_metadata' => ['position' => ['column' => 3, 'row' => 1]]],
      ['node_index' => 4, 'node_type_id' => 'run_node_type.exit', 'encounter_id' => null, 'status' => 'locked', 'generated_metadata' => ['position' => ['column' => 4, 'row' => 1]]],
    ], $first['nodes']);
    $this->assertSame([
      ['from_node_index' => 0, 'to_node_index' => 1, 'generated_metadata' => null],
      ['from_node_index' => 1, 'to_node_index' => 2, 'generated_metadata' => null],
      ['from_node_index' => 2, 'to_node_index' => 3, 'generated_metadata' => null],
      ['from_node_index' => 3, 'to_node_index' => 4, 'generated_metadata' => null],
    ], $first['edges']);
    $encoded = json_encode($first, JSON_THROW_ON_ERROR);
    $this->assertStringNotContainsString('run_id', $encoded);
    $this->assertStringNotContainsString('node_id', $encoded);
    $this->assertStringNotContainsString('encounter_template_id', $encoded);
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
