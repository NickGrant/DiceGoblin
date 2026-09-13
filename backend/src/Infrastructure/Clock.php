<?php
declare(strict_types=1);

namespace DiceGoblins\Infrastructure;

use DateTimeImmutable;

interface Clock
{
  public function now(): DateTimeImmutable;
}
