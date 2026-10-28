<?php
declare(strict_types=1);

namespace DiceGoblins\Tests\Support;

use DiceGoblins\Content\ContentRegistry;

final class ObjectiveContentFixture
{
  public const ID = 'objective.test.mountains_scout';

  /** @return array<string,mixed> */
  public static function definition(): array
  {
    return ['id' => self::ID, 'type' => 'objective', 'title' => 'Mountains Scout',
      'description' => 'Learn the roads through the Mountains.', 'category' => 'bounty',
      'requirements' => [
        ['id' => 'kobolds', 'label' => 'Defeat kobold skirmishers', 'kind' => 'defeat_enemy_unit_type',
          'target_id' => 'enemy_unit_type.kobold_skirmisher', 'needed' => 10],
        ['id' => 'runs', 'label' => 'Complete Mountains runs', 'kind' => 'complete_region_run',
          'target_id' => 'region.mountains', 'needed' => 2],
        ['id' => 'scout', 'label' => 'Speak with the Farm scout', 'kind' => 'complete_dialogue',
          'target_id' => 'dialogue.camp.farm_scout', 'needed' => 1],
      ],
      'completion_event_id' => 'event.mountains_boss_completed'];
  }

  /** @param list<array<string,mixed>> $objectives @param list<array<string,mixed>> $extraDefinitions */
  public static function registry(array $objectives = [], ?array $primary = null, array $extraDefinitions = []): ContentRegistry
  {
    $definitions = array_values(array_filter(CanonicalContentFixture::definitions(),
      static fn(array $definition): bool => $definition['type'] !== 'objective'));
    $definitions[] = $primary ?? self::definition();
    foreach ($objectives as $objective) $definitions[] = $objective;
    foreach ($extraDefinitions as $definition) $definitions[] = $definition;
    $root = sys_get_temp_dir() . '/dice-goblins-objectives-' . bin2hex(random_bytes(6));
    mkdir($root, 0777, true);
    $path = $root . '/fixture.json';
    file_put_contents($path, json_encode(['definitions' => $definitions], JSON_THROW_ON_ERROR));
    try { return ContentRegistry::load($root); }
    finally { unlink($path); rmdir($root); }
  }
}
