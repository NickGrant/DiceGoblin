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

try {
  $pdo = $processDbEnv !== [] ? pdoFromProcessDbEnv($processDbEnv) : Db::pdo();
  $regionId = regionId($pdo, $regionSlug);
  $generator = new RunGraphGenerator($pdo);
  $result = compareGenerators($generator, $regionId, $regionSlug, $seedPrefix, $runs);

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
function compareGenerators(RunGraphGenerator $generator, int $regionId, string $regionSlug, string $seedPrefix, int $runs): array
{
  $rows = [];
  $laneNodeCounts = [];
  $patternNodeCounts = [];
  $laneEdgeCounts = [];
  $patternEdgeCounts = [];
  $laneStartToBossPaths = [];
  $patternStartToBossPaths = [];
  $laneBossToExitPaths = [];
  $patternBossToExitPaths = [];
  $laneTypes = [];
  $patternTypes = [];

  for ($i = 1; $i <= $runs; $i++) {
    $seed = "{$seedPrefix}-{$i}";
    $lane = $generator->generateWithVersion($regionId, $regionSlug, $seed, true, 'lane-v1');
    $pattern = $generator->generateWithVersion($regionId, $regionSlug, $seed, true, 'pattern-v1');
    $laneSummary = graphSummary($lane);
    $patternSummary = graphSummary($pattern);

    $laneNodeCounts[] = $laneSummary['node_count'];
    $patternNodeCounts[] = $patternSummary['node_count'];
    $laneEdgeCounts[] = $laneSummary['edge_count'];
    $patternEdgeCounts[] = $patternSummary['edge_count'];
    pushNullableMetric($laneStartToBossPaths, $laneSummary['boss_path']['start_to_boss']);
    pushNullableMetric($patternStartToBossPaths, $patternSummary['boss_path']['start_to_boss']);
    pushNullableMetric($laneBossToExitPaths, $laneSummary['boss_path']['boss_to_exit']);
    pushNullableMetric($patternBossToExitPaths, $patternSummary['boss_path']['boss_to_exit']);
    mergeCounts($laneTypes, $laneSummary['node_types']);
    mergeCounts($patternTypes, $patternSummary['node_types']);

    $rows[] = [
      'seed' => $seed,
      'lane_v1' => $laneSummary,
      'pattern_v1' => $patternSummary,
      'delta' => [
        'node_count' => $patternSummary['node_count'] - $laneSummary['node_count'],
        'edge_count' => $patternSummary['edge_count'] - $laneSummary['edge_count'],
        'start_to_boss' => nullableDelta($patternSummary['boss_path']['start_to_boss'], $laneSummary['boss_path']['start_to_boss']),
        'boss_to_exit' => nullableDelta($patternSummary['boss_path']['boss_to_exit'], $laneSummary['boss_path']['boss_to_exit']),
      ],
    ];
  }

  ksort($laneTypes);
  ksort($patternTypes);
  return [
    'region_slug' => $regionSlug,
    'runs' => $runs,
    'lane_v1' => [
      'node_count' => distribution($laneNodeCounts),
      'edge_count' => distribution($laneEdgeCounts),
      'boss_path' => [
        'start_to_boss' => nullableDistribution($laneStartToBossPaths),
        'boss_to_exit' => nullableDistribution($laneBossToExitPaths),
      ],
      'node_types' => $laneTypes,
    ],
    'pattern_v1' => [
      'node_count' => distribution($patternNodeCounts),
      'edge_count' => distribution($patternEdgeCounts),
      'boss_path' => [
        'start_to_boss' => nullableDistribution($patternStartToBossPaths),
        'boss_to_exit' => nullableDistribution($patternBossToExitPaths),
      ],
      'node_types' => $patternTypes,
    ],
    'results' => $rows,
  ];
}

/**
 * @param array{nodes:list<array<string,mixed>>,edges:list<array<string,mixed>>} $graph
 * @return array{node_count:int,edge_count:int,node_types:array<string,int>,available_count:int,has_generation:bool,boss_path:array{start_to_boss:int|null,boss_to_exit:int|null}}
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
  echo sprintf('- lane_v1: %s' . PHP_EOL, json_encode($result['lane_v1'], JSON_UNESCAPED_SLASHES));
  echo sprintf('- pattern_v1: %s' . PHP_EOL, json_encode($result['pattern_v1'], JSON_UNESCAPED_SLASHES));
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
