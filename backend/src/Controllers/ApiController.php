<?php
declare(strict_types=1);

namespace DiceGoblins\Controllers;

use DiceGoblins\Core\Response;

final class ApiController
{
  public function health(): void
  {
    Response::json([
      'ok' => true,
      'service' => 'dice-goblins-backend',
      'time' => gmdate('c'),
    ]);
  }

  public function session(): void
  {
    $userId = $_SESSION['user_id'] ?? null;

    if (!$userId) {
      Response::json([
        'authenticated' => false,
      ]);
      return;
    }

    Response::json([
      'authenticated' => true,
      'user' => [
        'id' => (string)$userId,
        'display_name' => $_SESSION['display_name'] ?? null,
      ],
    ]);
  }
}
