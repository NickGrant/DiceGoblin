<?php
declare(strict_types=1);

use DiceGoblins\Core\Autoloader;
use DiceGoblins\Core\Env;
use DiceGoblins\Core\Router;
use DiceGoblins\Controllers\ApiController;
use DiceGoblins\Controllers\AuthController;

require_once __DIR__ . '/../src/Core/Autoloader.php';
Autoloader::register(__DIR__ . '/../src');

// Load environment
Env::load(__DIR__ . '/../.env');

$env = \DiceGoblins\Core\Env::get('APP_ENV', 'dev');

// In dev on localhost you usually won't be HTTPS; in prod you should.
$isSecure = ($env === 'prod');

// For OAuth, Lax is typically the right default.
$cookieParams = [
  'lifetime' => 0,
  'path' => '/',
  'domain' => '',
  'secure' => $isSecure,
  'httponly' => true,
  'samesite' => 'Lax',
];

// PHP 8.0+ supports array form
session_set_cookie_params($cookieParams);

// Sessions
session_name(Env::get('SESSION_NAME', 'dice_goblins_session'));
session_start();


// Dev CORS for Vite + cookies
$allowedOrigins = array_filter(
  array_map('trim', explode(',', Env::get('DEV_ALLOWED_ORIGINS', '')))
);

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

if ($origin && in_array($origin, $allowedOrigins, true)) {
  header("Access-Control-Allow-Origin: $origin");
  header('Vary: Origin');
  header('Access-Control-Allow-Credentials: true');
  header('Access-Control-Allow-Headers: Content-Type');
  header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
  http_response_code(204);
  exit;
}

$router = new Router();
$api = new ApiController();

$auth = new AuthController();

$router->get('/auth/discord/start', [$auth, 'discordStart']);
$router->get('/auth/discord/callback', [$auth, 'discordCallback']);

$router->post('/api/v1/auth/logout', [$auth, 'logout']);

$router->get('/api/v1/health', [$api, 'health']);
$router->get('/api/v1/session', [$api, 'session']);

$router->dispatch();
