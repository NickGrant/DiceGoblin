<?php
declare(strict_types=1);

namespace DiceGoblins\Tests\Integration;

use DiceGoblins\Application\Commands\ProvisionWarbandFixtureCommand;
use DiceGoblins\Application\Commands\ReplaceUnitLoadoutCommand;
use DiceGoblins\Application\Commands\ActiveRunConfigurationPolicy;
use DiceGoblins\Application\Commands\UnitConfigurationSupport;
use DiceGoblins\Application\Queries\UnitDetailQuery;
use DiceGoblins\Content\ContentRegistry;
use DiceGoblins\Controllers\ControllerServiceFactory;
use DiceGoblins\Controllers\WarbandController;
use DiceGoblins\Repositories\PlayerStateRepository;
use DiceGoblins\Repositories\WarbandDiceRepository;
use DiceGoblins\Repositories\WarbandFixtureRepository;
use DiceGoblins\Repositories\WarbandUnitRepository;
use DiceGoblins\Tests\Support\IntegrationTestCase;
use RuntimeException;

final class UnitConfigurationControllerTest extends IntegrationTestCase
{
  protected function supportsVnextBaseline(): bool { return true; }

  public function testBothMutationsRequireAuthenticationAndCsrf(): void
  {
    $controller = new WarbandController();
    foreach (['renameUnit', 'replaceUnitLoadout'] as $method) {
      $this->setJsonBody($method === 'renameUnit' ? ['name' => 'New'] : ['abilities' => []]);
      $unauthorized = $this->invoke(fn() => $controller->{$method}('1'));
      $this->assertSame(401, $unauthorized['status']);
      $this->assertSame('unauthorized', $unauthorized['body']['error']['code'] ?? null);
    }

    $userId = $this->createAccount();
    $_SESSION['user_id'] = $userId;
    $_SESSION['csrf_token'] = 'expected';
    $_SERVER['HTTP_X_CSRF_TOKEN'] = 'wrong';
    foreach (['renameUnit', 'replaceUnitLoadout'] as $method) {
      $this->setJsonBody($method === 'renameUnit' ? ['name' => 'New'] : ['abilities' => []]);
      $csrf = $this->invoke(fn() => $controller->{$method}('1'));
      $this->assertSame(403, $csrf['status']);
      $this->assertSame('csrf_invalid', $csrf['body']['error']['code'] ?? null);
    }
  }

  public function testRenameValidatesExactBodyNormalizesUnicodeAndNoopsWithoutRevision(): void
  {
    [$userId, $fixture] = $this->fixtureAccount();
    $unitId = $fixture['unit_ids']['bruiser'];
    $beforeRevision = $this->revision($userId);
    $invalid = [
      [],
      ['name' => 12],
      ['name' => " \t\n "],
      ['name' => str_repeat('界', 129)],
      ['name' => 'Valid', 'extra' => true],
    ];
    foreach ($invalid as $body) {
      $response = $this->command($userId, 'renameUnit', $unitId, $body);
      $this->assertConfigurationError($response);
      $this->assertSame($beforeRevision, $this->revision($userId));
    }

    $_SESSION['user_id'] = $userId;
    $_SESSION['csrf_token'] = 'unit-csrf';
    $_SERVER['HTTP_X_CSRF_TOKEN'] = 'unit-csrf';
    $_SERVER['DICE_GOBLINS_TEST_RAW_BODY'] = '{bad json';
    $this->assertConfigurationError($this->invoke(fn() => (new WarbandController())->renameUnit($unitId)));

    $maximum = str_repeat('界', 128);
    $acceptedMaximum = $this->command($userId, 'renameUnit', $unitId, ['name' => $maximum]);
    $this->assertSame(200, $acceptedMaximum['status']);
    $this->assertSame($maximum, $acceptedMaximum['body']['data']['unit']['display_name'] ?? null);
    $this->assertSame($beforeRevision + 1, $acceptedMaximum['body']['data']['player_revision'] ?? null);

    $renamed = $this->command($userId, 'renameUnit', $unitId, ['name' => " \t🐲 名\n "]);
    $this->assertSame(200, $renamed['status'], json_encode($renamed['body']));
    $this->assertSame('🐲 名', $renamed['body']['data']['unit']['display_name'] ?? null);
    $this->assertSame($beforeRevision + 2, $renamed['body']['data']['player_revision'] ?? null);
    $this->assertSame(['id', 'display_name', 'unit_type_id', 'kin_id', 'level', 'xp', 'lifecycle_status', 'promotion_history', 'owned_ability_ids', 'ability_loadout', 'dice_bindings'], array_keys($renamed['body']['data']['unit'] ?? []));

    $noop = $this->command($userId, 'renameUnit', $unitId, ['name' => '  🐲 名  ']);
    $this->assertSame(200, $noop['status']);
    $this->assertSame($beforeRevision + 2, $noop['body']['data']['player_revision'] ?? null);
    $this->assertSame($beforeRevision + 2, $this->revision($userId));
  }

