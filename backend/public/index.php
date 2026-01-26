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

$env = Env::get('APP_ENV', 'dev');

/**
 * Determine whether the current request is HTTPS.
 * In prod behind a proxy/CDN, HTTPS may be terminated upstream; honor X-Forwarded-Proto if present.
 */
$isHttps =
  (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
  || (isset($_SERVER['SERVER_PORT']) && (string)$_SERVER['SERVER_PORT'] === '443')
  || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string)$_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https');

// In dev on localhost you usually won't be HTTPS; in prod you should.
$cookieSecure = ($env === 'prod') ? true : $isHttps;

// For OAuth, Lax is typically the right default.
// If you ever move to cross-site embedding or a different frontend domain that must POST with cookies,
// you may need SameSite=None + Secure.
$cookieParams = [
  'lifetime' => 0,
  'path' => '/',
  'domain' => '',
  'secure' => $cookieSecure,
  'httponly' => true,
  'samesite' => 'Lax',
];

// PHP 8.0+ supports array form
session_set_cookie_params($cookieParams);

// Sessions
session_name(Env::get('SESSION_NAME', 'dice_goblins_session'));
session_start();

/**
 * Basic security headers (safe defaults; adjust if serving HTML).
 * If you only serve JSON APIs, these are generally harmless.
 */
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');

// -----------------------------
// CORS (primarily for dev)
// -----------------------------

$allowedOrigins = array_filter(
  array_map('trim', explode(',', Env::get('DEV_ALLOWED_ORIGINS', '')))
);

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

if ($origin && in_array($origin, $allowedOrigins, true)) {
  header("Access-Control-Allow-Origin: $origin");
  header('Vary: Origin');
  header('Access-Control-Allow-Credentials: true');

  // Include X-CSRF-Token for CsrfService::extractProvidedToken()
  header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token');
  header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
}

// Respond to preflight quickly
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
  http_response_code(204);
  exit;
}

// -----------------------------
// Routes
// -----------------------------

$router = new Router();

$api = new ApiController();
$auth = new AuthController();

// Auth
$router->get('/auth/discord/start', [$auth, 'discordStart']);
$router->get('/auth/discord/callback', [$auth, 'discordCallback']);
$router->post('/api/v1/auth/logout', [$auth, 'logout']);

// API
$router->get('/api/v1/health', [$api, 'health']);
$router->get('/api/v1/session', [$api, 'session']);
$router->get('/api/v1/profile', [$api, 'profile']);
$router->get('/api/v1/runs/current', [$api, 'currentRun']);
$router->post('/api/v1/runs', [$api, 'createRun']);
$router->get('/api/v1/abilities', [$api, 'abilities']);

// Dispatch
$router->dispatch();
