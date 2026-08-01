<?php
declare(strict_types=1);

namespace DiceGoblins\Services;

use PDO;
use RuntimeException;

final class OwnedDiceGrantService
{
  private DiceAffixService $diceAffixService;
  private CodexOwnershipService $codexOwnershipService;

  public function __construct(
    private readonly PDO $pdo,
    ?DiceAffixService $diceAffixService = null,
    ?CodexOwnershipService $codexOwnershipService = null,
  ) {
    $this->diceAffixService = $diceAffixService ?? new DiceAffixService($pdo);
    $this->codexOwnershipService = $codexOwnershipService ?? new CodexOwnershipService($pdo);
  }

  /**
   * @param array<int,array{affix_definition_id:int,value:float|int}>|null $fixedAffixes
   * @return array{id:int,dice_definition_id:int,sides:int,rarity:string}
   */
  public function grantByDefinitionId(
    int $userId,
    int $diceDefinitionId,
    ?array $fixedAffixes = null,
  ): array {
    $definition = $this->loadDefinitionById($diceDefinitionId);
    if ($definition === null) {
      throw new RuntimeException('Unknown dice definition.');
    }

    $insert = $this->pdo->prepare('
      INSERT INTO `dice_instances` (`user_id`, `dice_definition_id`, `display_name`)
      VALUES (?, ?, NULL)
    ');
    $insert->execute([$userId, $diceDefinitionId]);
    $diceInstanceId = (int)$this->pdo->lastInsertId();

    if (is_array($fixedAffixes)) {
      $this->insertFixedAffixes($diceInstanceId, $fixedAffixes);
    } else {
      $this->diceAffixService->assignAffixesToDiceInstance($diceInstanceId);
    }
    $this->grantAffixCodexEntries($userId, $diceInstanceId);

    return [
      'id' => $diceInstanceId,
      'dice_definition_id' => $diceDefinitionId,
      'sides' => (int)$definition['sides'],
      'rarity' => (string)$definition['rarity'],
    ];
  }

  /**
   * @param array<int,array{affix_definition_id:int,value:float|int}>|null $fixedAffixes
   * @return array{id:int,dice_definition_id:int,sides:int,rarity:string}
   */
  public function grantByRarityAndSides(
    int $userId,
    string $rarity,
    int $sides,
    ?array $fixedAffixes = null,
  ): array {
    $definition = $this->loadDefinitionByRarityAndSides($rarity, $sides);
    if ($definition === null) {
      throw new RuntimeException('Unknown dice definition for requested rarity and size.');
    }

    return $this->grantByDefinitionId($userId, (int)$definition['id'], $fixedAffixes);
  }

  /**
   * @return array{id:int,sides:int,rarity:string}|null
   */
  private function loadDefinitionById(int $diceDefinitionId): ?array
  {
    $stmt = $this->pdo->prepare('
      SELECT `id`, `sides`, `rarity`
      FROM `dice_definitions`
      WHERE `id` = ?
      LIMIT 1
    ');
    $stmt->execute([$diceDefinitionId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!is_array($row)) {
      return null;
    }

    return [
      'id' => (int)$row['id'],
      'sides' => (int)$row['sides'],
      'rarity' => (string)$row['rarity'],
    ];
  }

  /**
   * @return array{id:int,sides:int,rarity:string}|null
   */
  private function loadDefinitionByRarityAndSides(string $rarity, int $sides): ?array
  {
    $stmt = $this->pdo->prepare('
      SELECT `id`, `sides`, `rarity`
      FROM `dice_definitions`
      WHERE `rarity` = ? AND `sides` = ?
      ORDER BY `id` ASC
      LIMIT 1
    ');
    $stmt->execute([$rarity, $sides]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!is_array($row)) {
      return null;
    }

    return [
      'id' => (int)$row['id'],
      'sides' => (int)$row['sides'],
      'rarity' => (string)$row['rarity'],
    ];
  }

  /**
   * @param array<int,array{affix_definition_id:int,value:float|int}> $fixedAffixes
   */
  private function insertFixedAffixes(int $diceInstanceId, array $fixedAffixes): void
  {
    if (count($fixedAffixes) === 0) {
      return;
    }

    $insert = $this->pdo->prepare('
      INSERT INTO `dice_instance_affixes` (`dice_instance_id`, `affix_definition_id`, `value`)
      VALUES (?, ?, ?)
    ');

    foreach ($fixedAffixes as $affix) {
      $affixDefinitionId = (int)($affix['affix_definition_id'] ?? 0);
      if ($affixDefinitionId <= 0) {
        continue;
      }

      $insert->execute([
        $diceInstanceId,
        $affixDefinitionId,
        (float)($affix['value'] ?? 0.0),
      ]);
    }
  }

  private function grantAffixCodexEntries(int $userId, int $diceInstanceId): void
  {
    $stmt = $this->pdo->prepare('
      SELECT DISTINCT ad.`slug`
      FROM `dice_instance_affixes` dia
      JOIN `affix_definitions` ad ON ad.`id` = dia.`affix_definition_id`
      WHERE dia.`dice_instance_id` = ?
      ORDER BY ad.`slug` ASC
    ');
    $stmt->execute([$diceInstanceId]);

    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $slug) {
      $slug = trim((string)$slug);
      if ($slug !== '') {
        $this->codexOwnershipService->grant($userId, CodexOwnershipService::TYPE_AFFIX, $slug, 'owned_die');
      }
    }
  }
}
