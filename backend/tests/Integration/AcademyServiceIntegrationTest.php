<?php
declare(strict_types=1);

namespace DiceGoblins\Tests\Integration;

use DiceGoblins\Repositories\PlayerStateRepository;
use DiceGoblins\Services\AcademyService;
use DiceGoblins\Tests\Support\IntegrationTestCase;

final class AcademyServiceIntegrationTest extends IntegrationTestCase
{
  protected function integrationSkipMessage(): string
  {
    return 'Set TEST_DB_DSN to run academy service integration tests.';
  }

  public function testUnlockUnitTypeSpendsCurrencyAndGrantsUnlock(): void
  {
    $userId = $this->insertUser('qa_academy_service_buy', 'QA Academy Service Buy');
    $this->grantUnlock($userId, 'feature', 'academy');
    $this->setSoftCurrency($userId, 300);

    $result = $this->service()->unlockUnitType($userId, 'support_banner_t1');

    $this->assertSame('support_banner_t1', $result['unit_type_slug']);
    $this->assertSame(250, (int)$result['cost']);
    $this->assertSame(50, (int)$result['currency_soft']);
    $this->assertSame(
      '1',
      (string)$this->scalar(
        "SELECT COUNT(*) FROM `user_unlocks` WHERE `user_id` = ? AND `unlock_namespace` = 'unit_type' AND `unlock_key` = 'support_banner_t1'",
        [$userId]
      )
    );
  }

  public function testTierTwoResearchRequiresCompletedRun(): void
  {
    $userId = $this->insertUser('qa_academy_service_t2_locked', 'QA Academy Service T2 Locked');
    $this->grantUnlock($userId, 'feature', 'academy');
    $this->setSoftCurrency($userId, 600);

    $catalog = $this->service()->buildCatalog($userId);
    $tierTwo = $this->findUnitUnlock($catalog['unit_unlocks'], 'frontline_bruiser_t2');

    $this->assertFalse((bool)($tierTwo['is_available'] ?? true));
    $this->assertSame('Complete any run', (string)($tierTwo['requirements'][0]['label'] ?? ''));

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Complete any run before researching Tier II unit types.');

    $this->service()->unlockUnitType($userId, 'frontline_bruiser_t2');
  }

  public function testTierTwoResearchUnlocksAfterCompletedRun(): void
  {
    $userId = $this->insertUser('qa_academy_service_t2_ready', 'QA Academy Service T2 Ready');
    $regionId = $this->insertRegion();
    $this->grantUnlock($userId, 'feature', 'academy');
    $this->setSoftCurrency($userId, 600);
    $this->insertRun($userId, $regionId, 771122, 'completed');

    $catalog = $this->service()->buildCatalog($userId);
    $tierTwo = $this->findUnitUnlock($catalog['unit_unlocks'], 'frontline_bruiser_t2');

    $this->assertTrue((bool)($tierTwo['is_available'] ?? false));
    $this->assertTrue((bool)($tierTwo['requirements'][0]['is_met'] ?? false));

    $result = $this->service()->unlockUnitType($userId, 'frontline_bruiser_t2');

    $this->assertSame('frontline_bruiser_t2', $result['unit_type_slug']);
    $this->assertSame(500, (int)$result['cost']);
    $this->assertSame(100, (int)$result['currency_soft']);
  }

  public function testCatalogDomainRequiresAcademyFeature(): void
  {
    $userId = $this->insertUser('qa_academy_service_locked', 'QA Academy Service Locked');

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Academy has not been unlocked yet.');

    $this->service()->buildCatalog($userId);
  }

  private function service(): AcademyService
  {
    $pdo = $this->pdo;
    \assert($pdo instanceof \PDO);

    return new AcademyService($pdo, new PlayerStateRepository($pdo));
  }

  /**
   * @param array<int,array<string,mixed>> $unitUnlocks
   * @return array<string,mixed>
   */
  private function findUnitUnlock(array $unitUnlocks, string $unitTypeSlug): array
  {
    foreach ($unitUnlocks as $unitUnlock) {
      if (($unitUnlock['unit_type_slug'] ?? '') === $unitTypeSlug) {
        return $unitUnlock;
      }
    }

    self::fail("Missing Academy unit unlock {$unitTypeSlug}.");
  }
}
