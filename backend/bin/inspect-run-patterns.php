<?php
declare(strict_types=1);

use DiceGoblins\Core\Autoloader;
use DiceGoblins\Core\Db;
use DiceGoblins\Core\Env;
use DiceGoblins\Repositories\RunPatternCatalogRepository;
use DiceGoblins\Services\RunPatternGenerationRequestBuilder;
use DiceGoblins\Services\RunPatternPreviewAssemblerService;

require_once __DIR__ . '/../src/Core/Autoloader.php';
Autoloader::register(__DIR__ . '/../src');
$processDbEnv = processDbEnv();
Env::load(__DIR__ . '/../.env');
restoreProcessDbEnv($processDbEnv);

$options = parseOptions($argv);
$format = (string)($options['format'] ?? 'text');
$region = (string)($options['region'] ?? 'mountains');
$seed = (string)($options['seed'] ?? 'inspect');
$generatorVersion = (string)($options['generator'] ?? 'pattern-v1');
$assemble = (bool)($options['assemble'] ?? false);

try {
  $pdo = $processDbEnv !== [] ? pdoFromProcessDbEnv($processDbEnv) : Db::pdo();
  $request = (new RunPatternGenerationRequestBuilder(new RunPatternCatalogRepository($pdo)))->build($region, $seed, $generatorVersion);
  $result = [
    'region_slug' => $request['region_slug'],
    'seed' => $request['seed'],
    'generator_version' => $request['generator_version'],
    'profile_version' => $request['profile_version'],
    'catalog_hash' => $request['catalog_hash'],
    'phase_rule_counts' => phaseRuleCounts($request['rules_by_phase']),
    'pattern_count' => count($request['patterns_by_key']),
    'variant_count' => variantCount($request['variants_by_pattern_key']),
    'tile_count' => count($request['tiles_by_pattern_key']),
    'patterns' => patternRows($request['patterns_by_key'], $request['variants_by_pattern_key'], $request['tiles_by_pattern_key']),
  ];
  if ($assemble) {
    $assembly = (new RunPatternPreviewAssemblerService())->assemble($request);
    $result['assembly'] = assemblySummary($assembly);
  }

  outputResult($result, $format);
  exit(0);
} catch (Throwable $throwable) {
  fwrite(STDERR, 'Run pattern inspection failed: ' . $throwable->getMessage() . PHP_EOL);
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

/**
 * @param array<string,mixed> $result
 */
function outputResult(array $result, string $format): void
{
  if ($format === 'json') {
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    return;
  }

  echo 'Run pattern catalog inspection' . PHP_EOL;
  foreach ($result as $key => $value) {
    if (is_array($value)) {
      echo sprintf('- %s: %s', $key, json_encode($value, JSON_UNESCAPED_SLASHES)) . PHP_EOL;
      continue;
    }
    echo sprintf('- %s: %s', $key, (string)$value) . PHP_EOL;
  }
}

/**
 * @param array<string,list<array<string,mixed>>> $rulesByPhase
 * @return array<string,int>
 */
function phaseRuleCounts(array $rulesByPhase): array
{
  $counts = [];
  foreach ($rulesByPhase as $phase => $rules) {
    $counts[$phase] = count($rules);
  }
  ksort($counts);
  return $counts;
}

/**
 * @param array<string,list<array<string,mixed>>> $variantsByPattern
 */
function variantCount(array $variantsByPattern): int
{
  $count = 0;
  foreach ($variantsByPattern as $variants) {
    $count += count($variants);
  }
  return $count;
}

/**
 * @param array<string,array<string,mixed>> $patterns
 * @param array<string,list<array<string,mixed>>> $variants
 * @param array<string,array<string,mixed>> $tiles
 * @return list<array<string,mixed>>
 */
function patternRows(array $patterns, array $variants, array $tiles): array
{
  $rows = [];
  foreach ($patterns as $key => $pattern) {
    $row = [
      'pattern_key' => $key,
      'content_hash' => (string)$pattern['content_hash'],
      'variant_count' => count($variants[$key] ?? []),
    ];
    if (isset($tiles[$key]) && is_array($tiles[$key])) {
      $row['tile'] = tileSummary($tiles[$key]);
    }
    $rows[] = $row;
  }
  return $rows;
}

/**
 * @param array<string,mixed> $tile
 * @return array<string,mixed>
 */
function tileSummary(array $tile): array
{
  return [
    'width' => (int)($tile['width'] ?? 0),
    'height' => (int)($tile['height'] ?? 0),
    'cost' => (int)($tile['cost'] ?? 0),
    'node_count' => count(is_array($tile['nodes'] ?? null) ? $tile['nodes'] : []),
    'connector_count' => count(is_array($tile['connectors'] ?? null) ? $tile['connectors'] : []),
    'edge_count' => count(is_array($tile['edges'] ?? null) ? $tile['edges'] : []),
    'exit_count' => count(is_array($tile['exits'] ?? null) ? $tile['exits'] : []),
    'tags' => array_values(array_map('strval', is_array($tile['tags'] ?? null) ? $tile['tags'] : [])),
  ];
}

/**
 * @param array{graph:array{nodes:list<array<string,mixed>>,edges:list<array<string,mixed>>},trace:array<string,mixed>,validation:array{valid:bool,errors:list<string>}} $assembly
 * @return array<string,mixed>
 */
function assemblySummary(array $assembly): array
{
  $nodes = array_values(array_filter($assembly['graph']['nodes'] ?? [], 'is_array'));
  $edges = array_values(array_filter($assembly['graph']['edges'] ?? [], 'is_array'));
  return [
    'valid' => (bool)($assembly['validation']['valid'] ?? false),
    'errors' => array_values(array_map('strval', is_array($assembly['validation']['errors'] ?? null) ? $assembly['validation']['errors'] : [])),
    'node_count' => count($nodes),
    'edge_count' => count($edges),
    'node_types' => nodeTypeCounts($nodes),
    'pattern_frequency' => patternFrequency($nodes),
    'spine_depth' => spineDepth($nodes),
    'max_straight_spine_nodes' => maxStraightSpineNodes($nodes),
    'branch_count' => branchCount($nodes),
    'map_ascii' => assemblyAsciiMap($nodes),
    'nodes' => assemblyNodeRows($nodes),
    'trace' => [
      'counters' => is_array($assembly['trace']['counters'] ?? null) ? $assembly['trace']['counters'] : [],
      'duration_ms' => $assembly['trace']['duration_ms'] ?? null,
      'event_count' => (int)($assembly['trace']['event_count'] ?? 0),
      'truncated' => (bool)($assembly['trace']['truncated'] ?? false),
    ],
  ];
}

/**
 * @param list<array<string,mixed>> $nodes
 * @return array{lines:list<string>,legend:array<string,string>}
 */
function assemblyAsciiMap(array $nodes): array
{
  if ($nodes === []) {
    return ['lines' => [], 'legend' => []];
  }

  $points = [];
  $minX = PHP_INT_MAX;
  $maxX = PHP_INT_MIN;
  $minY = PHP_INT_MAX;
  $maxY = PHP_INT_MIN;
  foreach ($nodes as $node) {
    $x = (int)($node['x'] ?? 0);
    $y = (int)($node['y'] ?? 0);
    $minX = min($minX, $x);
    $maxX = max($maxX, $x);
    $minY = min($minY, $y);
    $maxY = max($maxY, $y);
    $points["{$x}:{$y}"][] = nodeTypeSymbol((string)($node['type'] ?? 'unknown'));
  }

  $lines = [];
  for ($y = $minY; $y <= $maxY; $y++) {
    $cells = [];
    for ($x = $minX; $x <= $maxX; $x++) {
      $symbols = $points["{$x}:{$y}"] ?? [];
      $cells[] = $symbols === []
        ? '..'
        : str_pad(implode('', array_slice($symbols, 0, 2)), 2, '.', STR_PAD_RIGHT);
    }
    $lines[] = sprintf('y%+d %s', $y, implode(' ', $cells));
  }

  return [
    'lines' => $lines,
    'legend' => [
      'S' => 'start',
      'C' => 'combat',
      'K' => 'chaos',
      'R' => 'rest',
      'H' => 'hazard',
      'N' => 'shrine',
      'L' => 'loot',
      'D' => 'dialogue',
      'B' => 'boss',
      'E' => 'exit',
      '?' => 'unknown',
    ],
  ];
}

function nodeTypeSymbol(string $type): string
{
  return match ($type) {
    'start' => 'S',
    'combat' => 'C',
    'chaos' => 'K',
    'rest' => 'R',
    'hazard' => 'H',
    'shrine' => 'N',
    'loot' => 'L',
    'dialogue' => 'D',
    'boss' => 'B',
    'exit' => 'E',
    default => '?',
  };
}

/**
 * @param list<array<string,mixed>> $nodes
 * @return array<string,int>
 */
function nodeTypeCounts(array $nodes): array
{
  $counts = [];
  foreach ($nodes as $node) {
    $type = (string)($node['type'] ?? $node['node_type'] ?? 'unknown');
    $counts[$type] = ($counts[$type] ?? 0) + 1;
  }
  ksort($counts);
  return $counts;
}

/**
 * @param list<array<string,mixed>> $nodes
 * @return array<string,int>
 */
function patternFrequency(array $nodes): array
{
  $counts = [];
  foreach ($nodes as $node) {
    $patternKey = (string)($node['pattern_key'] ?? '');
    if ($patternKey !== '') {
      $counts[$patternKey] = ($counts[$patternKey] ?? 0) + 1;
    }
  }
  ksort($counts);
  return $counts;
}

/**
 * @param list<array<string,mixed>> $nodes
 */
function spineDepth(array $nodes): int
{
  $max = 0;
  foreach ($nodes as $node) {
    if ((string)($node['path_role'] ?? '') === 'spine') {
      $max = max($max, (int)($node['depth'] ?? 0));
    }
  }
  return $max;
}

/**
 * @param list<array<string,mixed>> $nodes
 */
function branchCount(array $nodes): int
{
  $branches = [];
  foreach ($nodes as $node) {
    $branchKey = (string)($node['branch_key'] ?? '');
    if ($branchKey !== '') {
      $branches[$branchKey] = true;
    }
  }
  return count($branches);
}

/**
 * @param list<array<string,mixed>> $nodes
 */
function maxStraightSpineNodes(array $nodes): int
{
  $spine = array_values(array_filter($nodes, static function (array $node): bool {
    return (string)($node['path_role'] ?? '') === 'spine';
  }));
  if ($spine === []) {
    return 0;
  }

  usort($spine, static function (array $left, array $right): int {
    $leftX = (int)($left['x'] ?? 0);
    $rightX = (int)($right['x'] ?? 0);
    if ($leftX !== $rightX) {
      return $leftX <=> $rightX;
    }
    return ((int)($left['depth'] ?? 0)) <=> ((int)($right['depth'] ?? 0));
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

    if ($previous !== null
      && (int)($node['x'] ?? 0) === ((int)($previous['x'] ?? 0)) + 1
      && (int)($node['y'] ?? 0) === (int)($previous['y'] ?? 0)
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

/**
 * @param list<array<string,mixed>> $nodes
 * @return list<array<string,mixed>>
 */
function assemblyNodeRows(array $nodes): array
{
  usort($nodes, static function (array $left, array $right): int {
    $leftX = (int)($left['x'] ?? 0);
    $rightX = (int)($right['x'] ?? 0);
    if ($leftX !== $rightX) {
      return $leftX <=> $rightX;
    }
    $leftY = (int)($left['y'] ?? 0);
    $rightY = (int)($right['y'] ?? 0);
    return $leftY <=> $rightY;
  });

  return array_map(
    static fn(array $node): array => [
      'key' => (string)($node['key'] ?? ''),
      'type' => (string)($node['type'] ?? 'unknown'),
      'x' => (int)($node['x'] ?? 0),
      'y' => (int)($node['y'] ?? 0),
      'pattern_key' => (string)($node['pattern_key'] ?? ''),
      'path_role' => (string)($node['path_role'] ?? ''),
      'depth' => (int)($node['depth'] ?? 0),
      'branch_key' => (string)($node['branch_key'] ?? ''),
    ],
    $nodes,
  );
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
