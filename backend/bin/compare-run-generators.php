<?php
declare(strict_types=1);

use DiceGoblins\Core\Autoloader;
use DiceGoblins\Core\Db;
use DiceGoblins\Core\Env;
use DiceGoblins\Services\RunGraphGenerator;

require_once __DIR__ . '/../src/Core/Autoloader.php';
Autoloader::register(__DIR__ . '/../src');
$processDbEnv = processDbEnv();
Env::load(__DIR__ . '/../.env');
restoreProcessDbEnv($processDbEnv);

$options = parseOptions($argv);
$format = (string)($options['format'] ?? 'text');
$regionSlug = (string)($options['region'] ?? 'mountains');
$runs = max(1, (int)($options['runs'] ?? 10));
$seedPrefix = (string)($options['seed'] ?? 'generator-compare');
$baselineVersion = (string)($options['baseline'] ?? 'lane-v1');
$candidateVersion = (string)($options['candidate'] ?? 'pattern-v1');

try {
  $pdo = $processDbEnv !== [] ? pdoFromProcessDbEnv($processDbEnv) : Db::pdo();
  $regionId = regionId($pdo, $regionSlug);
  $generator = new RunGraphGenerator($pdo);
  $result = compareGenerators($generator, $regionId, $regionSlug, $seedPrefix, $runs, $baselineVersion, $candidateVersion);

  outputResult($result, $format);
  exit(0);
} catch (Throwable $throwable) {
  fwrite(STDERR, 'Run generator comparison failed: ' . $throwable->getMessage() . PHP_EOL);
  exit(1);
}

/**
 * @param list<string> $argv
 * @return array<string,mixed>
 */
function parseOptions(array $argv): array
{
  $options = [];
  for ($i = 1; $i < count($argv); $i++) {
    $arg = $argv[$i];
    if (!str_starts_with($arg, '--')) {
      continue;
    }

    $arg = substr($arg, 2);
    if (str_contains($arg, '=')) {
      [$key, $value] = explode('=', $arg, 2);
      $options[$key] = $value;
      continue;
    }

    $key = $arg;
    $value = $argv[$i + 1] ?? null;
    if ($value !== null && !str_starts_with($value, '--')) {
      $options[$key] = $value;
      $i++;
      continue;
    }

    $options[$key] = true;
  }

  return $options;
}

function regionId(PDO $pdo, string $regionSlug): int
{
  $stmt = $pdo->prepare('SELECT `id` FROM `regions` WHERE `slug` = ? LIMIT 1');
  $stmt->execute([$regionSlug]);
  $id = $stmt->fetchColumn();
  if ($id === false) {
    throw new RuntimeException("Unknown region {$regionSlug}.");
  }
  return (int)$id;
}

/**
 * @return array<string,mixed>
 */
function compareGenerators(
  RunGraphGenerator $generator,
  int $regionId,
  string $regionSlug,
  string $seedPrefix,
  int $runs,
  string $baselineVersion,
  string $candidateVersion
): array
{
  $rows = [];
  $baselineMetrics = emptyMetrics();
  $candidateMetrics = emptyMetrics();

  for ($i = 1; $i <= $runs; $i++) {
    $seed = "{$seedPrefix}-{$i}";
    $baseline = $generator->generateWithVersion($regionId, $regionSlug, $seed, true, $baselineVersion);
    $candidate = $generator->generateWithVersion($regionId, $regionSlug, $seed, true, $candidateVersion);
    $baselineSummary = graphSummary($baseline);
    $candidateSummary = graphSummary($candidate);

    recordMetrics($baselineMetrics, $baselineSummary);
    recordMetrics($candidateMetrics, $candidateSummary);

    $rows[] = [
      'seed' => $seed,
      $baselineVersion => $baselineSummary,
      $candidateVersion => $candidateSummary,
      'delta' => [
        'node_count' => $candidateSummary['node_count'] - $baselineSummary['node_count'],
        'edge_count' => $candidateSummary['edge_count'] - $baselineSummary['edge_count'],
        'branch_count' => $candidateSummary['branch_count'] - $baselineSummary['branch_count'],
        'occupied_rows' => $candidateSummary['occupied_rows'] - $baselineSummary['occupied_rows'],
        'occupied_columns' => $candidateSummary['occupied_columns'] - $baselineSummary['occupied_columns'],
        'start_to_boss' => nullableDelta($candidateSummary['boss_path']['start_to_boss'], $baselineSummary['boss_path']['start_to_boss']),
        'boss_to_exit' => nullableDelta($candidateSummary['boss_path']['boss_to_exit'], $baselineSummary['boss_path']['boss_to_exit']),
      ],
    ];
  }

  return [
    'region_slug' => $regionSlug,
    'runs' => $runs,
    'baseline_version' => $baselineVersion,
    'candidate_version' => $candidateVersion,
    $baselineVersion => summarizeMetrics($baselineMetrics),
    $candidateVersion => summarizeMetrics($candidateMetrics),
    'results' => $rows,
  ];
}

