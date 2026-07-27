<?php
declare(strict_types=1);

namespace DiceGoblins\Tests\Integration;

use DiceGoblins\Services\UserAssetGrantService;
use DiceGoblins\Services\LineageUnlockService;
use DiceGoblins\Services\SpliceVariantService;
use DiceGoblins\Services\UserUnlockService;
use DiceGoblins\Tests\Support\BattleFlowIntegrationCase;

final class UserAssetGrantServiceIntegrationTest extends BattleFlowIntegrationCase
{
  protected function integrationSkipMessage(): string
  {
    return 'Set TEST_DB_DSN to run user asset grant integration tests.';
  }

  public function testGrantUnitsBySlugPreservesTierFromSlug(): void
  {
    $userId = $this->insertUser();

    $granted = $this->service()->grantUnitsBySlug($userId, 'frontline_bruiser_t2', 2);

    $this->assertCount(2, $granted);
    foreach ($granted as $row) {
      $unitId = (int)($row['id'] ?? 0);
      $this->assertGreaterThan(0, $unitId);
      $this->assertSame(2, (int)$this->scalar('SELECT `tier` FROM `unit_instances` WHERE `id` = ?', [$unitId]));
    }
  }

  public function testGrantUnitCanPersistRequestedSpliceVariant(): void
  {
    $userId = $this->insertUser();

    $granted = $this->service()->grantUnitBySlug($userId, 'frontline_bruiser_t1', null, 1, 0, false, null, 'rat_splice');

    $this->assertSame('rat_splice', (string)($granted['splice_variant_slug'] ?? ''));
    $this->assertSame(
      'rat_splice',
      (string)$this->scalar('SELECT `splice_variant_slug` FROM `unit_instances` WHERE `id` = ?', [(int)$granted['id']])
    );
  }

  public function testRandomUnitGrantFallsBackToBasicWhenOnlyExplicitKinIsLocked(): void
  {
    $userId = $this->insertUser();
    $snapshot = $this->snapshotSpliceVariants();

    try {
      $this->prepareBasicAndPigOnlyRandomPool();
      $variantService = new SpliceVariantService($this->pdo);

      $this->assertSame(1, $variantService->totalEnabledWeightForUser($userId));
      $this->assertSame(SpliceVariantService::BASIC_GOBLIN, $variantService->rollVariantSlugForUser($userId, 0));
      $granted = $this->service()->grantUnitBySlug($userId, 'frontline_bruiser_t1');

      $this->assertSame(SpliceVariantService::BASIC_GOBLIN, (string)($granted['splice_variant_slug'] ?? ''));
      $this->assertSame(
        SpliceVariantService::BASIC_GOBLIN,
        (string)$this->scalar('SELECT `splice_variant_slug` FROM `unit_instances` WHERE `id` = ?', [(int)$granted['id']])
      );
    } finally {
      $this->restoreSpliceVariants($snapshot);
    }
  }

  public function testExplicitRewardPayloadCanGrantLockedKin(): void
  {
    $userId = $this->insertUser();
    $unlockService = new UserUnlockService($this->pdo);
    $unlockService->grant($userId, UserUnlockService::NAMESPACE_UNIT_TYPE, 'frontline_bruiser_t1');
    $snapshot = $this->snapshotSpliceVariants();

    try {
      $this->prepareBasicAndPigOnlyRandomPool();
      $this->assertFalse((new LineageUnlockService($this->pdo))->isUnlocked($userId, LineageUnlockService::PIG_KIN));

      $created = $this->service()->materializeRewardUnitGrants($userId, [
        'unit_grants' => [
          [
            'unit_type_slug' => 'frontline_bruiser_t1',
            'splice_variant_slug' => LineageUnlockService::PIG_KIN,
            'tier' => 1,
            'level' => 1,
          ],
        ],
      ]);

      $this->assertCount(1, $created);
      $this->assertSame(
        LineageUnlockService::PIG_KIN,
        (string)$this->scalar('SELECT `splice_variant_slug` FROM `unit_instances` WHERE `id` = ?', [(int)$created[0]])
      );
    } finally {
      $this->restoreSpliceVariants($snapshot);
    }
  }

  public function testUserAwareRandomPoolIncludesExplicitKinAfterUnlock(): void
  {
    $userId = $this->insertUser();
    $snapshot = $this->snapshotSpliceVariants();

    try {
      $this->prepareBasicAndPigOnlyRandomPool();
      (new LineageUnlockService($this->pdo))->grant($userId, LineageUnlockService::PIG_KIN);

      $service = new SpliceVariantService($this->pdo);

      $this->assertSame(101, $service->totalEnabledWeightForUser($userId));
      $this->assertSame(LineageUnlockService::PIG_KIN, $service->rollVariantSlugForUser($userId, 1));
    } finally {
      $this->restoreSpliceVariants($snapshot);
    }
  }

  public function testGrantDiceBatchCreatesRequestedCount(): void
  {
    $userId = $this->insertUser();

    $granted = $this->service()->grantDiceBatch($userId, 6, 'rare', 2);

    $this->assertCount(2, $granted);
    $diceCount = (int)$this->scalar('SELECT COUNT(*) FROM `dice_instances` WHERE `user_id` = ?', [$userId]);
    $this->assertSame(2, $diceCount);
  }

