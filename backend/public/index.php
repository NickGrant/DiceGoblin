<?php
declare(strict_types=1);

/**
 * File: C:\xampp\htdocs\dice-goblin\backend\public\index.php
 * Purpose: Project PHP module.
 */

use DiceGoblins\Core\Autoloader;
use DiceGoblins\Core\Env;
use DiceGoblins\Core\Router;
use DiceGoblins\Controllers\ApiController;
use DiceGoblins\Controllers\AcademyController;
use DiceGoblins\Controllers\AuthController;
use DiceGoblins\Controllers\BattleController;
use DiceGoblins\Controllers\BountyBoardController;
use DiceGoblins\Controllers\ChaosEncounterController;
use DiceGoblins\Controllers\DebugController;
use DiceGoblins\Controllers\GameplayController;
use DiceGoblins\Controllers\RunNodeController;
use DiceGoblins\Controllers\ShopController;
use DiceGoblins\Controllers\TeamController;

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
$academy = new AcademyController();
$auth = new AuthController();
$battle = new BattleController();
$bountyBoard = new BountyBoardController();
$chaosEncounter = new ChaosEncounterController();
$debug = new DebugController();
$gameplay = new GameplayController();
$runNode = new RunNodeController();
$shop = new ShopController();
$team = new TeamController();

// Auth
$router->get('/auth/discord/start', [$auth, 'discordStart']);
$router->get('/auth/discord/callback', [$auth, 'discordCallback']);
$router->post('/api/v1/auth/local/register', [$auth, 'localRegister']);
$router->post('/api/v1/auth/local/login', [$auth, 'localLogin']);
$router->post('/api/v1/auth/local/password-reset/request', [$auth, 'requestPasswordReset']);
$router->post('/api/v1/auth/local/password-reset/confirm', [$auth, 'confirmPasswordReset']);
$router->post('/api/v1/auth/logout', [$auth, 'logout']);

// API
$router->get('/api/v1/health', [$api, 'health']);
$router->get('/api/v1/session', [$api, 'session']);
$router->get('/api/v1/profile', [$api, 'profile']);
$router->post('/api/v1/dialogues/:dialogueId/seen', [$api, 'markDialogueSeen']);
$router->get('/api/v1/academy', [$academy, 'catalog']);
$router->post('/api/v1/academy/unlock-unit-type', [$academy, 'unlockUnitType']);
$router->get('/api/v1/shop', [$shop, 'catalog']);
$router->post('/api/v1/shop/purchase', [$shop, 'purchase']);
$router->get('/api/v1/bounties', [$bountyBoard, 'board']);
$router->post('/api/v1/bounties/accept', [$bountyBoard, 'accept']);
$router->post('/api/v1/bounties/sync', [$bountyBoard, 'sync']);
$router->post('/api/v1/bounties/:userBountyId/claim', [$bountyBoard, 'claim']);
$router->get('/api/v1/runs/current', [$api, 'currentRun']);
$router->post('/api/v1/runs', [$api, 'createRun']);
$router->post('/api/v1/runs/:runId/abandon', [$api, 'abandonRun']);
$router->post('/api/v1/runs/:runId/exit', [$api, 'exitRun']);
$router->post('/api/v1/runs/:runId/nodes/:nodeId/rest/open', [$gameplay, 'openRest']);
$router->post('/api/v1/runs/:runId/nodes/:nodeId/rest/finalize', [$gameplay, 'finalizeRest']);
$router->post('/api/v1/runs/:runId/nodes/:nodeId/chaos/generate', [$chaosEncounter, 'generate']);
$router->post('/api/v1/runs/:runId/nodes/:nodeId/chaos/reroll', [$chaosEncounter, 'reroll']);
$router->post('/api/v1/runs/:runId/nodes/:nodeId/chaos/finalize', [$chaosEncounter, 'finalize']);
$router->get('/api/v1/abilities', [$api, 'abilities']);
// Debug / dev-only endpoints
$router->get('/api/v1/debug/catalog', [$debug, 'catalog']);
$router->post('/api/v1/debug/grant/currency', [$debug, 'grantCurrency']);
$router->post('/api/v1/debug/grant/unit', [$debug, 'grantUnit']);
$router->post('/api/v1/debug/grant/dice', [$debug, 'grantDice']);
$router->post('/api/v1/debug/grant/region-item', [$debug, 'grantRegionItem']);
$router->post('/api/v1/debug/units/set-level', [$debug, 'setUnitLevel']);
$router->post('/api/v1/debug/reset-account', [$debug, 'resetAccount']);

$router->post('/api/v1/runs/:runId/nodes/:nodeId/resolve', [$runNode, 'resolveNode']);
$router->post('/api/v1/runs/:runId/nodes/:nodeId/dialogue/complete', [$runNode, 'completeDialogueNode']);
$router->get('/api/v1/battles/:battleId/log',[$battle, 'getBattleLog']);
$router->post('/api/v1/battles/:battleId/claim',[$battle, 'claimBattle']);
// Compatibility-critical identifiers remain `teams` in route keys.
$router->get('/api/v1/units/:unitInstanceId/promotion-options', [$gameplay, 'getPromotionOptions']);
$router->post('/api/v1/units/:unitInstanceId/promote', [$gameplay, 'promoteUnit']);
$router->put('/api/v1/units/:unitInstanceId/capstone', [$gameplay, 'selectCapstone']);
$router->patch('/api/v1/units/:unitInstanceId/name', [$gameplay, 'renameUnit']);
$router->put('/api/v1/units/:unitInstanceId/loadout', [$gameplay, 'replaceEquippedAbilities']);
$router->put('/api/v1/units/:unitInstanceId/abilities/:abilityId/slots/:slotIndex/dice', [$gameplay, 'assignAbilitySlotDie']);
$router->delete('/api/v1/units/:unitInstanceId/abilities/:abilityId/slots/:slotIndex/dice', [$gameplay, 'clearAbilitySlotDie']);
$router->post('/api/v1/units/:unitInstanceId/dice/equip', [$gameplay, 'equipDice']);
$router->post('/api/v1/units/:unitInstanceId/dice/unequip', [$gameplay, 'unequipDice']);
$router->post('/api/v1/dice/:diceInstanceId/sell', [$gameplay, 'sellDice']);
$router->post('/api/v1/dice/:diceInstanceId/salvage', [$gameplay, 'salvageDice']);

$router->post('/api/v1/teams', [$team, 'createTeam']);
$router->post('/api/v1/teams/:teamId/activate', [$team, 'activateTeam']);
$router->put('/api/v1/teams/:teamId', [$team, 'updateTeam']);
$router->delete('/api/v1/teams/:teamId', [$team, 'deleteTeam']);

// Dispatch
$router->dispatch();
