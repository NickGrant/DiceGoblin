<?php
declare(strict_types=1);

namespace DiceGoblins\Tests\Integration;

use DiceGoblins\Application\Commands\ProvisionWarbandFixtureCommand;
use DiceGoblins\Application\WarbandIntegrityException;
use DiceGoblins\Content\ContentRegistry;
use DiceGoblins\Controllers\AuthController;
use DiceGoblins\Controllers\ControllerServiceFactory;
use DiceGoblins\Controllers\WarbandController;
use DiceGoblins\Controllers\WarbandFixtureController;
use DiceGoblins\Repositories\WarbandFixtureRepository;
use DiceGoblins\Tests\Support\IntegrationTestCase;
use RuntimeException;

final class WarbandReadControllerTest extends IntegrationTestCase
{
  private string|false $originalAppEnv;
  private bool $hadEnvValue;
  private mixed $originalEnvValue;
  private bool $hadServerValue;
  private mixed $originalServerValue;
  private string|false $originalFixtureOptIn;
  private bool $hadFixtureEnvValue;
  private mixed $originalFixtureEnvValue;
  private bool $hadFixtureServerValue;
  private mixed $originalFixtureServerValue;

  protected function supportsVnextBaseline(): bool
  {
    return true;
  }

  protected function setUp(): void
  {
    parent::setUp();
    $this->originalAppEnv = getenv('APP_ENV');
    $this->hadEnvValue = array_key_exists('APP_ENV', $_ENV);
    $this->originalEnvValue = $_ENV['APP_ENV'] ?? null;
    $this->hadServerValue = array_key_exists('APP_ENV', $_SERVER);
    $this->originalServerValue = $_SERVER['APP_ENV'] ?? null;
    $this->originalFixtureOptIn = getenv('ENABLE_WARBAND_FIXTURES');
    $this->hadFixtureEnvValue = array_key_exists('ENABLE_WARBAND_FIXTURES', $_ENV);
    $this->originalFixtureEnvValue = $_ENV['ENABLE_WARBAND_FIXTURES'] ?? null;
    $this->hadFixtureServerValue = array_key_exists('ENABLE_WARBAND_FIXTURES', $_SERVER);
    $this->originalFixtureServerValue = $_SERVER['ENABLE_WARBAND_FIXTURES'] ?? null;
    $this->setAppEnv('test');
    $this->setFixtureOptIn('1');
  }

  protected function tearDown(): void
  {
    if ($this->originalAppEnv === false) putenv('APP_ENV');
    else putenv('APP_ENV=' . $this->originalAppEnv);
    if ($this->hadEnvValue) $_ENV['APP_ENV'] = $this->originalEnvValue;
    else unset($_ENV['APP_ENV']);
    if ($this->hadServerValue) $_SERVER['APP_ENV'] = $this->originalServerValue;
    else unset($_SERVER['APP_ENV']);
    if ($this->originalFixtureOptIn === false) putenv('ENABLE_WARBAND_FIXTURES');
    else putenv('ENABLE_WARBAND_FIXTURES=' . $this->originalFixtureOptIn);
    if ($this->hadFixtureEnvValue) $_ENV['ENABLE_WARBAND_FIXTURES'] = $this->originalFixtureEnvValue;
    else unset($_ENV['ENABLE_WARBAND_FIXTURES']);
    if ($this->hadFixtureServerValue) $_SERVER['ENABLE_WARBAND_FIXTURES'] = $this->originalFixtureServerValue;
    else unset($_SERVER['ENABLE_WARBAND_FIXTURES']);
    parent::tearDown();
  }

  public function testWarbandReadsAndFixtureRequireAuthentication(): void
  {
    $controller = new WarbandController();
    foreach ([
      fn() => $controller->units(),
      fn() => $controller->unitDetail('1'),
      fn() => $controller->dice(),
      fn() => $controller->squads(),
      fn() => (new WarbandFixtureController())->replace(),
    ] as $request) {
      $response = $this->invoke($request);
      $this->assertSame(401, $response['status']);
      $this->assertSame('unauthorized', $response['body']['error']['code'] ?? null);
    }
  }

