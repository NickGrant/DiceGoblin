<?php
declare(strict_types=1);

namespace DiceGoblins\Tests\Support;

use DiceGoblins\Core\Db;
use PDO;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

abstract class IntegrationTestCase extends TestCase
{
  protected ?PDO $pdo = null;

  /** @var array<string,bool> */
  private array $schemaPresenceCache = [];

  /** @var array<int,int> */
  private array $trackedUserIds = [];

  /** @var array<int,int> */
  private array $trackedRegionIds = [];

  protected function setUp(): void
  {
    parent::setUp();

    $dsn = getenv('TEST_DB_DSN') ?: '';
    $user = getenv('TEST_DB_USER') ?: '';
    $pass = getenv('TEST_DB_PASS') ?: '';
    if ($dsn === '') {
      $this->markTestSkipped($this->integrationSkipMessage());
    }

    $this->pdo = new PDO($dsn, $user, $pass, [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
      PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    $this->resetHttpGlobals();
    $this->resetDbSingleton();
  }

  protected function tearDown(): void
  {
    if ($this->pdo !== null) {
      $this->deleteTrackedData();
    }

    $this->resetHttpGlobals();
    $this->resetDbSingleton();
    $this->pdo = null;

    parent::tearDown();
  }

  protected function integrationSkipMessage(): string
  {
    return 'Set TEST_DB_DSN to run integration tests.';
  }

  /**
   * @param array<string,mixed> $body
   */
  protected function setJsonBody(array $body): void
  {
    $_POST = [];
    $_SERVER['DICE_GOBLINS_TEST_RAW_BODY'] = (string)json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  }

  /**
   * @return array{status:int,body:array<string,mixed>}
   */
  protected function invoke(callable $fn): array
  {
    http_response_code(200);
    ob_start();
    try {
      $fn();
      $raw = (string)ob_get_clean();
    } finally {
      $_POST = [];
      unset($_SERVER['DICE_GOBLINS_TEST_RAW_BODY']);
    }

    $decoded = json_decode($raw, true);
    return [
      'status' => http_response_code(),
      'body' => is_array($decoded) ? $decoded : [],
    ];
  }

  protected function insertUser(string $discordPrefix = 'qa_user', string $displayPrefix = 'QA User'): int
  {
    $token = bin2hex(random_bytes(4));
    $normalizedPrefix = substr(preg_replace('/[^a-z0-9_]/i', '_', $discordPrefix) ?? 'qa_user', 0, 23);
    $stmt = $this->pdo?->prepare('INSERT INTO `users` (`discord_id`, `display_name`) VALUES (?, ?)');
    $stmt?->execute(["{$normalizedPrefix}_{$token}", "{$displayPrefix} {$token}"]);
    $id = (int)$this->pdo?->lastInsertId();
    $this->trackedUserIds[] = $id;
    return $id;
  }

  protected function trackUserId(int $userId): void
  {
    if ($userId > 0) {
      $this->trackedUserIds[] = $userId;
    }
  }

  protected function grantUnlock(int $userId, string $namespace, string $unlockKey): void
  {
    $stmt = $this->pdo?->prepare('
      INSERT IGNORE INTO `user_unlocks` (`user_id`, `unlock_namespace`, `unlock_key`)
      VALUES (?, ?, ?)
    ');
    $stmt?->execute([$userId, $namespace, $unlockKey]);
  }

  protected function setSoftCurrency(int $userId, int $amount): void
  {
    $stmt = $this->pdo?->prepare('
      INSERT INTO `player_state` (`user_id`, `currency_soft`, `currency_hard`, `last_login_at`)
      VALUES (?, ?, 0, NULL)
      ON DUPLICATE KEY UPDATE `currency_soft` = VALUES(`currency_soft`)
    ');
    $stmt?->execute([$userId, max(0, $amount)]);
  }

  protected function insertRegion(
    int $energyCost = 5,
    bool $enabled = true,
    string $slugPrefix = 'qa-region',
    string $namePrefix = 'QA Region',
    string $theme = 'qa_theme'
  ): int {
    $token = bin2hex(random_bytes(4));
    $stmt = $this->pdo?->prepare(
      'INSERT INTO `regions` (`slug`, `name`, `theme`, `recommended_level`, `energy_cost`, `is_enabled`) VALUES (?, ?, ?, 1, ?, ?)'
    );
    $stmt?->execute(["{$slugPrefix}-{$token}", "{$namePrefix} {$token}", $theme, $energyCost, $enabled ? 1 : 0]);
    $id = (int)$this->pdo?->lastInsertId();
    $this->trackedRegionIds[] = $id;
    return $id;
  }

  protected function insertTeam(int $userId, string $name = 'QA Squad', bool $active = true): int
  {
    $stmt = $this->pdo?->prepare('INSERT INTO `teams` (`user_id`, `name`, `is_active`) VALUES (?, ?, ?)');
    $stmt?->execute([$userId, $name, $active ? 1 : 0]);
    return (int)$this->pdo?->lastInsertId();
  }

  protected function insertRun(int $userId, int $regionId, int $seed = 555111, string $status = 'active'): int
  {
    $stmt = $this->pdo?->prepare('INSERT INTO `region_runs` (`user_id`, `region_id`, `seed`, `status`) VALUES (?, ?, ?, ?)');
    $stmt?->execute([$userId, $regionId, $seed, $status]);
    return (int)$this->pdo?->lastInsertId();
  }

  protected function unlockRegion(int $userId, int $regionId): void
  {
    $stmt = $this->pdo?->prepare('INSERT INTO `region_unlocks` (`user_id`, `region_id`) VALUES (?, ?)');
    $stmt?->execute([$userId, $regionId]);
  }

  protected function setEnergy(int $userId, int $current, int $max): void
  {
    $stmt = $this->pdo?->prepare(
      'INSERT INTO `energy_state` (`user_id`, `energy_current`, `energy_max`, `regen_rate_per_hour`, `last_regen_at`)
       VALUES (?, ?, ?, 6.00, UTC_TIMESTAMP())
       ON DUPLICATE KEY UPDATE `energy_current` = VALUES(`energy_current`), `energy_max` = VALUES(`energy_max`), `last_regen_at` = VALUES(`last_regen_at`)'
    );
    $stmt?->execute([$userId, $current, $max]);
  }

  /**
   * @param array<int,int|string> $params
   * @return int|string
   */
  protected function scalar(string $sql, array $params): int|string
  {
    $stmt = $this->pdo?->prepare($sql);
    $stmt?->execute($params);
    $value = $stmt?->fetchColumn();
    return is_string($value) || is_int($value) ? $value : (string)$value;
  }

  /**
   * @param array<int,int> $ids
   */
  protected function deleteByIds(string $table, string $idColumn, array $ids): void
  {
    $ids = array_values(array_unique(array_filter($ids, static fn(int $v): bool => $v > 0)));
    if (count($ids) === 0) {
      return;
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $this->pdo?->prepare("DELETE FROM `$table` WHERE `$idColumn` IN ($placeholders)");
    $stmt?->execute($ids);
  }

  private function deleteTrackedData(): void
  {
    if (count($this->trackedUserIds) > 0) {
      $this->deleteUserOwnedData($this->trackedUserIds);
    }
    if (count($this->trackedRegionIds) > 0) {
      $this->deleteByIds('regions', 'id', $this->trackedRegionIds);
    }

    $this->trackedUserIds = [];
    $this->trackedRegionIds = [];
  }

  /**
   * @param array<int,int> $userIds
   */
  private function deleteUserOwnedData(array $userIds): void
  {
    $userIds = array_values(array_unique(array_filter($userIds, static fn(int $v): bool => $v > 0)));
    if (count($userIds) === 0) {
      return;
    }

    $placeholders = implode(',', array_fill(0, count($userIds), '?'));

    $this->execDeleteByUserIds("DELETE br FROM `battle_rewards` br JOIN `battles` b ON b.`id` = br.`battle_id` WHERE b.`user_id` IN ($placeholders)", $userIds);
    $this->execDeleteByUserIds("DELETE bl FROM `battle_logs` bl JOIN `battles` b ON b.`id` = bl.`battle_id` WHERE b.`user_id` IN ($placeholders)", $userIds);
    $this->execDeleteByUserIds("DELETE FROM `battles` WHERE `user_id` IN ($placeholders)", $userIds);

    if ($this->schemaHasTable('chaos_encounter_results')) {
      $this->execDeleteByUserIds("DELETE FROM `chaos_encounter_results` WHERE `user_id` IN ($placeholders)", $userIds);
    }
    $this->execDeleteByUserIds("DELETE re FROM `run_edges` re JOIN `region_runs` rr ON rr.`id` = re.`run_id` WHERE rr.`user_id` IN ($placeholders)", $userIds);
    $this->execDeleteByUserIds("DELETE rus FROM `run_unit_state` rus JOIN `region_runs` rr ON rr.`id` = rus.`run_id` WHERE rr.`user_id` IN ($placeholders)", $userIds);
    $this->execDeleteByUserIds("DELETE rn FROM `run_nodes` rn JOIN `region_runs` rr ON rr.`id` = rn.`run_id` WHERE rr.`user_id` IN ($placeholders)", $userIds);
    $this->execDeleteByUserIds("DELETE FROM `region_runs` WHERE `user_id` IN ($placeholders)", $userIds);

    $this->execDeleteByUserIds("DELETE tf FROM `team_formation` tf JOIN `teams` t ON t.`id` = tf.`team_id` WHERE t.`user_id` IN ($placeholders)", $userIds);
    $this->execDeleteByUserIds("DELETE tu FROM `team_units` tu JOIN `teams` t ON t.`id` = tu.`team_id` WHERE t.`user_id` IN ($placeholders)", $userIds);
    if ($this->schemaHasTable('unit_ability_dice')) {
      $this->execDeleteByUserIds("DELETE uad FROM `unit_ability_dice` uad JOIN `unit_instances` ui ON ui.`id` = uad.`unit_instance_id` WHERE ui.`user_id` IN ($placeholders)", $userIds);
    }
    if ($this->schemaHasTable('unit_instance_equipped_abilities')) {
      $this->execDeleteByUserIds("DELETE uea FROM `unit_instance_equipped_abilities` uea JOIN `unit_instances` ui ON ui.`id` = uea.`unit_instance_id` WHERE ui.`user_id` IN ($placeholders)", $userIds);
    }
    if ($this->schemaHasTable('unit_instance_unlocked_abilities')) {
      $this->execDeleteByUserIds("DELETE uua FROM `unit_instance_unlocked_abilities` uua JOIN `unit_instances` ui ON ui.`id` = uua.`unit_instance_id` WHERE ui.`user_id` IN ($placeholders)", $userIds);
    }
    $this->execDeleteByUserIds("DELETE ud FROM `unit_dice` ud JOIN `unit_instances` ui ON ui.`id` = ud.`unit_instance_id` WHERE ui.`user_id` IN ($placeholders)", $userIds);
    $this->execDeleteByUserIds("DELETE dia FROM `dice_instance_affixes` dia JOIN `dice_instances` di ON di.`id` = dia.`dice_instance_id` WHERE di.`user_id` IN ($placeholders)", $userIds);
    if ($this->schemaHasTable('shop_daily_deals')) {
      $this->execDeleteByUserIds("DELETE FROM `shop_daily_deals` WHERE `user_id` IN ($placeholders)", $userIds);
    }

    if ($this->schemaHasTable('user_bounties')) {
      $this->execDeleteByUserIds("DELETE FROM `user_bounties` WHERE `user_id` IN ($placeholders)", $userIds);
    }
    $this->execDeleteByUserIds("DELETE FROM `user_grants` WHERE `user_id` IN ($placeholders)", $userIds);
    if ($this->schemaHasTable('user_unlocks')) {
      $this->execDeleteByUserIds("DELETE FROM `user_unlocks` WHERE `user_id` IN ($placeholders)", $userIds);
    }
    $this->execDeleteByUserIds("DELETE FROM `unit_promotions` WHERE `user_id` IN ($placeholders)", $userIds);
    $this->execDeleteByUserIds("DELETE FROM `user_region_items` WHERE `user_id` IN ($placeholders)", $userIds);
    $this->execDeleteByUserIds("DELETE FROM `region_unlocks` WHERE `user_id` IN ($placeholders)", $userIds);
    $this->execDeleteByUserIds("DELETE FROM `dice_instances` WHERE `user_id` IN ($placeholders)", $userIds);
    $this->execDeleteByUserIds("DELETE FROM `unit_instances` WHERE `user_id` IN ($placeholders)", $userIds);
    $this->execDeleteByUserIds("DELETE FROM `teams` WHERE `user_id` IN ($placeholders)", $userIds);
    $this->execDeleteByUserIds("DELETE FROM `energy_state` WHERE `user_id` IN ($placeholders)", $userIds);
    $this->execDeleteByUserIds("DELETE FROM `player_state` WHERE `user_id` IN ($placeholders)", $userIds);
    $this->execDeleteByUserIds("DELETE FROM `users` WHERE `id` IN ($placeholders)", $userIds);
  }

  /**
   * @param array<int,int> $userIds
   */
  private function execDeleteByUserIds(string $sql, array $userIds): void
  {
    $stmt = $this->pdo?->prepare($sql);
    $stmt?->execute($userIds);
  }

  private function schemaHasTable(string $table): bool
  {
    if (array_key_exists($table, $this->schemaPresenceCache)) {
      return $this->schemaPresenceCache[$table];
    }

    $stmt = $this->pdo?->prepare('
      SELECT COUNT(*)
      FROM INFORMATION_SCHEMA.TABLES
      WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = ?
    ');
    $stmt?->execute([$table]);
    $exists = ((int)($stmt?->fetchColumn() ?: 0)) > 0;
    $this->schemaPresenceCache[$table] = $exists;
    return $exists;
  }

  private function resetHttpGlobals(): void
  {
    $_SESSION = [];
    $_POST = [];
    $_SERVER['HTTP_X_CSRF_TOKEN'] = '';
    unset($_SERVER['DICE_GOBLINS_TEST_RAW_BODY']);
    http_response_code(200);
  }

  private function resetDbSingleton(): void
  {
    $ref = new ReflectionClass(Db::class);
    $prop = $ref->getProperty('pdo');
    $prop->setAccessible(true);
    $prop->setValue(null, null);
  }
}