/**
 * @param array{nodes:list<array<string,mixed>>,edges:list<array<string,mixed>>} $graph
 * @return array{node_count:int,edge_count:int,branch_count:int,occupied_rows:int,occupied_columns:int,max_straight_spine_nodes:int,node_types:array<string,int>,available_count:int,has_generation:bool,boss_path:array{start_to_boss:int|null,boss_to_exit:int|null}}
 */
function graphSummary(array $graph): array
{
  $nodeTypes = [];
  $available = 0;
  foreach (array_values(array_filter($graph['nodes'] ?? [], 'is_array')) as $node) {
    $type = (string)($node['node_type'] ?? 'unknown');
    $nodeTypes[$type] = ($nodeTypes[$type] ?? 0) + 1;
    if ((string)($node['status'] ?? '') === 'available') {
      $available++;
    }
  }
  ksort($nodeTypes);
  return [
    'node_count' => count($graph['nodes'] ?? []),
    'edge_count' => count($graph['edges'] ?? []),
    'branch_count' => is_numeric($graph['generation']['branch_count'] ?? null)
      ? (int)$graph['generation']['branch_count']
      : branchCount(array_values(array_filter($graph['nodes'] ?? [], 'is_array'))),
    'occupied_rows' => occupiedCoordinateCount(array_values(array_filter($graph['nodes'] ?? [], 'is_array')), 'y'),
    'occupied_columns' => occupiedCoordinateCount(array_values(array_filter($graph['nodes'] ?? [], 'is_array')), 'x'),
    'max_straight_spine_nodes' => maxStraightSpineNodes(array_values(array_filter($graph['nodes'] ?? [], 'is_array'))),
    'node_types' => $nodeTypes,
    'available_count' => $available,
    'has_generation' => is_array($graph['generation'] ?? null),
    'boss_path' => bossPathMetrics(
      array_values(array_filter($graph['nodes'] ?? [], 'is_array')),
      array_values(array_filter($graph['edges'] ?? [], 'is_array')),
    ),
  ];
}

/**
 * @param list<array<string,mixed>> $nodes
 * @param list<array<string,mixed>> $edges
 * @return array{start_to_boss:int|null,boss_to_exit:int|null}
 */
function bossPathMetrics(array $nodes, array $edges): array
{
  $start = firstNodeIndexByType($nodes, 'start') ?? firstNodeIndexByStatus($nodes, 'available');
  $boss = firstNodeIndexByType($nodes, 'boss');
  $exit = firstNodeIndexByType($nodes, 'exit');

  return [
    'start_to_boss' => $start !== null && $boss !== null
      ? shortestPathLength($start, $boss, $edges)
      : null,
    'boss_to_exit' => $boss !== null && $exit !== null
      ? shortestPathLength($boss, $exit, $edges)
      : null,
  ];
}

/**
 * @return array<string,mixed>
 */
function emptyMetrics(): array
{
  return [
    'node_count' => [],
    'edge_count' => [],
    'branch_count' => [],
    'occupied_rows' => [],
    'occupied_columns' => [],
    'max_straight_spine_nodes' => [],
    'start_to_boss' => [],
    'boss_to_exit' => [],
    'node_types' => [],
  ];
}

/**
 * @param array<string,mixed> $metrics
 * @param array<string,mixed> $summary
 */
function recordMetrics(array &$metrics, array $summary): void
{
  foreach (['node_count', 'edge_count', 'branch_count', 'occupied_rows', 'occupied_columns', 'max_straight_spine_nodes'] as $key) {
    $metrics[$key][] = (int)($summary[$key] ?? 0);
  }
  pushNullableMetric($metrics['start_to_boss'], $summary['boss_path']['start_to_boss'] ?? null);
  pushNullableMetric($metrics['boss_to_exit'], $summary['boss_path']['boss_to_exit'] ?? null);
  mergeCounts($metrics['node_types'], is_array($summary['node_types'] ?? null) ? $summary['node_types'] : []);
}

/**
 * @param array<string,mixed> $metrics
 * @return array<string,mixed>
 */
