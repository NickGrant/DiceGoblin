<?php
declare(strict_types=1);

namespace DiceGoblins\Application;

use DiceGoblins\Content\ContentRegistry;
use DiceGoblins\Content\ContentValidationException;

final class WarbandContentGuard
{
  public function __construct(private readonly ContentRegistry $content) {}

  /** @return array<string,mixed> */
  public function unitType(string $id): array
  {
    return $this->resolve(fn(): array => $this->content->unitType($id));
  }

  /** @return array<string,mixed> */
  public function kin(string $id): array
  {
    return $this->resolve(fn(): array => $this->content->kin($id));
  }

  /** @return array<string,mixed> */
  public function ability(string $id): array
  {
    return $this->resolve(fn(): array => $this->content->ability($id));
  }

  /** @return array<string,mixed> */
  public function diceProfile(string $id): array
  {
    return $this->resolve(fn(): array => $this->content->diceProfile($id));
  }

  /** @param callable():array<string,mixed> $resolver
   *  @return array<string,mixed>
   */
  private function resolve(callable $resolver): array
  {
    try {
      return $resolver();
    } catch (ContentValidationException $e) {
      throw new WarbandIntegrityException('Persisted Warband state references invalid authored content.', 0, $e);
    }
  }
}