  public function testUnitTargetsAreNonDisclosingForMissingForeignTerminalAndMalformedIds(): void
  {
    [$userId] = $this->fixtureAccount();
    [$otherId, $otherFixture] = $this->fixtureAccount();
    $terminalId = $this->insertUnit($userId, 'Retired', 'retired');
    $beforeRevision = $this->revision($userId);
    foreach ([$otherFixture['unit_ids']['bruiser'], $terminalId, '999999999', '01', '-1'] as $unitId) {
      foreach ([
        ['renameUnit', ['name' => 'Stolen']],
        ['replaceUnitLoadout', ['abilities' => [['ability_id' => 'ability.basic_attack_melee', 'dice_instance_ids' => ['1']]]]],
      ] as [$method, $body]) {
        $response = $this->command($userId, $method, (string)$unitId, $body);
        $this->assertSame(404, $response['status']);
        $this->assertSame('unit_not_found', $response['body']['error']['code'] ?? null);
        $encoded = json_encode($response['body']);
        $this->assertStringNotContainsString('Stolen', $encoded);
        $this->assertStringNotContainsString((string)$otherId, $encoded);
      }
    }
    $this->assertSame($beforeRevision, $this->revision($userId));
  }

  public function testLoadoutAtomicallyReplacesOrderAndBindingsAllowsSameUnitMovesAndNoops(): void
  {
    [$userId, $fixture] = $this->fixtureAccount();
    $unitId = $fixture['unit_ids']['saboteur'];
    $dice = $fixture['dice_ids'];
    $proposal = ['abilities' => [
      ['dice_instance_ids' => [$dice['saboteur_sleep_a']], 'ability_id' => 'ability.basic_attack_ranged'],
      ['ability_id' => 'ability.sleep_dart', 'dice_instance_ids' => [$dice['saboteur_basic'], $dice['saboteur_sleep_b']]],
    ]];
    $beforeRevision = $this->revision($userId);

    $updated = $this->command($userId, 'replaceUnitLoadout', $unitId, $proposal);
    $this->assertSame(200, $updated['status'], json_encode($updated['body']));
    $this->assertSame($beforeRevision + 1, $updated['body']['data']['player_revision'] ?? null);
    $this->assertSame([
      ['ability_id' => 'ability.basic_attack_ranged', 'equip_order' => 0],
      ['ability_id' => 'ability.sleep_dart', 'equip_order' => 1],
    ], $updated['body']['data']['unit']['ability_loadout'] ?? null);
    $expectedBindings = [
      ['ability_id' => 'ability.basic_attack_ranged', 'slot_index' => 0, 'dice_instance_id' => $dice['saboteur_sleep_a']],
      ['ability_id' => 'ability.sleep_dart', 'slot_index' => 0, 'dice_instance_id' => $dice['saboteur_basic']],
      ['ability_id' => 'ability.sleep_dart', 'slot_index' => 1, 'dice_instance_id' => $dice['saboteur_sleep_b']],
    ];
    $this->assertSame($expectedBindings, $updated['body']['data']['unit']['dice_bindings'] ?? null);
    $this->assertSame(['0', '1'], array_map('strval', $this->column('SELECT `equip_order` FROM `unit_ability_loadout` WHERE `unit_id` = ? ORDER BY `equip_order`', [$unitId])));

    $detail = $this->query($userId, 'unitDetail', $unitId);
    $this->assertSame($expectedBindings, $detail['body']['data']['unit']['dice_bindings'] ?? null);
    $diceResponse = $this->query($userId, 'dice');
    $bindingsById = [];
    foreach ($diceResponse['body']['data']['dice'] ?? [] as $die) $bindingsById[$die['id']] = $die['bindings'];
    $this->assertSame([['unit_id' => $unitId, 'ability_id' => 'ability.basic_attack_ranged', 'slot_index' => 0]], $bindingsById[$dice['saboteur_sleep_a']] ?? null);
    $this->assertSame([['unit_id' => $unitId, 'ability_id' => 'ability.sleep_dart', 'slot_index' => 0]], $bindingsById[$dice['saboteur_basic']] ?? null);

    $noop = $this->command($userId, 'replaceUnitLoadout', $unitId, $proposal);
    $this->assertSame(200, $noop['status']);
    $this->assertSame($beforeRevision + 1, $noop['body']['data']['player_revision'] ?? null);
    $this->assertSame($beforeRevision + 1, $this->revision($userId));
  }

