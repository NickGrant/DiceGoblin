<?php
declare(strict_types=1);

namespace DiceGoblins\Tests\Integration;

use DiceGoblins\Application\Queries\CodexQuery;
use DiceGoblins\Application\Rewards\RewardApplicationIntegrityException;
use DiceGoblins\Application\Rewards\RewardApplicationService;
use DiceGoblins\Content\ContentRegistry;
use DiceGoblins\Controllers\CodexController;
use DiceGoblins\Domain\Rewards\RewardContext;
use DiceGoblins\Domain\Rewards\RewardFinalizer;
use DiceGoblins\Repositories\PlayerStateRepository;
use DiceGoblins\Repositories\ResolvedEventRepository;
use DiceGoblins\Repositories\UserCodexRepository;
use DiceGoblins\Repositories\UserRepository;
use DiceGoblins\Repositories\UserUnlockRepository;
use DiceGoblins\Repositories\WarbandUnitRepository;
use DiceGoblins\Tests\Support\IntegrationTestCase;
use DiceGoblins\Tests\Support\ScriptedRewardRollSource;

final class CodexRewardIntegrationTest extends IntegrationTestCase
{

  public function testCleanSchemaHasUniqueUserScopedOwnershipAndCascade(): void
  {
    [$first, $second] = $this->users();
    $repository = new UserCodexRepository($this->pdo);
    $this->assertTrue($repository->insertIfAbsent($first, 'codex.farm.mudking'));
    $this->assertFalse($repository->insertIfAbsent($first, 'codex.farm.mudking'));
    $this->assertTrue($repository->owns($first, 'codex.farm.mudking'));
    $this->assertFalse($repository->owns($second, 'codex.farm.mudking'));
    $this->assertSame([], $repository->listIdsForUser($second));
    $this->assertSame('1', (string)$this->scalar('SELECT COUNT(*) FROM `user_codex_ownership` WHERE `user_id` = ?', [$first]));
    $this->pdo?->prepare('DELETE FROM `users` WHERE `id` = ?')->execute([$first]);
    $this->assertSame('0', (string)$this->scalar('SELECT COUNT(*) FROM `user_codex_ownership` WHERE `user_id` = ?', [$first]));
  }

  public function testGrantReplayDuplicateRollReloadAndOtherUserProjection(): void
  {
    [$first, $second] = $this->users();
    $content = $this->fixtureContent();
    $query = new CodexQuery(new UserCodexRepository($this->pdo), $content);
    $this->assertSame(null, $query->execute($first)['entries'][0]['body']);
    $rolls = new ScriptedRewardRollSource([1]);
    $this->pdo?->beginTransaction();
    $firstResult = $this->service($content, $rolls)->apply($first, 'event.codex_test', 'test_command', 'codex:1', new RewardContext(0, 0, [], []));
    (new PlayerStateRepository($this->pdo))->incrementRevision($first);
    $this->pdo?->commit();
    $this->assertSame('granted', $firstResult->entries()[0]['outcome']);
    $this->assertSame(['codex_id' => 'codex.farm.mudking'], $firstResult->entries()[0]['grant']);

    $emptyRolls = new ScriptedRewardRollSource([]);
    $this->pdo?->beginTransaction();
    $replay = $this->service($content, $emptyRolls)->apply($first, 'event.codex_test', 'test_command', 'codex:1', null);
    $this->pdo?->commit();
    $this->assertSame($firstResult->toArray(), $replay->toArray());
    $this->assertSame(0, $emptyRolls->consumed());
    $this->assertSame('2', (string)$this->scalar('SELECT `player_revision` FROM `user_state` WHERE `user_id` = ?', [$first]));

    $this->pdo?->beginTransaction();
    $duplicate = $this->service($content, new ScriptedRewardRollSource([1]))->apply($first, 'event.codex_test', 'test_command', 'codex:2', new RewardContext(0, 0, [], []));
    $this->pdo?->commit();
    $this->assertSame('already_owned', $duplicate->entries()[0]['outcome']);
    $this->assertSame('1', (string)$this->scalar('SELECT COUNT(*) FROM `user_codex_ownership` WHERE `user_id` = ?', [$first]));
    $this->assertSame('2', (string)$this->scalar('SELECT COUNT(*) FROM `resolved_events` WHERE `user_id` = ?', [$first]));
    $this->assertNotNull((new CodexQuery(new UserCodexRepository($this->pdo), $content))->execute($first)['entries'][0]['body']);
    $this->assertSame(null, $query->execute($second)['entries'][0]['body']);
    $this->assertFalse($query->execute($second)['entries'][0]['owned']);
  }

