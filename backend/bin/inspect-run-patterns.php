<?php
declare(strict_types=1);

use DiceGoblins\Core\Autoloader;
use DiceGoblins\Core\Db;
use DiceGoblins\Core\Env;
use DiceGoblins\Repositories\RunPatternCatalogRepository;
use DiceGoblins\Services\RunPatternGenerationRequestBuilder;

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
    'patterns' => patternRows($request['patterns_by_key'], $request['variants_by_pattern_key']),
  ];

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
 * @return list<array<string,mixed>>
 */
function patternRows(array $patterns, array $variants): array
{
  $rows = [];
  foreach ($patterns as $key => $pattern) {
    $rows[] = [
      'pattern_key' => $key,
      'content_hash' => (string)$pattern['content_hash'],
      'variant_count' => count($variants[$key] ?? []),
    ];
  }
  return $rows;
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