  public function testFreshAccountHasEmptyCollectionsAndCannotGuessAnotherUsersUnit(): void
  {
    $ownerId = $this->createAccount('empty-warband@example.test', 'Empty Warband');
    $otherId = $this->createAccount('other-warband@example.test', 'Other Warband');
    $otherUnitId = $this->insertUnit($otherId, 'unit_type.bruiser', 'kin.goblin', 'Secret');
    $this->insertDie($otherId, 6, 'dice_profile.cardboard_plain');
    $this->insertSquad($otherId, 'Secret Squad');
    $_SESSION['user_id'] = $ownerId;
    $controller = new WarbandController();

    foreach ([['units', fn() => $controller->units()], ['dice', fn() => $controller->dice()], ['squads', fn() => $controller->squads()]] as [$key, $request]) {
      $response = $this->invoke($request);
      $this->assertSame(200, $response['status'], json_encode($response['body']));
      $this->assertSame([], $response['body']['data'][$key] ?? null);
    }

    $response = $this->invoke(fn() => $controller->unitDetail((string)$otherUnitId));
    $this->assertSame(404, $response['status']);
    $this->assertSame('unit_not_found', $response['body']['error']['code'] ?? null);
    $this->assertStringNotContainsString('Secret', json_encode($response['body']));
  }

  public function testFixturePopulatesOwnedReadModelsWithStableShapesAndNoReadWrites(): void
  {
    $userId = $this->createAccount('fixture-reader@example.test', 'Fixture Reader');
    $observerId = $this->createAccount('fixture-observer@example.test', 'Fixture Observer');
    $_SESSION['user_id'] = $userId;
    $fixture = $this->invokeFixture();
    $fixtureData = $fixture['body']['data']['fixture'] ?? [];
    $this->assertSame('replace_owned_warband', $fixtureData['mode'] ?? null);
    $this->assertSame(2, $fixtureData['player_revision'] ?? null);

    $before = $this->warbandSnapshot($userId);
    $controller = new WarbandController();

    $units = $this->invoke(fn() => $controller->units());
    $this->assertSame(200, $units['status'], json_encode($units['body']));
    $unitRows = $units['body']['data']['units'] ?? [];
    $this->assertCount(6, $unitRows);
    $this->assertSame(
      ['id', 'display_name', 'unit_type_id', 'kin_id', 'level', 'xp', 'lifecycle_status'],
      array_keys($unitRows[0]),
    );
    $this->assertSame(['bruiser', 'guardian', 'marksman', 'bannerbearer', 'saboteur', 'enforcer'], array_map(
      static fn(array $unit): string => substr((string)$unit['unit_type_id'], strlen('unit_type.')),
      $unitRows,
    ));

    $enforcerId = (string)($fixtureData['unit_ids']['enforcer'] ?? '');
    $detail = $this->invoke(fn() => $controller->unitDetail($enforcerId));
    $this->assertSame(200, $detail['status'], json_encode($detail['body']));
    $unit = $detail['body']['data']['unit'] ?? [];
    $this->assertSame(
      ['id', 'display_name', 'unit_type_id', 'kin_id', 'level', 'xp', 'lifecycle_status', 'promotion_history', 'owned_ability_ids', 'ability_loadout', 'dice_bindings'],
      array_keys($unit),
    );
    $this->assertSame('Knuckles', $unit['display_name'] ?? null);
    $this->assertIsString($unit['promotion_history'][0]['promoted_at'] ?? null);
    $this->assertSame([[
      'from_unit_type_id' => 'unit_type.bruiser',
      'to_unit_type_id' => 'unit_type.enforcer',
      'promoted_at' => $unit['promotion_history'][0]['promoted_at'] ?? null,
    ]], $unit['promotion_history'] ?? null);
    $this->assertContains('ability.menacing_follow_through', $unit['owned_ability_ids'] ?? []);
    $this->assertSame([
      ['ability_id' => 'ability.skullcrack', 'equip_order' => 0],
      ['ability_id' => 'ability.heavy_strike', 'equip_order' => 1],
      ['ability_id' => 'ability.basic_attack_melee', 'equip_order' => 2],
    ], $unit['ability_loadout'] ?? null);
    $this->assertSame([
      ['ability_id' => 'ability.skullcrack', 'slot_index' => 0, 'dice_instance_id' => $fixtureData['dice_ids']['enforcer_skullcrack']],
      ['ability_id' => 'ability.heavy_strike', 'slot_index' => 0, 'dice_instance_id' => $fixtureData['dice_ids']['enforcer_heavy']],
      ['ability_id' => 'ability.basic_attack_melee', 'slot_index' => 0, 'dice_instance_id' => $fixtureData['dice_ids']['enforcer_basic']],
    ], $unit['dice_bindings'] ?? null);

    $dice = $this->invoke(fn() => $controller->dice());
    $this->assertSame(200, $dice['status'], json_encode($dice['body']));
    $diceRows = $dice['body']['data']['dice'] ?? [];
    $this->assertCount(14, $diceRows);
    $this->assertSame(['id', 'size', 'profile_id', 'lifecycle_status', 'bindings'], array_keys($diceRows[0]));
    $this->assertNotEmpty(array_filter($diceRows, static fn(array $die): bool => count($die['bindings']) > 0));

    $squads = $this->invoke(fn() => $controller->squads());
    $this->assertSame(200, $squads['status'], json_encode($squads['body']));
    $squadRows = $squads['body']['data']['squads'] ?? [];
    $this->assertCount(2, $squadRows);
    foreach ($squadRows as $squad) {
      $this->assertSame(['id', 'name', 'is_active', 'formation'], array_keys($squad));
      $this->assertCount(9, $squad['formation']);
      $this->assertSame(range(0, 8), array_keys($squad['formation']));
    }
    $this->assertTrue($squadRows[0]['is_active']);
    $this->assertSame([0, 1, 3, 4, 6], array_keys(array_filter($squadRows[0]['formation'], static fn($id): bool => $id !== null)));
    $this->assertSame($before, $this->warbandSnapshot($userId));

    $_SESSION['user_id'] = $observerId;
    $this->assertSame([], $this->invoke(fn() => $controller->units())['body']['data']['units'] ?? null);
    $this->assertSame([], $this->invoke(fn() => $controller->dice())['body']['data']['dice'] ?? null);
    $this->assertSame([], $this->invoke(fn() => $controller->squads())['body']['data']['squads'] ?? null);
  }

