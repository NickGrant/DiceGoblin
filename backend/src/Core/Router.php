<?php
declare(strict_types=1);

namespace DiceGoblins\Core;

use Throwable;

final class Router
{
  /** @var array<string, array<string, callable>> */
  private array $routes = [
    'GET' => [],
    'POST' => [],
    'PUT' => [],
    'PATCH' => [],
    'DELETE' => [],
  ];

  public function get(string $path, callable $handler): void
  {
    $this->routes['GET'][$path] = $handler;
  }

  public function post(string $path, callable $handler): void
  {
    $this->routes['POST'][$path] = $handler;
  }

  public function put(string $path, callable $handler): void
  {
    $this->routes['PUT'][$path] = $handler;
  }

  public function patch(string $path, callable $handler): void
  {
    $this->routes['PATCH'][$path] = $handler;
  }

  public function delete(string $path, callable $handler): void
  {
    $this->routes['DELETE'][$path] = $handler;
  }

  public function dispatch(): void
  {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $uri = $_SERVER['REQUEST_URI'] ?? '/';

    $path = parse_url($uri, PHP_URL_PATH) ?: '/';

    $handler = $this->routes[$method][$path] ?? null;
    if (!$handler) {
      Response::json(['error' => 'Not Found', 'path' => $path], 404);
      return;
    }

    try {
      $handler();
    } catch (Throwable $e) {
      Response::json([
        'error' => 'Internal Server Error',
        'message' => $e->getMessage(),
      ], 500);
    }
  }
}
