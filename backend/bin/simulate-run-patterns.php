<?php
declare(strict_types=1);

use DiceGoblins\Core\Autoloader;
use DiceGoblins\Core\Db;
use DiceGoblins\Core\Env;
use DiceGoblins\Repositories\RunPatternCatalogRepository;
use DiceGoblins\Services\RunPatternGenerationRequestBuilder;
use DiceGoblins\Services\RunPatternSimulationService;

require_once __DIR__ . '/../src/Core/Autoloader.php';
Autoloader::register(__DIR__ . '/../src');
$processDbEnv = processDbEnv();
Env::load(__DIR__ . '/../.env');
restoreProcessDbEnv($processDbEnv);

$options = parseOptions($argv);
$format = (string)($options['format'] ?? 'text');
$region = (string)($options['region'] ?? 'mountains');
$runs = (int)($options['runs'] ?? 25);
$seed = (string)($options['seed'] ?? 'pattern-sim');
$generatorVersion = (string)($options['generator'] ?? 'pattern-v1');
$gate = (bool)($options['gate'] ?? false);

try {
  $pdo = $processDbEnv !== [] ? pdoFromProcessDbEnv($processDbEnv) : Db::pdo();
  $simulator = new RunPatternSimulationService(
    new RunPatternGenerationRequestBuilder(new RunPatternCatalogRepository($pdo))
  );
  $simulation = $simulator->simulate($region, $runs, $seed, $generatorVersion);
  if ($gate) {
    $simulation['gate'] = $simulator->evaluateGate($simulation, gateOptions($options));
  }

  outputResult($simulation, $format);
  $passed = $gate
    ? (bool)($simulation['gate']['passed'] ?? false)
    : ((int)$simulation['successes']) === ((int)$simulation['runs']);
  exit($passed ? 0 : 1);
} catch (Throwable $throwable) {
  fwrite(STDERR, 'Run pattern simulation failed: ' . $throwable->getMessage() . PHP_EOL);
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

  echo 'Run pattern simulation' . PHP_EOL;
  echo sprintf(
    '- region: %s (%s)' . PHP_EOL,
    (string)$result['region_slug'],
    (string)$result['generator_version']
  );
  echo sprintf('- runs: %d' . PHP_EOL, (int)$result['runs']);
  echo sprintf('- success_rate: %.4f' . PHP_EOL, (float)$result['success_rate']);
  echo sprintf('- fallback_rate: %.4f' . PHP_EOL, (float)$result['fallback_rate']);
  echo sprintf('- node_count: %s' . PHP_EOL, json_encode($result['node_count'], JSON_UNESCAPED_SLASHES));
  echo sprintf('- edge_count: %s' . PHP_EOL, json_encode($result['edge_count'], JSON_UNESCAPED_SLASHES));
  echo sprintf('- branch_count: %s' . PHP_EOL, json_encode($result['branch_count'], JSON_UNESCAPED_SLASHES));
  echo sprintf('- spine_depth: %s' . PHP_EOL, json_encode($result['spine_depth'], JSON_UNESCAPED_SLASHES));
  echo sprintf('- max_straight_spine_nodes: %s' . PHP_EOL, json_encode($result['max_straight_spine_nodes'], JSON_UNESCAPED_SLASHES));
  echo sprintf('- occupied_rows: %s' . PHP_EOL, json_encode($result['occupied_rows'], JSON_UNESCAPED_SLASHES));
  echo sprintf('- occupied_columns: %s' . PHP_EOL, json_encode($result['occupied_columns'], JSON_UNESCAPED_SLASHES));
  echo sprintf('- backtracks: %s' . PHP_EOL, json_encode($result['backtracks'], JSON_UNESCAPED_SLASHES));
  echo sprintf('- duration_ms: %s' . PHP_EOL, json_encode($result['duration_ms'], JSON_UNESCAPED_SLASHES));
  echo sprintf('- boss_path: %s' . PHP_EOL, json_encode($result['boss_path'], JSON_UNESCAPED_SLASHES));
  echo sprintf('- validation_failures: %s' . PHP_EOL, json_encode($result['validation_failures'], JSON_UNESCAPED_SLASHES));
  echo sprintf('- node_type_frequency: %s' . PHP_EOL, json_encode($result['node_type_frequency'], JSON_UNESCAPED_SLASHES));
  echo sprintf('- pattern_frequency: %s' . PHP_EOL, json_encode($result['pattern_frequency'], JSON_UNESCAPED_SLASHES));
  if (isset($result['gate']) && is_array($result['gate'])) {
    echo sprintf('- gate: %s' . PHP_EOL, ((bool)($result['gate']['passed'] ?? false)) ? 'passed' : 'failed');
    foreach (array_values(array_filter(is_array($result['gate']['checks'] ?? null) ? $result['gate']['checks'] : [], 'is_array')) as $check) {
      echo sprintf(
        '  - %s: %s (actual=%s expected=%s)' . PHP_EOL,
        (string)($check['name'] ?? 'unknown'),
        (bool)($check['passed'] ?? false) ? 'passed' : 'failed',
        json_encode($check['actual'] ?? null, JSON_UNESCAPED_SLASHES),
        json_encode($check['expected'] ?? null, JSON_UNESCAPED_SLASHES),
      );
    }
  }
}

/**
 * @param array<string,mixed> $options
 * @return array<string,mixed>
 */
function gateOptions(array $options): array
{
  $mapping = [
    'min-success-rate' => 'min_success_rate',
    'max-fallback-rate' => 'max_fallback_rate',
    'max-backtracks-avg' => 'max_backtracks_avg',
    'min-branch-count' => 'min_branch_count',
    'max-straight-spine-nodes' => 'max_straight_spine_nodes',
    'min-occupied-rows' => 'min_occupied_rows',
    'max-occupied-rows' => 'max_occupied_rows',
    'min-occupied-columns' => 'min_occupied_columns',
    'max-occupied-columns' => 'max_occupied_columns',
  ];

  $gateOptions = [];
  foreach ($mapping as $cliKey => $optionKey) {
    if (array_key_exists($cliKey, $options)) {
      $gateOptions[$optionKey] = $options[$cliKey];
    }
  }
  return $gateOptions;
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