  public function testLifecyclePolicyExcludesTerminalUnitsAndDiceAndDetailReturnsNotFound(): void
  {
    $userId = $this->createAccount('lifecycle@example.test', 'Lifecycle');
    $activeUnit = $this->insertUnit($userId, 'unit_type.guardian', 'kin.goblin', 'Active');
    $terminalUnit = $this->insertUnit($userId, 'unit_type.bruiser', 'kin.goblin', 'Retired', 'retired');
    $this->insertDie($userId, 6, 'dice_profile.cardboard_plain');
    $this->insertDie($userId, 8, 'dice_profile.wood_plain', 'salvaged');
    $_SESSION['user_id'] = $userId;
    $controller = new WarbandController();

    $units = $this->invoke(fn() => $controller->units());
    $this->assertSame([(string)$activeUnit], array_column($units['body']['data']['units'] ?? [], 'id'));
    $dice = $this->invoke(fn() => $controller->dice());
    $this->assertCount(1, $dice['body']['data']['dice'] ?? []);
    $detail = $this->invoke(fn() => $controller->unitDetail((string)$terminalUnit));
    $this->assertSame(404, $detail['status']);
    $this->assertSame('unit_not_found', $detail['body']['error']['code'] ?? null);
  }

  public function testMissingOrWrongAuthoredIdsFailAsNarrowIntegrityErrors(): void
  {
    $userId = $this->createAccount('bad-content@example.test', 'Bad Content');
    $_SESSION['user_id'] = $userId;
    $controller = new WarbandController();

    $unitId = $this->insertUnit($userId, 'unit_type.missing', 'kin.goblin', 'Broken');
    $units = $this->invoke(fn() => $controller->units());
    $this->assertIntegrityResponse($units);
    $this->pdo?->prepare('UPDATE `unit_instances` SET `unit_type_id` = ? WHERE `id` = ?')->execute(['kin.goblin', $unitId]);
    $detail = $this->invoke(fn() => $controller->unitDetail((string)$unitId));
    $this->assertIntegrityResponse($detail);
    $this->pdo?->prepare('DELETE FROM `unit_instances` WHERE `id` = ?')->execute([$unitId]);

    $abilityUnitId = $this->insertUnit($userId, 'unit_type.guardian', 'kin.goblin', 'Bad Ability');
    $this->pdo?->prepare('INSERT INTO `unit_abilities` (`unit_id`, `ability_id`) VALUES (?, ?)')->execute([$abilityUnitId, 'ability.missing']);
    $abilityDetail = $this->invoke(fn() => $controller->unitDetail((string)$abilityUnitId));
    $this->assertIntegrityResponse($abilityDetail);
    $this->pdo?->prepare('DELETE FROM `unit_instances` WHERE `id` = ?')->execute([$abilityUnitId]);

    $this->insertDie($userId, 6, 'unit_type.bruiser');
    $dice = $this->invoke(fn() => $controller->dice());
    $this->assertIntegrityResponse($dice);
  }

