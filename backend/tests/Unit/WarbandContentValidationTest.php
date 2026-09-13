<?php
declare(strict_types=1);

namespace DiceGoblins\Tests\Unit;

use DiceGoblins\Content\ContentRegistry;
use DiceGoblins\Content\ContentValidationException;
use PHPUnit\Framework\TestCase;

final class WarbandContentValidationTest extends TestCase
{
  /** @var list<string> */
  private array $temporaryRoots = [];

  protected function tearDown(): void
  {
    foreach ($this->temporaryRoots as $root) $this->removeTree($root);
  }

  /** @dataProvider invalidNamespaceProvider */
  public function testWarbandDefinitionsRequireTheirStableIdNamespace(array $definition, string $message): void
  {
    $this->assertInvalid([...$this->baseDefinitions(), $definition], $message);
  }

  public function invalidNamespaceProvider(): array
  {
    return [
      'kin' => [$this->kin(['id' => 'unit_type.goblin']), 'kin. namespace'],
      'unit type' => [$this->unitType(['id' => 'kin.bruiser']), 'unit_type. namespace'],
      'ability' => [$this->ability(['id' => 'unit_type.attack']), 'ability. namespace'],
      'material' => [$this->material(['id' => 'dice_profile.cardboard']), 'dice_material. namespace'],
      'aspect' => [$this->aspect(['id' => 'dice_profile.striking']), 'dice_aspect. namespace'],
      'profile' => [$this->profile(['id' => 'dice_material.cardboard']), 'dice_profile. namespace'],
    ];
  }

  public function testUnitStatsAndAbilitySlotConfigurationAreValidated(): void
  {
    $stats = ['hp' => 10, 'attack' => 2, 'defense' => 2, 'precision' => 4];
    $this->assertInvalid([...$this->baseDefinitions(), $this->ability(), $this->unitType(['base_stats' => $stats])], 'exactly HP, Attack, Defense, Precision, and Resolve');
    $this->assertInvalid([...$this->baseDefinitions(), $this->ability(), $this->unitType(['description' => ''])], "field 'description' must be a non-empty string");
    $this->assertInvalid([...$this->baseDefinitions(), $this->ability(['dice_slot_count' => 0])], 'dice_slot_count');
    $this->assertInvalid([...$this->baseDefinitions(), $this->ability(['kind' => 'passive', 'dice_slot_count' => 1])], 'dice_slot_count');
    $this->assertInvalid([...$this->baseDefinitions(), $this->ability(['handler_config' => ['not-an-object']])], "field 'handler_config' must be an object");
    $this->assertInvalid([...$this->baseDefinitions(), $this->aspect(['effect_config' => 'not-an-object'])], "field 'effect_config' must be an object");
  }

  public function testUnitAbilityReferencesMustExist(): void
  {
    $this->assertInvalid([...$this->baseDefinitions(), $this->unitType()], "references missing ability 'ability.example'");
  }

  public function testProfileReferencesAndSupportedSizesAreValidated(): void
  {
    $base = [...$this->baseDefinitions(), $this->material(), $this->aspect()];
    $this->assertInvalid([...$this->baseDefinitions(), $this->profile()], "references missing dice_material 'dice_material.cardboard'");
    $this->assertInvalid([...$this->baseDefinitions(), $this->material(), $this->profile(['aspect_ids' => ['dice_aspect.missing']])], "references missing dice_aspect 'dice_aspect.missing'");
    $this->assertInvalid([...$base, $this->profile(['allowed_sizes' => [7]])], 'unsupported die size');
    $this->assertInvalid([...$base, $this->profile(['rarity' => 'mythic'])], "field 'rarity' must be one of");
    $this->assertInvalid([...$base, $this->profile(['aspect_ids' => ['dice_aspect.striking', 'dice_aspect.striking']])], 'duplicate reference');
  }

  public function testReferencesCannotMasqueradeAsAnotherContentType(): void
  {
    $this->assertInvalid([
      ...$this->baseDefinitions(),
      $this->material(),
      $this->aspect(),
      $this->profile(['material_id' => 'dice_aspect.striking']),
    ], "field 'material_id' must use the dice_material. namespace");
  }

  public function testProfileSizesMustBeAllowedByMaterialAndEveryAspect(): void
  {
    $this->assertInvalid([
      ...$this->baseDefinitions(),
      $this->material(['allowed_sizes' => [4, 6]]),
      $this->aspect(['allowed_sizes' => [4, 6]]),
      $this->profile(['allowed_sizes' => [8]]),
    ], 'material dice_material.cardboard disallows');

    $this->assertInvalid([
      ...$this->baseDefinitions(),
      $this->material(['allowed_sizes' => [4, 6]]),
      $this->aspect(['allowed_sizes' => [4]]),
      $this->profile(['allowed_sizes' => [6]]),
    ], 'aspect dice_aspect.striking disallows');

    $this->assertInvalid([
      ...$this->baseDefinitions(),
      $this->material(['allowed_sizes' => [4]]),
      $this->aspect(['allowed_sizes' => [6]]),
      $this->profile(['allowed_sizes' => [4]]),
    ], 'has no legal effective die sizes');
  }

  public function testTypedRegistryHelperRejectsWrongDefinitionType(): void
  {
    $registry = ContentRegistry::load(dirname(__DIR__, 2) . '/content');
    $this->expectException(ContentValidationException::class);
    $this->expectExceptionMessage("is not type 'kin'");
    $registry->kin('region.the_farm');
  }

