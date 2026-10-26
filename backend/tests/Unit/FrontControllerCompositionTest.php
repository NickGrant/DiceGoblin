<?php
declare(strict_types=1);

namespace DiceGoblins\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class FrontControllerCompositionTest extends TestCase
{
  public function testImportsControllerBindingsAndHttpRoutesAreUnique(): void
  {
    $source = file_get_contents(dirname(__DIR__, 2) . '/public/index.php');
    $this->assertIsString($source);
    $imports = $bindings = $routes = [];
    foreach (preg_split('/\R/', $source) as $line) {
      if (preg_match('/^use (.+);$/', $line, $match)) $imports[] = $match[1];
      if (preg_match('/^\$([a-zA-Z_][a-zA-Z_0-9]*) = new /', $line, $match)) $bindings[] = $match[1];
      if (preg_match('/^\$router->(get|post|put|patch|delete)\(\x27([^\x27]+)\x27,/', $line, $match)) {
        $routes[] = strtoupper($match[1]) . ' ' . $match[2];
      }
    }
    $this->assertSame($imports, array_values(array_unique($imports)), 'Front-controller imports must be unique.');
    $this->assertSame($bindings, array_values(array_unique($bindings)), 'Front-controller bindings must be unique.');
    $this->assertSame($routes, array_values(array_unique($routes)), 'HTTP method/path registrations must be unique.');
    $this->assertSame(1, count(array_keys($imports, 'DiceGoblins\\Controllers\\CodexController', true)));
    $this->assertSame(1, count(array_keys($bindings, 'codex', true)));
    $this->assertSame(1, count(array_keys($routes, 'GET /api/v1/codex', true)));
  }
}
