<?php
declare(strict_types=1);

use DiceGoblins\Application\Commands\ProvisionWarbandFixtureCommand;
use DiceGoblins\Application\Commands\WarbandFixtureEnvironment;
use DiceGoblins\Content\ContentRegistry;
use DiceGoblins\Core\Autoloader;
use DiceGoblins\Core\Db;
use DiceGoblins\Core\Env;
use DiceGoblins\Repositories\WarbandFixtureRepository;

require_once __DIR__ . '/../src/Core/Autoloader.php';
Autoloader::register(__DIR__ . '/../src');

try {
  if (!WarbandFixtureEnvironment::isEnabled(Env::get('APP_ENV'), Env::get('ENABLE_WARBAND_FIXTURES'))) {
    throw new RuntimeException('UAT Warband seeding is disabled in this environment.');
  }
  if (count($argv) !== 2 || !preg_match('/^--(user-id|display-name)=(.+)$/', $argv[1], $matches)) {
    throw new RuntimeException('Usage: php bin/seed-warband-uat.php --display-name=Nick (or --user-id=3)');
  }
  $pdo = Db::pdo();
  if ($matches[1] === 'user-id') {
    if (!preg_match('/^[1-9][0-9]*$/', $matches[2])) throw new RuntimeException('User ID must be canonical and positive.');
    $lookup = $pdo->prepare('SELECT `id`, `display_name` FROM `users` WHERE `id` = ?');
  } else {
    $lookup = $pdo->prepare('SELECT `id`, `display_name` FROM `users` WHERE BINARY `display_name` = BINARY ? ORDER BY `id` LIMIT 2');
  }
  $lookup->execute([$matches[2]]);
  $accounts = $lookup->fetchAll();
  if (count($accounts) !== 1) throw new RuntimeException('Target account was not found uniquely; use --user-id.');
  $account = $accounts[0];
  $fixture = (new ProvisionWarbandFixtureCommand(
    $pdo,
    new WarbandFixtureRepository($pdo),
    ContentRegistry::load(dirname(__DIR__) . '/content'),
  ))->execute((int)$account['id'], seedOnly: true);
  fwrite(STDOUT, json_encode(['account' => ['id' => (string)$account['id'], 'display_name' => $account['display_name']],
    'fixture' => $fixture], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) . PHP_EOL);
} catch (Throwable $error) {
  fwrite(STDERR, $error->getMessage() . PHP_EOL);
  exit(1);
}
