<?php
declare(strict_types=1);

namespace DiceGoblins\Tests\Integration;

use DiceGoblins\Controllers\ControllerServiceFactory;
use DiceGoblins\Controllers\GameBootstrapController;
use DiceGoblins\Controllers\WarbandController;
use DiceGoblins\Tests\Support\IntegrationTestCase;

final class SquadCommandControllerTest extends IntegrationTestCase
{
  protected function supportsVnextBaseline(): bool { return true; }

  public function testEveryMutationRequiresAuthenticationAndCsrf(): void
  {
    $controller = new WarbandController();
    $requests = [
      fn() => $controller->createSquad(), fn() => $controller->updateSquad('1'),
      fn() => $controller->activateSquad('1'), fn() => $controller->deleteSquad('1'),
    ];
    foreach ($requests as $request) {
      $this->assertSame(401, $this->invoke($request)['status']);
    }
    $userId = $this->createAccount('squad-security@example.test', 'Security');
    $_SESSION['user_id'] = $userId;
    foreach ($requests as $request) {
      $response = $this->invoke($request);
      $this->assertSame(403, $response['status']);
      $this->assertSame('csrf_invalid', $response['body']['error']['code'] ?? null);
    }
    $this->assertSame(0, $this->squadCount($userId));
  }

  public function testCreateNormalizesFormationSetsFirstActivePreservesItAndReplaysIdempotently(): void
  {
    $userId = $this->createAccount('squad-create@example.test', 'Creator');
    $unitA = $this->insertUnit($userId, 'unit_type.bruiser', 'kin.goblin', 'Grub');
    $unitB = $this->insertUnit($userId, 'unit_type.guardian', 'kin.pig', 'Moss');
    $first = $this->command($userId, 'createSquad', null, $this->configuration('  Raiders  ', [0 => $unitA, 8 => $unitB]), 'create-key-001');
    $this->assertSame(200, $first['status'], json_encode($first['body']));
    $data = $first['body']['data'];
    $firstId = (string)$data['squad']['id'];
    $this->assertSame('Raiders', $data['squad']['name']);
    $this->assertSame([$unitA, null, null, null, null, null, null, null, $unitB], array_map(
      static fn($id) => $id === null ? null : (int)$id, $data['squad']['formation']));
    $this->assertTrue($data['squad']['is_active']);
    $this->assertSame(2, $data['player_revision']);

    $replay = $this->command($userId, 'createSquad', null, $this->configuration('Raiders', [0 => $unitA, 8 => $unitB]), 'create-key-001');
    $this->assertEquals($data, $replay['body']['data']);
    $this->assertSame(1, $this->squadCount($userId));
    $this->assertSame(2, $this->revision($userId));

    $second = $this->command($userId, 'createSquad', null, $this->configuration('Empty'), 'create-key-002');
    $this->assertFalse($second['body']['data']['squad']['is_active']);
    $this->assertSame($firstId, $second['body']['data']['active_squad_id']);
    $this->assertSame(3, $this->revision($userId));

    $conflict = $this->command($userId, 'createSquad', null, $this->configuration('Changed'), 'create-key-001');
    $this->assertSame(409, $conflict['status']);
    $this->assertSame(2, $this->squadCount($userId));
    $this->assertSame(3, $this->revision($userId));

    $otherId = $this->createAccount('squad-create-other@example.test', 'Other');
    $other = $this->command($otherId, 'createSquad', null, $this->configuration('Independent'), 'create-key-001');
    $this->assertSame(200, $other['status']);
    $this->assertSame(1, $this->squadCount($otherId));

    $_SESSION['user_id'] = $userId;
    $bootstrap = $this->invoke(fn() => (new GameBootstrapController())->bootstrap());
    $active = $bootstrap['body']['data']['active_squad'] ?? null;
    $this->assertSame($firstId, $active['id'] ?? null);
    $this->assertSame(9, count($active['formation'] ?? []));
    $this->assertSame([(string)$unitA, (string)$unitB], array_column($active['units'] ?? [], 'id'));
    $this->assertSame(3, $bootstrap['body']['data']['player']['player_revision'] ?? null);
  }

