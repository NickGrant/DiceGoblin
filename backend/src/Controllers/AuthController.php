<?php
declare(strict_types=1);

/**
 * File: C:\xampp\htdocs\dice-goblin\backend\src\Controllers\AuthController.php
 * Purpose: Project PHP module.
 */

namespace DiceGoblins\Controllers;

use DiceGoblins\Core\Db;
use DiceGoblins\Core\Env;
use DiceGoblins\Core\Http;
use DiceGoblins\Core\Response;

use DiceGoblins\Repositories\EnergyRepository;
use DiceGoblins\Repositories\PlayerStateRepository;
use DiceGoblins\Repositories\UserRepository;

use DiceGoblins\Services\CsrfService;
use DiceGoblins\Services\GrantService;
use DiceGoblins\Services\PlayerBootstrapper;
use DiceGoblins\Services\SessionService;

use Throwable;

final class AuthController
{
  public function discordStart(): void
  {
    $clientId = Env::require('DISCORD_CLIENT_ID');
    $redirectUri = Env::require('DISCORD_REDIRECT_URI');

    // OAuth state (CSRF protection for the OAuth flow)
    $state = bin2hex(random_bytes(16));
    $_SESSION['oauth_state'] = $state;

    $params = http_build_query([
      'client_id' => $clientId,
      'redirect_uri' => $redirectUri,
      'response_type' => 'code',
      'scope' => 'identify',
      'state' => $state,
      'prompt' => 'none',
    ]);

    $authUrl = 'https://discord.com/api/oauth2/authorize?' . $params;

    header('Location: ' . $authUrl, true, 302);
  }

  public function discordCallback(): void
  {
    $expectedState = $_SESSION['oauth_state'] ?? null;
    unset($_SESSION['oauth_state']);

    $code  = $_GET['code'] ?? null;
    $state = $_GET['state'] ?? null;
    $error = $_GET['error'] ?? null;

    if (is_string($error) && $error !== '') {
      $this->redirectWithError('discord_error', $error);
      return;
    }

    if (!is_string($code) || $code === '' || !is_string($state) || $state === '') {
      $this->redirectWithError('missing_code_or_state', 'Missing code/state');
      return;
    }

    if (!is_string($expectedState) || !hash_equals($expectedState, $state)) {
      $this->redirectWithError('invalid_state', 'State mismatch');
      return;
    }

    $clientId     = Env::require('DISCORD_CLIENT_ID');
    $clientSecret = Env::require('DISCORD_CLIENT_SECRET');
    $redirectUri  = Env::require('DISCORD_REDIRECT_URI');

    // Exchange code -> access token
    $tokenResp = Http::postForm(
      'https://discord.com/api/oauth2/token',
      [],
      [
        'client_id' => $clientId,
        'client_secret' => $clientSecret,
        'grant_type' => 'authorization_code',
        'code' => $code,
        'redirect_uri' => $redirectUri,
      ]
    );

    $status = (int)($tokenResp['status'] ?? 0);
    if ($status < 200 || $status >= 300) {
      $this->redirectWithError('token_exchange_failed', 'Token exchange failed');
      return;
    }

    $tokenJson = json_decode((string)($tokenResp['body'] ?? ''), true);
    if (!is_array($tokenJson)) {
      $this->redirectWithError('token_bad_json', 'Invalid token response');
      return;
    }

    $accessToken = $tokenJson['access_token'] ?? null;
    if (!is_string($accessToken) || $accessToken === '') {
      $this->redirectWithError('token_missing', 'No access_token in response');
      return;
    }

    // Fetch Discord user identity
    $meResp = Http::get(
      'https://discord.com/api/users/@me',
      [
        'Authorization' => 'Bearer ' . $accessToken,
      ]
    );

    $meStatus = (int)($meResp['status'] ?? 0);
    if ($meStatus < 200 || $meStatus >= 300) {
      $this->redirectWithError('discord_me_failed', 'Failed to fetch user profile');
      return;
    }

    $me = json_decode((string)($meResp['body'] ?? ''), true);
    if (!is_array($me)) {
      $this->redirectWithError('discord_me_bad_json', 'Invalid profile response');
      return;
    }

    $discordId = $me['id'] ?? null;
    if (!is_string($discordId) || $discordId === '') {
      $this->redirectWithError('discord_id_missing', 'No id from Discord');
      return;
    }

    // Determine display name
    $globalName = $me['global_name'] ?? null;
    $username   = $me['username'] ?? null;
    $displayName = (is_string($globalName) && $globalName !== '')
      ? $globalName
      : ((is_string($username) && $username !== '') ? $username : 'Goblin');

    // Compute avatar URL once
    $avatarUrl = null;
    $avatar = $me['avatar'] ?? null;
    if (is_string($avatar) && $avatar !== '') {
      $avatarUrl = "https://cdn.discordapp.com/avatars/{$discordId}/{$avatar}.png";
    }

    // Upsert local user + establish session
    try {
      $services = $this->services();

      $userId = $services['userRepo']->upsertUserByDiscordId($discordId, $displayName, $avatarUrl);
      session_regenerate_id(true);

      // Establish minimal session (only user_id + rotated CSRF token)
      $services['sessionService']->establishSession($userId);
    } catch (Throwable $e) {
      $this->redirectWithError('user_upsert_failed', 'Could not create/load user');
      return;
    }

    $frontend = $this->frontendBaseUrl();
    header('Location: ' . $frontend . '/', true, 302);
  }