  public function testLoadoutRejectsAbilityAndRequestShapeViolationsWithoutMutation(): void
  {
    [$userId, $fixture] = $this->fixtureAccount();
    $unitId = $fixture['unit_ids']['bruiser'];
    $dieA = $fixture['dice_ids']['bruiser_basic'];
    $dieB = $fixture['dice_ids']['bruiser_heavy'];
    $invalid = [
      [],
      ['abilities' => []],
      ['abilities' => 'nope'],
      ['abilities' => [['ability_id' => 'ability.basic_attack_melee', 'dice_instance_ids' => [$dieA], 'extra' => true]]],
      ['abilities' => [['ability_id' => 'Ability.bad', 'dice_instance_ids' => [$dieA]]]],
      ['abilities' => [['ability_id' => 'kin.goblin', 'dice_instance_ids' => [$dieA]]]],
      ['abilities' => [['ability_id' => 'ability.thick_hide', 'dice_instance_ids' => []]]],
      ['abilities' => [['ability_id' => 'ability.aimed_shot', 'dice_instance_ids' => [$dieA]]]],
      ['abilities' => [['ability_id' => 'ability.missing', 'dice_instance_ids' => [$dieA]]]],
      ['abilities' => [['ability_id' => 'ability.heavy_strike', 'dice_instance_ids' => []]]],
      ['abilities' => [['ability_id' => 'ability.heavy_strike', 'dice_instance_ids' => [$dieA, $dieB]]]],
      ['abilities' => [
        ['ability_id' => 'ability.heavy_strike', 'dice_instance_ids' => [$dieA]],
        ['ability_id' => 'ability.heavy_strike', 'dice_instance_ids' => [$dieB]],
      ]],
      ['abilities' => [
        ['ability_id' => 'ability.heavy_strike', 'dice_instance_ids' => [$dieA]],
        ['ability_id' => 'ability.basic_attack_melee', 'dice_instance_ids' => [$dieA]],
      ]],
      ['abilities' => [['ability_id' => 'ability.heavy_strike', 'dice_instance_ids' => [1]]]],
      ['abilities' => [['ability_id' => 'ability.heavy_strike', 'dice_instance_ids' => ['01']]]],
    ];
    $before = $this->configurationSnapshot($unitId, $userId);
    foreach ($invalid as $body) {
      $this->assertConfigurationError($this->command($userId, 'replaceUnitLoadout', $unitId, $body));
      $this->assertSame($before, $this->configurationSnapshot($unitId, $userId));
    }
  }