  public function testInvalidKeysNamesAndFormationsFailBeforeMutation(): void
  {
    $userId = $this->createAccount('squad-invalid@example.test', 'Invalid');
    $unit = $this->insertUnit($userId, 'unit_type.bruiser', 'kin.goblin', 'Grub');
    foreach ([null, 'short', str_repeat('x', 129), 'bad key!!'] as $key) {
      $response = $this->command($userId, 'createSquad', null, $this->configuration('Valid'), $key);
      $this->assertSame(400, $response['status']);
    }
    $invalidBodies = [
      ['name' => ' ', 'formation' => array_fill(0, 9, null)],
      ['name' => str_repeat('x', 129), 'formation' => array_fill(0, 9, null)],
      ['name' => 'Short', 'formation' => array_fill(0, 8, null)],
      ['name' => 'Extra', 'formation' => array_fill(0, 9, null), 'extra' => true],
      ['name' => 'Numeric', 'formation' => [$unit, null, null, null, null, null, null, null, null]],
      ['name' => 'Duplicate', 'formation' => [(string)$unit, (string)$unit, null, null, null, null, null, null, null]],
    ];
    foreach ($invalidBodies as $index => $body) {
      $response = $this->command($userId, 'createSquad', null, $body, 'invalid-body-' . $index);
      $this->assertSame(422, $response['status'], json_encode($response['body']));
    }
    $this->assertSame(0, $this->squadCount($userId));
    $this->assertSame(1, $this->revision($userId));
  }

  public function testUnavailableUnitsAreRejectedWithoutDisclosureOrPartialWrites(): void
  {
    $userId = $this->createAccount('squad-units@example.test', 'Owner');
    $otherId = $this->createAccount('squad-units-other@example.test', 'Hidden Owner');
    $foreign = $this->insertUnit($otherId, 'unit_type.bruiser', 'kin.goblin', 'Secret Unit');
    $terminal = $this->insertUnit($userId, 'unit_type.guardian', 'kin.pig', 'Gone', 'retired');
    foreach ([$foreign, $terminal, 999999999] as $index => $id) {
      $response = $this->command($userId, 'createSquad', null, $this->configuration('Rejected', [0 => $id]), 'unit-check-' . $index);
      $this->assertSame(422, $response['status']);
      $this->assertStringNotContainsString('Secret Unit', json_encode($response['body']));
    }
    $this->assertSame(0, $this->squadCount($userId));
    $this->assertSame(1, $this->revision($userId));
  }

  public function testUpdateIsAtomicAndKeepsActiveSquad(): void
  {
    $userId = $this->createAccount('squad-update@example.test', 'Updater');
    $unitA = $this->insertUnit($userId, 'unit_type.bruiser', 'kin.goblin', 'A');
    $unitB = $this->insertUnit($userId, 'unit_type.guardian', 'kin.pig', 'B');
    $created = $this->command($userId, 'createSquad', null, $this->configuration('Before', [0 => $unitA]), 'update-create');
    $squadId = (string)$created['body']['data']['squad']['id'];
    $updated = $this->command($userId, 'updateSquad', $squadId, $this->configuration('After', [4 => $unitB]));
    $this->assertSame(200, $updated['status'], json_encode($updated['body']));
    $this->assertSame('After', $updated['body']['data']['squad']['name']);
    $this->assertTrue($updated['body']['data']['squad']['is_active']);
    $this->assertSame((string)$unitB, $updated['body']['data']['squad']['formation'][4]);
    $this->assertSame(3, $this->revision($userId));

    $failure = $this->command($userId, 'updateSquad', $squadId, $this->configuration('Must Roll Back', [0 => 999999999]));
    $this->assertSame(422, $failure['status']);
    $this->assertSame('After', $this->scalar('SELECT `name` FROM `squads` WHERE `id` = ?', [(int)$squadId]));
    $this->assertSame((string)$unitB, (string)$this->scalar('SELECT `unit_id` FROM `squad_units` WHERE `squad_id` = ?', [(int)$squadId]));
    $this->assertSame(3, $this->revision($userId));
  }

  public function testActivationNoopAndDeletionSemanticsChangeRevisionExactlyOnce(): void
  {
    $userId = $this->createAccount('squad-lifecycle@example.test', 'Lifecycle');
    $first = $this->command($userId, 'createSquad', null, $this->configuration('First'), 'life-first');
    $second = $this->command($userId, 'createSquad', null, $this->configuration('Second'), 'life-second');
    $firstId = (string)$first['body']['data']['squad']['id'];
    $secondId = (string)$second['body']['data']['squad']['id'];

    $blocked = $this->command($userId, 'deleteSquad', $firstId);
    $this->assertSame(409, $blocked['status']);
    $this->assertSame(3, $this->revision($userId));
    $activated = $this->command($userId, 'activateSquad', $secondId);
    $this->assertSame(4, $activated['body']['data']['player_revision']);
    $noop = $this->command($userId, 'activateSquad', $secondId);
    $this->assertSame(4, $noop['body']['data']['player_revision']);
    $deleted = $this->command($userId, 'deleteSquad', $firstId);
    $this->assertSame($secondId, $deleted['body']['data']['active_squad_id']);
    $this->assertSame(5, $this->revision($userId));
    $only = $this->command($userId, 'deleteSquad', $secondId);
    $this->assertNull($only['body']['data']['active_squad_id']);
    $this->assertSame(6, $this->revision($userId));
    $this->assertSame(0, $this->squadCount($userId));
  }