  public function testCorruptPersistedReferenceAbortsBeforeRollOrFinalizedRow(): void
  {
    [$first] = $this->users();
    $this->pdo?->prepare('INSERT INTO `user_codex_ownership` (`user_id`, `codex_id`) VALUES (?, ?)')->execute([$first, 'codex.missing']);
    $content = $this->fixtureContent();
    $rolls = new ScriptedRewardRollSource([1]);
    $this->pdo?->beginTransaction();
    try {
      $this->service($content, $rolls)->apply($first, 'event.codex_test', 'test_command', 'corrupt:1', new RewardContext(0, 0, [], []));
      $this->fail('Expected corrupt Codex reference to fail.');
    } catch (RewardApplicationIntegrityException) {
      $this->pdo?->rollBack();
    }
    $this->assertSame(0, $rolls->consumed());
    $this->assertSame('0', (string)$this->scalar('SELECT COUNT(*) FROM `resolved_events` WHERE `user_id` = ?', [$first]));
    $this->expectException(\RuntimeException::class);
    (new CodexQuery(new UserCodexRepository($this->pdo), $content))->execute($first);
  }

  public function testCodexEndpointRequiresSessionRedactsUnownedBodyAndRejectsCorruptOwnership(): void
  {
    [$first, $second] = $this->users();
    $content = ContentRegistry::load(dirname(__DIR__, 2) . '/tests/Fixtures/content');
    $controller = new CodexController($content);
    $unauthorized = $this->invoke(fn() => $controller->catalog());
    $this->assertSame(401, $unauthorized['status']);
    (new UserCodexRepository($this->pdo))->insertIfAbsent($first, 'codex.farm.mudking');
    $_SESSION['user_id'] = $first;
    $owner = $this->invoke(fn() => $controller->catalog());
    $this->assertSame(200, $owner['status']);
    $this->assertTrue($owner['body']['data']['entries'][0]['owned']);
    $_SESSION['user_id'] = $second;
    $other = $this->invoke(fn() => $controller->catalog());
    $this->assertSame(200, $other['status']);
    $this->assertFalse($other['body']['data']['entries'][0]['owned']);
    $this->assertNull($other['body']['data']['entries'][0]['body']);
    $this->assertStringNotContainsString('rallies the boars', json_encode($other['body'], JSON_THROW_ON_ERROR));
    $this->pdo?->prepare('INSERT INTO `user_codex_ownership` (`user_id`, `codex_id`) VALUES (?, ?)')->execute([$second, 'codex.missing']);
    $corrupt = $this->invoke(fn() => $controller->catalog());
    $this->assertSame(500, $corrupt['status']);
    $this->assertSame('codex_data_integrity_error', $corrupt['body']['error']['code']);
    $this->assertStringNotContainsString('codex.missing', json_encode($corrupt['body'], JSON_THROW_ON_ERROR));
  }

  /** @return array{int,int} */
  private function users(): array
  {
    $users = new UserRepository($this->pdo);
    $first = $users->createUser('Codex First', null); $second = $users->createUser('Codex Second', null);
    $this->trackUserId($first); $this->trackUserId($second);
    $this->pdo?->prepare('INSERT INTO `user_state` (`user_id`, `energy_current`) VALUES (?, 50)')->execute([$first]);
    $this->pdo?->prepare('INSERT INTO `user_state` (`user_id`, `energy_current`) VALUES (?, 50)')->execute([$second]);
    return [$first, $second];
  }

  private function service(ContentRegistry $content, ScriptedRewardRollSource $rolls): RewardApplicationService
  {
    return new RewardApplicationService($this->pdo, $content, new RewardFinalizer($rolls),
      new ResolvedEventRepository($this->pdo), new PlayerStateRepository($this->pdo),
      new WarbandUnitRepository($this->pdo), new UserUnlockRepository($this->pdo), new UserCodexRepository($this->pdo));
  }

  private function fixtureContent(): ContentRegistry
  {
    $definitions = \DiceGoblins\Tests\Support\CanonicalContentFixture::definitions();
    $definitions[] = ['id' => 'reward_definition.codex_test', 'type' => 'reward_definition', 'entries' => [[
      'key' => 'discovery', 'probability_basis_points' => 10000, 'reward_type' => 'codex',
      'config' => ['codex_id' => 'codex.farm.mudking'],
    ]]];
    $definitions[] = ['id' => 'event.codex_test', 'type' => 'event', 'reward_definition_id' => 'reward_definition.codex_test'];
    $root = sys_get_temp_dir() . '/dice-goblins-codex-' . bin2hex(random_bytes(6));
    mkdir($root, 0777, true); $path = $root . '/fixture.json';
    file_put_contents($path, json_encode(['definitions' => $definitions], JSON_THROW_ON_ERROR));
    try { return ContentRegistry::load($root); }
    finally { unlink($path); rmdir($root); }
  }
}
