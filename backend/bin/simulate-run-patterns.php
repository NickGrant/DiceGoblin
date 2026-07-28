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

try {
  $pdo = $processDbEnv !== [] ? pdoFromProcessDbEnv($processDbEnv) : Db::pdo();
  $simulation = (new RunPatternSimulationService(
    new RunPatternGenerationRequestBuilder(new RunPatternCatalogRepository($pdo))
  ))->simulate($region, $runs, $seed, $generatorVersion);

  outputResult($simulation, $format);
  exit(((int)$simulation['successes']) === ((int)$simulation['runs']) ? 0 : 1);
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
  echo sprintf('- node_count: %s' . PHP_EOL, json_encode($result['node_count'], JSON_UNESCAPED_SLASHES));
  echo sprintf('- validation_failures: %s' . PHP_EOL, json_encode($result['validation_failures'], JSON_UNESCAPED_SLASHES));
  echo sprintf('- pattern_frequency: %s' . PHP_EOL, json_encode($result['pattern_frequency'], JSON_UNESCAPED_SLASHES));
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