  /** @return list<array<string,mixed>> */
  private function baseDefinitions(): array
  {
    return [
      ['id' => 'config.gameplay', 'type' => 'gameplay_config', 'starting_energy' => 10, 'energy_normal_max' => 10, 'energy_regeneration_per_hour' => 1, 'starting_region_id' => 'region.farm'],
      ['id' => 'region.farm', 'type' => 'region', 'display_name' => 'Farm', 'art_key' => 'farm', 'run_generation_id' => 'run_generation.test'],
      ['id' => 'run_node_type.test_start', 'type' => 'run_node_type', 'display_name' => 'Start', 'description' => 'Start.', 'icon_key' => 'start'],
      ['id' => 'run_node_type.exit', 'type' => 'run_node_type', 'display_name' => 'Exit', 'description' => 'Exit.', 'icon_key' => 'exit'],
      [
        'id' => 'run_generation.test', 'type' => 'run_generation', 'algorithm' => 'fixed_graph_v1', 'start_node_key' => 'start',
        'nodes' => [
          ['key' => 'start', 'node_type_id' => 'run_node_type.test_start', 'position' => ['column' => 0, 'row' => 0]],
          ['key' => 'exit', 'node_type_id' => 'run_node_type.exit', 'position' => ['column' => 1, 'row' => 0]],
        ],
        'edges' => [['from' => 'start', 'to' => 'exit']],
      ],
    ];
  }

  /** @param array<string,mixed> $overrides
   *  @return array<string,mixed>
   */
  private function kin(array $overrides = []): array
  {
    return array_replace([
      'id' => 'kin.goblin', 'type' => 'kin', 'display_name' => 'Goblin', 'description' => 'Goblin kin.', 'art_key' => 'goblin', 'trait_summary' => 'No modifier.',
      'stat_modifiers' => ['hp' => 0, 'attack' => 0, 'defense' => 0, 'precision' => 0, 'resolve' => 0],
    ], $overrides);
  }

  /** @param array<string,mixed> $overrides
   *  @return array<string,mixed>
   */
  private function unitType(array $overrides = []): array
  {
    return array_replace([
      'id' => 'unit_type.bruiser', 'type' => 'unit_type', 'display_name' => 'Bruiser', 'description' => 'A bruiser.', 'art_key' => 'goblin_bruiser', 'role' => 'frontline', 'tier' => 1,
      'base_stats' => ['hp' => 10, 'attack' => 2, 'defense' => 2, 'precision' => 4, 'resolve' => 4],
      'growth_per_level' => ['hp' => 1, 'attack' => 1, 'defense' => 1, 'precision' => 1, 'resolve' => 1],
      'ability_ids' => ['ability.example'],
    ], $overrides);
  }

  /** @param array<string,mixed> $overrides
   *  @return array<string,mixed>
   */
  private function ability(array $overrides = []): array
  {
    return array_replace([
      'id' => 'ability.example', 'type' => 'ability', 'kind' => 'active', 'display_name' => 'Example', 'description' => 'An example.', 'icon_key' => 'example', 'dice_slot_count' => 1,
      'action_delay' => 4, 'resolution_priority' => 1, 'target_rule' => 'enemy_front_prefer', 'handler_id' => 'example', 'handler_config' => ['power_ratio' => 1],
    ], $overrides);
  }

  /** @param array<string,mixed> $overrides
   *  @return array<string,mixed>
   */
  private function material(array $overrides = []): array
  {
    return array_replace([
      'id' => 'dice_material.cardboard', 'type' => 'dice_material', 'display_name' => 'Cardboard', 'description' => 'Cardboard.', 'art_key' => 'cardboard', 'allowed_sizes' => [4, 6],
    ], $overrides);
  }

  /** @param array<string,mixed> $overrides
   *  @return array<string,mixed>
   */
  private function aspect(array $overrides = []): array
  {
    return array_replace([
      'id' => 'dice_aspect.striking', 'type' => 'dice_aspect', 'display_name' => 'Striking', 'description' => 'Striking.', 'allowed_sizes' => [4, 6],
      'effect_id' => 'flat_damage_bonus', 'effect_config' => ['amount' => 1],
    ], $overrides);
  }

  /** @param array<string,mixed> $overrides
   *  @return array<string,mixed>
   */
  private function profile(array $overrides = []): array
  {
    return array_replace([
      'id' => 'dice_profile.cardboard_striking', 'type' => 'dice_profile', 'display_name' => 'Striking Cardboard', 'material_id' => 'dice_material.cardboard',
      'rarity' => 'common', 'aspect_ids' => ['dice_aspect.striking'], 'allowed_sizes' => [4, 6],
    ], $overrides);
  }

  /** @param list<array<string,mixed>> $definitions */
  private function assertInvalid(array $definitions, string $message): void
  {
    $root = sys_get_temp_dir() . '/dice-goblins-warband-content-' . bin2hex(random_bytes(6));
    mkdir($root, 0777, true);
    $this->temporaryRoots[] = $root;
    file_put_contents($root . '/content.json', json_encode(['definitions' => $definitions], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    try {
      ContentRegistry::load($root);
      $this->fail('Expected invalid Warband content to be rejected.');
    } catch (ContentValidationException $e) {
      $this->assertStringContainsString($message, $e->getMessage());
    }
  }

  private function removeTree(string $root): void
  {
    if (!is_dir($root)) return;
    $items = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($items as $item) $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    rmdir($root);
  }
}
