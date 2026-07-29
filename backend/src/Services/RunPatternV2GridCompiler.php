<?php
declare(strict_types=1);

namespace DiceGoblins\Services;

use RuntimeException;

final class RunPatternV2GridCompiler
{
  /**
   * @param array<string,mixed> $pattern
   * @return array<string,mixed>
   */
  public function compile(array $pattern): array
  {
    if ((string)($pattern['schema_version'] ?? '') !== 'pattern-v2') {
      throw new RuntimeException('Run pattern V2 compiler requires schema_version pattern-v2.');
    }

    $width = (int)($pattern['width'] ?? 0);
    $height = (int)($pattern['height'] ?? 0);
    if ($width <= 0 || $height <= 0) {
      throw new RuntimeException('Run pattern V2 tiles require positive width and height.');
    }

    $nodes = [];
    $connectors = [];
    $grid = is_array($pattern['grid'] ?? null) ? array_values($pattern['grid']) : [];
    foreach ($grid as $rowIndex => $row) {
      if (!is_array($row)) {
        continue;
      }

      foreach (array_values($row) as $colIndex => $cell) {
        if (!is_array($cell)) {
          continue;
        }

        $type = (string)($cell['type'] ?? '');
        if ($type === 'connector') {
          $connectors[] = ['row' => $rowIndex, 'col' => $colIndex];
          continue;
        }

        $key = (string)($cell['key'] ?? "r{$rowIndex}c{$colIndex}");
        $nodes[] = [
          ...$cell,
          'key' => $key,
          'type' => $type,
          'x' => $colIndex,
          'y' => $rowIndex,
        ];
      }
    }

    $connections = array_values(array_filter(is_array($pattern['connections'] ?? null) ? $pattern['connections'] : [], 'is_array'));

    return [
      'tile_key' => (string)($pattern['slug'] ?? '') . '@' . (int)($pattern['version'] ?? 0),
      'pattern_slug' => (string)($pattern['slug'] ?? ''),
      'pattern_version' => (int)($pattern['version'] ?? 0),
      'tags' => is_array($pattern['tags'] ?? null) ? $pattern['tags'] : [],
      'cost' => (int)($pattern['cost'] ?? count($nodes)),
      'width' => $width,
      'height' => $height,
      'nodes' => $nodes,
      'connectors' => $connectors,
      'connections' => $connections,
      'edges' => $this->edges($pattern, $connections),
      'exits' => array_values(array_filter(is_array($pattern['exits'] ?? null) ? $pattern['exits'] : [], 'is_array')),
    ];
  }

  /**
   * @param array<string,mixed> $pattern
   * @param list<array<string,mixed>> $connections
   * @return list<array<string,mixed>>
   */
  private function edges(array $pattern, array $connections): array
  {
    $edges = array_values(array_filter(is_array($pattern['edges'] ?? null) ? $pattern['edges'] : [], 'is_array'));
    foreach ($connections as $connection) {
      $from = (string)($connection['from'] ?? '');
      $to = (string)($connection['to'] ?? '');
      if ($from === '' || $to === '') {
        continue;
      }

      $edges[] = [
        'from' => $from,
        'to' => $to,
        'through' => array_values(array_filter(is_array($connection['through'] ?? null) ? $connection['through'] : [], 'is_array')),
      ];
    }

    return $edges;
  }
}