  public function testCrossOwnerBindingsAndSquadRowsFailWithoutLeakingForeignIdentity(): void
  {
    $ownerId = $this->createAccount('corrupt-owner@example.test', 'Owner');
    $otherId = $this->createAccount('corrupt-other@example.test', 'Other');
    $unitId = $this->insertUnit($ownerId, 'unit_type.bruiser', 'kin.goblin', 'Owner Unit');
    $foreignDieId = $this->insertDie($otherId, 6, 'dice_profile.cardboard_plain');
    $this->pdo?->prepare('INSERT INTO `unit_abilities` (`unit_id`, `ability_id`) VALUES (?, ?)')->execute([$unitId, 'ability.basic_attack_melee']);
    $this->pdo?->prepare('INSERT INTO `unit_ability_loadout` (`unit_id`, `ability_id`, `equip_order`) VALUES (?, ?, 0)')->execute([$unitId, 'ability.basic_attack_melee']);
    $this->pdo?->prepare('INSERT INTO `unit_ability_dice` (`unit_id`, `ability_id`, `slot_index`, `dice_instance_id`) VALUES (?, ?, 0, ?)')->execute([$unitId, 'ability.basic_attack_melee', $foreignDieId]);
    $controller = new WarbandController();

    $_SESSION['user_id'] = $ownerId;
    $detail = $this->invoke(fn() => $controller->unitDetail((string)$unitId));
    $this->assertIntegrityResponse($detail);
    $this->assertStringNotContainsString((string)$foreignDieId, json_encode($detail['body']));
    $this->assertIntegrityResponse($this->invoke(fn() => $controller->dice()));

    $_SESSION['user_id'] = $otherId;
    $foreignDice = $this->invoke(fn() => $controller->dice());
    $this->assertIntegrityResponse($foreignDice);
    $this->assertStringNotContainsString((string)$unitId, json_encode($foreignDice['body']));

    $this->pdo?->prepare('DELETE FROM `unit_ability_dice` WHERE `unit_id` = ?')->execute([$unitId]);
    $foreignUnitId = $this->insertUnit($otherId, 'unit_type.marksman', 'kin.pig', 'Hidden Unit');
    $squadId = $this->insertSquad($ownerId, 'Corrupt Squad');
    $this->pdo?->prepare('INSERT INTO `squad_units` (`squad_id`, `unit_id`, `position`) VALUES (?, ?, 0)')->execute([$squadId, $foreignUnitId]);
    $_SESSION['user_id'] = $ownerId;
    $squads = $this->invoke(fn() => $controller->squads());
    $this->assertIntegrityResponse($squads);
    $this->assertStringNotContainsString((string)$foreignUnitId, json_encode($squads['body']));

    $this->pdo?->prepare('DELETE FROM `squads` WHERE `id` = ?')->execute([$squadId]);
    $foreignSquadId = $this->insertSquad($otherId, 'Hidden Squad');
    $this->pdo?->prepare('UPDATE `user_state` SET `active_squad_id` = ? WHERE `user_id` = ?')->execute([$foreignSquadId, $ownerId]);
    $activeSquad = $this->invoke(fn() => $controller->squads());
    $this->assertIntegrityResponse($activeSquad);
    $this->assertStringNotContainsString((string)$foreignSquadId, json_encode($activeSquad['body']));
  }

