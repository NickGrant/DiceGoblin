<?php
declare(strict_types=1);

use DiceGoblins\Core\Autoloader;
use DiceGoblins\Core\Env;
use DiceGoblins\Services\ShrineTuningSamplerService;

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
  fwrite(STDERR, "Refusing to sample shrine tuning unless APP_ENV is dev/local/test/testing or SIMULATION_ENABLED=1 is explicitly set.\n");
  exit(2);
}

$regions = isset($options['regions'])
  ? array_values(array_filter(array_map('trim', explode(',', (string)$options['regions']))))
  : [];
$samples = max(1, (int)($options['samples'] ?? 200));
$allowDeclineable = !empty($options['allow-declineable']);
$seedPrefix = (string)($options['seed'] ?? 'shrine-tuning');

$report = (new ShrineTuningSamplerService())->sample($regions, $samples, $allowDeclineable, $seedPrefix);
if (($options['format'] ?? 'text') === 'json') {
  echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
  exit(0);
}

printTextReport($report);

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
  echo sprintf(
    "Dice Goblins shrine tuning sample\nSamples per quality: %d\nDeclineable included: %s\n\n",
    (int)($report['samples_per_quality'] ?? 0),
    !empty($report['allow_declineable']) ? 'yes' : 'no'
  );

  $regions = is_array($report['regions'] ?? null) ? $report['regions'] : [];
  foreach ($regions as $region => $qualities) {
    echo strtoupper((string)$region) . PHP_EOL;
    if (!is_array($qualities)) {
      continue;
    }
    foreach ($qualities as $quality => $summary) {
      if (!is_array($summary)) {
        continue;
      }
      echo sprintf(
        "  %s: avg teeth %s, declineable %d\n",
        (string)$quality,
        (string)($summary['avg_currency_soft'] ?? '0'),
        (int)($summary['declineable_count'] ?? 0)
      );
      $counts = is_array($summary['effect_counts'] ?? null) ? $summary['effect_counts'] : [];
      foreach ($counts as $slug => $count) {
        echo sprintf("    %s: %d\n", (string)$slug, (int)$count);
      }
    }
    echo PHP_EOL;
  }
}
