<?php
declare(strict_types=1);

namespace DiceGoblins\Tests\Support;

use DiceGoblins\Content\ContentRegistry;

final class ContextualEncounterFixture
{
  public const ENCOUNTER_ID = 'encounter.test_contextual_shrine';
  public const ITEM_ID = 'item.test_ritual_wick';
  public const GENERATION_ID = 'run_generation.test_contextual_shrine';

  /** @return list<array<string,mixed>> */
  public static function definitions(): array
  {
    $definitions = ShrineContentFixture::definitions();
    foreach ($definitions as &$definition) {
      if ($definition['id'] === ShrineContentFixture::ENCOUNTER_ID) {
        $definition['id'] = self::ENCOUNTER_ID;
        $definition['display_name'] = 'Test Wick Shrine';
        $definition['description'] = 'A test-only rite awaits a wick.';
        $definition['prompt'] = 'Continue or offer a wick?';
        $definition['actions'] = [
          ['id' => 'continue', 'label' => 'CONTINUE', 'effect' => ['type' => 'restore_participating_units_to_max']],
          ['id' => 'offer_wick', 'label' => 'OFFER WICK', 'effect' => [
            'type' => 'advance_step', 'step_id' => 'kindled', 'item_id' => self::ITEM_ID, 'quantity' => 1,
          ]],
        ];
        $definition['steps'] = [[
          'id' => 'kindled', 'body' => 'The wick burns steadily.', 'prompt' => 'Complete the rite?',
          'actions' => [['id' => 'complete_rite', 'label' => 'COMPLETE RITE',
            'effect' => ['type' => 'restore_participating_units_to_max']]],
        ]];
      }
      if ($definition['id'] === ShrineContentFixture::GENERATION_ID) {
        $definition['id'] = self::GENERATION_ID;
        $definition['nodes'][3]['encounter_id'] = self::ENCOUNTER_ID;
      }
    }
    unset($definition);
    $definitions[] = [
      'id' => self::ITEM_ID, 'type' => 'item', 'display_name' => 'Test Ritual Wick',
      'description' => 'A test-only material for the wick shrine.', 'category' => 'material',
      'rarity' => 'common', 'icon_key' => 'icon_item_field_poultice', 'stackable' => true,
    ];
    return $definitions;
  }

  public static function registry(): ContentRegistry
  {
    $root = sys_get_temp_dir() . '/dice-goblins-contextual-' . bin2hex(random_bytes(6));
    mkdir($root, 0777, true);
    try {
      file_put_contents($root . '/content.json', json_encode(['definitions' => self::definitions()], JSON_THROW_ON_ERROR));
      return ContentRegistry::load($root);
    } finally {
      unlink($root . '/content.json');
      rmdir($root);
    }
  }
}
