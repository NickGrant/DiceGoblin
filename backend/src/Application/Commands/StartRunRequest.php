<?php
declare(strict_types=1);

namespace DiceGoblins\Application\Commands;

final class StartRunRequest
{
  private const REGION_ID_PATTERN = '/^region\.[a-z][a-z0-9_]*$/D';

  private function __construct(public readonly string $regionId) {}

  /** @param array<string,mixed> $request */
  public static function fromRequest(array $request): self
  {
    if (array_keys($request) !== ['region_id']) {
      throw new RunStartException('invalid_run_request', 'Run request is invalid.', 422);
    }
    $regionId = $request['region_id'];
    if (!is_string($regionId) || strlen($regionId) > 128 || preg_match(self::REGION_ID_PATTERN, $regionId) !== 1) {
      throw new RunStartException('invalid_run_request', 'Run request is invalid.', 422);
    }
    return new self($regionId);
  }

  /** @return array{region_id:string} */
  public function canonicalRequest(): array
  {
    return ['region_id' => $this->regionId];
  }
}
