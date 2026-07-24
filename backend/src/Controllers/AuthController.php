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
use DiceGoblins\Http\JsonRequestBody;

use DiceGoblins\Repositories\UserRepository;

use DiceGoblins\Services\CsrfService;
use DiceGoblins\Services\SessionService;

use Throwable;

final class AuthController
{
  public function localRegister(): void
  {
    $body = JsonRequestBody::decode();
    if ($body === null) {
      $this->jsonAuthError('validation_error', 'Invalid JSON body.', 400);
      return;
    }

    $email = $this->normalizeEmailInput($body['email'] ?? null);
    $password = $this->stringInput($body['password'] ?? null);
    $displayName = trim($this->stringInput($body['display_name'] ?? null));

    if (!$this->isValidEmail($email)) {
      $this->jsonAuthError('validation_error', 'Enter a valid email address.', 400);
      return;
    }
    if (strlen($password) < 8) {
      $this->jsonAuthError('validation_error', 'Password must be at least 8 characters.', 400);
      return;
    }
    if (strlen($password) > 256) {
      $this->jsonAuthError('validation_error', 'Password is too long.', 400);
      return;
    }
    if ($displayName === '') {
      $displayName = 'Goblin';
    }
    if (strlen($displayName) > 128) {
      $this->jsonAuthError('validation_error', 'Display name is too long.', 400);
      return;
    }

    try {
      $services = $this->services();

      if ($services['userRepo']->getUserByLocalEmail($email) !== null) {
        $this->jsonAuthError('email_already_registered', 'That email is already registered.', 409);
        return;
      }

      $userId = $services['userRepo']->createLocalUser(
        $email,
        password_hash($password, PASSWORD_DEFAULT),
        $displayName,
      );
      $this->regenerateActiveSessionId();
      $services['sessionService']->establishSession($userId);

      Response::json([
        'ok' => true,
        'data' => $services['sessionService']->getSessionPayload(),
      ], 201);
    } catch (Throwable $e) {
      if ((string)$e->getCode() === '23000') {
        $this->jsonAuthError('email_already_registered', 'That email is already registered.', 409);
        return;
      }

      $this->jsonAuthError('registration_failed', 'Could not create account.', 500);
    }
  }

  public function localLogin(): void
  {
    $body = JsonRequestBody::decode();
    if ($body === null) {
      $this->jsonAuthError('validation_error', 'Invalid JSON body.', 400);
      return;
    }

    $email = $this->normalizeEmailInput($body['email'] ?? null);
    $password = $this->stringInput($body['password'] ?? null);

    if (!$this->isValidEmail($email) || $password === '') {
      $this->jsonAuthError('invalid_credentials', 'Email or password is incorrect.', 401);
      return;
    }

    try {
      $services = $this->services();
      $user = $services['userRepo']->getUserByLocalEmail($email);

      if ($user === null || !password_verify($password, (string)$user['password_hash'])) {
        $this->jsonAuthError('invalid_credentials', 'Email or password is incorrect.', 401);
        return;
      }

      $this->regenerateActiveSessionId();
      $services['sessionService']->establishSession((int)$user['id']);

      Response::json([
        'ok' => true,
        'data' => $services['sessionService']->getSessionPayload(),
      ]);
    } catch (Throwable) {
      $this->jsonAuthError('login_failed', 'Could not sign in.', 500);
    }
  }

  public function requestPasswordReset(): void
  {
    $body = JsonRequestBody::decode();
    if ($body === null) {
      $this->jsonAuthError('validation_error', 'Invalid JSON body.', 400);
      return;
    }

    $email = $this->normalizeEmailInput($body['email'] ?? null);
    if (!$this->isValidEmail($email)) {
      $this->jsonAuthError('validation_error', 'Enter a valid email address.', 400);
      return;
    }

    $responseData = [
      'message' => 'If that account exists, a password reset is available.',
    ];

    try {
      $services = $this->services();
      $user = $services['userRepo']->getUserByLocalEmail($email);

      if ($user !== null) {
        $token = bin2hex(random_bytes(32));
        $expiresAt = gmdate('Y-m-d H:i:s', time() + 3600);
        $services['userRepo']->createPasswordResetToken(
          (int)$user['id'],
          hash('sha256', $token),
          $expiresAt,
        );

        if ($this->shouldExposePasswordResetToken()) {
          $responseData['reset_token'] = $token;
          $responseData['expires_at'] = $expiresAt . 'Z';
        }
      }

      Response::json([
        'ok' => true,
        'data' => $responseData,
      ]);
    } catch (Throwable) {
      $this->jsonAuthError('password_reset_request_failed', 'Could not request password reset.', 500);
    }
  }

  public function confirmPasswordReset(): void
  {
    $body = JsonRequestBody::decode();
    if ($body === null) {
      $this->jsonAuthError('validation_error', 'Invalid JSON body.', 400);
      return;
    }

    $token = trim($this->stringInput($body['token'] ?? null));
    $password = $this->stringInput($body['password'] ?? null);

    if ($token === '') {
      $this->jsonAuthError('validation_error', 'Reset token is required.', 400);
      return;
    }
    if (!$this->isValidPassword($password)) {
      $this->jsonAuthError('validation_error', 'Password must be between 8 and 256 characters.', 400);
      return;
    }

    try {
      $services = $this->services();
      $userId = $services['userRepo']->consumePasswordResetToken(
        hash('sha256', $token),
        password_hash($password, PASSWORD_DEFAULT),
      );

      if ($userId === null) {
        $this->jsonAuthError('password_reset_invalid', 'Reset token is invalid or expired.', 400);
        return;
      }

      $this->regenerateActiveSessionId();
      $services['sessionService']->establishSession($userId);

      Response::json([
        'ok' => true,
        'data' => $services['sessionService']->getSessionPayload(),
      ]);
    } catch (Throwable) {
      $this->jsonAuthError('password_reset_failed', 'Could not reset password.', 500);
    }
  }

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
      $this->regenerateActiveSessionId();

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
    $core = ControllerServiceFactory::buildCore($pdo);

    return [
      'userRepo' => $core['userRepo'],
      'sessionService' => $core['sessionService'],
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

  private function jsonAuthError(string $code, string $message, int $status): void
  {
    Response::json([
      'ok' => false,
      'error' => [
        'code' => $code,
        'message' => $message,
      ],
    ], $status);
  }

  private function regenerateActiveSessionId(): void
  {
    if (session_status() === PHP_SESSION_ACTIVE) {
      session_regenerate_id(true);
    }
  }

  private function normalizeEmailInput(mixed $value): string
  {
    return strtolower(trim($this->stringInput($value)));
  }

  private function stringInput(mixed $value): string
  {
    return is_string($value) ? $value : '';
  }

  private function isValidEmail(string $email): bool
  {
    return $email !== ''
      && strlen($email) <= 255
      && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
  }

  private function isValidPassword(string $password): bool
  {
    return strlen($password) >= 8 && strlen($password) <= 256;
  }

  private function shouldExposePasswordResetToken(): bool
  {
    return Env::get('LOCAL_AUTH_EXPOSE_RESET_TOKEN', '0') === '1'
      || Env::get('APP_ENV', 'dev') !== 'prod';
  }

  private function frontendBaseUrl(): string
  {
    $frontend = Env::get('FRONTEND_URL', 'http://localhost:5173');
    return str_replace(["\r", "\n"], '', (string)$frontend); // prevent header injection
  }
}
