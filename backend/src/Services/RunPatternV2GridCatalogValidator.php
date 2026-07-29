<?php
declare(strict_types=1);

namespace DiceGoblins\Services;

final class RunPatternV2GridCatalogValidator
{
  private const VALID_NODE_TYPES = ['combat', 'loot', 'rest', 'boss', 'exit', 'dialogue', 'hazard', 'shrine', 'chaos'];
  private const VALID_DIRECTIONS = ['left', 'right', 'up', 'down'];

  /**
   * @param list<array<string,mixed>> $definitions
   * @return array{valid:bool,errors:list<string>,pattern_count:int}
   */
  public function validateDefinitions(array $definitions): array
  {
    $errors = [];
    $count = 0;
    foreach ($definitions as $definition) {
      $pattern = is_array($definition['definition'] ?? null) ? $definition['definition'] : $definition;
      if ((string)($pattern['schema_version'] ?? '') !== 'pattern-v2') {
        continue;
      }

      $count++;
      $this->validatePattern($pattern, $errors);
    }

    return [
      'valid' => $errors === [],
      'errors' => $errors,
      'pattern_count' => $count,
    ];
  }

  /**
   * @param array<string,mixed> $pattern
   * @param list<string> $errors
   */
  private function validatePattern(array $pattern, array &$errors): void
  {
    $key = $this->patternKey($pattern);
    $width = (int)($pattern['width'] ?? 0);
    $height = (int)($pattern['height'] ?? 0);
    if ($width <= 0 || $height <= 0) {
      $errors[] = "{$key} must define positive width and height.";
      return;
    }

    if ((int)($pattern['cost'] ?? 0) <= 0) {
      $errors[] = "{$key} must define a positive cost.";
    }

    $grid = is_array($pattern['grid'] ?? null) ? array_values($pattern['grid']) : [];
    if (count($grid) !== $height) {
      $errors[] = "{$key} grid height must match height {$height}.";
    }

    $nodeKeys = [];
    $nodeCells = [];
    $connectorCells = [];
    foreach ($grid as $rowIndex => $row) {
      if (!is_array($row)) {
        $errors[] = "{$key} grid row {$rowIndex} must be an array.";
        continue;
      }
      if (count($row) !== $width) {
        $errors[] = "{$key} grid row {$rowIndex} width must match width {$width}.";
      }

      foreach (array_values($row) as $colIndex => $cell) {
        if ($cell === null) {
          continue;
        }
        if (!is_array($cell)) {
          $errors[] = "{$key} grid cell {$rowIndex}:{$colIndex} must be null or an object.";
          continue;
        }

        $type = (string)($cell['type'] ?? '');
        if ($type === 'connector') {
          if (isset($cell['key'])) {
            $errors[] = "{$key} connector cell {$rowIndex}:{$colIndex} must not define a runtime node key.";
          }
          $connectorCells[$this->cellKey($rowIndex, $colIndex)] = true;
          continue;
        }

        if (!in_array($type, self::VALID_NODE_TYPES, true)) {
          $errors[] = "{$key} node at {$rowIndex}:{$colIndex} has invalid node type {$type}.";
        }

        $nodeKey = (string)($cell['key'] ?? '');
        if ($nodeKey === '' || isset($nodeKeys[$nodeKey])) {
          $errors[] = "{$key} has missing or duplicate node key {$nodeKey}.";
        }
        $nodeKeys[$nodeKey] = true;
        $nodeCells[$this->cellKey($rowIndex, $colIndex)] = $nodeKey;
      }
    }

    if ($nodeKeys === []) {
      $errors[] = "{$key} must define at least one runtime node cell.";
    }

    $this->validateConnections($pattern, $nodeKeys, $connectorCells, $width, $height, $errors);
    $this->validateExits($pattern, $nodeCells, $connectorCells, $width, $height, $errors);
  }

  /**
   * @param array<string,mixed> $pattern
   * @param array<string,true> $nodeKeys
   * @param array<string,true> $connectorCells
   * @param list<string> $errors
   */
  private function validateConnections(array $pattern, array $nodeKeys, array $connectorCells, int $width, int $height, array &$errors): void
  {
    $key = $this->patternKey($pattern);
    foreach (array_values(array_filter(is_array($pattern['connections'] ?? null) ? $pattern['connections'] : [], 'is_array')) as $index => $connection) {
      $from = (string)($connection['from'] ?? '');
      $to = (string)($connection['to'] ?? '');
      if (!isset($nodeKeys[$from]) || !isset($nodeKeys[$to])) {
        $errors[] = "{$key} connection {$index} references unknown endpoint {$from}->{$to}.";
      }
      if ($from !== '' && $from === $to) {
        $errors[] = "{$key} connection {$index} may not connect {$from} to itself.";
      }

      foreach (array_values(array_filter(is_array($connection['through'] ?? null) ? $connection['through'] : [], 'is_array')) as $through) {
        $row = (int)($through['row'] ?? -1);
        $col = (int)($through['col'] ?? -1);
        if (!$this->isInBounds($row, $col, $width, $height)) {
          $errors[] = "{$key} connection {$index} has out-of-bounds connector {$row}:{$col}.";
          continue;
        }
        if (!isset($connectorCells[$this->cellKey($row, $col)])) {
          $errors[] = "{$key} connection {$index} through {$row}:{$col} must reference a connector cell.";
        }
      }
    }
  }

  /**
   * @param array<string,mixed> $pattern
   * @param array<string,string> $nodeCells
   * @param array<string,true> $connectorCells
   * @param list<string> $errors
   */
  private function validateExits(array $pattern, array $nodeCells, array $connectorCells, int $width, int $height, array &$errors): void
  {
    $key = $this->patternKey($pattern);
    $exits = array_values(array_filter(is_array($pattern['exits'] ?? null) ? $pattern['exits'] : [], 'is_array'));
    if ($exits === []) {
      $errors[] = "{$key} must define at least one perimeter exit.";
      return;
    }

    foreach ($exits as $index => $exit) {
      $row = (int)($exit['row'] ?? -1);
      $col = (int)($exit['col'] ?? -1);
      if (!$this->isInBounds($row, $col, $width, $height)) {
        $errors[] = "{$key} exit {$index} is out of bounds at {$row}:{$col}.";
        continue;
      }
      if ($row !== 0 && $row !== ($height - 1) && $col !== 0 && $col !== ($width - 1)) {
        $errors[] = "{$key} exit {$index} must be on the tile perimeter.";
      }

      $cellKey = $this->cellKey($row, $col);
      if (!isset($nodeCells[$cellKey]) && !isset($connectorCells[$cellKey])) {
        $errors[] = "{$key} exit {$index} must reference a node or connector cell.";
      }

      $direction = (string)($exit['direction'] ?? '');
      if (!in_array($direction, self::VALID_DIRECTIONS, true)) {
        $errors[] = "{$key} exit {$index} has invalid direction {$direction}.";
      }
    }
  }

  private function patternKey(array $pattern): string
  {
    return (string)($pattern['slug'] ?? 'unknown') . '@' . (int)($pattern['version'] ?? 0);
  }

  private function cellKey(int $row, int $col): string
  {
    return "{$row}:{$col}";
  }

  private function isInBounds(int $row, int $col, int $width, int $height): bool
  {
    return $row >= 0 && $row < $height && $col >= 0 && $col < $width;
  }
}
