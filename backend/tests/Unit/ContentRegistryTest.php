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
    $this->assertSame(12, $registry->energyRegenerationPerHour());
    $this->assertSame([
      'id' => 'region.the_farm',
      'type' => 'region',
      'display_name' => 'The Farm',
      'art_key' => 'farm',
    ], $registry->definition('region.the_farm'));
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
    $config = fn(string $id = 'config.gameplay', string $region = 'region.the_farm'): array => [
      'id' => $id,
      'type' => 'gameplay_config',
      'starting_energy' => 10,
      'energy_normal_max' => 20,
      'energy_regeneration_per_hour' => 12,
      'starting_region_id' => $region,
    ];
    $region = ['id' => 'region.the_farm', 'type' => 'region', 'display_name' => 'Farm', 'art_key' => 'farm'];
    return [
      'shape' => [['one.json' => ['definitions' => 'nope']], 'definitions array'],
      'invalid id' => [['one.json' => ['definitions' => [$config('Bad ID')]]], 'invalid stable id'],
      'duplicate id' => [[
        'a.json' => ['definitions' => [$config(), $region]],
        'b.json' => ['definitions' => [$region]],
      ], "Duplicate stable id 'region.the_farm'"],
      'range' => [['one.json' => ['definitions' => [array_merge($config(), ['starting_energy' => -1]), $region]]], 'starting_energy'],
      'normal max range' => [['one.json' => ['definitions' => [array_merge($config(), ['energy_normal_max' => 0]), $region]]], 'energy_normal_max'],
      'regen rate interval' => [['one.json' => ['definitions' => [array_merge($config(), ['energy_regeneration_per_hour' => 7]), $region]]], 'divide evenly'],
      'broken reference' => [['one.json' => ['definitions' => [$config('config.gameplay', 'region.missing'), $region]]], 'references missing region'],
    ];
  }

  public function testProjectionIsAllowlistedAndSharesRevision(): void
  {
    $root = $this->copyCanonicalContent();
    $regionPath = $root . '/regions/the-farm.json';
    $document = json_decode((string)file_get_contents($regionPath), true, 512, JSON_THROW_ON_ERROR);
    $document['definitions'][0]['new_private_field'] = 'must not leak';
    file_put_contents($regionPath, json_encode($document, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

    $registry = ContentRegistry::load($root);
    $projection = (new ClientContentProjector())->project($registry);
    $encoded = json_encode($projection, JSON_THROW_ON_ERROR);

    $this->assertSame($registry->revision(), $projection['revision']);
    $this->assertSame([
      'id' => 'region.the_farm',
      'display_name' => 'The Farm',
      'art_key' => 'farm',
    ], $projection['content']['regions']['region.the_farm']);
    $this->assertStringNotContainsString('starting_energy', $encoded);
    $this->assertStringNotContainsString('starting_region_id', $encoded);
    $this->assertStringNotContainsString('new_private_field', $encoded);
  }

  public function testRevisionIsIndependentOfFileOrganizationAndChangesWithCanonicalContent(): void
  {
    $canonical = ContentRegistry::load($this->canonicalRoot());
    $reorganized = $this->rootWithFiles([
      'everything.json' => ['definitions' => [
        $canonical->definition('region.the_farm'),
        $canonical->definition('config.gameplay'),
      ]],
    ]);
    $this->assertSame($canonical->revision(), ContentRegistry::load($reorganized)->revision());

    $changed = $this->rootWithFiles([
      'everything.json' => ['definitions' => [
        array_merge($canonical->definition('config.gameplay'), ['starting_energy' => 11]),
        $canonical->definition('region.the_farm'),
      ]],
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
      'config/gameplay.json' => ['definitions' => [$registry->definition('config.gameplay')]],
      'regions/the-farm.json' => ['definitions' => [$registry->definition('region.the_farm')]],
    ]);
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
