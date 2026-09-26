<?php
declare(strict_types=1);

namespace DiceGoblins\Domain\Inventory;

use RuntimeException;

final class InsufficientInventoryException extends RuntimeException {}
