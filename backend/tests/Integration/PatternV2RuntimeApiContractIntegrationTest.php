<?php
declare(strict_types=1);

namespace DiceGoblins\Tests\Integration;

use DiceGoblins\Controllers\ApiController;
use DiceGoblins\Tests\Support\IntegrationTestCase;

final class PatternV2RuntimeApiContractIntegrationTest extends IntegrationTestCase
{
  private string|false $previousPatternV2Regions = false;

  protected function setUp(): void
  {
    $this->previousPatternV2Regions = getenv('RUN_PATTERN_V2_REGIONS');
    putenv('RUN_PATTERN_V2_REGIONS=mountains,swamps');
    $_ENV['RUN_PATTERN_V2_REGIONS'] = 'mountains,swamps';
    $_SERVER['RUN_PATTERN_V2_REGIONS'] = 'mountains,swamps';

    parent::setUp();
  }

  protected function tearDown(): void
  {
    parent::tearDown();

    if ($this->previousPatternV2Regions === false) {
      putenv('RUN_PATTERN_V2_REGIONS');
      unset($_ENV['RUN_PATTERN_V2_REGIONS'], $_SERVER['RUN_PATTERN_V2_REGIONS']);
      return;
    }

    putenv('RUN_PATTERN_V2_REGIONS=' . $this->previousPatternV2Regions);
    $_ENV['RUN_PATTERN_V2_REGIONS'] = $this->previousPatternV2Regions;
    $_SERVER['RUN_PATTERN_V2_REGIONS'] = $this->previousPatternV2Regions;
  }

  protected function integrationSkipMessage(): string
  {
    return 'Set TEST_DB_DSN to run Pattern-V2 runtime API contract integration tests.';
  }

  /**
   * @dataProvider patternV2RegionProvider
   */
  public function testCreateRunAndCurrentRunExposePatternV2RendererContract(string $regionSlug): void
  {
    $userId = $this->insertUser('qa_pattern_v2_api', 'QA Pattern V2 API');
    $regionId = $this->seededRegionId($regionSlug);
    $this->unlockRegion($userId, $regionId);
    $this->insertTeam($userId, 'QA V2 Squad', true);
    $this->setEnergy($userId, 50, 50);
    $this->grantUnlock($userId, 'feature', 'wrong_machine');

    $_SESSION['user_id'] = $userId;
    $_SESSION['csrf_token'] = 'valid_csrf';
    $_SERVER['HTTP_X_CSRF_TOKEN'] = 'valid_csrf';
    $this->setJsonBody(['region_id' => (string)$regionId]);

    $api = new ApiController();
    $createResponse = $this->invoke(fn() => $api->createRun());

    $this->assertSame(200, $createResponse['status'], json_encode($createResponse['body']));
    $this->assertSame(true, $createResponse['body']['ok'] ?? null);

    $currentRunResponse = $this->invoke(fn() => $api->currentRun());
    $this->assertSame(200, $currentRunResponse['status'], json_encode($currentRunResponse['body']));
    $this->assertSame(true, $currentRunResponse['body']['ok'] ?? null);

    $data = is_array($currentRunResponse['body']['data'] ?? null) ? $currentRunResponse['body']['data'] : [];
    $run = is_array($data['run'] ?? null) ? $data['run'] : [];
    $map = is_array($data['map'] ?? null) ? $data['map'] : [];
    $nodes = array_values(array_filter(is_array($map['nodes'] ?? null) ? $map['nodes'] : [], 'is_array'));
    $edges = array_values(array_filter(is_array($map['edges'] ?? null) ? $map['edges'] : [], 'is_array'));

    $this->assertSame('pattern-v2', (string)($run['generator_version'] ?? ''));
    $this->assertSame($regionSlug, (string)($run['region_slug'] ?? ''));
    $this->assertGreaterThanOrEqual(28, count($nodes));
    $this->assertGreaterThanOrEqual(30, count($edges));
    $this->assertGreaterThanOrEqual(5, count(array_unique(array_map(
      static fn(array $node): int => self::nodeGenerationCoordinate($node, 'y'),
      $nodes
    ))));

    $summary = is_array($run['generation_summary'] ?? null) ? $run['generation_summary'] : [];
    $this->assertSame('pattern-v2', (string)($summary['generator_version'] ?? ''));
    $this->assertGreaterThanOrEqual(5, (int)($summary['occupied_rows'] ?? 0));
    $this->assertLessThanOrEqual(20, (int)($summary['occupied_columns'] ?? 999));
    $this->assertGreaterThanOrEqual(2, (int)($summary['branch_count'] ?? 0));

    $firstNodeMeta = $this->decodedNodeMeta($nodes[0]);
    $this->assertSame('pattern-v2', (string)($firstNodeMeta['generation']['generator_version'] ?? ''));
    $this->assertArrayHasKey('x', $firstNodeMeta['generation'] ?? []);
    $this->assertArrayHasKey('y', $firstNodeMeta['generation'] ?? []);

    $this->assertTrue($this->hasWaypointEdge($edges), 'Pattern-V2 current-run payload should expose connector waypoints on at least one edge.');
    $this->assertSame(0, (int)$this->scalar(
      'SELECT COUNT(*) FROM `run_nodes` WHERE `run_id` = ? AND `node_type` = \'connector\'',
      [(int)($run['run_id'] ?? 0)]
    ));
  }

  /** @return array<string,array{0:string}> */
  public static function patternV2RegionProvider(): array
  {
    return [
      'mountains' => ['mountains'],
      'swamps' => ['swamps'],
    ];
  }

  private function seededRegionId(string $slug): int
  {
    $regionId = (int)$this->scalar('SELECT `id` FROM `regions` WHERE `slug` = ? LIMIT 1', [$slug]);
    $this->assertGreaterThan(0, $regionId, sprintf('Seeded region `%s` must exist.', $slug));
    return $regionId;
  }

  /**
   * @param array<string,mixed> $node
   * @return array<string,mixed>
   */
  private function decodedNodeMeta(array $node): array
  {
    $meta = json_decode((string)($node['meta_json'] ?? ''), true);
    $this->assertIsArray($meta);
    return $meta;
  }

  /**
   * @param array<string,mixed> $node
   */
  private static function nodeGenerationCoordinate(array $node, string $key): int
  {
    $meta = json_decode((string)($node['meta_json'] ?? ''), true);
    return (int)($meta['generation'][$key] ?? $meta[$key === 'x' ? 'col' : 'row'] ?? 0);
  }

  /**
   * @param list<array<string,mixed>> $edges
   */
  private function hasWaypointEdge(array $edges): bool
  {
    foreach ($edges as $edge) {
      $meta = json_decode((string)($edge['meta_json'] ?? ''), true);
      if (is_array($meta) && count(is_array($meta['through'] ?? null) ? $meta['through'] : []) > 0) {
        return true;
      }
    }

    return false;
  }
}