  public function testLoadoutRejectsUnavailableInvalidAndCrossUnitDiceWithoutDisclosure(): void
  {
    [$userId, $fixture] = $this->fixtureAccount();
    [$otherId, $otherFixture] = $this->fixtureAccount();
    $unitId = $fixture['unit_ids']['saboteur'];
    $unbound = $this->insertDie($userId, 6, 'dice_profile.cardboard_plain');
    $terminal = $this->insertDie($userId, 6, 'dice_profile.cardboard_plain', 'salvaged');
    $badProfile = $this->insertDie($userId, 6, 'dice_profile.missing');
    $badSize = $this->insertDie($userId, 7, 'dice_profile.cardboard_plain');
    $before = $this->configurationSnapshot($unitId, $userId);

    foreach ([
      '999999999',
      $otherFixture['dice_ids']['bruiser_basic'],
      $terminal,
      $badProfile,
      $badSize,
      $fixture['dice_ids']['guardian_shield'],
    ] as $unavailableDie) {
      $proposal = $this->saboteurProposal($fixture, (string)$unavailableDie, (string)$unbound);
      $response = $this->command($userId, 'replaceUnitLoadout', $unitId, $proposal);
      $this->assertConfigurationError($response);
      $encoded = json_encode($response['body']);
      $this->assertStringNotContainsString((string)$unavailableDie, $encoded);
      $this->assertStringNotContainsString((string)$otherId, $encoded);
      $this->assertSame($before, $this->configurationSnapshot($unitId, $userId));
    }
  }

  public function testPersistenceFailureRollsBackLoadoutBindingsAndRevision(): void
  {
    [$userId, $fixture] = $this->fixtureAccount();
    $unitId = (int)$fixture['unit_ids']['saboteur'];
    $content = ContentRegistry::load(dirname(__DIR__, 2) . '/content');
    $units = new WarbandUnitRepository($this->pdo);
    $dice = new WarbandDiceRepository($this->pdo);
    $playerState = new PlayerStateRepository($this->pdo);
    $support = new UnitConfigurationSupport($playerState, $dice, new UnitDetailQuery($units, $content), $content);
    $command = new ReplaceUnitLoadoutCommand($this->pdo, $playerState, $units, $support,
      new ActiveRunConfigurationPolicy(new \DiceGoblins\Repositories\RunPersistenceRepository($this->pdo)),
      static function(): void { throw new RuntimeException('Injected rollback.'); });
    $before = $this->configurationSnapshot((string)$unitId, $userId);

    try {
      $command->execute($userId, $unitId, ['abilities' => [
        ['ability_id' => 'ability.basic_attack_ranged', 'dice_instance_ids' => [$fixture['dice_ids']['saboteur_basic']]],
      ]]);
      $this->fail('Expected injected failure.');
    } catch (RuntimeException $e) {
      $this->assertSame('Injected rollback.', $e->getMessage());
    }
    $this->assertSame($before, $this->configurationSnapshot((string)$unitId, $userId));
  }

  public function testCorruptCurrentStateBlocksRenameAndLoadoutWithoutRepair(): void
  {
    [$userId, $fixture] = $this->fixtureAccount();
    $unitId = $fixture['unit_ids']['saboteur'];
    $this->pdo?->prepare('UPDATE `unit_ability_loadout` SET `equip_order` = 5 WHERE `unit_id` = ? AND `equip_order` = 1')->execute([$unitId]);
    $before = $this->configurationSnapshot($unitId, $userId);

    foreach ([
      ['renameUnit', ['name' => 'No Repair']],
      ['replaceUnitLoadout', $this->saboteurProposal($fixture, $fixture['dice_ids']['saboteur_sleep_a'], $fixture['dice_ids']['saboteur_sleep_b'])],
    ] as [$method, $body]) {
      $response = $this->command($userId, $method, $unitId, $body);
      $this->assertSame(500, $response['status']);
      $this->assertSame('warband_data_integrity_error', $response['body']['error']['code'] ?? null);
      $this->assertSame($before, $this->configurationSnapshot($unitId, $userId));
    }
  }

  /** @return array{0:int,1:array<string,mixed>} */
  private function fixtureAccount(): array
  {
    $userId = $this->createAccount();
    $fixture = (new ProvisionWarbandFixtureCommand(
      $this->pdo,
      new WarbandFixtureRepository($this->pdo),
      ContentRegistry::load(dirname(__DIR__, 2) . '/content'),
    ))->execute($userId);
    return [$userId, $fixture];
  }

