<?php
declare(strict_types=1);

namespace DiceGoblins\Core;

use Throwable;

final class Router
{
  /** @var array<string, array<string, callable>> */
  private array $staticRoutes = [
    'GET' => [],
    'POST' => [],
    'PUT' => [],
    'PATCH' => [],
    'DELETE' => [],
  ];

  /**
   * Param routes are stored as a list so we can evaluate patterns in order.
   *
   * @var array<string, array<int, array{pattern:string, regex:string, params:array<int,string>, handler:callable}>>
   */
  private array $paramRoutes = [
    'GET' => [],
    'POST' => [],
    'PUT' => [],
    'PATCH' => [],
    'DELETE' => [],
  ];

  public function get(string $path, callable $handler): void
  {
    $this->add('GET', $path, $handler);
  }

  public function post(string $path, callable $handler): void
  {
    $this->add('POST', $path, $handler);
  }

  public function put(string $path, callable $handler): void
  {
    $this->add('PUT', $path, $handler);
  }

  public function patch(string $path, callable $handler): void
  {
    $this->add('PATCH', $path, $handler);
  }

  public function delete(string $path, callable $handler): void
  {
    $this->add('DELETE', $path, $handler);
  }

  private function add(string $method, string $path, callable $handler): void
  {
    if (str_contains($path, ':')) {
      [$regex, $params] = $this->compileParamRoute($path);
      $this->paramRoutes[$method][] = [
        'pattern' => $path,
        'regex' => $regex,
        'params' => $params,
        'handler' => $handler,
      ];
      return;
    }

    $this->staticRoutes[$method][$path] = $handler;
  }

  public function dispatch(): void
  {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $uri = $_SERVER['REQUEST_URI'] ?? '/';

    $path = parse_url($uri, PHP_URL_PATH) ?: '/';

    // 1) Static match (fast path)
    $handler = $this->staticRoutes[$method][$path] ?? null;
    if ($handler) {
      $this->invoke($handler, []);
      return;
    }

    // 2) Param match
    foreach ($this->paramRoutes[$method] as $route) {
      $matches = [];
      if (!preg_match($route['regex'], $path, $matches)) {
        continue;
      }

      $args = [];
      foreach ($route['params'] as $p) {
        $args[] = isset($matches[$p]) ? (string)$matches[$p] : null;
      }

      $this->invoke($route['handler'], $args);
      return;
    }

    Response::json(['error' => 'Not Found', 'path' => $path], 404);
  }

  /**
   * @param array<int, mixed> $args
   */
  private function invoke(callable $handler, array $args): void
  {
    try {
      // Backwards compatible: if handler expects no args, PHP will ignore extras ONLY for user functions? Not reliably.
      // So we only splat if we actually have args.
      if (count($args) === 0) {
        $handler();
        return;
      }

      $handler(...$args);
    } catch (Throwable $e) {
      Response::json([
        'error' => 'Internal Server Error',
        'message' => $e->getMessage(),
      ], 500);
    }
  }

  /**
   * Convert `/api/v1/runs/:runId/nodes/:nodeId/resolve`
   * into `#^/api/v1/runs/(?P<runId>[^/]+)/nodes/(?P<nodeId>[^/]+)/resolve$#`
   *
   * @return array{0:string,1:array<int,string>} [regex, paramNames]
   */
  private function compileParamRoute(string $pattern): array
  {
    $parts = explode('/', trim($pattern, '/'));
    $params = [];

    $regexParts = [];
    foreach ($parts as $part) {
      if ($part !== '' && $part[0] === ':') {
        $name = substr($part, 1);

        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name)) {
          throw new \RuntimeException("Invalid route param name: {$name}");
        }

        $params[] = $name;
        $regexParts[] = '(?P<' . $name . '>[^/]+)';
      } else {
        $regexParts[] = preg_quote($part, '#');
      }
    }

    $regex = '#^/' . implode('/', $regexParts) . '$#';
    return [$regex, $params];
  }

}
