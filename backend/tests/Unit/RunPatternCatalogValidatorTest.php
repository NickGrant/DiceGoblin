<?php
declare(strict_types=1);

namespace DiceGoblins\Tests\Unit;

use DiceGoblins\Services\RunPatternCatalogValidator;
use PHPUnit\Framework\TestCase;

final class RunPatternCatalogValidatorTest extends TestCase
{
  public function testDefaultCatalogValidatesInitialPatternSourceFiles(): void
  {
    $result = (new RunPatternCatalogValidator())->validateDefaultCatalog();

    $this->assertTrue($result['valid'], implode("\n", $result['errors']));
    $this->assertSame([], $result['errors']);
    $this->assertSame(6, $result['pattern_count']);
    $this->assertSame(12, $result['rule_count']);
    $this->assertSame(2, $result['profile_count']);
    $this->assertSame(10, $result['variant_count']);
    $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $result['catalog_hash']);
  }

  public function testValidatorReportsUnknownRulePatternAndMissingFallback(): void
  {
    $root = $this->makeCatalogRoot();
    $this->writeJson($root . '/shared/patterns.json', [
      'catalog_version' => 1,
      'patterns' => [
        $this->minimalStartPattern(),
      ],
    ]);
    $this->writeJson($root . '/mountains/rules.json', [
      'region_slug' => 'mountains',
      'generator_version' => 'pattern-v1',
      'rules' => [
        [
          'pattern_slug' => 'missing_pattern',
          'pattern_version' => 1,
          'base_weight' => 100,
          'allowed_phase' => 'start',
          'min_depth' => 0,
          'max_depth' => 0,
          'max_per_run' => 1,
          'cooldown_patterns' => 0,
          'enabled' => true,
        ],
      ],
    ]);
    $this->writeJson($root . '/profiles/mountains.pattern-v1.json', [
      ...$this->minimalProfile(),
      'requirements' => [
        'fallback_patterns' => ['missing_pattern'],
      ],
    ]);

    $result = (new RunPatternCatalogValidator())->validateDirectory($root);

    $this->assertFalse($result['valid']);
    $this->assertContains('Rule references unknown pattern missing_pattern@1.', $result['errors']);
    $this->assertContains('mountains profile references missing fallback pattern missing_pattern.', $result['errors']);
  }

  public function testValidatorRejectsUnreachablePatternNodesAndCycles(): void
  {
    $root = $this->makeCatalogRoot();
    $this->writeJson($root . '/shared/patterns.json', [
      'catalog_version' => 1,
      'patterns' => [
        [
          'slug' => 'bad_branch',
          'version' => 1,
          'status' => 'enabled',
          'tags' => ['branch'],
          'allowed_transforms' => ['identity'],
          'node_cost' => 3,
          'nodes' => [
            ['key' => 'entry', 'type' => 'combat', 'x' => 0, 'y' => 0],
            ['key' => 'exit', 'type' => 'loot', 'x' => 1, 'y' => 0],
            ['key' => 'orphan', 'type' => 'rest', 'x' => 2, 'y' => 0],
          ],
          'edges' => [
            ['from' => 'entry', 'to' => 'exit'],
            ['from' => 'exit', 'to' => 'entry'],
          ],
          'sockets' => [
            ['id' => 'entry_left', 'kind' => 'entry', 'node' => 'entry', 'direction' => 'left', 'path_eligibility' => ['branch']],
            ['id' => 'exit_right', 'kind' => 'exit', 'node' => 'orphan', 'direction' => 'right', 'path_eligibility' => ['branch']],
          ],
        ],
      ],
    ]);
    $this->writeJson($root . '/mountains/rules.json', [
      'region_slug' => 'mountains',
      'generator_version' => 'pattern-v1',
      'rules' => [
        [
          'pattern_slug' => 'bad_branch',
          'pattern_version' => 1,
          'base_weight' => 10,
          'allowed_phase' => 'branch',
          'min_depth' => 1,
          'max_depth' => null,
          'max_per_run' => 1,
          'cooldown_patterns' => 0,
          'enabled' => true,
        ],
      ],
    ]);
    $this->writeJson($root . '/profiles/mountains.pattern-v1.json', $this->minimalProfile());

    $result = (new RunPatternCatalogValidator())->validateDirectory($root);

    $this->assertFalse($result['valid']);
    $this->assertContains('bad_branch@1 contains an internal cycle.', $result['errors']);
    $this->assertContains('bad_branch@1 has nodes that are not reachable from an entry socket.', $result['errors']);
    $this->assertContains('bad_branch@1 has exit sockets that are not reachable from every entry socket.', $result['errors']);
  }

  private function makeCatalogRoot(): string
  {
    $root = sys_get_temp_dir() . '/dice-goblin-run-patterns-' . bin2hex(random_bytes(6));
    mkdir($root . '/shared', 0777, true);
    mkdir($root . '/mountains', 0777, true);
    mkdir($root . '/profiles', 0777, true);
    return $root;
  }

  /**
   * @param array<string,mixed> $data
   */
  private function writeJson(string $path, array $data): void
  {
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    $this->assertIsString($json);
    file_put_contents($path, $json);
  }

  /**
   * @return array<string,mixed>
   */
  private function minimalStartPattern(): array
  {
    return [
      'slug' => 'shared_start_single',
      'version' => 1,
      'status' => 'enabled',
      'tags' => ['start'],
      'allowed_transforms' => ['identity'],
      'node_cost' => 1,
      'nodes' => [
        ['key' => 'start', 'type' => 'combat', 'x' => 0, 'y' => 0],
      ],
      'edges' => [],
      'sockets' => [
        ['id' => 'exit_right', 'kind' => 'exit', 'node' => 'start', 'direction' => 'right', 'path_eligibility' => ['spine']],
      ],
    ];
  }

  /**
   * @return array<string,mixed>
   */
  private function minimalProfile(): array
  {
    $budgets = [];
    foreach ([
      'total_nodes',
      'spine_nodes',
      'branch_nodes',
      'combat_nodes',
      'reward_nodes',
      'recovery_nodes',
      'hazard_nodes',
      'shrine_nodes',
      'chaos_nodes',
      'branch_count',
      'frontier_count',
      'pattern_instances',
    ] as $budgetKey) {
      $budgets[$budgetKey] = ['min' => 0, 'target' => 1, 'max' => 2, 'hard' => false];
    }

    return [
      'region_slug' => 'mountains',
      'generator_version' => 'pattern-v1',
      'profile_version' => 1,
      'enabled' => true,
      'bounds' => ['min_col' => 0, 'max_col' => 4, 'min_row' => 0, 'max_row' => 4],
      'budgets' => $budgets,
      'requirements' => ['fallback_patterns' => ['shared_start_single']],
      'retry_policy' => ['candidate_retries' => 1, 'backtrack_depth' => 1, 'generation_attempts' => 1],
      'weight_policy' => [],
    ];
  }
}
