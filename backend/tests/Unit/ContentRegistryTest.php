<?php
declare(strict_types=1);

namespace DiceGoblins\Tests\Unit;

use DiceGoblins\Content\ClientContentProjector;
use DiceGoblins\Content\ContentRegistry;
use DiceGoblins\Content\ContentValidationException;
use PHPUnit\Framework\TestCase;

final class ContentRegistryTest extends TestCase
{
  /** @var list<string> */
  private array $temporaryRoots = [];

  protected function tearDown(): void
  {
    foreach ($this->temporaryRoots as $root) $this->removeTree($root);
  }

  public function testCanonicalContentLoadsAcrossFilesByStableIdentity(): void
  {
    $registry = ContentRegistry::load($this->canonicalRoot());

    $this->assertSame(50, $registry->startingEnergy());
    $this->assertSame(50, $registry->energyNormalMaximum());
    $this->assertSame(10, $registry->runEnergyCost());
    $this->assertSame(12.0, $registry->energyRegenerationPerHour());
    $this->assertSame([
      'id' => 'region.the_farm',
      'type' => 'region',
      'display_name' => 'The Farm',
      'art_key' => 'farm',
      'run_generation_id' => 'run_generation.the_farm',
    ], $registry->definition('region.the_farm'));
    $this->assertCount(2, $registry->definitionsOfType('kin'));
    $this->assertCount(20, $registry->definitionsOfType('unit_type'));
    $this->assertCount(31, $registry->definitionsOfType('ability'));
    $this->assertCount(2, $registry->definitionsOfType('enemy_unit_type'));
    $this->assertCount(1, $registry->definitionsOfType('encounter'));
    $this->assertCount(5, $registry->definitionsOfType('dice_material'));
    $this->assertCount(6, $registry->definitionsOfType('dice_aspect'));
    $this->assertCount(11, $registry->definitionsOfType('dice_profile'));
    $this->assertCount(5, $registry->definitionsOfType('run_node_type'));
    $this->assertCount(2, $registry->definitionsOfType('region'));
    $this->assertCount(1, $registry->definitionsOfType('run_generation'));
    $this->assertCount(1, $registry->definitionsOfType('unlock'));
    $this->assertSame('region.mountains', $registry->unlock('unlock.region.mountains')['target_id']);
    $this->assertSame('Pig Kin', $registry->kin('kin.pig')['display_name']);
    $this->assertSame(2, $registry->ability('ability.sleep_dart')['dice_slot_count']);
    $this->assertSame('dice_material.cardboard', $registry->diceProfile('dice_profile.cardboard_plain')['material_id']);
    $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $registry->revision());
  }

  public function testMalformedJsonFailsValidation(): void
  {
    $root = $this->rootWithFiles(['bad.json' => '{']);
    $this->expectException(ContentValidationException::class);
    $this->expectExceptionMessage('Malformed JSON');
    ContentRegistry::load($root);
  }

  /** @dataProvider invalidContentProvider */
  public function testInvalidContentFailsValidation(array $files, string $message): void
  {
    $root = $this->rootWithFiles($files);
    $this->expectException(ContentValidationException::class);
    $this->expectExceptionMessage($message);
    ContentRegistry::load($root);
  }

  public function invalidContentProvider(): array
  {
    $config = fn(string $id = 'config.gameplay', string $region = 'region.test'): array => [
      'id' => $id,
      'type' => 'gameplay_config',
      'starting_energy' => 10,
      'energy_normal_max' => 20,
      'energy_regeneration_per_hour' => 12,
      'run_energy_cost' => 10,
      'starting_region_id' => $region,
    ];
    $region = ['id' => 'region.test', 'type' => 'region', 'display_name' => 'Test', 'art_key' => 'test', 'run_generation_id' => 'run_generation.test'];
    $runContent = $this->minimalRunDefinitions();
    return [
      'shape' => [['one.json' => ['definitions' => 'nope']], 'definitions array'],
      'invalid id' => [['one.json' => ['definitions' => [$config('Bad ID'), $region, ...$runContent]]], 'invalid stable id'],
      'duplicate id' => [[
        'a.json' => ['definitions' => [$config(), $region, ...$runContent]],
        'b.json' => ['definitions' => [$region]],
      ], "Duplicate stable id 'region.test'"],
      'range' => [['one.json' => ['definitions' => [array_merge($config(), ['starting_energy' => -1]), $region, ...$runContent]]], 'starting_energy'],
      'normal max range' => [['one.json' => ['definitions' => [array_merge($config(), ['energy_normal_max' => 0]), $region, ...$runContent]]], 'energy_normal_max'],
      'zero regen rate' => [['one.json' => ['definitions' => [array_merge($config(), ['energy_regeneration_per_hour' => 0]), $region, ...$runContent]]], 'positive number'],
      'negative regen rate' => [['one.json' => ['definitions' => [array_merge($config(), ['energy_regeneration_per_hour' => -2.5]), $region, ...$runContent]]], 'positive number'],
      'zero run cost' => [['one.json' => ['definitions' => [array_merge($config(), ['run_energy_cost' => 0]), $region, ...$runContent]]], 'run_energy_cost'],
      'fractional run cost' => [['one.json' => ['definitions' => [array_merge($config(), ['run_energy_cost' => 1.5]), $region, ...$runContent]]], 'run_energy_cost'],
      'broken reference' => [['one.json' => ['definitions' => [$config('config.gameplay', 'region.missing'), $region, ...$runContent]]], 'references missing region'],
    ];
  }

  public function testPositiveNonEvenAndFractionalRegenerationRatesAreValidAuthoredContent(): void
  {
    foreach ([7, 7.25] as $rate) {
      $root = $this->rootWithFiles([
        "rate-{$rate}.json" => ['definitions' => [
          [
            'id' => 'config.gameplay',
            'type' => 'gameplay_config',
            'starting_energy' => 50,
            'energy_normal_max' => 50,
            'energy_regeneration_per_hour' => $rate,
            'run_energy_cost' => 10,
            'starting_region_id' => 'region.test',
          ],
          ['id' => 'region.test', 'type' => 'region', 'display_name' => 'Test', 'art_key' => 'test', 'run_generation_id' => 'run_generation.test'],
          ...$this->minimalRunDefinitions(),
        ]],
      ]);

      $this->assertSame((float)$rate, ContentRegistry::load($root)->energyRegenerationPerHour());
    }
  }

  public function testProjectionIsAllowlistedAndSharesRevision(): void
  {
    $root = $this->copyCanonicalContent();
    $contentPath = $root . '/everything.json';
    $document = json_decode((string)file_get_contents($contentPath), true, 512, JSON_THROW_ON_ERROR);
    foreach ($document['definitions'] as &$definition) {
      if (($definition['id'] ?? null) === 'ability.basic_attack_melee') {
        $definition['handler_config']['power_ratio'] = 1.01;
      }
    }
    unset($definition);
    file_put_contents($contentPath, json_encode($document, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

    $registry = ContentRegistry::load($root);
    $projection = (new ClientContentProjector())->project($registry);
    $encoded = json_encode($projection, JSON_THROW_ON_ERROR);

    $this->assertSame($registry->revision(), $projection['revision']);
    $this->assertSame([
      'id' => 'region.the_farm',
      'display_name' => 'The Farm',
      'art_key' => 'farm',
    ], $projection['content']['regions']['region.the_farm']);
    $this->assertSame([
      'id' => 'ability.sleep_dart',
      'kind' => 'active',
      'display_name' => 'Sleep Dart',
      'description' => 'Puts an enemy to sleep until damaged.',
      'icon_key' => 'icon_ability_sleep_dart',
      'dice_slot_count' => 2,
    ], $projection['content']['abilities']['ability.sleep_dart']);
    $this->assertSame('dice_material.bone', $projection['content']['dice_profiles']['dice_profile.bone_explosive']['material_id']);
    $this->assertStringNotContainsString('starting_energy', $encoded);
    $this->assertStringNotContainsString('starting_region_id', $encoded);
    foreach (['handler_id', 'handler_config', 'action_delay', 'resolution_priority', 'target_rule', 'effect_id', 'effect_config'] as $privateField) {
      $this->assertStringNotContainsString($privateField, $encoded);
    }
  }

  public function testRevisionIsIndependentOfFileOrganizationAndChangesWithCanonicalContent(): void
  {
    $canonical = ContentRegistry::load($this->canonicalRoot());
    $definitions = $this->canonicalDefinitions($canonical);
    $reorganized = $this->rootWithFiles([
      'everything.json' => ['definitions' => array_reverse($definitions)],
    ]);
    $this->assertSame($canonical->revision(), ContentRegistry::load($reorganized)->revision());

    $changedDefinitions = $definitions;
    foreach ($changedDefinitions as &$definition) {
      if (($definition['id'] ?? null) === 'ability.basic_attack_melee') {
        $definition['handler_config']['power_ratio'] = 1.01;
      }
    }
    unset($definition);
    $changed = $this->rootWithFiles([
      'everything.json' => ['definitions' => $changedDefinitions],
    ]);
    $this->assertNotSame($canonical->revision(), ContentRegistry::load($changed)->revision());
  }

  private function canonicalRoot(): string
  {
    return dirname(__DIR__, 2) . '/content';
  }

  private function copyCanonicalContent(): string
  {
    $registry = ContentRegistry::load($this->canonicalRoot());
    return $this->rootWithFiles([
      'everything.json' => ['definitions' => $this->canonicalDefinitions($registry)],
    ]);
  }

  /** @return list<array<string,mixed>> */
  private function canonicalDefinitions(ContentRegistry $registry): array
  {
    $definitions = [];
    foreach (['gameplay_config', 'region', 'kin', 'unit_type', 'enemy_unit_type', 'encounter', 'ability', 'dice_material', 'dice_aspect', 'dice_profile', 'run_node_type', 'run_generation', 'unlock', 'event', 'reward_definition'] as $type) {
      foreach ($registry->definitionsOfType($type) as $definition) $definitions[] = $definition;
    }
    return $definitions;
  }

  /** @return list<array<string,mixed>> */
  private function minimalRunDefinitions(): array
  {
    return [
      ['id' => 'run_node_type.combat', 'type' => 'run_node_type', 'display_name' => 'Combat', 'description' => 'Fight.', 'icon_key' => 'combat'],
      ['id' => 'run_node_type.exit', 'type' => 'run_node_type', 'display_name' => 'Exit', 'description' => 'Leave.', 'icon_key' => 'exit'],
      [
        'id' => 'run_generation.test',
        'type' => 'run_generation',
        'algorithm' => 'fixed_graph_v1',
        'start_node_key' => 'start',
        'nodes' => [
          ['key' => 'start', 'node_type_id' => 'run_node_type.combat', 'position' => ['column' => 0, 'row' => 0]],
          ['key' => 'exit', 'node_type_id' => 'run_node_type.exit', 'position' => ['column' => 1, 'row' => 0]],
        ],
        'edges' => [['from' => 'start', 'to' => 'exit']],
      ],
    ];
  }

  /** @param array<string, array<mixed>|string> $files */
  private function rootWithFiles(array $files): string
  {
    $root = sys_get_temp_dir() . '/dice-goblins-content-' . bin2hex(random_bytes(6));
    mkdir($root, 0777, true);
    $this->temporaryRoots[] = $root;
    foreach ($files as $relative => $content) {
      $path = $root . '/' . $relative;
      if (!is_dir(dirname($path))) mkdir(dirname($path), 0777, true);
      file_put_contents($path, is_string($content) ? $content : json_encode($content, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    }
    return $root;
  }

  private function removeTree(string $root): void
  {
    if (!is_dir($root)) return;
    $items = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($items as $item) $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    rmdir($root);
  }
}
