<?php
declare(strict_types=1);

namespace DiceGoblins\Content;

final class ClientContentProjector
{
  private const REGION_FIELDS = ['id', 'display_name', 'description', 'art_key'];

  /** @return array{revision: string, content: array{regions: array<string, array<string, mixed>>}} */
  public function project(ContentRegistry $registry): array
  {
    $regions = [];
    foreach ($registry->definitionsOfType('region') as $id => $definition) {
      $regions[$id] = array_intersect_key($definition, array_flip(self::REGION_FIELDS));
    }
    ksort($regions, SORT_STRING);
    return ['revision' => $registry->revision(), 'content' => ['regions' => $regions]];
  }
}
