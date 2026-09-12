<?php
declare(strict_types=1);

namespace DiceGoblins\Application\Commands;

use RuntimeException;

final class IdempotencyConflictException extends RuntimeException {}
