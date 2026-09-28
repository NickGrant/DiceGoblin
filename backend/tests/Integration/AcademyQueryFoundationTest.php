<?php
declare(strict_types=1);

namespace DiceGoblins\Tests\Integration;

use DiceGoblins\Application\Queries\AcademyQuery;
use DiceGoblins\Content\ContentRegistry;
use DiceGoblins\Controllers\AcademyReadController;
use DiceGoblins\Repositories\PlayerStateRepository;
use DiceGoblins\Repositories\UserUnlockRepository;
use DiceGoblins\Tests\Support\IntegrationTestCase;

final class AcademyQueryFoundationTest extends IntegrationTestCase
{
  protected function supportsVnextBaseline(): bool { return true; }

  public function testReadDerivesOwnershipAndPrerequisitesWithoutMutation(): void
  {
    $userId = $this->user();
    $unlocks = new UserUnlockRepository($this->pdo);
    $query = new AcademyQuery(new PlayerStateRepository($this->pdo), $unlocks,
      ContentRegistry::load(dirname(__DIR__, 2) . '/content'));
    $initial = $query->execute($userId);
    $this->assertSame(3, $initial['raw_chaos']);
    $this->assertSame(7, $initial['player_revision']);
    $this->assertSame(9, count($initial['upgrades']));
    $ids = array_column($initial['upgrades'], 'upgrade_id');
    $sorted = $ids; sort($sorted, SORT_STRING);
    $this->assertSame($sorted, $ids);
    $initialById = array_column($initial['upgrades'], null, 'upgrade_id');
    $this->assertTrue($initialById['academy_upgrade.die_size_d10']['available']);
    $this->assertFalse($initialById['academy_upgrade.die_size_d12']['available']);
    $this->assertSame(['currency_id' => 'raw_chaos', 'amount' => 5], $initialById['academy_upgrade.die_size_d10']['price']);
    $unlocks->insertIfAbsent($userId, 'unlock.capability.die_size_d10');
    $after = $query->execute($userId);
    $afterById = array_column($after['upgrades'], null, 'upgrade_id');
    $this->assertTrue($afterById['academy_upgrade.die_size_d10']['owned']);
    $this->assertFalse($afterById['academy_upgrade.die_size_d10']['available']);
    $this->assertTrue($afterById['academy_upgrade.die_size_d12']['available']);
    $this->assertSame(3, $after['raw_chaos']);
    $this->assertSame(7, $after['player_revision']);
    $this->assertSame('1', (string)$this->scalar('SELECT COUNT(*) FROM `user_unlocks` WHERE `user_id` = ?', [$userId]));
  }

  public function testEndpointRequiresAuthentication(): void
  {
    $response = $this->invoke(fn() => (new AcademyReadController())->catalog());
    $this->assertSame(401, $response['status']);
  }

  public function testAuthenticatedEndpointReadsWithoutCsrfOrProvisioning(): void
  {
    $userId = $this->user();
    $_SESSION['user_id'] = $userId;
    $before = $this->pdo?->query("SELECT `raw_chaos`, `player_revision` FROM `user_state` WHERE `user_id` = {$userId}")->fetch();
    $response = $this->invoke(fn() => (new AcademyReadController())->catalog());
    $this->assertSame(200, $response['status'], json_encode($response['body']));
    $this->assertSame(3, $response['body']['data']['raw_chaos']);
    $this->assertSame(7, $response['body']['data']['player_revision']);
    $this->assertSame($before, $this->pdo?->query("SELECT `raw_chaos`, `player_revision` FROM `user_state` WHERE `user_id` = {$userId}")->fetch());
    $this->assertSame('0', (string)$this->scalar('SELECT COUNT(*) FROM `user_unlocks` WHERE `user_id` = ?', [$userId]));
  }

  private function user(): int
  {
    $this->pdo?->exec("INSERT INTO `users` (`display_name`) VALUES ('Academy Query')");
    $id = (int)$this->pdo?->lastInsertId(); $this->trackUserId($id);
    $this->pdo?->prepare('INSERT INTO `user_state` (`user_id`, `raw_chaos`, `energy_current`, `player_revision`) VALUES (?, 3, 50, 7)')->execute([$id]);
    return $id;
  }
}
