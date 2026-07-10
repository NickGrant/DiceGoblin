<?php
declare(strict_types=1);

namespace DiceGoblins\Services;

use PDO;
use RuntimeException;

final class UserAssetGrantService
{
  private OwnedUnitGrantService $ownedUnitGrantService;
  private OwnedDiceGrantService $ownedDiceGrantService;
  private UserUnlockService $userUnlockService;

  public function __construct(
    private readonly PDO $pdo,
    ?OwnedUnitGrantService $ownedUnitGrantService = null,
    ?OwnedDiceGrantService $ownedDiceGrantService = null,
    ?UserUnlockService $userUnlockService = null,
  ) {
    $this->ownedUnitGrantService = $ownedUnitGrantService ?? new OwnedUnitGrantService($pdo);
    $this->ownedDiceGrantService = $ownedDiceGrantService ?? new OwnedDiceGrantService($pdo);
    $this->userUnlockService = $userUnlockService ?? new UserUnlockService($pdo);
  }

  /**
   * @return array{id:int,unit_type_id:int,unit_type_slug:string,tier:int,level:int}
   */
  public function grantUnitBySlug(
    int $userId,
    string $unitTypeSlug,
    ?int $tier = null,
    int $level = 1,
    int $xp = 0,
    bool $locked = false,
    ?string $displayName = null,
  ): array {
    return $this->ownedUnitGrantService->grantBySlug(
      $userId,
      $unitTypeSlug,
      $tier,
      $level,
      $xp,
      $locked,
      $displayName,
    );
  }

  /**
   * @return array<int,array{id:string,unit_type_slug:string}>
   */
  public function grantUnitsBySlug(int $userId, string $unitTypeSlug, int $count = 1): array
  {
    $count = max(1, min(25, $count));
    $granted = [];

    for ($i = 0; $i < $count; $i += 1) {
      $unit = $this->grantUnitBySlug($userId, $unitTypeSlug);
      $granted[] = [
        'id' => (string)$unit['id'],
        'unit_type_slug' => $unitTypeSlug,
      ];
    }

    return $granted;
  }

  /**
   * @param array<int,array{affix_definition_id:int,value:float|int}>|null $fixedAffixes
   * @return array{id:int,dice_definition_id:int,sides:int,rarity:string}
   */
  public function grantDiceByDefinitionId(int $userId, int $diceDefinitionId, ?array $fixedAffixes = null): array
  {
    return $this->ownedDiceGrantService->grantByDefinitionId($userId, $diceDefinitionId, $fixedAffixes);
  }

  /**
   * @param array<int,array{affix_definition_id:int,value:float|int}>|null $fixedAffixes
   * @return array{id:int,dice_definition_id:int,sides:int,rarity:string}
   */
  public function grantDiceByRarityAndSides(
    int $userId,
    string $rarity,
    int $sides,
    ?array $fixedAffixes = null,
  ): array {
    return $this->ownedDiceGrantService->grantByRarityAndSides($userId, $rarity, $sides, $fixedAffixes);
  }

  /**
   * @return array<int,array{id:string,sides:int,rarity:string}>
   */
  public function grantDiceBatch(int $userId, int $sides, string $rarity, int $count = 1): array
  {
    $count = max(1, min(25, $count));
    $granted = [];

    for ($i = 0; $i < $count; $i += 1) {
      $dice = $this->grantDiceByRarityAndSides($userId, $rarity, $sides);
      $granted[] = [
        'id' => (string)$dice['id'],
        'sides' => $sides,
        'rarity' => $rarity,
      ];
    }

    return $granted;
  }

  /**
   * @param array<string,mixed> $rewards
   * @return array<int,string>
   */
  public function materializeRewardUnitGrants(int $userId, array $rewards): array
  {
    $unitGrants = $rewards['unit_grants'] ?? null;
    if (!is_array($unitGrants) || count($unitGrants) === 0) {
      return [];
    }

    $created = [];
    foreach ($unitGrants as $grant) {
      if (!is_array($grant)) {
        continue;
      }

      $slug = trim((string)($grant['unit_type_slug'] ?? ''));
      if ($slug === '' || !$this->userUnlockService->isUnlocked($userId, UserUnlockService::NAMESPACE_UNIT_TYPE, $slug)) {
        continue;
      }

      try {
        $grantedUnit = $this->grantUnitBySlug(
          $userId,
          $slug,
          max(1, min(3, (int)($grant['tier'] ?? 1))),
          max(1, (int)($grant['level'] ?? 1))
        );
      } catch (RuntimeException) {
        continue;
      }

      $created[] = (string)$grantedUnit['id'];
    }

    return $created;
  }

  /**
   * @param array<string,mixed> $rewards
   * @return array<int,string>
   */
  public function materializeRewardDiceGrants(int $userId, array $rewards): array
  {
    $diceGrants = $rewards['dice_grants'] ?? null;
    if (!is_array($diceGrants) || count($diceGrants) === 0) {
      return [];
    }

    $created = [];
    foreach ($diceGrants as $grant) {
      if (!is_array($grant)) {
        continue;
      }

      $rarity = trim((string)($grant['rarity'] ?? 'common'));
      $sides = max(2, (int)($grant['sides'] ?? 6));

      try {
        $grantedDice = $this->grantDiceByRarityAndSides($userId, $rarity, $sides);
      } catch (RuntimeException) {
        continue;
      }

      $created[] = (string)$grantedDice['id'];
    }

    return $created;
  }
}
