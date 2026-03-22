<?php
declare(strict_types=1);

namespace DiceGoblins\Tests\Integration;

use DiceGoblins\Services\DiceAffixService;
use DiceGoblins\Tests\Support\IntegrationTestCase;

final class DiceAffixServiceIntegrationTest extends IntegrationTestCase
{
  public function testEnsureAffixesForUserBackfillsDiceByRarity(): void
  {
    $userId = $this->insertUser('qa_affix', 'QA Affix');

    $commonDefinitionId = (int)$this->scalar(
      "SELECT `id` FROM `dice_definitions` WHERE `rarity` = 'common' AND `sides` = 6 ORDER BY `id` ASC LIMIT 1",
      []
    );
    $rareDefinitionId = (int)$this->scalar(
      "SELECT `id` FROM `dice_definitions` WHERE `rarity` = 'rare' AND `sides` = 8 ORDER BY `id` ASC LIMIT 1",
      []
    );

    $this->assertGreaterThan(0, $commonDefinitionId);
    $this->assertGreaterThan(0, $rareDefinitionId);

    $insert = $this->pdo?->prepare('INSERT INTO `dice_instances` (`user_id`, `dice_definition_id`, `display_name`) VALUES (?, ?, ?)');
    $insert?->execute([$userId, $commonDefinitionId, 'Common Test Die']);
    $commonDiceId = (int)$this->pdo?->lastInsertId();
    $insert?->execute([$userId, $rareDefinitionId, 'Rare Test Die']);
    $rareDiceId = (int)$this->pdo?->lastInsertId();

    $service = new DiceAffixService($this->pdo);
    $service->ensureAffixesForUser($userId);

    $commonAffixCount = (int)$this->scalar(
      'SELECT COUNT(*) FROM `dice_instance_affixes` WHERE `dice_instance_id` = ?',
      [$commonDiceId]
    );
    $rareAffixCount = (int)$this->scalar(
      'SELECT COUNT(*) FROM `dice_instance_affixes` WHERE `dice_instance_id` = ?',
      [$rareDiceId]
    );

    $this->assertSame(0, $commonAffixCount);
    $this->assertSame(2, $rareAffixCount);
  }

  public function testAssignedAffixesNeverExceedDiceRarity(): void
  {
    $userId = $this->insertUser('qa_affix_cap', 'QA Affix Cap');
    $definitionId = (int)$this->scalar(
      "SELECT `id` FROM `dice_definitions` WHERE `rarity` = 'uncommon' AND `sides` = 6 ORDER BY `id` ASC LIMIT 1",
      []
    );
    $this->assertGreaterThan(0, $definitionId);

    $insert = $this->pdo?->prepare('INSERT INTO `dice_instances` (`user_id`, `dice_definition_id`, `display_name`) VALUES (?, ?, ?)');
    $insert?->execute([$userId, $definitionId, 'Uncommon Test Die']);
    $diceId = (int)$this->pdo?->lastInsertId();

    $service = new DiceAffixService($this->pdo);
    $service->assignAffixesToDiceInstance($diceId);

    $violations = (int)$this->scalar(
      "SELECT COUNT(*)
       FROM `dice_instance_affixes` dia
       JOIN `dice_instances` di ON di.`id` = dia.`dice_instance_id`
       JOIN `dice_definitions` dd ON dd.`id` = di.`dice_definition_id`
       JOIN `affix_definitions` ad ON ad.`id` = dia.`affix_definition_id`
       WHERE dia.`dice_instance_id` = ?
         AND (
           CASE ad.`rarity`
             WHEN 'common' THEN 0
             WHEN 'uncommon' THEN 1
             WHEN 'rare' THEN 2
             WHEN 'epic' THEN 3
             WHEN 'legendary' THEN 4
             ELSE 99
           END
         ) > (
           CASE dd.`rarity`
             WHEN 'common' THEN 0
             WHEN 'uncommon' THEN 1
             WHEN 'rare' THEN 2
             WHEN 'epic' THEN 3
             WHEN 'legendary' THEN 4
             ELSE -1
           END
         )",
      [$diceId]
    );

    $this->assertSame(0, $violations);
  }
}