  public function testMissingAndForeignSquadsAreNonDisclosingForEveryMutation(): void
  {
    $userId = $this->createAccount('squad-owner@example.test', 'Owner');
    $otherId = $this->createAccount('squad-hidden@example.test', 'Hidden');
    $hidden = $this->command($otherId, 'createSquad', null, $this->configuration('Secret Squad'), 'hidden-create');
    $hiddenId = (string)$hidden['body']['data']['squad']['id'];
    foreach ([
      $this->command($userId, 'updateSquad', $hiddenId, $this->configuration('Steal')),
      $this->command($userId, 'activateSquad', $hiddenId),
      $this->command($userId, 'deleteSquad', $hiddenId),
      $this->command($userId, 'deleteSquad', '999999999'),
    ] as $response) {
      $this->assertSame(404, $response['status']);
      $this->assertSame('squad_not_found', $response['body']['error']['code'] ?? null);
      $this->assertStringNotContainsString('Secret Squad', json_encode($response['body']));
    }
    $this->assertSame(1, $this->revision($userId));
  }

  public function testCorruptActiveStateAndTerminalBootstrapUnitFailSafelyWithoutRepair(): void
  {
    $userId = $this->createAccount('squad-corrupt@example.test', 'Corrupt');
    $otherId = $this->createAccount('squad-corrupt-other@example.test', 'Other');
    $foreign = $this->command($otherId, 'createSquad', null, $this->configuration('Foreign'), 'foreign-active');
    $foreignId = (int)$foreign['body']['data']['squad']['id'];
    $this->pdo?->prepare('UPDATE `user_state` SET `active_squad_id` = ? WHERE `user_id` = ?')->execute([$foreignId, $userId]);
    $before = $this->revision($userId);
    $_SESSION['user_id'] = $userId;
    $bootstrap = $this->invoke(fn() => (new GameBootstrapController())->bootstrap());
    $this->assertSame(500, $bootstrap['status']);
    $this->assertSame('player_state_integrity_error', $bootstrap['body']['error']['code'] ?? null);
    $command = $this->command($userId, 'createSquad', null, $this->configuration('No Repair'), 'corrupt-create');
    $this->assertSame(500, $command['status']);
    $this->assertSame($before, $this->revision($userId));
    $this->assertSame($foreignId, (int)$this->scalar('SELECT `active_squad_id` FROM `user_state` WHERE `user_id` = ?', [$userId]));

    $this->pdo?->prepare('UPDATE `user_state` SET `active_squad_id` = NULL WHERE `user_id` = ?')->execute([$userId]);
    $created = $this->command($userId, 'createSquad', null, $this->configuration('Owned'), 'owned-active');
    $ownedId = (int)$created['body']['data']['squad']['id'];
    $terminal = $this->insertUnit($userId, 'unit_type.bruiser', 'kin.goblin', 'Terminal');
    $this->pdo?->prepare('INSERT INTO `squad_units` (`squad_id`, `unit_id`, `position`) VALUES (?, ?, 0)')->execute([$ownedId, $terminal]);
    $this->pdo?->prepare("UPDATE `unit_instances` SET `lifecycle_status` = 'retired' WHERE `id` = ?")->execute([$terminal]);
    $beforeTerminalBootstrap = $this->revision($userId);
    $_SESSION['user_id'] = $userId;
    $terminalBootstrap = $this->invoke(fn() => (new GameBootstrapController())->bootstrap());
    $this->assertSame(500, $terminalBootstrap['status']);
    $this->assertSame($beforeTerminalBootstrap, $this->revision($userId));
  }

