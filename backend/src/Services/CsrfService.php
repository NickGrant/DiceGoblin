<?php
declare(strict_types=1);

namespace DiceGoblins\Services;

use RuntimeException;

final class CsrfService
{
  /**
   * Session key used to store the CSRF token.
   */
  public const SESSION_KEY = 'csrf_token';

  /**
   * Ensures a CSRF token exists in the session and returns it.
   */
  public function getOrCreateToken(): string
  {
    $existing = $_SESSION[self::SESSION_KEY] ?? null;

    if (is_string($existing) && $existing !== '') {
      return $existing;
    }

    $token = $this->generateToken();
    $_SESSION[self::SESSION_KEY] = $token;

    return $token;
  }

  /**
   * Rotates the CSRF token (generates a new one, overwriting the old).
   */
  public function rotateToken(): string
  {
    $token = $this->generateToken();
    $_SESSION[self::SESSION_KEY] = $token;
    return $token;
  }

  /**
   * Clears the CSRF token from session.
   */
  public function clearToken(): void
  {
    unset($_SESSION[self::SESSION_KEY]);
  }

  /**
   * Validate a provided CSRF token against the session token.
   *
   * Use this for state-changing requests (POST/PUT/PATCH/DELETE).
   */
  public function validateToken(?string $providedToken): bool
  {
    $sessionToken = $_SESSION[self::SESSION_KEY] ?? null;

    if (!is_string($sessionToken) || $sessionToken === '') {
      return false;
    }

    if (!is_string($providedToken) || $providedToken === '') {
      return false;
    }

    // Constant-time compare to reduce side-channel risk.
    return hash_equals($sessionToken, $providedToken);
  }

  /**
   * Extract token from common locations:
   * - X-CSRF-Token header
   * - csrf_token form field
   *
   * You can call validateToken($this->extractProvidedToken()) in controllers.
   */
  public function extractProvidedToken(): ?string
  {
    // Header
    $header = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
    if (is_string($header) && $header !== '') {
      return $header;
    }

    // Form field (application/x-www-form-urlencoded or multipart/form-data)
    $post = $_POST['csrf_token'] ?? null;
    if (is_string($post) && $post !== '') {
      return $post;
    }

    // JSON body token is intentionally not parsed here to keep this service small/pure.
    // If you standardize on JSON bodies, add a Request helper that decodes JSON once
    // and provides it to controllers/services.
    return null;
  }

  // -----------------------------
  // Internals
  // -----------------------------

  private function generateToken(): string
  {
    try {
      return bin2hex(random_bytes(32)); // 64 hex chars
    } catch (\Throwable $e) {
      // random_bytes should not fail under normal conditions; still fail closed.
      throw new RuntimeException('Unable to generate CSRF token.');
    }
  }
}