  public function logout(): void
  {
    try {
      $services = $this->services();
      $services['sessionService']->clearSession();
    } catch (Throwable $e) {
      // Continue to destroy session regardless.
    }

    // Unset all session values
    $_SESSION = [];

    // Destroy the session cookie
    if (ini_get('session.use_cookies')) {
      $params = session_get_cookie_params();
      setcookie(
        session_name(),
        '',
        [
          'expires' => time() - 3600,
          'path' => $params['path'] ?? '/',
          'domain' => $params['domain'] ?? '',
          'secure' => (bool)($params['secure'] ?? false),
          'httponly' => (bool)($params['httponly'] ?? true),
          'samesite' => $params['samesite'] ?? 'Lax',
        ]
      );
    }

    session_destroy();

    Response::json(['ok' => true], 200);
  }

  // -----------------------------
  // Internals
  // -----------------------------

  /**
   * Manual composition (no DI container).
   *
   * @return array{
   *   userRepo: UserRepository,
   *   sessionService: SessionService
   * }
   */
  private function services(): array
  {
    $pdo = Db::pdo();

    $userRepo = new UserRepository($pdo);
    $playerStateRepo = new PlayerStateRepository($pdo);
    $energyRepo = new EnergyRepository($pdo);

    $csrfService = new CsrfService();
    $grantService = new GrantService();

    $bootstrapper = new PlayerBootstrapper(
      $playerStateRepo,
      $energyRepo,
      $grantService
    );

    $sessionService = new SessionService(
      $userRepo,
      $csrfService,
      $bootstrapper
    );

    return [
      'userRepo' => $userRepo,
      'sessionService' => $sessionService,
    ];
  }

  private function redirectWithError(string $code, string $details): void
  {
    $frontend = $this->frontendBaseUrl();

    $msg = trim(preg_replace('/\s+/', ' ', (string)$details));
    $msg = substr($msg, 0, 200);

    $qs = http_build_query([
      'auth_error' => $code,
      'msg' => $msg,
    ]);

    header('Location: ' . $frontend . '/?' . $qs, true, 302);
  }

  private function frontendBaseUrl(): string
  {
    $frontend = Env::get('FRONTEND_URL', 'http://localhost:5173');
    return str_replace(["\r", "\n"], '', (string)$frontend); // prevent header injection
  }
}
