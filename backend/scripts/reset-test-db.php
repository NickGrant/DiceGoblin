<?php
declare(strict_types=1);

/**
 * Resets the TEST_DB_DSN database using versioned schema artifact:
 *   backend/migrations/schema_all.sql
 *
 * Usage:
 *   composer --working-dir=backend test:db:reset
 */

require_once __DIR__ . '/../tests/bootstrap.php';

$dsn = getenv('TEST_DB_DSN') ?: '';
$user = getenv('TEST_DB_USER') ?: '';
$pass = getenv('TEST_DB_PASS') ?: '';

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

$schemaPath = realpath(__DIR__ . '/../migrations/schema_all.sql');
if ($schemaPath === false || !is_file($schemaPath)) {
  fwrite(STDERR, "schema_all.sql not found.\n");
  exit(1);
}

$mysqli = mysqli_init();
if ($mysqli === false) {
  fwrite(STDERR, "Unable to initialize mysqli.\n");
  exit(1);
}

if (!$mysqli->real_connect($host, $user, $pass, $dbName, $port)) {
  fwrite(STDERR, "MySQL connect failed: {$mysqli->connect_error}\n");
  exit(1);
}

$mysqli->set_charset('utf8mb4');

// Drop all current tables to ensure deterministic reset.
$tableRows = $mysqli->query('SHOW TABLES');
if ($tableRows === false) {
  fwrite(STDERR, "Failed to list tables: {$mysqli->error}\n");
  $mysqli->close();
  exit(1);
}

$tables = [];
while ($row = $tableRows->fetch_row()) {
  if (isset($row[0]) && is_string($row[0]) && $row[0] !== '') {
    $tables[] = $row[0];
  }
}
$tableRows->free();

if (!$mysqli->query('SET FOREIGN_KEY_CHECKS=0')) {
  fwrite(STDERR, "Failed to disable foreign keys: {$mysqli->error}\n");
  $mysqli->close();
  exit(1);
}

foreach ($tables as $table) {
  $escaped = str_replace('`', '``', $table);
  if (!$mysqli->query("DROP TABLE IF EXISTS `{$escaped}`")) {
    fwrite(STDERR, "Failed dropping table {$table}: {$mysqli->error}\n");
    $mysqli->close();
    exit(1);
  }
}

if (!$mysqli->query('SET FOREIGN_KEY_CHECKS=1')) {
  fwrite(STDERR, "Failed to re-enable foreign keys: {$mysqli->error}\n");
  $mysqli->close();
  exit(1);
}

$sql = (string)file_get_contents($schemaPath);
if (trim($sql) === '') {
  fwrite(STDERR, "schema_all.sql is empty.\n");
  $mysqli->close();
  exit(1);
}

if (!$mysqli->multi_query($sql)) {
  fwrite(STDERR, "Failed applying schema_all.sql: {$mysqli->error}\n");
  $mysqli->close();
  exit(1);
}

do {
  $result = $mysqli->store_result();
  if ($result instanceof mysqli_result) {
    $result->free();
  }
} while ($mysqli->more_results() && $mysqli->next_result());

if ($mysqli->errno !== 0) {
  fwrite(STDERR, "Error while applying schema: {$mysqli->error}\n");
  $mysqli->close();
  exit(1);
}

$mysqli->close();
fwrite(STDOUT, "Test DB reset complete using migrations/schema_all.sql\n");
exit(0);
