<?php
declare(strict_types=1);

use DiceGoblins\Core\Autoloader;
use DiceGoblins\Core\Db;
use DiceGoblins\Core\Env;
use DiceGoblins\Services\BalanceSimulationService;

require_once __DIR__ . '/../src/Core/Autoloader.php';
Autoloader::register(__DIR__ . '/../src');
$processAppEnv = getenv('APP_ENV');
$processSimulationEnabled = getenv('SIMULATION_ENABLED');
Env::load(__DIR__ . '/../.env');

$options = parseOptions($argv);
$env = strtolower(is_string($processAppEnv) && $processAppEnv !== '' ? $processAppEnv : (Env::get('APP_ENV', 'unknown') ?? 'unknown'));
$simulationEnabled = (is_string($processSimulationEnabled) && $processSimulationEnabled !== '')
  ? $processSimulationEnabled === '1'
  : Env::get('SIMULATION_ENABLED', '0') === '1';
$safeLocalEnvironments = ['dev', 'local', 'test', 'testing'];
if (!$simulationEnabled && !in_array($env, $safeLocalEnvironments, true)) {
  fwrite(STDERR, "Refusing to run simulations unless APP_ENV is dev/local/test/testing or SIMULATION_ENABLED=1 is explicitly set.\n");
  exit(2);
}

try {
  $report = (new BalanceSimulationService(Db::pdo()))->run($options);
  if (($options['format'] ?? 'text') === 'json') {
    echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit(0);
  }

  printTextReport($report);
  exit(0);
} catch (Throwable $throwable) {
  fwrite(STDERR, 'Simulation failed: ' . $throwable->getMessage() . PHP_EOL);
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

/** @param array<string,mixed> $report */
function printTextReport(array $report): void
{
  $summary = is_array($report['summary'] ?? null) ? $report['summary'] : [];
  echo sprintf(
    "Dice Goblins balance simulation\nMode: %s\nRegion: %s\nSamples: %d\n\n",
    (string)($report['mode'] ?? 'unknown'),
    (string)($report['region'] ?? 'unknown'),
    (int)(($report['config']['samples'] ?? 0))
  );

  foreach ($summary as $key => $value) {
    if (is_array($value)) {
      echo $key . ': ' . json_encode($value, JSON_UNESCAPED_SLASHES) . PHP_EOL;
      continue;
    }

    echo $key . ': ' . (string)$value . PHP_EOL;
  }
}
