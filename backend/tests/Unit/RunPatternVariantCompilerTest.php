<?php
declare(strict_types=1);

namespace DiceGoblins\Tests\Unit;

use DiceGoblins\Services\RunPatternVariantCompiler;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class RunPatternVariantCompilerTest extends TestCase
{
  public function testCompilesIdentityAndMirrorYVariantsWithNormalizedFootprints(): void
  {
    $pattern = $this->patternBySlug('shared_shrine_combat_loot_branch');

    $variants = (new RunPatternVariantCompiler())->compile($pattern);

    $this->assertSame(
      ['shared_shrine_combat_loot_branch@1:identity', 'shared_shrine_combat_loot_branch@1:mirror_y'],
      array_map(static fn(array $variant): string => (string)$variant['variant_key'], $variants)
    );
    $this->assertSame(['width' => 3, 'height' => 2, 'min_x' => 0, 'max_x' => 2, 'min_y' => 0, 'max_y' => 1], $variants[0]['footprint']);
    $this->assertSame(['width' => 3, 'height' => 2, 'min_x' => 0, 'max_x' => 2, 'min_y' => 0, 'max_y' => 1], $variants[1]['footprint']);

    $identityLoot = $this->nodeByKey($variants[0], 'loot');
    $mirroredLoot = $this->nodeByKey($variants[1], 'loot');
    $this->assertSame(0, $identityLoot['y']);
    $this->assertSame(1, $mirroredLoot['y']);
  }

  public function testMirrorYSwapsVerticalSocketDirections(): void
  {
    $pattern = [
      'slug' => 'vertical_socket',
      'version' => 1,
      'allowed_transforms' => ['mirror_y'],
      'node_cost' => 1,
      'nodes' => [
        ['key' => 'n', 'type' => 'combat', 'x' => 0, 'y' => 0],
      ],
      'edges' => [],
      'sockets' => [
        ['id' => 'entry_up', 'kind' => 'entry', 'node' => 'n', 'direction' => 'up', 'path_eligibility' => ['branch']],
        ['id' => 'exit_down', 'kind' => 'exit', 'node' => 'n', 'direction' => 'down', 'path_eligibility' => ['branch']],
      ],
    ];

    $variant = (new RunPatternVariantCompiler())->compile($pattern)[0];

    $this->assertSame('down', $variant['sockets'][0]['direction']);
    $this->assertSame('up', $variant['sockets'][1]['direction']);
  }

  public function testRejectsUnsupportedTransforms(): void
  {
    $this->expectException(RuntimeException::class);
    $this->expectExceptionMessage('Unsupported pattern transform rotate_90.');

    (new RunPatternVariantCompiler())->compile([
      'slug' => 'bad',
      'version' => 1,
      'allowed_transforms' => ['rotate_90'],
      'nodes' => [],
      'edges' => [],
      'sockets' => [],
    ]);
  }

  /**
   * @return array<string,mixed>
   */
  private function patternBySlug(string $slug): array
  {
    $raw = file_get_contents(dirname(__DIR__, 2) . '/data/run-patterns/shared/patterns.json');
    $this->assertIsString($raw);
    $data = json_decode($raw, true);
    $this->assertIsArray($data);

    foreach ($data['patterns'] as $pattern) {
      if (is_array($pattern) && (string)($pattern['slug'] ?? '') === $slug) {
        return $pattern;
      }
    }

    $this->fail("Pattern {$slug} was not found.");
  }

  /**
   * @param array<string,mixed> $variant
   * @return array<string,mixed>
   */
  private function nodeByKey(array $variant, string $key): array
  {
    foreach ($variant['nodes'] as $node) {
      if (is_array($node) && (string)($node['key'] ?? '') === $key) {
        return $node;
      }
    }

    $this->fail("Node {$key} was not found.");
  }
}
