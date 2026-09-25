<?php
declare(strict_types=1);

namespace DiceGoblins\Tests\Unit;

use DiceGoblins\Application\RegionAvailabilityPolicy;
use DiceGoblins\Content\ContentRegistry;
use JsonException;
use PHPUnit\Framework\TestCase;

final class RegionAvailabilityPolicyTest extends TestCase
{
  public function testDerivesPlayableRegionsFromAuthoredTargetsRatherThanUnlockNames(): void
  {
    $policy = new RegionAvailabilityPolicy($this->content());

    $this->assertSame(['region.start'], $policy->availableRegionIds([]));
    $this->assertSame(
      ['region.start', 'region.alpha', 'region.second'],
      $policy->availableRegionIds(['unlock.path.second', 'unlock.odd_alpha_name', 'unlock.region.unknown']),
    );
    $this->assertFalse($policy->isPlayable('region.unplayable'));
    $this->assertFalse($policy->isPlayable('region.unknown'));
  }

  private function content(): ContentRegistry
  {
    $root = sys_get_temp_dir() . '/dice-goblins-region-policy-' . bin2hex(random_bytes(6));
    mkdir($root, 0777, true);
    $definitions = [
      ['id' => 'config.gameplay', 'type' => 'gameplay_config', 'starting_energy' => 50, 'energy_normal_max' => 50,
        'energy_regeneration_per_hour' => 12, 'run_energy_cost' => 10, 'starting_region_id' => 'region.start'],
      ['id' => 'region.start', 'type' => 'region', 'display_name' => 'Start', 'art_key' => 'start',
        'run_generation_id' => 'run_generation.shared'],
      ['id' => 'region.second', 'type' => 'region', 'display_name' => 'Second', 'art_key' => 'second',
        'run_generation_id' => 'run_generation.shared'],
      ['id' => 'region.alpha', 'type' => 'region', 'display_name' => 'Alpha', 'art_key' => 'alpha',
        'run_generation_id' => 'run_generation.shared'],
      ['id' => 'region.unplayable', 'type' => 'region', 'display_name' => 'Unplayable', 'art_key' => 'none'],
      ['id' => 'unlock.path.second', 'type' => 'unlock', 'target_type' => 'region', 'target_id' => 'region.second'],
      ['id' => 'unlock.odd_alpha_name', 'type' => 'unlock', 'target_type' => 'region', 'target_id' => 'region.alpha'],
      ['id' => 'run_node_type.combat', 'type' => 'run_node_type', 'display_name' => 'Combat',
        'description' => 'Fight.', 'icon_key' => 'combat'],
      ['id' => 'run_node_type.exit', 'type' => 'run_node_type', 'display_name' => 'Exit',
        'description' => 'Leave.', 'icon_key' => 'exit'],
      ['id' => 'run_generation.shared', 'type' => 'run_generation', 'algorithm' => 'fixed_graph_v1',
        'start_node_key' => 'start', 'nodes' => [
          ['key' => 'start', 'node_type_id' => 'run_node_type.combat', 'position' => ['column' => 0, 'row' => 1]],
          ['key' => 'exit', 'node_type_id' => 'run_node_type.exit', 'position' => ['column' => 1, 'row' => 1]],
        ], 'edges' => [['from' => 'start', 'to' => 'exit']]],
    ];
    try {
      file_put_contents($root . '/content.json', json_encode(['definitions' => $definitions], JSON_THROW_ON_ERROR));
      return ContentRegistry::load($root);
    } catch (JsonException $e) {
      throw new \RuntimeException('Test content could not be encoded.', 0, $e);
    } finally {
      if (is_file($root . '/content.json')) unlink($root . '/content.json');
      if (is_dir($root)) rmdir($root);
    }
  }
}
