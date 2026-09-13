<?php
declare(strict_types=1);

namespace DiceGoblins\Tests\Integration;

use DiceGoblins\Application\Commands\ProvisionWarbandFixtureCommand;
use DiceGoblins\Content\ContentRegistry;
use DiceGoblins\Controllers\ControllerServiceFactory;
use DiceGoblins\Controllers\RunController;
use DiceGoblins\Controllers\WarbandController;
use DiceGoblins\Repositories\WarbandFixtureRepository;
use DiceGoblins\Tests\Support\IntegrationTestCase;

final class ActiveRunWarbandLockTest extends IntegrationTestCase
{
  protected function supportsVnextBaseline(): bool { return true; }

  public function testActiveRunLocksOnlyParticipatingCombatConfiguration(): void
  {
    [$userId, $fixture] = $this->fixtureAccount('locks');
    $activeSquadId = (int)$fixture['active_squad_id'];
    $participant = (string)$fixture['unit_ids']['bruiser'];
    $nonParticipant = (string)$fixture['unit_ids']['saboteur'];
    $this->pdo?->prepare('DELETE FROM `squad_units` WHERE `squad_id` = ? AND `unit_id` = ?')
      ->execute([$activeSquadId, (int)$nonParticipant]);
    $otherSquad = $this->insertSquad($userId, 'Other');
    $disposableSquad = $this->insertSquad($userId, 'Disposable');
    $formation = $this->formation($activeSquadId);
    $runId = $this->start($userId);
    $revision = $this->revision($userId);

    $blocked = [
      $this->squadCommand($userId, 'activateSquad', (string)$otherSquad),
      $this->squadCommand($userId, 'updateSquad', (string)$activeSquadId,
        ['name' => 'Changed Formation', 'formation' => array_reverse($formation)]),
      $this->squadCommand($userId, 'deleteSquad', (string)$activeSquadId),
      $this->unitCommand($userId, 'replaceUnitLoadout', $participant, [
        'abilities' => [['ability_id' => 'ability.basic_attack_melee', 'dice_instance_ids' => [$fixture['dice_ids']['bruiser_basic']]],
      ]]),
    ];
    foreach ($blocked as $response) {
      $this->assertSame(409, $response['status'], json_encode($response['body']));
      $this->assertSame('active_run_configuration_locked', $response['body']['error']['code'] ?? null);
    }
    $this->assertSame($revision, $this->revision($userId));

    $reactivate = $this->squadCommand($userId, 'activateSquad', (string)$activeSquadId);
    $this->assertSame(200, $reactivate['status']);
    $this->assertSame($revision, $reactivate['body']['data']['player_revision'] ?? null);

    $renameSquad = $this->squadCommand($userId, 'updateSquad', (string)$activeSquadId,
      ['name' => 'Renamed Raiders', 'formation' => $formation]);
    $this->assertSame(200, $renameSquad['status'], json_encode($renameSquad['body']));
    $this->assertSame('Renamed Raiders', $renameSquad['body']['data']['squad']['name'] ?? null);

    $renameUnit = $this->unitCommand($userId, 'renameUnit', $participant, ['name' => 'Run Hero']);
    $this->assertSame(200, $renameUnit['status'], json_encode($renameUnit['body']));

    $nonParticipantLoadout = $this->unitCommand($userId, 'replaceUnitLoadout', $nonParticipant, ['abilities' => [
      ['ability_id' => 'ability.basic_attack_ranged', 'dice_instance_ids' => [$fixture['dice_ids']['saboteur_sleep_a']]],
      ['ability_id' => 'ability.sleep_dart', 'dice_instance_ids' => [$fixture['dice_ids']['saboteur_basic'], $fixture['dice_ids']['saboteur_sleep_b']]],
    ]]);
    $this->assertSame(200, $nonParticipantLoadout['status'], json_encode($nonParticipantLoadout['body']));

    $otherUpdate = $this->squadCommand($userId, 'updateSquad', (string)$disposableSquad,
      ['name' => 'Disposable Renamed', 'formation' => array_fill(0, 9, null)]);
    $this->assertSame(200, $otherUpdate['status'], json_encode($otherUpdate['body']));
    $otherDelete = $this->squadCommand($userId, 'deleteSquad', (string)$disposableSquad);
    $this->assertSame(200, $otherDelete['status'], json_encode($otherDelete['body']));

    $abandon = $this->abandon($userId, $runId);
    $this->assertSame(200, $abandon['status'], json_encode($abandon['body']));
    $activateAfter = $this->squadCommand($userId, 'activateSquad', (string)$otherSquad);
    $this->assertSame(200, $activateAfter['status'], json_encode($activateAfter['body']));
    $this->assertSame((string)$otherSquad, $activateAfter['body']['data']['active_squad_id'] ?? null);
  }

