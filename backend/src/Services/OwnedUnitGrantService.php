<?php
declare(strict_types=1);

namespace DiceGoblins\Services;

use DiceGoblins\Repositories\UnitRepository;
use PDO;
use RuntimeException;

final class OwnedUnitGrantService
{
  private UnitRepository $unitRepo;
  private UnitNameGenerator $unitNameGenerator;
  private UnitLoadoutService $unitLoadoutService;
  private SpliceVariantService $spliceVariantService;

  public function __construct(
    private readonly PDO $pdo,
    ?UnitRepository $unitRepo = null,
    ?UnitNameGenerator $unitNameGenerator = null,
    ?UnitLoadoutService $unitLoadoutService = null,
    ?SpliceVariantService $spliceVariantService = null,
  ) {
    $this->unitRepo = $unitRepo ?? new UnitRepository($pdo);
    $this->unitNameGenerator = $unitNameGenerator ?? new UnitNameGenerator();
    $this->unitLoadoutService = $unitLoadoutService ?? new UnitLoadoutService($pdo);
    $this->spliceVariantService = $spliceVariantService ?? new SpliceVariantService($pdo);
  }

  /**
   * @return array{id:int,unit_type_id:int,unit_type_slug:string,tier:int,level:int,splice_variant_slug:string}
   */
  public function grantBySlug(
    int $userId,
    string $unitTypeSlug,
    ?int $tier = null,
    int $level = 1,
    int $xp = 0,
    bool $locked = false,
    ?string $displayName = null,
    ?string $spliceVariantSlug = null,
  ): array {
    $type = $this->loadUnitTypeBySlug($unitTypeSlug);
    if ($type === null) {
      throw new RuntimeException('Unknown unit_type_slug.');
    }

    return $this->grantByTypeId(
      $userId,
      (int)$type['id'],
      (string)$type['slug'],
      $tier ?? $this->tierFromSlug((string)$type['slug']),
      $level,
      $xp,
      $locked,
      $displayName,
      $spliceVariantSlug,
    );
  }

  /**
   * @return array{id:int,unit_type_id:int,unit_type_slug:string,tier:int,level:int,splice_variant_slug:string}
   */
  public function grantByTypeId(
    int $userId,
    int $unitTypeId,
    string $unitTypeSlug,
    int $tier = 1,
    int $level = 1,
    int $xp = 0,
    bool $locked = false,
    ?string $displayName = null,
    ?string $spliceVariantSlug = null,
  ): array {
    $resolvedSpliceVariantSlug = $this->resolveSpliceVariantSlug($spliceVariantSlug);
    $unitId = $this->unitRepo->createUnitInstance(
      $userId,
      $unitTypeId,
      max(1, $tier),
      max(1, $level),
      max(0, $xp),
      $locked,
      $displayName !== null ? trim($displayName) : $this->unitNameGenerator->generate(),
      $resolvedSpliceVariantSlug,
    );
    $this->unitLoadoutService->initializeUnit($unitId, $unitTypeId);

    return [
      'id' => $unitId,
      'unit_type_id' => $unitTypeId,
      'unit_type_slug' => $unitTypeSlug,
      'tier' => max(1, $tier),
      'level' => max(1, $level),
      'splice_variant_slug' => $resolvedSpliceVariantSlug,
    ];
  }

  /**
   * @return array{id:int,slug:string}|null
   */
  private function loadUnitTypeBySlug(string $unitTypeSlug): ?array
  {
    $stmt = $this->pdo->prepare('
      SELECT `id`, `slug`
      FROM `unit_types`
      WHERE `slug` = ?
      LIMIT 1
    ');
    $stmt->execute([$unitTypeSlug]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!is_array($row)) {
      return null;
    }

    return [
      'id' => (int)$row['id'],
      'slug' => (string)$row['slug'],
    ];
  }

  private function tierFromSlug(string $unitTypeSlug): int
  {
    if (preg_match('/_t(\d+)$/', $unitTypeSlug, $matches)) {
      return max(1, (int)$matches[1]);
    }

    return 1;
  }

  private function resolveSpliceVariantSlug(?string $spliceVariantSlug): string
  {
    $slug = trim((string)$spliceVariantSlug);
    if ($slug !== '') {
      return $this->spliceVariantService->describeVariant($slug)['slug'];
    }

    return $this->spliceVariantService->rollVariantSlug();
  }
}
