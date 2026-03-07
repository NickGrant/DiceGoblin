<?php
declare(strict_types=1);

namespace DiceGoblins\Controllers\Concerns;

use DiceGoblins\Core\Response;
use DiceGoblins\Services\CsrfService;

trait RequiresCsrf
{
  private function requireCsrf(CsrfService $csrfService): bool
  {
    $provided = $csrfService->extractProvidedToken();

    if (!$csrfService->validateToken($provided)) {
      Response::json([
        'ok' => false,
        'error' => [
          'code' => 'csrf_invalid',
          'message' => 'Invalid CSRF token.',
        ],
      ], 403);
      return false;
    }

    return true;
  }
}