  public function testMaterializeRewardUnitGrantsSkipsLockedUnitTypesAndCreatesUnlockedOnes(): void
  {
    $userId = $this->insertUser();
    $unlockService = new UserUnlockService($this->pdo);
    $unlockService->grant($userId, UserUnlockService::NAMESPACE_UNIT_TYPE, 'frontline_bruiser_t1');

    $created = $this->service()->materializeRewardUnitGrants($userId, [
      'unit_grants' => [
        ['unit_type_slug' => 'frontline_bruiser_t1', 'tier' => 1, 'level' => 1],
        ['unit_type_slug' => 'frontline_bruiser_t1', 'splice_variant_slug' => 'toad_splice', 'tier' => 1, 'level' => 1],
        ['unit_type_slug' => 'backline_marksman_t1', 'tier' => 1, 'level' => 1],
      ],
    ]);

    $this->assertCount(2, $created);
    $this->assertSame(
      'frontline_bruiser_t1',
      (string)$this->scalar(
        'SELECT ut.`slug`
         FROM `unit_instances` ui
         JOIN `unit_types` ut ON ut.`id` = ui.`unit_type_id`
         WHERE ui.`id` = ?',
        [(int)$created[0]]
      )
    );
    $this->assertSame(
      'toad_splice',
      (string)$this->scalar('SELECT `splice_variant_slug` FROM `unit_instances` WHERE `id` = ?', [(int)$created[1]])
    );
  }

  public function testMaterializeRewardDiceGrantsCreatesDiceIds(): void
  {
    $userId = $this->insertUser();

    $created = $this->service()->materializeRewardDiceGrants($userId, [
      'dice_grants' => [
        ['rarity' => 'common', 'sides' => 4],
        ['rarity' => 'rare', 'sides' => 6],
      ],
    ]);

    $this->assertCount(2, $created);
    $diceCount = (int)$this->scalar('SELECT COUNT(*) FROM `dice_instances` WHERE `user_id` = ?', [$userId]);
    $this->assertSame(2, $diceCount);
  }

  public function testMaterializeRewardItemGrantsAccumulatesGenericItems(): void
  {
    $userId = $this->insertUser();

    $granted = $this->service()->materializeRewardItemGrants($userId, [
      'item_grants' => [
        ['item_slug' => 'pig_ear', 'quantity' => 2],
        ['item_slug' => 'pig_ear', 'quantity' => 3],
        ['item_slug' => 'missing_item', 'quantity' => 99],
      ],
    ]);

    $this->assertCount(2, $granted);
    $this->assertSame('pig_ear', $granted[0]['item_slug']);
    $this->assertSame(2, $granted[0]['quantity']);
    $this->assertSame(5, $granted[1]['quantity']);
    $this->assertSame(
      5,
      (int)$this->scalar(
        'SELECT ui.`quantity`
         FROM `user_items` ui
         JOIN `items` i ON i.`id` = ui.`item_id`
         WHERE ui.`user_id` = ? AND i.`slug` = ?',
        [$userId, 'pig_ear']
      )
    );
  }

  private function service(): UserAssetGrantService
  {
    return new UserAssetGrantService($this->pdo);
  }

  /**
   * @return list<array<string,mixed>>
   */
  private function snapshotSpliceVariants(): array
  {
    $stmt = $this->pdo?->query('SELECT * FROM `splice_variants` ORDER BY `id` ASC');
    return is_object($stmt) ? $stmt->fetchAll(\PDO::FETCH_ASSOC) : [];
  }

  private function prepareBasicAndPigOnlyRandomPool(): void
  {
    $this->pdo?->exec('UPDATE `splice_variants` SET `grant_weight` = 0');
    $this->pdo?->prepare('
      UPDATE `splice_variants`
      SET `is_enabled` = 1, `grant_weight` = 1
      WHERE `slug` = ?
    ')->execute([SpliceVariantService::BASIC_GOBLIN]);

    $this->pdo?->prepare("
      INSERT INTO `splice_variants` (
        `slug`, `name`, `description`, `passive_summary`, `stat_modifiers_json`, `grant_weight`, `is_enabled`
      ) VALUES (?, 'Pig Kin', 'QA Pig Kin.', 'QA Pig Kin modifier.', JSON_OBJECT(), 100, 1)
      ON DUPLICATE KEY UPDATE
        `name` = VALUES(`name`),
        `description` = VALUES(`description`),
        `passive_summary` = VALUES(`passive_summary`),
        `stat_modifiers_json` = VALUES(`stat_modifiers_json`),
        `grant_weight` = VALUES(`grant_weight`),
        `is_enabled` = VALUES(`is_enabled`)
    ")->execute([LineageUnlockService::PIG_KIN]);
  }

  /**
   * @param list<array<string,mixed>> $snapshot
   */
  private function restoreSpliceVariants(array $snapshot): void
  {
    $this->pdo?->exec('DELETE FROM `splice_variants`');

    $stmt = $this->pdo?->prepare('
      INSERT INTO `splice_variants` (
        `id`, `slug`, `name`, `description`, `passive_summary`, `stat_modifiers_json`,
        `grant_weight`, `is_enabled`, `created_at`, `updated_at`
      ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ');

    foreach ($snapshot as $row) {
      $stmt?->execute([
        (int)$row['id'],
        (string)$row['slug'],
        (string)$row['name'],
        (string)$row['description'],
        (string)$row['passive_summary'],
        (string)$row['stat_modifiers_json'],
        (int)$row['grant_weight'],
        (int)$row['is_enabled'],
        (string)$row['created_at'],
        (string)$row['updated_at'],
      ]);
    }
  }
}
