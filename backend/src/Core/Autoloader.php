<?php
declare(strict_types=1);

/**
 * File: C:\xampp\htdocs\dice-goblin\backend\src\Core\Autoloader.php
 * Purpose: Project PHP module.
 */

namespace DiceGoblins\Core;

final class Autoloader
{
  public static function register(string $baseDir): void
  {
    spl_autoload_register(function (string $class) use ($baseDir): void {
      $prefix = 'DiceGoblins\\';
      $prefixLen = strlen($prefix);

      if (strncmp($prefix, $class, $prefixLen) !== 0) {
        return;
      }

      $relativeClass = substr($class, $prefixLen);
      $file = rtrim($baseDir, DIRECTORY_SEPARATOR)
        . DIRECTORY_SEPARATOR
        . str_replace('\\', DIRECTORY_SEPARATOR, $relativeClass)
        . '.php';

      if (is_file($file)) {
        require_once $file;
      }
    });
  }
}
