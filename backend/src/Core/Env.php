<?php
declare(strict_types=1);

/**
 * File: C:\xampp\htdocs\dice-goblin\backend\src\Core\Env.php
 * Purpose: Project PHP module.
 */

namespace DiceGoblins\Core;

final class Env
{
  /** @var array<string, string> */
  private static array $values = [];

  public static function load(string $path): void
  {
    if (!is_file($path)) {
      return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
      return;
    }

    foreach ($lines as $line) {
      $line = trim($line);

      // Skip comments
      if ($line === '' || str_starts_with($line, '#')) {
        continue;
      }

      // Split KEY=VALUE
      if (!str_contains($line, '=')) {
        continue;
      }

      [$key, $value] = explode('=', $line, 2);
      $key = trim($key);
      $value = trim($value);

      // Strip surrounding quotes
      if (
        (str_starts_with($value, '"') && str_ends_with($value, '"')) ||
        (str_starts_with($value, "'") && str_ends_with($value, "'"))
      ) {
        $value = substr($value, 1, -1);
      }

      self::$values[$key] = $value;

      // Populate PHP env for compatibility
      $_ENV[$key] = $value;
      putenv("$key=$value");
    }
  }

  public static function get(string $key, ?string $default = null): ?string
  {
    if (array_key_exists($key, self::$values)) {
      return self::$values[$key];
    }

    $env = getenv($key);
    if ($env !== false) {
      return $env;
    }

    return $default;
  }

  public static function require(string $key): string
  {
    $value = self::get($key);
    if ($value === null || $value === '') {
      throw new \RuntimeException("Missing required env var: $key");
    }

    return $value;
  }
}
