<?php
declare(strict_types=1);

namespace DiceGoblins\Services;

use PDO;

final class SpliceVariantService
{
  public const BASIC_GOBLIN = 'basic_goblin';

  private LineageUnlockService $lineageUnlockService;

  public function __construct(
    private readonly PDO $pdo,
    ?LineageUnlockService $lineageUnlockService = null,
  ) {
    $this->lineageUnlockService = $lineageUnlockService ?? new LineageUnlockService($pdo);
  }

  public function rollVariantSlug(?int $roll = null): string
  {
    return $this->rollFromVariants($this->enabledVariants(), $roll);
  }

  public function rollVariantSlugForUser(int $userId, ?int $roll = null): string
  {
    return $this->rollFromVariants($this->enabledVariantsForUser($userId), $roll);
  }

  public function totalEnabledWeight(): int
  {
    return $this->totalWeight($this->enabledVariants());
  }

  public function totalEnabledWeightForUser(int $userId): int
  {
    return $this->totalWeight($this->enabledVariantsForUser($userId));
  }

  /**
   * @param list<array{slug:string,grant_weight:int}> $variants
   */
  private function rollFromVariants(array $variants, ?int $roll = null): string
  {
    if ($variants === []) {
      return self::BASIC_GOBLIN;
    }

    $totalWeight = $this->totalWeight($variants);
    if ($totalWeight <= 0) {
      return self::BASIC_GOBLIN;
    }

    $cursor = $roll ?? random_int(0, $totalWeight - 1);
    $cursor = max(0, min($totalWeight - 1, $cursor));

    foreach ($variants as $variant) {
      $weight = max(0, (int)$variant['grant_weight']);
      if ($weight <= 0) {
        continue;
      }

      if ($cursor < $weight) {
        return (string)$variant['slug'];
      }

      $cursor -= $weight;
    }

    return self::BASIC_GOBLIN;
  }

  /**
   * @param list<array{slug:string,grant_weight:int}> $variants
   */
  private function totalWeight(array $variants): int
  {
    return array_sum(array_map(static fn(array $row): int => max(0, (int)$row['grant_weight']), $variants));
  }

  /**
   * @return array{slug:string,name:string,description:string,passive_summary:string,stat_modifiers:array<string,int>}
   */
  public function describeVariant(string $slug): array
  {
    $stmt = $this->pdo->prepare('
      SELECT `slug`, `name`, `description`, `passive_summary`, `stat_modifiers_json`
      FROM `splice_variants`
      WHERE `slug` = ?
      LIMIT 1
    ');
    $stmt->execute([$slug]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!is_array($row)) {
      return [
        'slug' => self::BASIC_GOBLIN,
        'name' => 'Basic Goblin',
        'description' => 'Baseline goblin stock with no kin tendency.',
        'passive_summary' => 'No kin trait.',
        'stat_modifiers' => [],
      ];
    }

    return [
      'slug' => (string)$row['slug'],
      'name' => (string)$row['name'],
      'description' => (string)$row['description'],
      'passive_summary' => (string)$row['passive_summary'],
      'stat_modifiers' => $this->decodeModifiers($row['stat_modifiers_json'] ?? null),
    ];
  }

  /**
   * @return list<array{slug:string,grant_weight:int}>
   */
  private function enabledVariants(): array
  {
    $stmt = $this->pdo->query('
      SELECT `slug`, `grant_weight`
      FROM `splice_variants`
      WHERE `is_enabled` = 1 AND `grant_weight` > 0
      ORDER BY `id` ASC
    ');

    return array_map(static fn(array $row): array => [
      'slug' => (string)$row['slug'],
      'grant_weight' => max(0, (int)$row['grant_weight']),
    ], $stmt->fetchAll(PDO::FETCH_ASSOC));
  }

  /**
   * @return list<array{slug:string,grant_weight:int}>
   */
  private function enabledVariantsForUser(int $userId): array
  {
    return array_values(array_filter(
      $this->enabledVariants(),
      fn(array $variant): bool => $this->isVariantRandomlyAvailableForUser($userId, (string)$variant['slug'])
    ));
  }

  private function isVariantRandomlyAvailableForUser(int $userId, string $variantSlug): bool
  {
    $lineageSlug = $this->lineageSlugForKinSlug($variantSlug);
    if ($lineageSlug === null) {
      return false;
    }

    return $this->lineageUnlockService->isUnlocked($userId, $lineageSlug);
  }

  private function lineageSlugForKinSlug(string $kinSlug): ?string
  {
    foreach ($this->lineageUnlockService->listCatalog() as $lineage) {
      $lineageSlug = (string)$lineage['lineage_slug'];
      if ($kinSlug === $lineageSlug || $kinSlug === (string)$lineage['kin_slug']) {
        return $lineageSlug;
      }
    }

    return null;
  }

  /**
   * @return array<string,int>
   */
  private function decodeModifiers(mixed $raw): array
  {
    if (is_string($raw)) {
      $decoded = json_decode($raw, true);
      $raw = is_array($decoded) ? $decoded : [];
    }

    if (!is_array($raw)) {
      return [];
    }

    $out = [];
    foreach (['attack', 'defense', 'max_hp', 'precision', 'resolve'] as $key) {
      $out[$key] = (int)($raw[$key] ?? 0);
    }

    return $out;
  }
}