function summarizeMetrics(array $metrics): array
{
  $nodeTypes = is_array($metrics['node_types'] ?? null) ? $metrics['node_types'] : [];
  ksort($nodeTypes);
  return [
    'node_count' => distribution($metrics['node_count']),
    'edge_count' => distribution($metrics['edge_count']),
    'branch_count' => distribution($metrics['branch_count']),
    'occupied_rows' => distribution($metrics['occupied_rows']),
    'occupied_columns' => distribution($metrics['occupied_columns']),
    'max_straight_spine_nodes' => distribution($metrics['max_straight_spine_nodes']),
    'boss_path' => [
      'start_to_boss' => nullableDistribution($metrics['start_to_boss']),
      'boss_to_exit' => nullableDistribution($metrics['boss_to_exit']),
    ],
    'node_types' => $nodeTypes,
  ];
}

/**
 * @param list<array<string,mixed>> $nodes
 */
function branchCount(array $nodes): int
{
  $branches = [];
  foreach ($nodes as $node) {
    $branchKey = (string)nodeGenerationValue($node, 'branch_key', '');
    if ($branchKey !== '') {
      $branches[$branchKey] = true;
    }
  }
  return count($branches);
}

/**
 * @param list<array<string,mixed>> $nodes
 */
function occupiedCoordinateCount(array $nodes, string $coordinate): int
{
  $occupied = [];
  foreach ($nodes as $node) {
    $value = nodeGenerationValue($node, $coordinate, null);
    if ($value === null) {
      $metaCoordinate = $coordinate === 'x' ? 'col' : ($coordinate === 'y' ? 'row' : $coordinate);
      $value = is_array($node['meta'] ?? null) ? ($node['meta'][$metaCoordinate] ?? null) : null;
    }

    if ($value !== null) {
      $occupied[(int)$value] = true;
    }
  }
  return count($occupied);
}

/**
 * @param list<array<string,mixed>> $nodes
 */
function maxStraightSpineNodes(array $nodes): int
{
  $spine = array_values(array_filter($nodes, static function (array $node): bool {
    return (string)nodeGenerationValue($node, 'path_role', '') === 'spine';
  }));
  if ($spine === []) {
    return 0;
  }

  usort($spine, static function (array $left, array $right): int {
    $leftX = (int)nodeGenerationValue($left, 'x', is_array($left['meta'] ?? null) ? ($left['meta']['col'] ?? 0) : 0);
    $rightX = (int)nodeGenerationValue($right, 'x', is_array($right['meta'] ?? null) ? ($right['meta']['col'] ?? 0) : 0);
    if ($leftX !== $rightX) {
      return $leftX <=> $rightX;
    }
    return ((int)nodeGenerationValue($left, 'depth', 0)) <=> ((int)nodeGenerationValue($right, 'depth', 0));
  });

  $max = 1;
  $current = 1;
  $previous = null;
  foreach ($spine as $node) {
    $type = (string)($node['type'] ?? $node['node_type'] ?? '');
    if (in_array($type, ['boss', 'exit'], true)) {
      $previous = null;
      $current = 1;
      continue;
    }

    $x = (int)nodeGenerationValue($node, 'x', is_array($node['meta'] ?? null) ? ($node['meta']['col'] ?? 0) : 0);
    $y = (int)nodeGenerationValue($node, 'y', is_array($node['meta'] ?? null) ? ($node['meta']['row'] ?? 0) : 0);
    $previousX = is_array($previous) ? (int)nodeGenerationValue($previous, 'x', is_array($previous['meta'] ?? null) ? ($previous['meta']['col'] ?? 0) : 0) : 0;
    $previousY = is_array($previous) ? (int)nodeGenerationValue($previous, 'y', is_array($previous['meta'] ?? null) ? ($previous['meta']['row'] ?? 0) : 0) : 0;
    if ($previous !== null
      && $x === $previousX + 1
      && $y === $previousY
    ) {
      $current++;
    } else {
      $current = 1;
    }

    $max = max($max, $current);
    $previous = $node;
  }

  return $max;
}

function nodeGenerationValue(array $node, string $key, mixed $default = null): mixed
{
  if (array_key_exists($key, $node)) {
    return $node[$key];
  }

  $meta = is_array($node['meta'] ?? null) ? $node['meta'] : [];
  $generation = is_array($meta['generation'] ?? null) ? $meta['generation'] : [];
  if (array_key_exists($key, $generation)) {
    return $generation[$key];
  }

  if ($key === 'x' && array_key_exists('col', $meta)) {
    return $meta['col'];
  }
  if ($key === 'y' && array_key_exists('row', $meta)) {
    return $meta['row'];
  }

  return $default;
}

/**
 * @param list<array<string,mixed>> $nodes
 */
