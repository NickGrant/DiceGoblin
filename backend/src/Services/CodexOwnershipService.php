<?php
declare(strict_types=1);

namespace DiceGoblins\Services;

use PDO;

final class CodexOwnershipService
{
  public const TYPE_ENEMY = 'enemy';
  public const TYPE_BIOME = 'biome';
  public const TYPE_FEATURE = 'feature';
  public const TYPE_UNIT_TYPE = 'unit_type';
  public const TYPE_KIN = 'kin';
  public const TYPE_AFFIX = 'affix';
  public const TYPE_ITEM = 'item';
  public const TYPE_LORE = 'lore';

  /** @var list<string> */
  private const ENTRY_TYPES = [
    self::TYPE_ENEMY,
    self::TYPE_BIOME,
    self::TYPE_FEATURE,
    self::TYPE_UNIT_TYPE,
    self::TYPE_KIN,
    self::TYPE_AFFIX,
    self::TYPE_ITEM,
    self::TYPE_LORE,
  ];

  public function __construct(private readonly PDO $pdo) {}

  /**
   * @param array<string,mixed>|null $metadata
   */
  public function grant(int $userId, string $entryType, string $entryKey, string $source = 'system', ?array $metadata = null): bool
  {
    $entryType = $this->normalizeEntryType($entryType);
    $entryKey = trim($entryKey);
    $source = trim($source) !== '' ? trim($source) : 'system';
    if ($userId <= 0 || $entryType === null || $entryKey === '') {
      return false;
    }

    $stmt = $this->pdo->prepare('
      INSERT IGNORE INTO `user_codex_entries` (`user_id`, `entry_type`, `entry_key`, `source`, `metadata_json`)
      VALUES (?, ?, ?, ?, ?)
    ');
    $stmt->execute([
      $userId,
      $entryType,
      $entryKey,
      $source,
      $metadata !== null ? json_encode($metadata, JSON_UNESCAPED_SLASHES) : null,
    ]);

    return $stmt->rowCount() > 0;
  }

  public function isOwned(int $userId, string $entryType, string $entryKey): bool
  {
    $entryType = $this->normalizeEntryType($entryType);
    $entryKey = trim($entryKey);
    if ($userId <= 0 || $entryType === null || $entryKey === '') {
      return false;
    }

    $stmt = $this->pdo->prepare('
      SELECT 1
      FROM `user_codex_entries`
      WHERE `user_id` = ? AND `entry_type` = ? AND `entry_key` = ?
      LIMIT 1
    ');
    $stmt->execute([$userId, $entryType, $entryKey]);

    return (bool)$stmt->fetchColumn();
  }

  /**
   * @return array{owned_entries:list<array{entry_type:string,entry_key:string,source:string,metadata:array<string,mixed>,discovered_at:string}>,owned_by_type:array<string,list<string>>}
   */
  public function profilePayload(int $userId): array
  {
    $ownedEntries = $this->listForUser($userId);
    $ownedByType = [];
    foreach (self::ENTRY_TYPES as $type) {
      $ownedByType[$type] = [];
    }

    foreach ($ownedEntries as $entry) {
      $ownedByType[$entry['entry_type']][] = $entry['entry_key'];
    }

    foreach ($ownedByType as $type => $keys) {
      $ownedByType[$type] = array_values(array_unique($keys));
    }

    return [
      'owned_entries' => $ownedEntries,
      'owned_by_type' => $ownedByType,
    ];
  }

  /**
   * @return list<array{entry_type:string,entry_key:string,source:string,metadata:array<string,mixed>,discovered_at:string}>
   */
  public function listForUser(int $userId): array
  {
    if ($userId <= 0) {
      return [];
    }

    $stmt = $this->pdo->prepare('
      SELECT `entry_type`, `entry_key`, `source`, `metadata_json`, `discovered_at`
      FROM `user_codex_entries`
      WHERE `user_id` = ?
      ORDER BY `entry_type` ASC, `entry_key` ASC
    ');
    $stmt->execute([$userId]);

    return array_map(fn(array $row): array => [
      'entry_type' => (string)$row['entry_type'],
      'entry_key' => (string)$row['entry_key'],
      'source' => (string)$row['source'],
      'metadata' => $this->decodeMetadata($row['metadata_json'] ?? null),
      'discovered_at' => (string)$row['discovered_at'],
    ], $stmt->fetchAll(PDO::FETCH_ASSOC));
  }

  public function syncDerivedEntriesForUser(int $userId): void
  {
    if ($userId <= 0) {
      return;
    }

    $this->syncUnlockEntries($userId, UserUnlockService::NAMESPACE_FEATURE, self::TYPE_FEATURE, 'feature_unlock');
    $this->syncUnlockEntries($userId, UserUnlockService::NAMESPACE_UNIT_TYPE, self::TYPE_UNIT_TYPE, 'unit_type_unlock');
    $this->syncUnlockEntries($userId, UserUnlockService::NAMESPACE_LINEAGE, self::TYPE_KIN, 'lineage_unlock');
    $this->syncUnlockEntries($userId, UserUnlockService::NAMESPACE_DIALOGUE, self::TYPE_LORE, 'dialogue');
    $this->syncOwnedUnitTypeEntries($userId);
    $this->syncOwnedKinEntries($userId);
    $this->syncOwnedAffixEntries($userId);
    $this->syncOwnedItemEntries($userId);
    $this->syncCompletedBiomeEntries($userId);
  }

  private function syncUnlockEntries(int $userId, string $namespace, string $entryType, string $source): void
  {
    $stmt = $this->pdo->prepare('
      INSERT IGNORE INTO `user_codex_entries` (`user_id`, `entry_type`, `entry_key`, `source`, `discovered_at`)
      SELECT `user_id`, ?, `unlock_key`, ?, `created_at`
      FROM `user_unlocks`
      WHERE `user_id` = ? AND `unlock_namespace` = ?
    ');
    $stmt->execute([$entryType, $source, $userId, $namespace]);
  }

  private function syncOwnedUnitTypeEntries(int $userId): void
  {
    $stmt = $this->pdo->prepare('
      INSERT IGNORE INTO `user_codex_entries` (`user_id`, `entry_type`, `entry_key`, `source`, `discovered_at`)
      SELECT ui.`user_id`, ?, ut.`slug`, ?, MIN(ui.`created_at`)
      FROM `unit_instances` ui
      JOIN `unit_types` ut ON ut.`id` = ui.`unit_type_id`
      WHERE ui.`user_id` = ?
      GROUP BY ui.`user_id`, ut.`slug`
    ');
    $stmt->execute([self::TYPE_UNIT_TYPE, 'owned_unit', $userId]);
  }

  private function syncOwnedKinEntries(int $userId): void
  {
    $stmt = $this->pdo->prepare('
      INSERT IGNORE INTO `user_codex_entries` (`user_id`, `entry_type`, `entry_key`, `source`, `discovered_at`)
      SELECT `user_id`, ?, `splice_variant_slug`, ?, MIN(`created_at`)
      FROM `unit_instances`
      WHERE `user_id` = ? AND `splice_variant_slug` IS NOT NULL AND `splice_variant_slug` <> \'\'
      GROUP BY `user_id`, `splice_variant_slug`
    ');
    $stmt->execute([self::TYPE_KIN, 'owned_unit', $userId]);
  }

  private function syncOwnedAffixEntries(int $userId): void
  {
    $stmt = $this->pdo->prepare('
      INSERT IGNORE INTO `user_codex_entries` (`user_id`, `entry_type`, `entry_key`, `source`, `discovered_at`)
      SELECT di.`user_id`, ?, ad.`slug`, ?, MIN(di.`created_at`)
      FROM `dice_instances` di
      JOIN `dice_instance_affixes` dia ON dia.`dice_instance_id` = di.`id`
      JOIN `affix_definitions` ad ON ad.`id` = dia.`affix_definition_id`
      WHERE di.`user_id` = ?
      GROUP BY di.`user_id`, ad.`slug`
    ');
    $stmt->execute([self::TYPE_AFFIX, 'owned_die', $userId]);
  }

  private function syncOwnedItemEntries(int $userId): void
  {
    $stmt = $this->pdo->prepare('
      INSERT IGNORE INTO `user_codex_entries` (`user_id`, `entry_type`, `entry_key`, `source`, `discovered_at`)
      SELECT ui.`user_id`, ?, i.`slug`, ?, ui.`first_acquired_at`
      FROM `user_items` ui
      JOIN `items` i ON i.`id` = ui.`item_id`
      WHERE ui.`user_id` = ? AND ui.`quantity` > 0
    ');
    $stmt->execute([self::TYPE_ITEM, 'owned_item', $userId]);
  }

  private function syncCompletedBiomeEntries(int $userId): void
  {
    $stmt = $this->pdo->prepare('
      INSERT IGNORE INTO `user_codex_entries` (`user_id`, `entry_type`, `entry_key`, `source`, `discovered_at`)
      SELECT rr.`user_id`, ?, r.`slug`, ?, MIN(COALESCE(rr.`ended_at`, rr.`updated_at`, rr.`created_at`))
      FROM `region_runs` rr
      JOIN `regions` r ON r.`id` = rr.`region_id`
      WHERE rr.`user_id` = ? AND rr.`status` = \'completed\'
      GROUP BY rr.`user_id`, r.`slug`
    ');
    $stmt->execute([self::TYPE_BIOME, 'completed_run', $userId]);
  }

  private function normalizeEntryType(string $entryType): ?string
  {
    $entryType = trim($entryType);
    return in_array($entryType, self::ENTRY_TYPES, true) ? $entryType : null;
  }

  /**
   * @return array<string,mixed>
   */
  private function decodeMetadata(mixed $raw): array
  {
    if (!is_string($raw) || trim($raw) === '') {
      return [];
    }

    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
  }
}