  public function testParticipatingSquadCannotBeDeletedWhenItIsTheOnlySquad(): void
  {
    [$userId, $fixture] = $this->fixtureAccount('last-squad');
    $this->start($userId);
    $before = $this->revision($userId);
    $response = $this->squadCommand($userId, 'deleteSquad', $fixture['active_squad_id']);
    $this->assertSame(409, $response['status']);
    $this->assertSame('active_run_configuration_locked', $response['body']['error']['code'] ?? null);
    $this->assertSame($before, $this->revision($userId));
  }

  /** @return array{0:int,1:array<string,mixed>} */
  private function fixtureAccount(string $token): array
  {
    $services = ControllerServiceFactory::buildContentAware($this->pdo);
    $userId = $services['accountCreationService']->createLocal($token . '-' . bin2hex(random_bytes(3)) . '@example.test',
      password_hash('test-password', PASSWORD_DEFAULT), 'Warband Locker');
    $this->trackUserId($userId);
    $fixture = (new ProvisionWarbandFixtureCommand($this->pdo, new WarbandFixtureRepository($this->pdo),
      ContentRegistry::load(dirname(__DIR__, 2) . '/content')))->execute($userId);
    return [$userId, $fixture];
  }

  private function insertSquad(int $userId, string $name): int
  {
    $stmt = $this->pdo?->prepare('INSERT INTO `squads` (`user_id`, `name`) VALUES (?, ?)');
    $stmt?->execute([$userId, $name]);
    return (int)$this->pdo?->lastInsertId();
  }

  /** @return list<?string> */
  private function formation(int $squadId): array
  {
    $formation = array_fill(0, 9, null);
    $stmt = $this->pdo?->prepare('SELECT `unit_id`, `position` FROM `squad_units` WHERE `squad_id` = ?');
    $stmt?->execute([$squadId]);
    foreach ($stmt?->fetchAll() ?: [] as $row) $formation[(int)$row['position']] = (string)$row['unit_id'];
    return $formation;
  }

  private function start(int $userId): int
  {
    $_SESSION['user_id'] = $userId; $_SESSION['csrf_token'] = 'csrf'; $_SERVER['HTTP_X_CSRF_TOKEN'] = 'csrf';
    $_SERVER['HTTP_IDEMPOTENCY_KEY'] = 'start-' . bin2hex(random_bytes(8));
    $this->setJsonBody(['region_id' => 'region.the_farm']);
    $response = $this->invoke(fn() => (new RunController())->start());
    $this->assertSame(200, $response['status'], json_encode($response['body']));
    return (int)$response['body']['data']['run']['id'];
  }

  /** @param array<string,mixed>|null $body @return array{status:int,body:array<string,mixed>} */
  private function squadCommand(int $userId, string $method, string $squadId, ?array $body = null): array
  {
    $_SESSION['user_id'] = $userId; $_SESSION['csrf_token'] = 'csrf'; $_SERVER['HTTP_X_CSRF_TOKEN'] = 'csrf';
    if ($body !== null) $this->setJsonBody($body);
    return $this->invoke(fn() => (new WarbandController())->{$method}($squadId));
  }

  /** @param array<string,mixed> $body @return array{status:int,body:array<string,mixed>} */
  private function unitCommand(int $userId, string $method, string $unitId, array $body): array
  {
    $_SESSION['user_id'] = $userId; $_SESSION['csrf_token'] = 'csrf'; $_SERVER['HTTP_X_CSRF_TOKEN'] = 'csrf';
    $this->setJsonBody($body);
    return $this->invoke(fn() => (new WarbandController())->{$method}($unitId));
  }

  /** @return array{status:int,body:array<string,mixed>} */
  private function abandon(int $userId, int $runId): array
  {
    $_SESSION['user_id'] = $userId; $_SESSION['csrf_token'] = 'csrf'; $_SERVER['HTTP_X_CSRF_TOKEN'] = 'csrf';
    return $this->invoke(fn() => (new RunController())->abandon((string)$runId));
  }

  private function revision(int $userId): int
  {
    return (int)$this->scalar('SELECT `player_revision` FROM `user_state` WHERE `user_id` = ?', [$userId]);
  }
}