  public function testFixtureRequiresCsrfAndIsDisabledForMissingProductionOrMisCasedEnvironment(): void
  {
    $userId = $this->createAccount('fixture-security@example.test', 'Fixture Security');
    $_SESSION['user_id'] = $userId;
    $_SESSION['csrf_token'] = 'expected';
    $controller = new WarbandFixtureController();

    $_SERVER['HTTP_X_CSRF_TOKEN'] = 'wrong';
    $csrf = $this->invoke(fn() => $controller->replace());
    $this->assertSame(403, $csrf['status']);
    $this->assertSame('csrf_invalid', $csrf['body']['error']['code'] ?? null);

    $_SERVER['HTTP_X_CSRF_TOKEN'] = 'expected';
    foreach ([null, 'prod', 'production', 'Dev'] as $environment) {
      $this->setAppEnv($environment);
      $disabled = $this->invoke(fn() => $controller->replace());
      $this->assertSame(404, $disabled['status']);
      $this->assertSame('warband_fixture_disabled', $disabled['body']['error']['code'] ?? null);
    }
    $this->setAppEnv('dev');
    $this->setFixtureOptIn(null);
    $disabledWithoutOptIn = $this->invoke(fn() => $controller->replace());
    $this->assertSame(404, $disabledWithoutOptIn['status']);
    $this->assertSame('warband_fixture_disabled', $disabledWithoutOptIn['body']['error']['code'] ?? null);
    $this->assertSame('0', (string)$this->scalar('SELECT COUNT(*) FROM `unit_instances` WHERE `user_id` = ?', [$userId]));
  }

  public function testFixtureIsRepeatSafeScopedToOwnerAndRollsBackCompletelyOnFailure(): void
  {
    $userId = $this->createAccount('fixture-repeat@example.test', 'Fixture Repeat');
    $otherId = $this->createAccount('fixture-preserve@example.test', 'Fixture Preserve');
    $foreignUnitId = $this->insertUnit($otherId, 'unit_type.guardian', 'kin.goblin', 'Untouched');
    $_SESSION['user_id'] = $userId;
    $first = $this->invokeFixture();
    $firstCounts = $this->assetCounts($userId);
    $second = $this->invokeFixture();
    $this->assertSame([6, 14, 2], $firstCounts);
    $this->assertSame($firstCounts, $this->assetCounts($userId));
    $this->assertNotSame($first['body']['data']['fixture']['unit_ids'], $second['body']['data']['fixture']['unit_ids']);
    $this->assertSame(2, $first['body']['data']['fixture']['player_revision'] ?? null);
    $this->assertSame(3, $second['body']['data']['fixture']['player_revision'] ?? null);
    $this->assertSame('1', (string)$this->scalar('SELECT COUNT(*) FROM `unit_instances` WHERE `id` = ? AND `user_id` = ?', [$foreignUnitId, $otherId]));

    $before = $this->warbandSnapshot($userId);
    $command = new ProvisionWarbandFixtureCommand(
      $this->pdo,
      new WarbandFixtureRepository($this->pdo),
      ContentRegistry::load(dirname(__DIR__, 2) . '/content'),
      static function(): void { throw new RuntimeException('Injected rollback check.'); },
    );
    try {
      $command->execute($userId);
      $this->fail('Expected injected fixture failure.');
    } catch (RuntimeException $e) {
      $this->assertSame('Injected rollback check.', $e->getMessage());
    }
    $this->assertSame($before, $this->warbandSnapshot($userId));
  }

