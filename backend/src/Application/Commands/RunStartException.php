<?php
declare(strict_types=1);

namespace DiceGoblins\Application\Commands;

use RuntimeException;

final class RunStartException extends RuntimeException
{
  public function __construct(
    public readonly string $errorCode,
    public readonly string $publicMessage,
    public readonly int $httpStatus,
  ) {
    parent::__construct($publicMessage);
  }
}
