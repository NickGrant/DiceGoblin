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
}
