<?php
declare(strict_types=1);

/**
 * File: C:\xampp\htdocs\dice-goblin\backend\tests\bootstrap.php
 * Purpose: Project PHP module.
 */

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

/**
 * Allow controller tests using Db::pdo() to target the same test database.
 */
$testDsn = getenv('TEST_DB_DSN') ?: '';
if (is_string($testDsn) && str_starts_with($testDsn, 'mysql:')) {
  $host = null;
  $port = null;
  $dbName = null;

  foreach (explode(';', substr($testDsn, strlen('mysql:'))) as $segment) {
    $parts = explode('=', $segment, 2);
    if (count($parts) !== 2) {
      continue;
    }
    $k = trim($parts[0]);
    $v = trim($parts[1]);
    if ($k === 'host') $host = $v;
    if ($k === 'port') $port = $v;
    if ($k === 'dbname') $dbName = $v;
  }

  $dbUser = getenv('TEST_DB_USER') ?: '';
  $dbPass = getenv('TEST_DB_PASS') ?: '';

  if ($host !== null) {
    putenv("DB_HOST=$host");
    $_ENV['DB_HOST'] = $host;
    $_SERVER['DB_HOST'] = $host;
  }
  if ($port !== null) {
    putenv("DB_PORT=$port");
    $_ENV['DB_PORT'] = $port;
    $_SERVER['DB_PORT'] = $port;
  }
  if ($dbName !== null) {
    putenv("DB_NAME=$dbName");
    $_ENV['DB_NAME'] = $dbName;
    $_SERVER['DB_NAME'] = $dbName;
  }
  if (is_string($dbUser) && $dbUser !== '') {
    putenv("DB_USER=$dbUser");
    $_ENV['DB_USER'] = $dbUser;
    $_SERVER['DB_USER'] = $dbUser;
  }
  if (is_string($dbPass)) {
    putenv("DB_PASS=$dbPass");
    $_ENV['DB_PASS'] = $dbPass;
    $_SERVER['DB_PASS'] = $dbPass;
  }
}
