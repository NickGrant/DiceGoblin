<?php
declare(strict_types=1);

namespace DiceGoblins\Application\Commands;

use RuntimeException;

final class DialogueCompletionException extends RuntimeException
{
  public function __construct(public readonly string $errorCode, public readonly int $httpStatus,
    string $message = 'Dialogue choice is unavailable.') { parent::__construct($message); }
}