  public function testUatSeedOnlyDoesNotReplaceWarbandOrTerminalRunHistory(): void
  {
    $userId = $this->createAccount('uat-seed@example.test', 'UAT Seed');
    $otherId = $this->createAccount('uat-other@example.test', 'Untouched');
    $foreignUnit = $this->insertUnit($otherId, 'unit_type.guardian', 'kin.goblin', 'Other Unit');
    $command = new ProvisionWarbandFixtureCommand($this->pdo, new WarbandFixtureRepository($this->pdo),
      ContentRegistry::load(dirname(__DIR__, 2) . '/content'));

    $first = $command->execute($userId, seedOnly: true);
    $this->assertSame('replace_owned_warband', $first['mode']);
    $this->assertSame([6, 14, 2], $this->assetCounts($userId));
    $this->assertSame(2, $first['player_revision']);
    $before = $this->warbandSnapshot($userId);

    $this->pdo?->prepare('INSERT INTO `runs` (`user_id`, `region_id`, `squad_id`, `status`, `ended_at`)
      VALUES (?, ?, ?, \'abandoned\', CURRENT_TIMESTAMP)')
      ->execute([$userId, 'region.the_farm', (int)$first['active_squad_id']]);
    $runId = (int)$this->pdo?->lastInsertId();
    $this->pdo?->prepare('INSERT INTO `run_unit_state` (`run_id`, `unit_id`, `current_hp`) VALUES (?, ?, 22)')
      ->execute([$runId, (int)$first['unit_ids']['bruiser']]);

    $second = $command->execute($userId, seedOnly: true);
    $this->assertSame('already_present', $second['mode']);
    $this->assertSame([6, 14, 2], [$second['unit_count'], $second['dice_count'], $second['squad_count']]);
    $this->assertSame(2, $second['player_revision']);
    $this->assertSame($before, $this->warbandSnapshot($userId));
    $this->assertSame('1', (string)$this->scalar('SELECT COUNT(*) FROM `run_unit_state` WHERE `run_id` = ?', [$runId]));
    $this->assertSame('1', (string)$this->scalar('SELECT COUNT(*) FROM `unit_instances` WHERE `id` = ? AND `user_id` = ?',
      [$foreignUnit, $otherId]));

    $this->pdo?->prepare('INSERT INTO `runs` (`user_id`, `region_id`, `squad_id`)
      VALUES (?, ?, ?)')->execute([$userId, 'region.the_farm', (int)$first['active_squad_id']]);
    try {
      $command->execute($userId, seedOnly: true);
      $this->fail('Expected active-run seeding refusal.');
    } catch (WarbandIntegrityException $error) {
      $this->assertStringContainsString('active', $error->getMessage());
    }
    $this->assertSame($before, $this->warbandSnapshot($userId));
  }

  public function testUatSeedOnlyRefusesIncompleteWarbandWithoutOverwriting(): void
  {
    $userId = $this->createAccount('uat-partial@example.test', 'UAT Partial');
    $unitId = $this->insertUnit($userId, 'unit_type.bruiser', 'kin.goblin', 'Existing');
    $command = new ProvisionWarbandFixtureCommand($this->pdo, new WarbandFixtureRepository($this->pdo),
      ContentRegistry::load(dirname(__DIR__, 2) . '/content'));
    try {
      $command->execute($userId, seedOnly: true);
      $this->fail('Expected incomplete-Warband seeding refusal.');
    } catch (WarbandIntegrityException $error) {
      $this->assertStringContainsString('incomplete', $error->getMessage());
    }
    $this->assertSame([1, 0, 0], $this->assetCounts($userId));
    $this->assertSame('1', (string)$this->scalar('SELECT COUNT(*) FROM `unit_instances` WHERE `id` = ?', [$unitId]));
    $this->assertSame('1', (string)$this->scalar('SELECT `player_revision` FROM `user_state` WHERE `user_id` = ?', [$userId]));
  }

  public function testOrdinaryRegistrationCreatesNoWarbandAssets(): void
  {
    $this->setJsonBody(['email' => 'registered-empty@example.test', 'password' => 'test-password', 'display_name' => 'Registered Empty']);
    $response = $this->invoke(fn() => (new AuthController())->localRegister());
    $this->assertSame(201, $response['status'], json_encode($response['body']));
    $userId = (int)($response['body']['data']['user']['id'] ?? 0);
    $this->trackUserId($userId);
    $this->assertSame([0, 0, 0], $this->assetCounts($userId));
  }

  /** @return array{status:int,body:array<string,mixed>} */
  private function invokeFixture(): array
  {
    $_SESSION['csrf_token'] = 'fixture-token';
    $_SERVER['HTTP_X_CSRF_TOKEN'] = 'fixture-token';
    $response = $this->invoke(fn() => (new WarbandFixtureController())->replace());
    $this->assertSame(200, $response['status'], json_encode($response['body']));
    return $response;
  }

  private function createAccount(string $email, string $displayName): int
  {
    $services = ControllerServiceFactory::buildContentAware($this->pdo);
    $userId = $services['accountCreationService']->createLocal($email, password_hash('test-password', PASSWORD_DEFAULT), $displayName);
    $this->trackUserId($userId);
    return $userId;
  }

  private function insertUnit(int $userId, string $type, string $kin, string $name, string $status = 'active'): int
  {
    $stmt = $this->pdo?->prepare('INSERT INTO `unit_instances` (`user_id`, `unit_type_id`, `kin_id`, `display_name`, `lifecycle_status`) VALUES (?, ?, ?, ?, ?)');
    $stmt?->execute([$userId, $type, $kin, $name, $status]);
    return (int)$this->pdo?->lastInsertId();
  }

  private function insertDie(int $userId, int $size, string $profile, string $status = 'active'): int
  {
    $stmt = $this->pdo?->prepare('INSERT INTO `dice_instances` (`user_id`, `size`, `profile_id`, `lifecycle_status`) VALUES (?, ?, ?, ?)');
    $stmt?->execute([$userId, $size, $profile, $status]);
    return (int)$this->pdo?->lastInsertId();
  }

  private function insertSquad(int $userId, string $name): int
  {
    $stmt = $this->pdo?->prepare('INSERT INTO `squads` (`user_id`, `name`) VALUES (?, ?)');
    $stmt?->execute([$userId, $name]);
    return (int)$this->pdo?->lastInsertId();
  }

  /** @return array{0:int,1:int,2:int} */
  private function assetCounts(int $userId): array
  {
    return [
      (int)$this->scalar('SELECT COUNT(*) FROM `unit_instances` WHERE `user_id` = ?', [$userId]),
      (int)$this->scalar('SELECT COUNT(*) FROM `dice_instances` WHERE `user_id` = ?', [$userId]),
      (int)$this->scalar('SELECT COUNT(*) FROM `squads` WHERE `user_id` = ?', [$userId]),
    ];
  }

  /** @return array<string,mixed> */
  private function warbandSnapshot(int $userId): array
  {
    $queries = [
      'state' => 'SELECT `active_squad_id`, `player_revision`, `updated_at` FROM `user_state` WHERE `user_id` = ? ORDER BY `user_id`',
      'units' => 'SELECT `id`, `unit_type_id`, `kin_id`, `display_name`, `level`, `xp`, `lifecycle_status`, `created_at`, `updated_at` FROM `unit_instances` WHERE `user_id` = ? ORDER BY `id`',
      'dice' => 'SELECT `id`, `size`, `profile_id`, `lifecycle_status`, `created_at`, `updated_at` FROM `dice_instances` WHERE `user_id` = ? ORDER BY `id`',
      'squads' => 'SELECT `id`, `name`, `created_at`, `updated_at` FROM `squads` WHERE `user_id` = ? ORDER BY `id`',
    ];
    $snapshot = [];
    foreach ($queries as $key => $sql) {
      $stmt = $this->pdo?->prepare($sql);
      $stmt?->execute([$userId]);
      $snapshot[$key] = $stmt?->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }
    foreach (['unit_promotions', 'unit_abilities', 'unit_ability_loadout', 'unit_ability_dice', 'squad_units'] as $table) {
      $join = in_array($table, ['squad_units'], true)
        ? "JOIN `squads` root ON root.`id` = child.`squad_id`"
        : "JOIN `unit_instances` root ON root.`id` = child.`unit_id`";
      $stmt = $this->pdo?->prepare("SELECT child.* FROM `$table` child $join WHERE root.`user_id` = ? ORDER BY 1, 2");
      $stmt?->execute([$userId]);
      $snapshot[$table] = $stmt?->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }
    return $snapshot;
  }

  /** @param array{status:int,body:array<string,mixed>} $response */
  private function assertIntegrityResponse(array $response): void
  {
    $this->assertSame(500, $response['status']);
    $this->assertSame('warband_data_integrity_error', $response['body']['error']['code'] ?? null);
    $this->assertStringNotContainsString('SQL', json_encode($response['body']));
  }

  private function setAppEnv(?string $value): void
  {
    if ($value === null) {
      putenv('APP_ENV');
      unset($_ENV['APP_ENV'], $_SERVER['APP_ENV']);
      return;
    }
    putenv('APP_ENV=' . $value);
    $_ENV['APP_ENV'] = $value;
    $_SERVER['APP_ENV'] = $value;
  }

  private function setFixtureOptIn(?string $value): void
  {
    if ($value === null) {
      putenv('ENABLE_WARBAND_FIXTURES');
      unset($_ENV['ENABLE_WARBAND_FIXTURES'], $_SERVER['ENABLE_WARBAND_FIXTURES']);
      return;
    }
    putenv('ENABLE_WARBAND_FIXTURES=' . $value);
    $_ENV['ENABLE_WARBAND_FIXTURES'] = $value;
    $_SERVER['ENABLE_WARBAND_FIXTURES'] = $value;
  }
}
