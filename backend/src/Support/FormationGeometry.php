<?php
declare(strict_types=1);

namespace DiceGoblins\Support;

use RuntimeException;

final class FormationGeometry
{
  /** @var list<string> */
  public const CELLS = ['A1', 'A2', 'A3', 'B1', 'B2', 'B3', 'C1', 'C2', 'C3'];

  /**
   * @param array<string,mixed> $stats
   * @return array{w:int,h:int}
   */
  public static function footprintFromStats(array $stats): array
  {
    $formation = $stats['formation'] ?? null;
    $width = is_array($formation) ? ($formation['w'] ?? null) : ($stats['formation_width'] ?? null);
    $height = is_array($formation) ? ($formation['h'] ?? null) : ($stats['formation_height'] ?? null);

    return [
      'w' => self::normalizeSpan($width),
      'h' => self::normalizeSpan($height),
    ];
  }

  public static function isValidCell(string $cell): bool
  {
    return preg_match('/^[ABC][123]$/', strtoupper(trim($cell))) === 1;
  }

  /**
   * @return array{row:int,col:int}|null
   */
  public static function cellToGrid(string $cell): ?array
  {
    $value = strtoupper(trim($cell));
    if (!self::isValidCell($value)) {
      return null;
    }

    return [
      'row' => ord($value[0]) - ord('A'),
      'col' => ((int)$value[1]) - 1,
    ];
  }

  public static function gridToCell(int $row, int $col): ?string
  {
    if ($row < 0 || $row > 2 || $col < 0 || $col > 2) {
      return null;
    }

    return sprintf('%s%d', chr(ord('A') + $row), $col + 1);
  }

  /**
   * @param array{w:int,h:int} $footprint
   * @return list<string>
   */
  public static function footprintCells(string $anchorCell, array $footprint): array
  {
    $anchor = self::cellToGrid($anchorCell);
    if ($anchor === null) {
      throw new RuntimeException('Invalid formation cell.');
    }

    $width = self::normalizeSpan($footprint['w'] ?? 1);
    $height = self::normalizeSpan($footprint['h'] ?? 1);
    $cells = [];

    for ($rowOffset = 0; $rowOffset < $height; $rowOffset += 1) {
      for ($colOffset = 0; $colOffset < $width; $colOffset += 1) {
        $cell = self::gridToCell($anchor['row'] + $rowOffset, $anchor['col'] + $colOffset);
        if ($cell === null) {
          throw new RuntimeException('Formation footprint exceeds squad bounds.');
        }
        $cells[] = $cell;
      }
    }

    sort($cells);
    return $cells;
  }

  /**
   * @param list<string> $cells
   * @return string|null
   */
  public static function anchorCellForCells(array $cells): ?string
  {
    $normalized = [];
    foreach ($cells as $cell) {
      $grid = self::cellToGrid($cell);
      if ($grid === null) {
        continue;
      }
      $normalized[] = [
        'cell' => strtoupper(trim($cell)),
        'row' => $grid['row'],
        'col' => $grid['col'],
      ];
    }

    if (count($normalized) === 0) {
      return null;
    }

    usort($normalized, static function (array $a, array $b): int {
      $rowCmp = $a['row'] <=> $b['row'];
      if ($rowCmp !== 0) {
        return $rowCmp;
      }
      return $a['col'] <=> $b['col'];
    });

    return (string)$normalized[0]['cell'];
  }

  private static function normalizeSpan(mixed $value): int
  {
    $span = is_numeric($value) ? (int)$value : 1;
    return max(1, min(3, $span));
  }
}
