<?php
declare(strict_types=1);

use DiceGoblins\Application\FarmAccessReconciler;
use DiceGoblins\Content\ContentRegistry;
use DiceGoblins\Core\Autoloader;
use DiceGoblins\Core\Db;
use DiceGoblins\Repositories\PlayerStateRepository;
use DiceGoblins\Repositories\UserUnlockRepository;
use DiceGoblins\Repositories\WarbandUnitRepository;

require_once __DIR__ . '/../src/Core/Autoloader.php';
Autoloader::register(__DIR__ . '/../src');

if ($argv !== [$argv[0], '--apply']) {
  fwrite(STDERR, "Usage: php bin/reconcile-farm-access.php --apply\n");
  exit(2);
}

try {
  $pdo = Db::pdo();
  $reconciler = new FarmAccessReconciler($pdo, new PlayerStateRepository($pdo),
    new WarbandUnitRepository($pdo), new UserUnlockRepository($pdo), ContentRegistry::loadConfigured());
  $ids = $pdo->query('SELECT DISTINCT `user_id` FROM `unit_instances` ORDER BY `user_id`')
    ->fetchAll(PDO::FETCH_COLUMN);
  $granted = 0;
  foreach ($ids as $id) if ($reconciler->reconcile((int)$id)) $granted++;
  fwrite(STDOUT, json_encode(['scanned' => count($ids), 'granted' => $granted], JSON_THROW_ON_ERROR) . PHP_EOL);
} catch (Throwable $e) {
  fwrite(STDERR, $e->getMessage() . PHP_EOL);
  exit(1);
}
