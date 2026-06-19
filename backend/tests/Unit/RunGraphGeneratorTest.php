<?php
declare(strict_types=1);

namespace DiceGoblins\Tests\Unit;

use DiceGoblins\Services\RunGraphGenerator;
use PDO;
use PHPUnit\Framework\TestCase;

final class RunGraphGeneratorTest extends TestCase
{
  private PDO $pdo;

  protected function setUp(): void
  {
    $this->pdo = new PDO('sqlite::memory:');
    $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $this->pdo->exec('
      CREATE TABLE encounter_templates (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        region_id INTEGER NOT NULL,
        slug TEXT NOT NULL,
        reward_profile_json TEXT NULL
      )
    ');

    $insert = $this->pdo->prepare('INSERT INTO encounter_templates (region_id, slug, reward_profile_json) VALUES (?, ?, ?)');
    $insert->execute([1, 'the_farm_mud_combat_1', '{"type":"combat"}']);
    $insert->execute([1, 'the_farm_loot_1', '{"type":"loot"}']);
    $insert->execute([1, 'the_farm_rest_1', '{"type":"rest"}']);
    $insert->execute([1, 'the_farm_mud_boss_1', '{"type":"boss"}']);
  }

  public function testApplyTreasureSenseRevealAddsAtMostOneLootBranch(): void
  {
    $generator = new RunGraphGenerator($this->pdo);
    $graph = $generator->generate(1, 'the_farm', 'seed-123');

    $revealed = $generator->applyTreasureSenseReveal(1, $graph, 'seed-123', 1.0);

    $revealedNodes = array_values(array_filter(
      $revealed['nodes'],
      static fn(array $node): bool => (bool)((is_array($node['meta'] ?? null) ? $node['meta'] : [])['revealed_by_treasure_sense'] ?? false)
    ));

    $this->assertCount(1, $revealedNodes);
    $this->assertSame('loot', (string)$revealedNodes[0]['node_type']);
    $this->assertNotNull($revealedNodes[0]['encounter_template_id'] ?? null);
  }

  public function testApplyTreasureSenseRevealSkipsWhenChanceFails(): void
  {
    $generator = new RunGraphGenerator($this->pdo);
    $graph = $generator->generate(1, 'the_farm', 'seed-456');

    $revealed = $generator->applyTreasureSenseReveal(1, $graph, 'seed-456', 0.0);

    $revealedCount = count(array_filter(
      $revealed['nodes'],
      static fn(array $node): bool => (bool)((is_array($node['meta'] ?? null) ? $node['meta'] : [])['revealed_by_treasure_sense'] ?? false)
    ));

    $this->assertSame(0, $revealedCount);
  }
}