  private function createAccount(): int
  {
    $services = ControllerServiceFactory::buildContentAware($this->pdo);
    $token = bin2hex(random_bytes(6));
    $id = $services['accountCreationService']->createLocal("unit-config-$token@example.test", password_hash('test-password', PASSWORD_DEFAULT), 'Unit Config');
    $this->trackUserId($id);
    return $id;
  }

  /** @param array<string,mixed> $body @return array{status:int,body:array<string,mixed>} */
  private function command(int $userId, string $method, string $unitId, array $body): array
  {
    $_SESSION['user_id'] = $userId;
    $_SESSION['csrf_token'] = 'unit-csrf';
    $_SERVER['HTTP_X_CSRF_TOKEN'] = 'unit-csrf';
    $this->setJsonBody($body);
    return $this->invoke(fn() => (new WarbandController())->{$method}($unitId));
  }

  /** @return array{status:int,body:array<string,mixed>} */
  private function query(int $userId, string $method, ?string $unitId = null): array
  {
    $_SESSION['user_id'] = $userId;
    $controller = new WarbandController();
    return $this->invoke(fn() => $unitId === null ? $controller->{$method}() : $controller->{$method}($unitId));
  }

  /** @return array<string,mixed> */
  private function saboteurProposal(array $fixture, string $firstDie, string $secondDie): array
  {
    return ['abilities' => [
      ['ability_id' => 'ability.sleep_dart', 'dice_instance_ids' => [$firstDie, $secondDie]],
      ['ability_id' => 'ability.basic_attack_ranged', 'dice_instance_ids' => [$fixture['dice_ids']['saboteur_basic']]],
    ]];
  }

  private function insertUnit(int $userId, string $name, string $status): string
  {
    $stmt = $this->pdo?->prepare('INSERT INTO `unit_instances` (`user_id`, `unit_type_id`, `kin_id`, `display_name`, `lifecycle_status`) VALUES (?, ?, ?, ?, ?)');
    $stmt?->execute([$userId, 'unit_type.bruiser', 'kin.goblin', $name, $status]);
    return (string)$this->pdo?->lastInsertId();
  }

  private function insertDie(int $userId, int $size, string $profile, string $status = 'active'): string
  {
    $stmt = $this->pdo?->prepare('INSERT INTO `dice_instances` (`user_id`, `size`, `profile_id`, `lifecycle_status`) VALUES (?, ?, ?, ?)');
    $stmt?->execute([$userId, $size, $profile, $status]);
    return (string)$this->pdo?->lastInsertId();
  }

  /** @return array<string,mixed> */
  private function configurationSnapshot(string $unitId, int $userId): array
  {
    $snapshot = ['revision' => $this->revision($userId)];
    foreach ([
      'unit' => 'SELECT `display_name` FROM `unit_instances` WHERE `id` = ?',
      'loadout' => 'SELECT `ability_id`, `equip_order` FROM `unit_ability_loadout` WHERE `unit_id` = ? ORDER BY `equip_order`',
      'bindings' => 'SELECT `ability_id`, `slot_index`, `dice_instance_id` FROM `unit_ability_dice` WHERE `unit_id` = ? ORDER BY `ability_id`, `slot_index`',
    ] as $key => $sql) {
      $stmt = $this->pdo?->prepare($sql);
      $stmt?->execute([$unitId]);
      $snapshot[$key] = $stmt?->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }
    return $snapshot;
  }

  /** @return list<mixed> */
  private function column(string $sql, array $params): array
  {
    $stmt = $this->pdo?->prepare($sql);
    $stmt?->execute($params);
    return $stmt?->fetchAll(\PDO::FETCH_COLUMN) ?: [];
  }

  private function revision(int $userId): int
  {
    return (int)$this->scalar('SELECT `player_revision` FROM `user_state` WHERE `user_id` = ?', [$userId]);
  }

  /** @param array{status:int,body:array<string,mixed>} $response */
  private function assertConfigurationError(array $response): void
  {
    $this->assertSame(422, $response['status'], json_encode($response['body']));
    $this->assertSame('invalid_unit_configuration', $response['body']['error']['code'] ?? null);
    $this->assertSame('Unit configuration is invalid.', $response['body']['error']['message'] ?? null);
  }
}
