<?php
declare(strict_types=1);

namespace DiceGoblins\Repositories;

use PDO;

final class WarbandDiceRepository
{
  public function __construct(private readonly PDO $pdo) {}

  /** @return array<int,array<string,mixed>> */
  public function listActiveForUser(int $userId): array
  {
    $stmt = $this->pdo->prepare('
      SELECT `id`, `size`, `profile_id`, `lifecycle_status`
      FROM `dice_instances`
      WHERE `user_id` = ? AND `lifecycle_status` = \'active\'
      ORDER BY `id` ASC
    ');
    $stmt->execute([$userId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
  }

  /**
   * Returns both inbound and outbound relationships relevant to an owner so
   * the query layer can reject cross-owner corruption without leaking IDs.
   *
   * @return array<int,array<string,mixed>>
   */
  public function listRelevantBindingsForUser(int $userId): array
  {
    $stmt = $this->pdo->prepare('
      SELECT
        uad.`unit_id`, uad.`ability_id`, uad.`slot_index`, uad.`dice_instance_id`,
        ui.`user_id` AS `unit_user_id`, ui.`lifecycle_status` AS `unit_lifecycle_status`,
        di.`user_id` AS `die_user_id`, di.`lifecycle_status` AS `die_lifecycle_status`,
        EXISTS(
          SELECT 1 FROM `unit_ability_loadout` ual
          WHERE ual.`unit_id` = uad.`unit_id` AND ual.`ability_id` = uad.`ability_id`
        ) AS `is_loaded`
      FROM `unit_ability_dice` uad
      JOIN `unit_instances` ui ON ui.`id` = uad.`unit_id`
      JOIN `dice_instances` di ON di.`id` = uad.`dice_instance_id`
      WHERE ui.`user_id` = ? OR di.`user_id` = ?
      ORDER BY uad.`dice_instance_id` ASC, uad.`unit_id` ASC, uad.`ability_id` ASC, uad.`slot_index` ASC
    ');
    $stmt->execute([$userId, $userId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
  }

  /** @param list<int> $diceIds @return array<int,array<string,mixed>> */
  public function listActiveByIdsForUser(int $userId, array $diceIds, bool $forUpdate = false): array
  {
    $diceIds = array_values(array_unique($diceIds));
    if ($diceIds === []) return [];
    sort($diceIds, SORT_NUMERIC);
    $placeholders = implode(',', array_fill(0, count($diceIds), '?'));
    $stmt = $this->pdo->prepare("
      SELECT `id`, `user_id`, `size`, `profile_id`, `lifecycle_status`
      FROM `dice_instances`
      WHERE `user_id` = ? AND `lifecycle_status` = 'active' AND `id` IN ($placeholders)
      ORDER BY `id` ASC" . ($forUpdate ? ' FOR UPDATE' : '')
    );
    $stmt->execute(array_merge([$userId], $diceIds));
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
  }

  /** @param list<int> $diceIds @return array<int,array<string,mixed>> */
  public function listBindingsForDiceIds(array $diceIds, bool $forUpdate = false): array
  {
    $diceIds = array_values(array_unique($diceIds));
    if ($diceIds === []) return [];
    sort($diceIds, SORT_NUMERIC);
    $placeholders = implode(',', array_fill(0, count($diceIds), '?'));
    $stmt = $this->pdo->prepare("
      SELECT `unit_id`, `ability_id`, `slot_index`, `dice_instance_id`
      FROM `unit_ability_dice`
      WHERE `dice_instance_id` IN ($placeholders)
      ORDER BY `dice_instance_id` ASC, `unit_id` ASC, `ability_id` ASC, `slot_index` ASC" . ($forUpdate ? ' FOR UPDATE' : '')
    );
    $stmt->execute($diceIds);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
  }
}
