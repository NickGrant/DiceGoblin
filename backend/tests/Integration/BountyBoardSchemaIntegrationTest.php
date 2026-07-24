<?php
declare(strict_types=1);

namespace DiceGoblins\Tests\Integration;

use DiceGoblins\Tests\Support\IntegrationTestCase;

final class BountyBoardSchemaIntegrationTest extends IntegrationTestCase
{
  protected function integrationSkipMessage(): string
  {
    return 'Set TEST_DB_DSN to run bounty board schema integration tests.';
  }

  public function testBountyDefinitionCanBeAcceptedByUser(): void
  {
    $userId = $this->insertUser('bounty_schema', 'Bounty Schema User');
    $slug = 'clear-farm-once-' . $userId;

    $definition = $this->pdo?->prepare('
      INSERT INTO `bounty_definitions` (`slug`, `title`, `description`, `category`, `objective_json`, `reward_json`)
      VALUES (?, ?, ?, ?, ?, ?)
    ');
    $definition?->execute([
      $slug,
      'Clear The Farm',
      'Complete a Farm run.',
      'region',
      json_encode(['event' => 'run_completed', 'region_slug' => 'the_farm', 'target' => 1], JSON_UNESCAPED_SLASHES),
      json_encode(['currency_soft' => 25], JSON_UNESCAPED_SLASHES),
    ]);
    $definitionId = (int)$this->pdo?->lastInsertId();

    $accepted = $this->pdo?->prepare('
      INSERT INTO `user_bounties` (`user_id`, `bounty_definition_id`, `progress_json`)
      VALUES (?, ?, ?)
    ');
    $accepted?->execute([
      $userId,
      $definitionId,
      json_encode(['current' => 0, 'target' => 1], JSON_UNESCAPED_SLASHES),
    ]);

    $select = $this->pdo?->prepare('
      SELECT ub.`status`, bd.`slug`, bd.`category`, ub.`progress_json`
      FROM `user_bounties` ub
      JOIN `bounty_definitions` bd ON bd.`id` = ub.`bounty_definition_id`
      WHERE ub.`user_id` = ?
      LIMIT 1
    ');
    $select?->execute([$userId]);
    $row = $select?->fetch() ?: [];

    $this->assertSame('accepted', (string)($row['status'] ?? ''));
    $this->assertSame($slug, (string)($row['slug'] ?? ''));
    $this->assertSame('region', (string)($row['category'] ?? ''));
    $progress = json_decode((string)($row['progress_json'] ?? ''), true);
    $this->assertSame(0, (int)($progress['current'] ?? -1));
    $this->assertSame(1, (int)($progress['target'] ?? -1));
  }
}
