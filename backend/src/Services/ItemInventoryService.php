<?php
declare(strict_types=1);

namespace DiceGoblins\Services;

use PDO;
use RuntimeException;

final class ItemInventoryService
{
  public function __construct(private readonly PDO $pdo)
  {
  }

  /**
   * @return array<int,array{
   *   item_id:string,
   *   item_slug:string,
   *   name:string,
   *   description:string,
   *   category:string,
   *   quantity:int,
   *   rarity:string,
   *   source_region_slug:?string,
   *   source_region_name:?string,
   *   source_family_slug:?string,
   *   icon_key:?string,
   *   lore_key:?string,
   *   is_visible_before_discovery:bool,
   *   is_spendable:bool,
   *   is_primary_progression:bool,
   *   meta:array<string,mixed>
   * }>
   */
  public function listForUser(int $userId): array
  {
    $stmt = $this->pdo->prepare('
      SELECT
        i.`id`,
        i.`slug`,
        i.`name`,
        i.`description`,
        i.`category`,
        i.`rarity`,
        i.`source_family_slug`,
        i.`icon_key`,
        i.`lore_key`,
        i.`is_visible_before_discovery`,
        i.`is_spendable`,
        i.`is_primary_progression`,
        i.`meta_json`,
        r.`slug` AS `source_region_slug`,
        r.`name` AS `source_region_name`,
        ui.`quantity`
      FROM `user_items` ui
      JOIN `items` i ON i.`id` = ui.`item_id`
      LEFT JOIN `regions` r ON r.`id` = i.`source_region_id`
      WHERE ui.`user_id` = ?
        AND ui.`quantity` > 0
      ORDER BY i.`category` ASC, i.`slug` ASC
    ');
    $stmt->execute([$userId]);

    return array_map(fn(array $row): array => $this->mapOwnedItemRow($row), $stmt->fetchAll(PDO::FETCH_ASSOC));
  }

  /**
   * @return array<int,array{
   *   id:string,
   *   slug:string,
   *   name:string,
   *   description:string,
   *   category:string,
   *   rarity:string,
   *   source_region_slug:?string,
   *   source_region_name:?string,
   *   source_family_slug:?string,
   *   is_stackable:bool
   * }>
   */
  public function listCatalog(): array
  {
    $stmt = $this->pdo->query('
      SELECT
        i.`id`,
        i.`slug`,
        i.`name`,
        i.`description`,
        i.`category`,
        i.`rarity`,
        i.`source_family_slug`,
        i.`is_stackable`,
        r.`slug` AS `source_region_slug`,
        r.`name` AS `source_region_name`
      FROM `items` i
      LEFT JOIN `regions` r ON r.`id` = i.`source_region_id`
      ORDER BY i.`category` ASC, i.`rarity` ASC, i.`slug` ASC
    ');

    return array_map(static fn(array $row): array => [
      'id' => (string)$row['id'],
      'slug' => (string)$row['slug'],
      'name' => (string)$row['name'],
      'description' => (string)$row['description'],
      'category' => (string)$row['category'],
      'rarity' => (string)$row['rarity'],
      'source_region_slug' => isset($row['source_region_slug']) ? (string)$row['source_region_slug'] : null,
      'source_region_name' => isset($row['source_region_name']) ? (string)$row['source_region_name'] : null,
      'source_family_slug' => isset($row['source_family_slug']) ? (string)$row['source_family_slug'] : null,
      'is_stackable' => ((int)$row['is_stackable']) === 1,
    ], $stmt->fetchAll(PDO::FETCH_ASSOC));
  }

  /**
   * @return array{item_slug:string,quantity:int,granted_quantity:int}
   */
  public function grantBySlug(int $userId, string $itemSlug, int $quantity): array
  {
    $itemSlug = trim($itemSlug);
    $quantity = max(1, $quantity);
    $itemId = $this->lookupItemId($itemSlug);
    if ($itemId === null) {
      throw new RuntimeException('Unknown item_slug.');
    }

    $stmt = $this->pdo->prepare('
      INSERT INTO `user_items` (`user_id`, `item_id`, `quantity`)
      VALUES (?, ?, ?)
      ON DUPLICATE KEY UPDATE `quantity` = `quantity` + VALUES(`quantity`)
    ');
    $stmt->execute([$userId, $itemId, $quantity]);

    return [
      'item_slug' => $itemSlug,
      'quantity' => $this->quantityFor($userId, $itemId),
      'granted_quantity' => $quantity,
    ];
  }

  /**
   * Spend an owned item row inside an existing transaction.
   *
   * @return array{item_slug:string,quantity:int,spent_quantity:int}
   */
  public function spendBySlugForUpdate(int $userId, string $itemSlug, int $quantity): array
  {
    $itemSlug = trim($itemSlug);
    $quantity = max(1, $quantity);
    $itemId = $this->lookupItemId($itemSlug);
    if ($itemId === null) {
      throw new RuntimeException('Unknown item_slug.');
    }

    $stmt = $this->pdo->prepare('
      SELECT `quantity`
      FROM `user_items`
      WHERE `user_id` = ? AND `item_id` = ?
      LIMIT 1
      FOR UPDATE
    ');
    $stmt->execute([$userId, $itemId]);
    $owned = (int)($stmt->fetchColumn() ?: 0);
    if ($owned < $quantity) {
      throw new RuntimeException('insufficient_items');
    }

    $nextQuantity = $owned - $quantity;
    $update = $this->pdo->prepare('
      UPDATE `user_items`
      SET `quantity` = ?
      WHERE `user_id` = ? AND `item_id` = ?
    ');
    $update->execute([$nextQuantity, $userId, $itemId]);

    return [
      'item_slug' => $itemSlug,
      'quantity' => $nextQuantity,
      'spent_quantity' => $quantity,
    ];
  }

  /**
   * @param array<string,mixed> $rewards
   * @return array<int,array{item_slug:string,quantity:int,granted_quantity:int}>
   */
  public function materializeRewardItemGrants(int $userId, array $rewards): array
  {
    $itemGrants = $rewards['item_grants'] ?? null;
    if (!is_array($itemGrants) || $itemGrants === []) {
      return [];
    }

    $granted = [];
    foreach ($itemGrants as $grant) {
      if (!is_array($grant)) {
        continue;
      }

      $slug = trim((string)($grant['item_slug'] ?? ''));
      if ($slug === '') {
        continue;
      }

      try {
        $granted[] = $this->grantBySlug($userId, $slug, max(1, (int)($grant['quantity'] ?? 1)));
      } catch (RuntimeException) {
        continue;
      }
    }

    return $granted;
  }

  private function lookupItemId(string $slug): ?int
  {
    $stmt = $this->pdo->prepare('SELECT `id` FROM `items` WHERE `slug` = ? LIMIT 1');
    $stmt->execute([$slug]);
    $value = $stmt->fetchColumn();
    return $value === false ? null : (int)$value;
  }

  private function quantityFor(int $userId, int $itemId): int
  {
    $stmt = $this->pdo->prepare('SELECT `quantity` FROM `user_items` WHERE `user_id` = ? AND `item_id` = ? LIMIT 1');
    $stmt->execute([$userId, $itemId]);
    return (int)($stmt->fetchColumn() ?: 0);
  }

  /**
   * @return array<string,mixed>
   */
  private function decodeMeta(mixed $raw): array
  {
    if (!is_string($raw) || trim($raw) === '') {
      return [];
    }

    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
  }

  /**
   * @return array<string,mixed>
   */
  private function mapOwnedItemRow(array $row): array
  {
    return [
      'item_id' => (string)$row['id'],
      'item_slug' => (string)$row['slug'],
      'name' => (string)$row['name'],
      'description' => (string)$row['description'],
      'category' => (string)$row['category'],
      'quantity' => (int)$row['quantity'],
      'rarity' => (string)$row['rarity'],
      'source_region_slug' => isset($row['source_region_slug']) ? (string)$row['source_region_slug'] : null,
      'source_region_name' => isset($row['source_region_name']) ? (string)$row['source_region_name'] : null,
      'source_family_slug' => isset($row['source_family_slug']) ? (string)$row['source_family_slug'] : null,
      'icon_key' => isset($row['icon_key']) ? (string)$row['icon_key'] : null,
      'lore_key' => isset($row['lore_key']) ? (string)$row['lore_key'] : null,
      'is_visible_before_discovery' => ((int)$row['is_visible_before_discovery']) === 1,
      'is_spendable' => ((int)$row['is_spendable']) === 1,
      'is_primary_progression' => ((int)$row['is_primary_progression']) === 1,
      'meta' => $this->decodeMeta($row['meta_json'] ?? null),
    ];
  }
}
