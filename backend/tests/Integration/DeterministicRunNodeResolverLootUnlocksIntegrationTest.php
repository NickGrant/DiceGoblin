<?php
declare(strict_types=1);

namespace DiceGoblins\Tests\Integration;

use DiceGoblins\Combat\Engine\DeterministicRunNodeResolver;
use DiceGoblins\Tests\Support\IntegrationTestCase;
use ReflectionClass;

final class DeterministicRunNodeResolverLootUnlocksIntegrationTest extends IntegrationTestCase
{
  protected function integrationSkipMessage(): string
  {
    return 'Set TEST_DB_DSN to run resolver unlock integration tests.';
  }

  public function testUnitRewardSelectionOnlyReturnsUnlockedUnitTypes(): void
  {
    $userId = $this->insertUser('qa_loot_unlocks', 'QA Loot Unlocks');
    $insert = $this->pdo?->prepare('
      INSERT INTO `user_unlocks` (`user_id`, `unlock_namespace`, `unlock_key`)
      VALUES (?, \'unit_type\', ?)
    ');
    $insert?->execute([$userId, 'frontline_bruiser_t1']);
    $insert?->execute([$userId, 'backline_marksman_t1']);

    $resolver = new DeterministicRunNodeResolver($this->pdo);
    $ref = new ReflectionClass($resolver);
    $method = $ref->getMethod('pickUnitTypeSlug');
    $method->setAccessible(true);

    $results = [];
    foreach (range(1, 20) as $seed) {
      $state = hash('sha256', 'loot-unlock-test-' . $seed);
      $results[] = $method->invokeArgs($resolver, [$userId, &$state]);
    }

    $results = array_values(array_unique(array_filter($results, static fn($slug): bool => is_string($slug) && $slug !== '')));
    sort($results);

    $this->assertSame([
      'backline_marksman_t1',
      'frontline_bruiser_t1',
    ], $results);
  }
}
