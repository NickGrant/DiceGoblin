<?php
declare(strict_types=1);

namespace DiceGoblins\RunGeneration;

interface RunGraphGenerator
{
  /** @param array<string,mixed> $generationDefinition
   *  @return array{nodes:list<array<string,mixed>>,edges:list<array<string,mixed>>}
   */
  public function generate(array $generationDefinition): array;
}
