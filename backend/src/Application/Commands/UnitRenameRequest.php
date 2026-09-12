<?php
declare(strict_types=1);

namespace DiceGoblins\Application\Commands;

final class UnitRenameRequest
{
  private function __construct(public readonly string $name) {}

  /** @param array<string,mixed> $request */
  public static function fromRequest(array $request): self
  {
    if (array_keys($request) !== ['name'] || !is_string($request['name'])) {
      throw new UnitConfigurationValidationException('Request must contain exactly one string name.');
    }

    $name = preg_replace('/^\s+|\s+$/u', '', $request['name']);
    if ($name === null || $name === '') {
      throw new UnitConfigurationValidationException('Unit name must contain between 1 and 128 characters.');
    }
    preg_match_all('/./us', $name, $characters);
    if (count($characters[0]) > 128) {
      throw new UnitConfigurationValidationException('Unit name must contain between 1 and 128 characters.');
    }

    return new self($name);
  }
}
