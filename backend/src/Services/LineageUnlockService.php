<?php
declare(strict_types=1);

namespace DiceGoblins\Services;

use PDO;

final class LineageUnlockService
{
  public const BASIC_GOBLIN = 'basic_goblin';
  public const PIG_KIN = 'pig_kin';

  /** @var array<string,array{lineage_slug:string,kin_slug:string,name:string,description:string,is_default:bool,sort_order:int}> */
  private const LINEAGES = [
    self::BASIC_GOBLIN => [
      'lineage_slug' => self::BASIC_GOBLIN,
      'kin_slug' => self::BASIC_GOBLIN,
      'name' => 'Basic Goblin',
      'description' => 'The flexible baseline goblin lineage available to every player.',
      'is_default' => true,
      'sort_order' => 0,
    ],
    self::PIG_KIN => [
      'lineage_slug' => self::PIG_KIN,
      'kin_slug' => self::PIG_KIN,
      'name' => 'Pig Kin',
      'description' => 'The first reconstructed goblin-kin lineage, rooted in stubborn Farm materials.',
      'is_default' => false,
      'sort_order' => 10,
    ],
  ];

  public function __construct(
    private readonly PDO $pdo,
  ) {}

  /**
   * @return array<int,array{lineage_slug:string,kin_slug:string,name:string,description:string,is_default:bool,sort_order:int}>
   */
  public function listCatalog(): array
  {
    $lineages = array_values(self::LINEAGES);
    usort($lineages, static fn(array $left, array $right): int => $left['sort_order'] <=> $right['sort_order']);
    return $lineages;
  }

  /**
   * @return array<int,array{lineage_slug:string,kin_slug:string,name:string,description:string,is_default:bool,is_implicit:bool,unlocked_at:?string}>
   */
  public function listForUser(int $userId): array
  {
    $explicitUnlocks = $this->explicitUnlocksForUser($userId);
    $lineages = [];

    foreach ($this->listCatalog() as $definition) {
      $slug = (string)$definition['lineage_slug'];
      $isDefault = (bool)$definition['is_default'];
      if (!$isDefault && !isset($explicitUnlocks[$slug])) {
        continue;
      }

      $lineages[] = [
        'lineage_slug' => $slug,
        'kin_slug' => (string)$definition['kin_slug'],
        'name' => (string)$definition['name'],
        'description' => (string)$definition['description'],
        'is_default' => $isDefault,
        'is_implicit' => $isDefault,
        'unlocked_at' => $isDefault ? null : $explicitUnlocks[$slug],
      ];
    }

    return $lineages;
  }

  public function grant(int $userId, string $lineageSlug): void
  {
    $lineageSlug = trim($lineageSlug);
    if ($lineageSlug === '' || !isset(self::LINEAGES[$lineageSlug]) || $lineageSlug === self::BASIC_GOBLIN) {
      return;
    }

    (new UserUnlockService($this->pdo))->grant($userId, UserUnlockService::NAMESPACE_LINEAGE, $lineageSlug);
  }

  public function isUnlocked(int $userId, string $lineageSlug): bool
  {
    $lineageSlug = trim($lineageSlug);
    if ($lineageSlug === self::BASIC_GOBLIN) {
      return true;
    }
    if ($lineageSlug === '' || !isset(self::LINEAGES[$lineageSlug])) {
      return false;
    }

    return (new UserUnlockService($this->pdo))->isUnlocked($userId, UserUnlockService::NAMESPACE_LINEAGE, $lineageSlug);
  }

  /**
   * @return array<string,string>
   */
  private function explicitUnlocksForUser(int $userId): array
  {
    $stmt = $this->pdo->prepare('
      SELECT `unlock_key`, `created_at`
      FROM `user_unlocks`
      WHERE `user_id` = ? AND `unlock_namespace` = ?
      ORDER BY `created_at` ASC, `unlock_key` ASC
    ');
    $stmt->execute([$userId, UserUnlockService::NAMESPACE_LINEAGE]);

    $unlocks = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
      $slug = (string)$row['unlock_key'];
      if (isset(self::LINEAGES[$slug])) {
        $unlocks[$slug] = (string)$row['created_at'];
      }
    }

    return $unlocks;
  }
}
