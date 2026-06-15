<?php
declare(strict_types=1);

/**
 * Provisions the Docker-backed integration-test database and user.
 *
 * Usage:
 *   composer --working-dir=backend test:db:provision
 */

require_once __DIR__ . '/../tests/bootstrap.php';

$dsn = getenv('TEST_DB_DSN') ?: '';
$testUser = getenv('TEST_DB_USER') ?: '';
$testPass = getenv('TEST_DB_PASS') ?: '';
$adminUser = getenv('TEST_DB_ADMIN_USER') ?: 'root';
$adminPass = getenv('TEST_DB_ADMIN_PASS') ?: 'rootpass';

if (!is_string($dsn) || $dsn === '') {
  fwrite(STDERR, "TEST_DB_DSN is required.\n");
  exit(1);
}

if (!preg_match('/^mysql:host=([^;]+);port=([0-9]+);dbname=([^;]+)(;.*)?$/', $dsn, $matches)) {
  fwrite(STDERR, "TEST_DB_DSN must be mysql with host/port/dbname segments.\n");
  exit(1);
}

$host = (string)$matches[1];
$port = (int)$matches[2];
$dbName = (string)$matches[3];

if (!is_string($testUser) || $testUser === '') {
  fwrite(STDERR, "TEST_DB_USER is required.\n");
  exit(1);
}

$mysqli = mysqli_init();
if ($mysqli === false) {
  fwrite(STDERR, "Unable to initialize mysqli.\n");
  exit(1);
}

if (!$mysqli->real_connect($host, $adminUser, is_string($adminPass) ? $adminPass : '', null, $port)) {
  fwrite(STDERR, "MySQL admin connect failed: {$mysqli->connect_error}\n");
  exit(1);
}

$mysqli->set_charset('utf8mb4');

$escapeIdentifier = static function (string $value): string {
  return str_replace('`', '``', $value);
};
$escapeString = static function (mysqli $connection, string $value): string {
  return $connection->real_escape_string($value);
};

$dbIdentifier = $escapeIdentifier($dbName);
$userLiteral = $escapeString($mysqli, $testUser);
$passLiteral = $escapeString($mysqli, is_string($testPass) ? $testPass : '');

$queries = [
  "CREATE DATABASE IF NOT EXISTS `{$dbIdentifier}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci",
  "CREATE USER IF NOT EXISTS '{$userLiteral}'@'%' IDENTIFIED BY '{$passLiteral}'",
  "ALTER USER '{$userLiteral}'@'%' IDENTIFIED BY '{$passLiteral}'",
  "GRANT ALL PRIVILEGES ON `{$dbIdentifier}`.* TO '{$userLiteral}'@'%'",
  "FLUSH PRIVILEGES",
];

foreach ($queries as $query) {
  if (!$mysqli->query($query)) {
    fwrite(STDERR, "Failed running query [{$query}]: {$mysqli->error}\n");
    $mysqli->close();
    exit(1);
  }
}

$mysqli->close();
fwrite(STDOUT, "Provisioned test DB '{$dbName}' for user '{$testUser}'.\n");
exit(0);
