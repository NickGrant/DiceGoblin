<?php
declare(strict_types=1);

namespace DiceGoblins\Tests\Integration;

use DiceGoblins\Services\UserAssetGrantService;
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

  private function service(): UserAssetGrantService
  {
    return new UserAssetGrantService($this->pdo);
  }
}
