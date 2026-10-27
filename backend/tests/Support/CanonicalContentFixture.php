<?php
declare(strict_types=1);

namespace DiceGoblins\Tests\Support;

use FilesystemIterator;
use JsonException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

/** Reads complete authored definitions for tests that mutate or extend the production catalog. */
final class CanonicalContentFixture
{
  /** @return list<array<string,mixed>> */
  public static function definitions(): array
  {
    $root = dirname(__DIR__, 2) . '/content';
    $paths = [];
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)) as $file) {
      if ($file->isFile() && strtolower($file->getExtension()) === 'json') $paths[] = $file->getPathname();
    }
    sort($paths, SORT_STRING);
    $definitions = [];
    foreach ($paths as $path) {
      $json = file_get_contents($path);
      if ($json === false) throw new RuntimeException("Cannot read canonical content '{$path}'.");
      try { $document = json_decode($json, true, 512, JSON_THROW_ON_ERROR); }
      catch (JsonException $e) { throw new RuntimeException("Cannot decode canonical content '{$path}'.", 0, $e); }
      if (!is_array($document) || !is_array($document['definitions'] ?? null)
        || !array_is_list($document['definitions'])) {
        throw new RuntimeException("Canonical content '{$path}' has no definitions list.");
      }
      foreach ($document['definitions'] as $definition) $definitions[] = $definition;
    }
    return $definitions;
  }
}