  public function testBootstrapRejectsForeignFormationUnitsAndInvalidAuthoredReferences(): void
  {
    $userId = $this->createAccount('squad-bootstrap-corrupt@example.test', 'Bootstrap Owner');
    $otherId = $this->createAccount('squad-bootstrap-foreign@example.test', 'Foreign Owner');
    $created = $this->command($userId, 'createSquad', null, $this->configuration('Active'), 'bootstrap-active');
    $squadId = (int)$created['body']['data']['squad']['id'];
    $foreignUnit = $this->insertUnit($otherId, 'unit_type.bruiser', 'kin.goblin', 'Hidden');
    $this->pdo?->prepare('INSERT INTO `squad_units` (`squad_id`, `unit_id`, `position`) VALUES (?, ?, 0)')->execute([$squadId, $foreignUnit]);
    $_SESSION['user_id'] = $userId;
    $foreign = $this->invoke(fn() => (new GameBootstrapController())->bootstrap());
    $this->assertSame(500, $foreign['status']);
    $this->assertStringNotContainsString('Hidden', json_encode($foreign['body']));

    $this->pdo?->prepare('DELETE FROM `squad_units` WHERE `squad_id` = ?')->execute([$squadId]);
    $ownedUnit = $this->insertUnit($userId, 'unit_type.not_authored', 'kin.goblin', 'Broken');
    $this->pdo?->prepare('INSERT INTO `squad_units` (`squad_id`, `unit_id`, `position`) VALUES (?, ?, 0)')->execute([$squadId, $ownedUnit]);
    $_SESSION['user_id'] = $userId;
    $authored = $this->invoke(fn() => (new GameBootstrapController())->bootstrap());
    $this->assertSame(500, $authored['status']);
    $this->assertSame('player_state_integrity_error', $authored['body']['error']['code'] ?? null);
    $this->assertSame(2, $this->revision($userId));
  }

  public function testBootstrapRejectsSavedSquadsWithoutAnActiveSelection(): void
  {
    $userId = $this->createAccount('squad-missing-active@example.test', 'Missing Active');
    $this->pdo?->prepare('INSERT INTO `squads` (`user_id`, `name`) VALUES (?, ?)')->execute([$userId, 'Orphaned Selection']);
    $before = $this->revision($userId);
    $_SESSION['user_id'] = $userId;

    $response = $this->invoke(fn() => (new GameBootstrapController())->bootstrap());

    $this->assertSame(500, $response['status']);
    $this->assertSame('player_state_integrity_error', $response['body']['error']['code'] ?? null);
    $this->assertSame($before, $this->revision($userId));
    $this->assertSame('', (string)$this->scalar('SELECT COALESCE(`active_squad_id`, \'\') FROM `user_state` WHERE `user_id` = ?', [$userId]));
  }

  /** @param array<int,int> $occupied @return array{name:string,formation:array<int,?string>} */
  private function configuration(string $name, array $occupied = []): array
  {
    $formation = array_fill(0, 9, null);
    foreach ($occupied as $position => $unitId) $formation[$position] = (string)$unitId;
    return ['name' => $name, 'formation' => $formation];
  }

  /** @param array<string,mixed>|null $body @return array{status:int,body:array<string,mixed>} */
  private function command(int $userId, string $method, ?string $squadId = null, ?array $body = null, ?string $key = null): array
  {
    $_SESSION['user_id'] = $userId;
    $_SESSION['csrf_token'] = 'squad-csrf';
    $_SERVER['HTTP_X_CSRF_TOKEN'] = 'squad-csrf';
    if ($key === null) unset($_SERVER['HTTP_IDEMPOTENCY_KEY']); else $_SERVER['HTTP_IDEMPOTENCY_KEY'] = $key;
    if ($body !== null) $this->setJsonBody($body);
    $controller = new WarbandController();
    return $this->invoke(fn() => $squadId === null ? $controller->{$method}() : $controller->{$method}($squadId));
  }

  private function createAccount(string $email, string $name): int
  {
    $services = ControllerServiceFactory::buildContentAware($this->pdo);
    $id = $services['accountCreationService']->createLocal($email, password_hash('test-password', PASSWORD_DEFAULT), $name);
    $this->trackUserId($id);
    return $id;
  }

  private function insertUnit(int $userId, string $type, string $kin, string $name, string $status = 'active'): int
  {
    $stmt = $this->pdo?->prepare('INSERT INTO `unit_instances` (`user_id`, `unit_type_id`, `kin_id`, `display_name`, `lifecycle_status`) VALUES (?, ?, ?, ?, ?)');
    $stmt?->execute([$userId, $type, $kin, $name, $status]);
    return (int)$this->pdo?->lastInsertId();
  }

  private function revision(int $userId): int { return (int)$this->scalar('SELECT `player_revision` FROM `user_state` WHERE `user_id` = ?', [$userId]); }
  private function squadCount(int $userId): int { return (int)$this->scalar('SELECT COUNT(*) FROM `squads` WHERE `user_id` = ?', [$userId]); }
}
