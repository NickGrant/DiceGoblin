<?php
declare(strict_types=1);

/**
 * File: C:\xampp\htdocs\dice-goblin\backend\src\Core\Db.php
 * Purpose: Project PHP module.
 */

namespace DiceGoblins\Core;

use PDO;

final class Db
{
  private static ?PDO $pdo = null;

  public static function pdo(): PDO
  {
    if (self::$pdo) {
      return self::$pdo;
    }

    $host = Env::require('DB_HOST');
    $port = Env::get('DB_PORT', '3306');
    $db   = Env::require('DB_NAME');
    $user = Env::require('DB_USER');
    $pass = Env::require('DB_PASS');

    $dsn = "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $pass, [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
      PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    self::$pdo = $pdo;
    return $pdo;
  }
}