function firstNodeIndexByType(array $nodes, string $type): ?int
{
  foreach ($nodes as $node) {
    if ((string)($node['node_type'] ?? $node['type'] ?? '') === $type) {
      return isset($node['node_index']) ? (int)$node['node_index'] : null;
    }
  }
  return null;
}

/**
 * @param list<array<string,mixed>> $nodes
 */
function firstNodeIndexByStatus(array $nodes, string $status): ?int
{
  foreach ($nodes as $node) {
    if ((string)($node['status'] ?? '') === $status) {
      return isset($node['node_index']) ? (int)$node['node_index'] : null;
    }
  }
  return null;
}

/**
 * @param list<array<string,mixed>> $edges
 */
function shortestPathLength(int $from, int $to, array $edges): ?int
{
  $adjacency = [];
  foreach ($edges as $edge) {
    $source = isset($edge['from']) ? (int)$edge['from'] : null;
    $target = isset($edge['to']) ? (int)$edge['to'] : null;
    if ($source !== null && $target !== null) {
      $adjacency[$source][] = $target;
    }
  }

  $queue = [[$from, 0]];
  $seen = [];
  while ($queue !== []) {
    [$current, $distance] = array_shift($queue);
    if (isset($seen[$current])) {
      continue;
    }
    if ($current === $to) {
      return (int)$distance;
    }

    $seen[$current] = true;
    foreach ($adjacency[$current] ?? [] as $next) {
      $queue[] = [(int)$next, ((int)$distance) + 1];
    }
  }

  return null;
}

/**
 * @param array<string,int> $target
 * @param array<string,int> $source
 */
function mergeCounts(array &$target, array $source): void
{
  foreach ($source as $key => $value) {
    $target[$key] = ($target[$key] ?? 0) + (int)$value;
  }
}

/**
 * @param list<int> $values
 * @return array{min:int,max:int,avg:float}
 */
function distribution(array $values): array
{
  return [
    'min' => min($values),
    'max' => max($values),
    'avg' => round(array_sum($values) / count($values), 2),
  ];
}

/**
 * @param list<int> $values
 */
function pushNullableMetric(array &$values, mixed $value): void
{
  if (is_numeric($value)) {
    $values[] = (int)$value;
  }
}

/**
 * @param list<int> $values
 * @return array{min:int|null,max:int|null,avg:float|null}
 */
function nullableDistribution(array $values): array
{
  if ($values === []) {
    return ['min' => null, 'max' => null, 'avg' => null];
  }

  return distribution($values);
}

function nullableDelta(mixed $left, mixed $right): ?int
{
  return is_numeric($left) && is_numeric($right)
    ? (int)$left - (int)$right
    : null;
}

/**
 * @param array<string,mixed> $result
 */
function outputResult(array $result, string $format): void
{
  if ($format === 'json') {
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    return;
  }

  echo 'Run generator comparison' . PHP_EOL;
  echo sprintf('- region: %s' . PHP_EOL, (string)$result['region_slug']);
  echo sprintf('- runs: %d' . PHP_EOL, (int)$result['runs']);
  $baseline = (string)$result['baseline_version'];
  $candidate = (string)$result['candidate_version'];
  echo sprintf('- baseline: %s %s' . PHP_EOL, $baseline, json_encode($result[$baseline], JSON_UNESCAPED_SLASHES));
  echo sprintf('- candidate: %s %s' . PHP_EOL, $candidate, json_encode($result[$candidate], JSON_UNESCAPED_SLASHES));
}

/**
 * @return array<string,string>
 */
function processDbEnv(): array
{
  $values = [];
  foreach (['DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USER', 'DB_PASS'] as $key) {
    $value = getenv($key);
    if ($value !== false) {
      $values[$key] = $value;
    }
  }
  return $values;
}

/**
 * @param array<string,string> $values
 */
function restoreProcessDbEnv(array $values): void
{
  foreach ($values as $key => $value) {
    putenv("{$key}={$value}");
    $_ENV[$key] = $value;
    $_SERVER[$key] = $value;
  }
}

/**
 * @param array<string,string> $values
 */
function pdoFromProcessDbEnv(array $values): PDO
{
  $host = $values['DB_HOST'] ?? 'db';
  $port = $values['DB_PORT'] ?? '3306';
  $db = $values['DB_NAME'] ?? '';
  $user = $values['DB_USER'] ?? '';
  $pass = $values['DB_PASS'] ?? '';
  if ($db === '' || $user === '') {
    throw new RuntimeException('DB_NAME and DB_USER are required when overriding DB env vars.');
  }

  return new PDO("mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4", $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
  ]);
}
