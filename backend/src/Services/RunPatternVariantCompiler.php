<?php
declare(strict_types=1);

namespace DiceGoblins\Services;

use RuntimeException;

final class RunPatternVariantCompiler
{
  /**
   * @param array<string,mixed> $pattern
   * @return list<array<string,mixed>>
   */
  public function compile(array $pattern): array
  {
    $transforms = is_array($pattern['allowed_transforms'] ?? null) ? $pattern['allowed_transforms'] : [];
    $variants = [];
    foreach ($transforms as $transform) {
      $variants[] = $this->compileTransform($pattern, (string)$transform);
    }

    return $variants;
  }

  /**
   * @param array<string,mixed> $pattern
   * @return array<string,mixed>
   */
  private function compileTransform(array $pattern, string $transform): array
  {
    if ($transform !== 'identity' && $transform !== 'mirror_y') {
      throw new RuntimeException("Unsupported pattern transform {$transform}.");
    }

    $nodes = [];
    foreach (array_values(array_filter(is_array($pattern['nodes'] ?? null) ? $pattern['nodes'] : [], 'is_array')) as $node) {
      $x = (int)($node['x'] ?? 0);
      $y = (int)($node['y'] ?? 0);
      if ($transform === 'mirror_y') {
        $y *= -1;
      }

      $nodes[] = [
        ...$node,
        'x' => $x,
        'y' => $y,
      ];
    }

    $nodes = $this->normalizeNodes($nodes);
    $sockets = [];
    foreach (array_values(array_filter(is_array($pattern['sockets'] ?? null) ? $pattern['sockets'] : [], 'is_array')) as $socket) {
      $sockets[] = [
        ...$socket,
        'direction' => $this->transformDirection((string)($socket['direction'] ?? ''), $transform),
      ];
    }

    $slug = (string)($pattern['slug'] ?? '');
    $version = (int)($pattern['version'] ?? 0);
    return [
      'variant_key' => "{$slug}@{$version}:{$transform}",
      'pattern_slug' => $slug,
      'pattern_version' => $version,
      'transform' => $transform,
      'tags' => is_array($pattern['tags'] ?? null) ? $pattern['tags'] : [],
      'node_cost' => (int)($pattern['node_cost'] ?? count($nodes)),
      'nodes' => $nodes,
      'edges' => array_values(array_filter(is_array($pattern['edges'] ?? null) ? $pattern['edges'] : [], 'is_array')),
      'sockets' => $sockets,
      'footprint' => $this->footprint($nodes),
    ];
  }

  /**
   * @param list<array<string,mixed>> $nodes
   * @return list<array<string,mixed>>
   */
  private function normalizeNodes(array $nodes): array
  {
    if ($nodes === []) {
      return [];
    }

    $minX = min(array_map(static fn(array $node): int => (int)$node['x'], $nodes));
    $minY = min(array_map(static fn(array $node): int => (int)$node['y'], $nodes));

    return array_map(
      static fn(array $node): array => [
        ...$node,
        'x' => (int)$node['x'] - $minX,
        'y' => (int)$node['y'] - $minY,
      ],
      $nodes
    );
  }

  private function transformDirection(string $direction, string $transform): string
  {
    if ($transform !== 'mirror_y') {
      return $direction;
    }

    return match ($direction) {
      'up' => 'down',
      'down' => 'up',
      default => $direction,
    };
  }

  /**
   * @param list<array<string,mixed>> $nodes
   * @return array{width:int,height:int,min_x:int,max_x:int,min_y:int,max_y:int}
   */
  private function footprint(array $nodes): array
  {
    if ($nodes === []) {
      return ['width' => 0, 'height' => 0, 'min_x' => 0, 'max_x' => 0, 'min_y' => 0, 'max_y' => 0];
    }

    $xs = array_map(static fn(array $node): int => (int)$node['x'], $nodes);
    $ys = array_map(static fn(array $node): int => (int)$node['y'], $nodes);
    $minX = min($xs);
    $maxX = max($xs);
    $minY = min($ys);
    $maxY = max($ys);

    return [
      'width' => ($maxX - $minX) + 1,
      'height' => ($maxY - $minY) + 1,
      'min_x' => $minX,
      'max_x' => $maxX,
      'min_y' => $minY,
      'max_y' => $maxY,
    ];
  }
}
