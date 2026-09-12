<?php
declare(strict_types=1);

namespace DiceGoblins\Application\Commands;

final class IdempotencyKey
{
  public static function validate(?string $key): string
  {
    if ($key === null || strlen($key) < 8 || strlen($key) > 128 || !preg_match('/^[A-Za-z0-9._:-]+$/D', $key)) {
      throw new IdempotencyKeyException('Idempotency-Key must be 8-128 opaque ASCII characters.');
    }
    return $key;
  }
}
