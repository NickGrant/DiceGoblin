<?php
declare(strict_types=1);

/** Reset TEST_DB_DSN from the fresh vNext baseline. No production database may be targeted. */
require_once __DIR__ . '/../tests/bootstrap.php';

$dsn = getenv('TEST_DB_DSN') ?: '';
$user = getenv('TEST_DB_USER') ?: '';
$pass = getenv('TEST_DB_PASS') ?: '';
$schemaPath = realpath(__DIR__ . '/../migrations/vnext_baseline.sql');

if (!preg_match('/^mysql:host=([^;]+);port=([0-9]+);dbname=([^;]+)(;.*)?$/', $dsn, $matches)
  || $user === '' || $schemaPath === false || !is_file($schemaPath)
  || $matches[3] !== 'goblin_test') {
  fwrite(STDERR, "The isolated goblin_test DSN, test credentials, and vNext baseline are required.\n");
  exit(1);
}

$sql = (string)file_get_contents($schemaPath);
if (trim($sql) === '') {
  fwrite(STDERR, "vnext_baseline.sql is empty.\n");
  exit(1);
}

try {
  $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::MYSQL_ATTR_MULTI_STATEMENTS => true]);
  $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
  $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
  foreach ($tables as $table) {
    $escaped = str_replace('`', '``', (string)$table);
    $pdo->exec("DROP TABLE IF EXISTS `{$escaped}`");
  }
  $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
  $pdo->exec($sql);
} catch (PDOException $error) {
  fwrite(STDERR, "Test database reset failed: {$error->getMessage()}\n");
  exit(1);
}

fwrite(STDOUT, "Test DB reset complete using migrations/vnext_baseline.sql\n");
