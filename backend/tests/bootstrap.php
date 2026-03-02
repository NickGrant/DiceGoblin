<?php
declare(strict_types=1);

use DiceGoblins\Core\Autoloader;

require_once __DIR__ . '/../src/Core/Autoloader.php';
Autoloader::register(__DIR__ . '/../src');

/**
 * Load local test environment vars from backend/.env.test.local (if present).
 * File format:
 *   KEY=value
 */
$testEnvPath = __DIR__ . '/../.env.test.local';
if (is_file($testEnvPath)) {
  $lines = file($testEnvPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
  foreach ($lines as $line) {
    $trimmed = trim($line);
    if ($trimmed === '' || str_starts_with($trimmed, '#')) {
      continue;
    }

    $parts = explode('=', $trimmed, 2);
    if (count($parts) !== 2) {
      continue;
    }

    $key = trim($parts[0]);
    $value = trim($parts[1]);
    if ($key === '') {
      continue;
    }

    putenv("$key=$value");
    $_ENV[$key] = $value;
    $_SERVER[$key] = $value;
  }
}
