<?php
declare(strict_types=1);

namespace DiceGoblins\Controllers;

use DiceGoblins\Core\Env;
use DiceGoblins\Core\Http;
use DiceGoblins\Core\Response;
use DiceGoblins\Core\Db;

final class AuthController
{
  public function discordStart(): void
  {
    $clientId = Env::require('DISCORD_CLIENT_ID');
    $redirectUri = Env::require('DISCORD_REDIRECT_URI');

    // CSRF protection
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

      $code  = $_GET['code']  ?? null;
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

      if (($tokenResp['status'] ?? 0) < 200 || ($tokenResp['status'] ?? 0) >= 300) {
          // Prefer logging $tokenResp['body'] server-side; keep user-facing error short.
          $this->redirectWithError('token_exchange_failed', 'Token exchange failed');
          return;
      }

      $tokenJson = json_decode((string)$tokenResp['body'], true);
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

      if (($meResp['status'] ?? 0) < 200 || ($meResp['status'] ?? 0) >= 300) {
          $this->redirectWithError('discord_me_failed', 'Failed to fetch user profile');
          return;
      }

      $me = json_decode((string)$meResp['body'], true);
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

      $pdo = Db::pdo();

      // Upsert by discord_id
      $stmt = $pdo->prepare(
          "INSERT INTO users (discord_id, display_name, avatar_url)
          VALUES (:discord_id, :display_name, :avatar_url)
          ON DUPLICATE KEY UPDATE
              display_name = VALUES(display_name),
              avatar_url   = VALUES(avatar_url)"
      );

      $stmt->execute([
          ':discord_id' => $discordId,
          ':display_name' => $displayName,
          ':avatar_url' => $avatarUrl,
      ]);

      // Fetch local id
      $stmt2 = $pdo->prepare(
          "SELECT id, display_name, avatar_url
          FROM users
          WHERE discord_id = :discord_id
          LIMIT 1"
      );
      $stmt2->execute([':discord_id' => $discordId]);
      $userRow = $stmt2->fetch();

      if (!is_array($userRow) || !isset($userRow['id'])) {
          $this->redirectWithError('user_upsert_failed', 'Could not load user after upsert');
          return;
      }

      session_regenerate_id(true);
      $_SESSION['user_id'] = (string)$userRow['id'];
      $_SESSION['display_name'] = (string)($userRow['display_name'] ?? $displayName);

      if (!empty($userRow['avatar_url'])) {
          $_SESSION['avatar_url'] = (string)$userRow['avatar_url'];
      } else {
          unset($_SESSION['avatar_url']);
      }

      $frontend = Env::get('FRONTEND_URL', 'http://localhost:5173');
      $frontend = str_replace(["\r", "\n"], '', $frontend); // prevent header injection

      header('Location: ' . $frontend . '/', true, 302);
  }


  public function logout(): void{
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
    Response::json(['ok' => true]);
  }


  private function redirectWithError(string $code, string $details): void
  {
      $frontend = Env::get('FRONTEND_URL', 'http://localhost:5173');
      $frontend = str_replace(["\r", "\n"], '', $frontend);

      $msg = trim(preg_replace('/\s+/', ' ', (string)$details));
      $msg = substr($msg, 0, 200);

      $qs = http_build_query([
          'auth_error' => $code,
          'msg' => $msg,
      ]);

      header('Location: ' . $frontend . '/?' . $qs, true, 302);
  }

}
