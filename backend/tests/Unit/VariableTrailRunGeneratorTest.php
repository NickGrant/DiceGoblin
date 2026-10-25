<?php
declare(strict_types=1);

namespace DiceGoblins\Tests\Unit;

use DiceGoblins\Content\ContentRegistry;
use DiceGoblins\RunGeneration\GeneratedRunGraphValidator;
use DiceGoblins\RunGeneration\VariableTrailRunGenerator;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class VariableTrailRunGeneratorTest extends TestCase
{
  public function testFarmRemainsItsFiveNodeFixedTutorial(): void
  {
    $content = ContentRegistry::load(dirname(__DIR__, 2) . '/content');
    $graph = (new VariableTrailRunGenerator())->generate($content->runGenerationForRegion('region.the_farm'));
    $this->assertCount(5, $graph['nodes']);
    $this->assertCount(4, $graph['edges']);
  }

  public function testMountainsProducesThreeOrFourDistinctOptionalSplitsAcrossSelections(): void
  {
    $definition = ContentRegistry::load(dirname(__DIR__, 2) . '/content')->runGenerationForRegion('region.mountains');
    $baseCount = count($definition['nodes']);
    $topologies = [];
    foreach ([
      static fn(int $minimum, int $maximum): int => $minimum,
      static fn(int $minimum, int $maximum): int => $maximum,
    ] as $choose) {
      $graph = (new VariableTrailRunGenerator(choose: $choose))->generate($definition);
      (new GeneratedRunGraphValidator())->assertValid($graph);
      $this->assertContains(count($graph['nodes']), [11, 12]);
      $this->assertCount(count($graph['nodes']) - 1, $graph['edges']);
      $this->assertSame('run_node_type.exit', $graph['nodes'][6]['node_type_id']);
      $outgoing = array_count_values(array_column($graph['edges'], 'from_node_index'));
      $splits = array_keys(array_filter($outgoing, static fn(int $count): bool => $count > 1));
      $this->assertContains(count($splits), [3, 4]);
      $this->assertContains(2, $splits); // Existing Rockfall dead end remains.
      foreach (array_slice($graph['nodes'], $baseCount) as $node) {
        $this->assertSame('run_node_type.hazard', $node['node_type_id']);
        $this->assertSame('locked', $node['status']);
        $this->assertNull($node['event_id']);
      }
      $positions = array_map(static fn(array $node): string =>
        $node['generated_metadata']['position']['column'] . ':' . $node['generated_metadata']['position']['row'], $graph['nodes']);
      $this->assertCount(count(array_unique($positions)), $positions);
      $topologies[] = array_column(array_slice($graph['edges'], count($definition['edges'])), 'from_node_index');
    }
    $this->assertNotSame($topologies[0], $topologies[1]);
  }

  public function testOutOfRangeRandomSelectionFailsBeforePersistence(): void
  {
    $definition = ContentRegistry::load(dirname(__DIR__, 2) . '/content')->runGenerationForRegion('region.mountains');
    $this->expectException(InvalidArgumentException::class);
    (new VariableTrailRunGenerator(choose: static fn(int $minimum, int $maximum): int => $maximum + 1))
      ->generate($definition);
  }
}
