<?php
declare(strict_types=1);

/**
 * File: C:\xampp\htdocs\dice-goblin\backend\src\Services\SessionService.php
 * Purpose: Project PHP module.
 */

namespace DiceGoblins\Services;

use DiceGoblins\Repositories\UserRepository;
use RuntimeException;

final class SessionService
{
  public function __construct(
    private readonly UserRepository $userRepo,
    private readonly CsrfService $csrfService,
    private readonly PlayerBootstrapper $bootstrapper,
  ) {}

  /**
   * Returns the session payload for GET /api/v1/session.
   *
   * Contract:
   * - If not authenticated: ['authenticated' => false]
   * - If authenticated: ['authenticated' => true, 'user' => {...}, 'csrf_token' => '...']
   *
   * Notes:
   * - Validates that the user still exists.
   * - Bootstraps baseline state (player_state, energy_state) for authenticated users.
   */
  public function getSessionPayload(): array
  {
    $userId = $_SESSION['user_id'] ?? null;
    if (!$userId) {
      return ['authenticated' => false];
    }

    $userId = (int)$userId;

    // Prefer DB truth over session cache for display fields (session can drift).
    $identity = $this->userRepo->getSessionIdentity($userId);

    if (!$identity) {
      // Session says logged in, but the DB row is gone; clear auth session.
      $this->clearAuthSession();
      return ['authenticated' => false];
    }

    // Ensure the baseline state exists so /profile (and other reads) are safe.
    $this->bootstrapper->ensureBaseline($userId);

    $csrf = $this->csrfService->getOrCreateToken();

    return [
      'authenticated' => true,
      'user' => [
        'id' => (string)$identity['id'],
        'display_name' => (string)$identity['display_name'],
        'avatar_url' => $identity['avatar_url'] !== null ? (string)$identity['avatar_url'] : null,
      ],
      'csrf_token' => $csrf,
    ];
  }

  /**
   * Helper for controllers: require login and return userId.
   * Throws if unauthenticated (so controller can map to 401).
   */
  public function requireUserId(): int
  {
    $userId = $_SESSION['user_id'] ?? null;
    if (!$userId) {
      throw new RuntimeException('unauthorized');
    }
    return (int)$userId;
  }

  /**
   * Used by AuthController after OAuth callback to establish the session.
   *
   * Keeps the session minimal; user display data is served from DB via getSessionPayload().
   */
  public function establishSession(int $userId): void
  {
    if ($userId <= 0) {
      throw new RuntimeException('Invalid userId.');
    }

    $_SESSION['user_id'] = $userId;

    // Rotate CSRF on login to reduce session fixation risk.
    $this->csrfService->rotateToken();

    // Ensure baseline rows exist for first login.
    $this->bootstrapper->ensureBaseline($userId);
  }

  /**
   * Clear auth session state.
   */
  public function clearSession(): void
  {
    $this->clearAuthSession();
  }

  // -----------------------------
  // Internals
  // -----------------------------

  private function clearAuthSession(): void
  {
    unset(
      $_SESSION['user_id'],
      // legacy fields you used previously; safe to unset even if not set
      $_SESSION['display_name'],
      $_SESSION['avatar_url'],
      $_SESSION['oauth_state'],
    );

    // Clear CSRF token as part of logout/invalidation
    $this->csrfService->clearToken();
  }
}
