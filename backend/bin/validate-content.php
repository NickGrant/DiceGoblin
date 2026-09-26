<?php
declare(strict_types=1);

use DiceGoblins\Content\ClientContentProjector;
use DiceGoblins\Content\ContentRegistry;

require_once __DIR__ . '/../src/Core/Autoloader.php';
DiceGoblins\Core\Autoloader::register(__DIR__ . '/../src');

$write = in_array('--write', $argv, true);
$root = dirname(__DIR__, 2);
$registry = ContentRegistry::load(dirname(__DIR__) . '/content');
$projection = (new ClientContentProjector())->project($registry);
// Preserve the client catalog object contract when no production items are approved yet.
if ($projection['content']['items'] === []) $projection['content']['items'] = (object)[];
$encoded = json_encode($projection, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . PHP_EOL;
$target = $root . '/frontend/public/game-content.json';

if ($write) {
  if (file_put_contents($target, $encoded) === false) throw new RuntimeException("Unable to write {$target}.");
  fwrite(STDOUT, "Generated client content {$registry->revision()}\n");
  exit(0);
}

$existing = is_file($target) ? file_get_contents($target) : false;
if ($existing !== $encoded) {
  fwrite(STDERR, "Client content projection is missing or stale; run npm run content:build.\n");
  exit(1);
}
fwrite(STDOUT, "Canonical content and client projection are valid ({$registry->revision()}).\n");
