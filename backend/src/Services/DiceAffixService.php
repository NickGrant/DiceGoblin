<?php
declare(strict_types=1);

namespace DiceGoblins\Services;

use PDO;

final class DiceAffixService
{
  /** @var array<string,int> */
  private const RARITY_RANK = [
    'common' => 0,
    'uncommon' => 1,
    'rare' => 2,
    'epic' => 3,
    'legendary' => 4,
  ];

  public function __construct(
    private readonly PDO $pdo,
  ) {}

  public static function rarityRank(string $rarity): int
  {
    return self::RARITY_RANK[strtolower(trim($rarity))] ?? 0;
  }

  public static function affixSlotsForRarity(string $rarity): int
  {
    return self::rarityRank($rarity);
  }

  public function ensureAffixesForUser(int $userId): void
  {
    $stmt = $this->pdo->prepare('
      SELECT
        di.`id`,
        dd.`rarity`,
        COUNT(dia.`affix_definition_id`) AS `affix_count`
      FROM `dice_instances` di
      JOIN `dice_definitions` dd ON dd.`id` = di.`dice_definition_id`
      LEFT JOIN `dice_instance_affixes` dia ON dia.`dice_instance_id` = di.`id`
      WHERE di.`user_id` = ?
      GROUP BY di.`id`, dd.`rarity`
      ORDER BY di.`id` ASC
    ');
    $stmt->execute([$userId]);

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
      $diceInstanceId = (int)($row['id'] ?? 0);
      $rarity = (string)($row['rarity'] ?? 'common');
      $expected = self::affixSlotsForRarity($rarity);
      $current = max(0, (int)($row['affix_count'] ?? 0));
      if ($diceInstanceId <= 0 || $current >= $expected) {
        continue;
      }
      $this->assignMissingAffixes($diceInstanceId, $rarity);
    }
  }

  public function assignAffixesToDiceInstance(int $diceInstanceId): void
  {
    $stmt = $this->pdo->prepare('
      SELECT dd.`rarity`
      FROM `dice_instances` di
      JOIN `dice_definitions` dd ON dd.`id` = di.`dice_definition_id`
      WHERE di.`id` = ?
      LIMIT 1
    ');
    $stmt->execute([$diceInstanceId]);
    $rarity = $stmt->fetchColumn();
    if (!is_string($rarity) || $rarity === '') {
      return;
    }

    $this->assignMissingAffixes($diceInstanceId, $rarity);
  }

  private function assignMissingAffixes(int $diceInstanceId, string $diceRarity): void
  {
    $expectedSlots = self::affixSlotsForRarity($diceRarity);
    if ($expectedSlots <= 0) {
      return;
    }

    $existing = $this->getExistingAffixIds($diceInstanceId);
    $remainingSlots = $expectedSlots - count($existing);
    if ($remainingSlots <= 0) {
      return;
    }

    $eligible = $this->getEligibleAffixDefinitions($diceRarity, $existing);
    if (count($eligible) === 0) {
      return;
    }

    $picked = $this->pickDefinitionsDeterministically($diceInstanceId, $eligible, $remainingSlots);
    if (count($picked) === 0) {
      return;
    }

    $insert = $this->pdo->prepare('
      INSERT INTO `dice_instance_affixes` (`dice_instance_id`, `affix_definition_id`, `value`)
      VALUES (?, ?, ?)
    ');

    foreach ($picked as $definition) {
      $insert->execute([
        $diceInstanceId,
        (int)$definition['id'],
        $this->resolveAffixValue($diceInstanceId, $definition),
      ]);
    }
  }

  /**
   * @return array<int,int>
   */
  private function getExistingAffixIds(int $diceInstanceId): array
  {
    $stmt = $this->pdo->prepare('SELECT `affix_definition_id` FROM `dice_instance_affixes` WHERE `dice_instance_id` = ?');
    $stmt->execute([$diceInstanceId]);

    return array_map(
      static fn(array $row): int => (int)$row['affix_definition_id'],
      $stmt->fetchAll(PDO::FETCH_ASSOC)
    );
  }

  /**
   * @param array<int,int> $excludeIds
   * @return array<int,array<string,mixed>>
   */
  private function getEligibleAffixDefinitions(string $diceRarity, array $excludeIds): array
  {
    $stmt = $this->pdo->query('
      SELECT
        `id`,
        `slug`,
        `rarity`,
        `min_value`,
        `max_value`
      FROM `affix_definitions`
      ORDER BY `id` ASC
    ');

    $diceRank = self::rarityRank($diceRarity);
    $excludeLookup = array_fill_keys($excludeIds, true);
    $eligible = [];

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
      $affixId = (int)($row['id'] ?? 0);
      if ($affixId <= 0 || isset($excludeLookup[$affixId])) {
        continue;
      }
      $affixRank = self::rarityRank((string)($row['rarity'] ?? 'common'));
      if ($affixRank > $diceRank) {
        continue;
      }
      $eligible[] = $row;
    }

    return $eligible;
  }

  /**
   * @param array<int,array<string,mixed>> $definitions
   * @return array<int,array<string,mixed>>
   */
  private function pickDefinitionsDeterministically(int $diceInstanceId, array $definitions, int $count): array
  {
    usort($definitions, static function (array $a, array $b) use ($diceInstanceId): int {
      $scoreA = hash('sha256', $diceInstanceId . '|' . (string)$a['id']);
      $scoreB = hash('sha256', $diceInstanceId . '|' . (string)$b['id']);
      return $scoreA <=> $scoreB;
    });

    return array_slice($definitions, 0, max(0, $count));
  }

  /**
   * @param array<string,mixed> $definition
   */
  private function resolveAffixValue(int $diceInstanceId, array $definition): float
  {
    $min = (float)($definition['min_value'] ?? 0);
    $max = (float)($definition['max_value'] ?? $min);
    if (abs($max - $min) < 0.000001) {
      return $min;
    }

    $hash = hash('sha256', $diceInstanceId . '|value|' . (string)($definition['id'] ?? '0'));
    $raw = hexdec(substr($hash, 0, 8));
    $ratio = ($raw % 10001) / 10000;
    return $min + (($max - $min) * $ratio);
  }
}
