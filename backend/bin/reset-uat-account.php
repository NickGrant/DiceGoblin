<?php
declare(strict_types=1);

use DiceGoblins\Application\Commands\ProvisionWarbandFixtureCommand;
use DiceGoblins\Application\Commands\WarbandFixtureEnvironment;
use DiceGoblins\Application\Queries\OnboardingQuery;
use DiceGoblins\Content\ContentRegistry;
use DiceGoblins\Core\Autoloader;
use DiceGoblins\Core\Db;
use DiceGoblins\Core\Env;
use DiceGoblins\Repositories\PlayerStateRepository;
use DiceGoblins\Repositories\UserCodexRepository;
use DiceGoblins\Repositories\WarbandFixtureRepository;
use DiceGoblins\Repositories\WarbandUnitRepository;

require_once __DIR__ . '/../src/Core/Autoloader.php';
Autoloader::register(__DIR__ . '/../src');

try {
  if (!WarbandFixtureEnvironment::isEnabled(Env::get('APP_ENV'), Env::get('ENABLE_WARBAND_FIXTURES'))) {
    throw new RuntimeException('UAT account reset is disabled in this environment.');
  }

  $options = getopt('', ['user-id:', 'display-name:', 'confirm-reset', 'fresh-onboarding']);
  $hasUserId = isset($options['user-id']);
  $hasDisplayName = isset($options['display-name']);
  $freshOnboarding = isset($options['fresh-onboarding']);
  if ($hasUserId === $hasDisplayName || !isset($options['confirm-reset'])
    || count($argv) !== ($freshOnboarding ? 4 : 3)) {
    throw new RuntimeException(
      'Usage: php bin/reset-uat-account.php (--user-id=3 or --display-name=Nick) --confirm-reset [--fresh-onboarding]',
    );
  }

  $pdo = Db::pdo();
  if ($hasUserId) {
    $selector = (string)$options['user-id'];
    if (!preg_match('/^[1-9][0-9]*$/', $selector)) {
      throw new RuntimeException('User ID must be canonical and positive.');
    }
    $lookup = $pdo->prepare('SELECT `id`, `display_name` FROM `users` WHERE `id` = ?');
  } else {
    $selector = (string)$options['display-name'];
    if ($selector === '') throw new RuntimeException('Display name must not be empty.');
    $lookup = $pdo->prepare(
      'SELECT `id`, `display_name` FROM `users` WHERE BINARY `display_name` = BINARY ? ORDER BY `id` LIMIT 2',
    );
  }
  $lookup->execute([$selector]);
  $accounts = $lookup->fetchAll(PDO::FETCH_ASSOC);
  if (count($accounts) !== 1) {
    throw new RuntimeException('Target account was not found uniquely; use --user-id.');
  }
  $account = $accounts[0];
  $userId = (int)$account['id'];
  $content = ContentRegistry::load(dirname(__DIR__) . '/content');

  try {
    $pdo->beginTransaction();
    $state = $pdo->prepare('SELECT 1 FROM `user_state` WHERE `user_id` = ? FOR UPDATE');
    $state->execute([$userId]);
    if (!(bool)$state->fetchColumn()) throw new RuntimeException('Target account has no player state.');

    $pdo->prepare('UPDATE `user_state` SET `active_squad_id` = NULL WHERE `user_id` = ?')->execute([$userId]);
    foreach (['idempotency_requests', 'resolved_events', 'runs', 'user_objectives', 'user_items', 'user_unlocks', 'squads',
      'unit_instances', 'dice_instances'] as $table) {
      $pdo->prepare("DELETE FROM `$table` WHERE `user_id` = ?")->execute([$userId]);
    }
    if ($freshOnboarding) {
      $pdo->prepare('DELETE FROM `user_codex_ownership` WHERE `user_id` = ?')->execute([$userId]);
    }
    $reset = $pdo->prepare('UPDATE `user_state`
      SET `teeth` = 0, `raw_chaos` = 0, `energy_current` = ?, `energy_last_regen_at` = UTC_TIMESTAMP(),
        `active_squad_id` = NULL, `player_revision` = 1
      WHERE `user_id` = ?');
    $reset->execute([$content->startingEnergy(), $userId]);
    if ($reset->rowCount() !== 1) throw new RuntimeException('Target player state could not be reset.');
    $pdo->commit();
  } catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    throw $error;
  }

  $fixture = $freshOnboarding ? null : (new ProvisionWarbandFixtureCommand(
    $pdo,
    new WarbandFixtureRepository($pdo),
    $content,
  ))->execute($userId);

  $summary = $pdo->prepare('SELECT us.`teeth`, us.`raw_chaos`, us.`energy_current`, us.`active_squad_id`,
      us.`player_revision`,
      (SELECT COUNT(*) FROM `unit_instances` WHERE `user_id` = ?) AS `units`,
      (SELECT COUNT(*) FROM `dice_instances` WHERE `user_id` = ?) AS `dice`,
      (SELECT COUNT(*) FROM `squads` WHERE `user_id` = ?) AS `squads`,
      (SELECT COUNT(*) FROM `user_codex_ownership` WHERE `user_id` = ?) AS `codex_entries`,
      (SELECT COUNT(*) FROM `runs` WHERE `user_id` = ?) AS `runs`,
      (SELECT COUNT(*) FROM `user_items` WHERE `user_id` = ?) AS `items`,
      (SELECT COUNT(*) FROM `user_objectives` WHERE `user_id` = ?) AS `objectives`,
      (SELECT COUNT(*) FROM `user_unlocks` WHERE `user_id` = ?) AS `unlocks`,
      (SELECT COUNT(*) FROM `resolved_events` WHERE `user_id` = ?) AS `resolved_events`,
      (SELECT COUNT(*) FROM `idempotency_requests` WHERE `user_id` = ?) AS `idempotency_requests`
    FROM `user_state` us WHERE us.`user_id` = ?');
  $summary->execute(array_fill(0, 11, $userId));
  $state = $summary->fetch(PDO::FETCH_ASSOC);
  $onboarding = (new OnboardingQuery(
    new WarbandUnitRepository($pdo),
    new UserCodexRepository($pdo),
    new PlayerStateRepository($pdo),
    $content,
  ))->execute($userId);
  if (!is_array($state)
    || (int)$state['teeth'] !== 0
    || (int)$state['raw_chaos'] !== 0
    || (int)$state['energy_current'] !== $content->startingEnergy()
    || (string)$state['active_squad_id'] !== ($freshOnboarding ? '' : (string)$fixture['active_squad_id'])
    || (int)$state['player_revision'] !== ($freshOnboarding ? 1 : 2)
    || (int)$state['units'] !== ($freshOnboarding ? 0 : count($fixture['unit_ids']))
    || (int)$state['squads'] !== ($freshOnboarding ? 0 : count($fixture['squad_ids']))
    || ($freshOnboarding && ((int)$state['dice'] !== 0 || (int)$state['codex_entries'] !== 0))
    || $onboarding['status'] !== ($freshOnboarding ? 'required' : 'complete')
    || (int)$state['runs'] !== 0
    || (int)$state['items'] !== 0
    || (int)$state['objectives'] !== 0
    || (int)$state['unlocks'] !== 0
    || (int)$state['resolved_events'] !== 0
    || (int)$state['idempotency_requests'] !== 0) {
    throw new RuntimeException('UAT account reset verification failed.');
  }

  fwrite(STDOUT, json_encode([
    'account' => ['id' => (string)$userId, 'display_name' => (string)$account['display_name']],
    'mode' => $freshOnboarding ? 'fresh_onboarding' : 'fixture',
    'fixture' => $fixture,
    'verified_state' => [
      'teeth' => 0,
      'raw_chaos' => 0,
      'energy' => $content->startingEnergy(),
      'units' => (int)$state['units'],
      'dice' => (int)$state['dice'],
      'squads' => (int)$state['squads'],
      'codex_entries' => (int)$state['codex_entries'],
      'runs' => 0,
      'items' => 0,
      'objectives' => 0,
      'unlocks' => 0,
      'resolved_events' => 0,
      'idempotency_requests' => 0,
      'player_revision' => $freshOnboarding ? 1 : 2,
      'onboarding_status' => $onboarding['status'],
    ],
  ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) . PHP_EOL);
} catch (Throwable $error) {
  fwrite(STDERR, $error->getMessage() . PHP_EOL);
  exit(1);
}
