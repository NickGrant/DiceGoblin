<?php
declare(strict_types=1);

/** Provision the isolated Docker-backed integration-test database and user. */
require_once __DIR__ . '/../tests/bootstrap.php';

$dsn = getenv('TEST_DB_DSN') ?: '';
$testUser = getenv('TEST_DB_USER') ?: '';
$testPass = getenv('TEST_DB_PASS') ?: '';
$adminUser = getenv('TEST_DB_ADMIN_USER') ?: 'root';
$adminPass = getenv('TEST_DB_ADMIN_PASS') ?: 'rootpass';

if (!preg_match('/^mysql:host=([^;]+);port=([0-9]+);dbname=([^;]+)(;.*)?$/', $dsn, $matches)
  || $testUser === '' || !preg_match('/^[a-zA-Z0-9_]+$/', $matches[3])) {
  fwrite(STDERR, "Valid TEST_DB_DSN and TEST_DB_USER are required.\n");
  exit(1);
}

try {
  $admin = new PDO("mysql:host={$matches[1]};port={$matches[2]};charset=utf8mb4", $adminUser, $adminPass,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
  $database = $matches[3];
  $user = $admin->quote($testUser);
  $password = $admin->quote($testPass);
  $admin->exec("CREATE DATABASE IF NOT EXISTS `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
  $admin->exec("CREATE USER IF NOT EXISTS {$user}@'%' IDENTIFIED BY {$password}");
  $admin->exec("ALTER USER {$user}@'%' IDENTIFIED BY {$password}");
  $admin->exec("GRANT ALL PRIVILEGES ON `{$database}`.* TO {$user}@'%'");
  $admin->exec('FLUSH PRIVILEGES');
} catch (PDOException $error) {
  fwrite(STDERR, "Test database provision failed: {$error->getMessage()}\n");
  exit(1);
}

fwrite(STDOUT, "Provisioned test DB '{$database}' for user '{$testUser}'.\n");
