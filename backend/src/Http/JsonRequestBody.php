<?php
declare(strict_types=1);

namespace DiceGoblins\Http;

final class JsonRequestBody
{
  /**
   * @return array<string,mixed>|null
   */
  public static function decode(): ?array
  {
    $raw = self::rawBody();
    if ($raw === null) {
      return null;
    }

    $raw = trim($raw);
    if ($raw === '') {
      return [];
    }

    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : null;
  }

  private static function rawBody(): ?string
  {
    $testOverride = $_SERVER['DICE_GOBLINS_TEST_RAW_BODY'] ?? null;
    if (is_string($testOverride)) {
      return $testOverride;
    }

    $raw = file_get_contents('php://input');
    return $raw === false ? null : $raw;
  }
}
