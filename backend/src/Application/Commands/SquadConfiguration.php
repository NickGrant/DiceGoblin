<?php
declare(strict_types=1);

namespace DiceGoblins\Application\Commands;

final class SquadConfiguration
{
  /** @param array<int,?int> $formation */
  private function __construct(
    public readonly string $name,
    public readonly array $formation,
  ) {}

  /** @param array<string,mixed> $request */
  public static function fromRequest(array $request): self
  {
    $keys = array_keys($request);
    sort($keys, SORT_STRING);
    if ($keys !== ['formation', 'name']) {
      throw new SquadValidationException('Request must contain exactly name and formation.');
    }

    if (!is_string($request['name'])) throw new SquadValidationException('Squad name must be a string.');
    $name = trim($request['name']);
    preg_match_all('/./us', $name, $characters);
    if ($name === '' || count($characters[0]) > 128) {
      throw new SquadValidationException('Squad name must contain between 1 and 128 characters.');
    }

    $formation = $request['formation'];
    if (!is_array($formation) || !array_is_list($formation) || count($formation) !== 9) {
      throw new SquadValidationException('Formation must contain exactly nine positions.');
    }

    $normalized = [];
    $seen = [];
    foreach ($formation as $value) {
      if ($value === null) {
        $normalized[] = null;
        continue;
      }
      if (!is_string($value) || !preg_match('/^[1-9][0-9]*$/D', $value)) {
        throw new SquadValidationException('Formation unit IDs must be canonical positive ID strings or null.');
      }
      $id = (int)$value;
      if ($id <= 0 || (string)$id !== $value || isset($seen[$value])) {
        throw new SquadValidationException('Formation contains an invalid or duplicate unit ID.');
      }
      $seen[$value] = true;
      $normalized[] = $id;
    }

    return new self($name, $normalized);
  }

  /** @return array{name:string,formation:array<int,?string>} */
  public function canonicalRequest(): array
  {
    return [
      'name' => $this->name,
      'formation' => array_map(static fn(?int $id): ?string => $id !== null ? (string)$id : null, $this->formation),
    ];
  }

  /** @return array<int,int> */
  public function unitIds(): array
  {
    return array_values(array_filter($this->formation, static fn(?int $id): bool => $id !== null));
  }
}
