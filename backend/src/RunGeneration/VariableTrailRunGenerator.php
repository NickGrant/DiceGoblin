<?php
declare(strict_types=1);

namespace DiceGoblins\RunGeneration;

use Closure;
use InvalidArgumentException;

/** Adds authored optional side trails to an otherwise fixed, validated run graph. */
final class VariableTrailRunGenerator implements RunGraphGenerator
{
  public function __construct(
    private readonly FixedGraphRunGenerator $base = new FixedGraphRunGenerator(),
    private readonly GeneratedRunGraphValidator $validator = new GeneratedRunGraphValidator(),
    private readonly ?Closure $choose = null,
  ) {}

  public function generate(array $generationDefinition): array
  {
    $graph = $this->base->generate($generationDefinition);
    $config = $generationDefinition['variable_trails'] ?? null;
    if ($config === null) return $graph;

    $indexByKey = [];
    foreach ($generationDefinition['nodes'] as $index => $node) $indexByKey[$node['key']] = $index;
    $attachments = $config['attachments'];
    $count = $this->pick($config['minimum'], $config['maximum']);
    for ($index = count($attachments) - 1; $index > 0; $index--) {
      $swap = $this->pick(0, $index);
      [$attachments[$index], $attachments[$swap]] = [$attachments[$swap], $attachments[$index]];
    }
    foreach (array_slice($attachments, 0, $count) as $attachment) {
      $templates = $config['template_keys'];
      $templateKey = $templates[$this->pick(0, count($templates) - 1)];
      $template = $graph['nodes'][$indexByKey[$templateKey]];
      $nodeIndex = count($graph['nodes']);
      $graph['nodes'][] = [
        ...$template,
        'node_index' => $nodeIndex,
        'status' => 'locked',
        'generated_metadata' => ['position' => $attachment['position']],
      ];
      $graph['edges'][] = [
        'from_node_index' => $indexByKey[$attachment['from']],
        'to_node_index' => $nodeIndex,
        'generated_metadata' => null,
      ];
    }
    $this->validator->assertValid($graph);
    return $graph;
  }

  private function pick(int $minimum, int $maximum): int
  {
    $value = $this->choose !== null ? ($this->choose)($minimum, $maximum) : random_int($minimum, $maximum);
    if (!is_int($value) || $value < $minimum || $value > $maximum) {
      throw new InvalidArgumentException('Trail generator selection is outside the authored range.');
    }
    return $value;
  }
}
