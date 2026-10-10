<?php
declare(strict_types=1);

namespace DiceGoblins\Tests\Integration;

use DiceGoblins\Application\Commands\IdempotencyConflictException;
use DiceGoblins\Application\Commands\ProvisionWarbandFixtureCommand;
use DiceGoblins\Application\Commands\ReconstructKinCommand;
use DiceGoblins\Application\Commands\ReconstructionException;
use DiceGoblins\Application\Commands\ReconstructionIntegrityException;
use DiceGoblins\Application\NormalUnitCreationService;
use DiceGoblins\Application\UnitTypeAvailabilityPolicy;
use DiceGoblins\Content\ContentRegistry;
use DiceGoblins\Controllers\WrongMachineReadController;
use DiceGoblins\Controllers\WrongMachineReconstructionController;
use DiceGoblins\Controllers\ControllerServiceFactory;
use DiceGoblins\Repositories\IdempotencyRequestRepository;
use DiceGoblins\Repositories\PlayerStateRepository;
use DiceGoblins\Repositories\UserItemRepository;
use DiceGoblins\Repositories\UserUnlockRepository;
use DiceGoblins\Repositories\WarbandUnitRepository;
use DiceGoblins\Repositories\WarbandFixtureRepository;
use DiceGoblins\Support\ClientSafeInteger;
use DiceGoblins\Tests\Support\IntegrationTestCase;
use PDO;
use RuntimeException;

final class ReconstructKinCommandTest extends IntegrationTestCase
{
  protected function supportsVnextBaseline(): bool { return true; }

  /** @dataProvider recipes */
  public function testFirstRestorationCreatesOneEligibleKinUnitAndReadSwitchesToRepeat(string $recipe, string $kin, array $items): void
  {
    $userId = $this->user($items);
    $this->unlock($userId, 'unlock.unit_type.guardian');
    $request = $this->request($recipe, 'first_restoration', $items);
    $draws = 0;
    $command = $this->command(function (int $count) use (&$draws): int {
      $this->assertSame(2, $count); $draws++; return 1;
    });
    $before = $this->snapshot($userId);
    $receipt = $command->execute($userId, $request, 'reconstruct-first-' . $kin);
    $this->assertSame([$recipe, 'first_restoration', 'unit_type.guardian', $kin], [
      $receipt['recipe_id'], $receipt['mode'], $receipt['unit']['unit_type_id'], $receipt['unit']['kin_id'],
    ]);
    $this->assertSame(['raw_chaos', 5, 12, 7], [
      $receipt['spend']['currency_id'], $receipt['spend']['amount'],
      $receipt['spend']['balance_before'], $receipt['spend']['balance_after'],
    ]);
    $this->assertSame(['granted', 'unlock.' . $kin], [
      $receipt['kin_restoration']['outcome'], $receipt['kin_restoration']['unlock_id'],
    ]);
    $this->assertSame(8, $receipt['player_revision']);
    $this->assertSame([1, 0, 'active'], [$receipt['unit']['level'], $receipt['unit']['xp'], $receipt['unit']['lifecycle_status']]);
    foreach ($receipt['consumed_items'] as $consumed) {
      $this->assertSame(0, $consumed['owned_after']);
      $this->assertSame($items[$consumed['item_id']], $consumed['quantity']);
    }
    $this->assertSame(1, $draws);
    $after = $this->snapshot($userId);
    $this->assertCount(count($before['units']) + 1, $after['units']);
    $this->assertContains('unlock.' . $kin, $after['unlocks']);
    $this->assertNotSame($before, $after);
    $this->assertSame($receipt, $this->command(static function (): int {
      throw new RuntimeException('Replay must not draw.');
    })->execute($userId, $request, 'reconstruct-first-' . $kin));
    $this->assertSame($after, $this->snapshot($userId));
    $this->expectBusiness($userId, $request, 'stale-first-after-restoration', 'reconstruction_changed');
    $_SESSION['user_id'] = $userId;
    $read = $this->invoke(fn() => (new WrongMachineReadController())->catalog());
    $this->assertSame(200, $read['status']);
    $recipeRead = array_values(array_filter($read['body']['data']['recipes'],
      static fn(array $row): bool => $row['recipe_id'] === $recipe))[0];
    $this->assertSame(['repeat_reconstruction', true, false], [
      $recipeRead['mode'], $recipeRead['kin_restored'], $recipeRead['reconstructable'],
    ]);
    $this->assertSame([7, 8], [$read['body']['data']['raw_chaos'], $read['body']['data']['player_revision']]);
    $this->assertSame([0, 0], array_column($recipeRead['ingredients'], 'owned'));
  }

  /** @dataProvider recipes */
  public function testRepeatUsesExplicitEligibleTypeAndDoesNotGrantKinAgain(string $recipe, string $kin, array $items): void
  {
    $userId = $this->user($items);
    $this->unlock($userId, 'unlock.' . $kin);
    $request = $this->request($recipe, 'repeat_reconstruction', $items, 'unit_type.bruiser');
    $first = $this->command(static function (): int { throw new RuntimeException('Repeat must not draw.'); })
      ->execute($userId, $request, 'reconstruct-repeat-' . $kin);
    $this->assertSame(['repeat_reconstruction', 'already_owned', 'unit_type.bruiser', $kin], [
      $first['mode'], $first['kin_restoration']['outcome'], $first['unit']['unit_type_id'], $first['unit']['kin_id'],
    ]);
    $this->assertSame('1', (string)$this->scalar('SELECT COUNT(*) FROM `user_unlocks` WHERE `user_id` = ? AND `unlock_id` = ?',
      [$userId, 'unlock.' . $kin]));
    $snapshot = $this->snapshot($userId);
    $this->assertSame($first, $this->command()->execute($userId, $request, 'reconstruct-repeat-' . $kin));
    $this->assertSame($snapshot, $this->snapshot($userId));
  }

  /** @return array<string,array{string,string,array<string,int>}> */
  public static function recipes(): array
  {
    return [
      'Pig' => ['reconstruction_recipe.reconstruct_pig_kin', 'kin.pig',
        ['item.pig_ear' => 3, 'item.mudking_crown_fragment' => 1]],
      'Lizard' => ['reconstruction_recipe.reconstruct_lizard_kin', 'kin.lizard_kin',
        ['item.kobold_scale' => 3, 'item.chief_engineer_lens' => 1]],
    ];
  }

  public function testRejectedResourcesPrerequisitesModeAndTypeNeverMutate(): void
  {
    [$recipe, $kin, $items] = self::recipes()['Pig'];
    $userId = $this->user($items);
    $this->pdo?->prepare('DELETE FROM `user_unlocks` WHERE `user_id` = ? AND `unlock_id` = ?')
      ->execute([$userId, 'unlock.capability.wrong_machine_access']);
    $base = $this->request($recipe, 'first_restoration', $items);
    $cases = [
      ['missing prerequisite', $base, 'reconstruction_unavailable', static fn() => null],
      ['stale mode', $this->request($recipe, 'repeat_reconstruction', $items, 'unit_type.bruiser'),
        'reconstruction_changed', fn() => $this->unlock($userId, 'unlock.capability.wrong_machine_access')],
      ['stale cost', array_replace($base, ['expected_price' => ['currency_id' => 'raw_chaos', 'amount' => 6]]),
        'reconstruction_changed', static fn() => null],
      ['stale ingredients', array_replace($base, ['expected_ingredients' => [
        ['item_id' => 'item.pig_ear', 'quantity' => 4], ['item_id' => 'item.mudking_crown_fragment', 'quantity' => 1]]]),
        'reconstruction_changed', static fn() => null],
    ];
    foreach ($cases as [$name, $request, $code, $setup]) {
      $setup(); $before = $this->snapshot($userId);
      $this->expectBusiness($userId, $request, $name, $code);
      $this->assertSame($before, $this->snapshot($userId));
    }
    $this->pdo?->prepare('UPDATE `user_state` SET `raw_chaos` = 4 WHERE `user_id` = ?')->execute([$userId]);
    $before = $this->snapshot($userId);
    $this->expectBusiness($userId, $base, 'short-chaos', 'insufficient_raw_chaos');
    $this->assertSame($before, $this->snapshot($userId));
    $this->pdo?->prepare('UPDATE `user_state` SET `raw_chaos` = 12 WHERE `user_id` = ?')->execute([$userId]);
    foreach ($items as $itemId => $quantity) {
      $this->pdo?->prepare('UPDATE `user_items` SET `quantity` = ? WHERE `user_id` = ? AND `item_id` = ?')
        ->execute([$quantity - 1, $userId, $itemId]);
      $before = $this->snapshot($userId);
      $this->expectBusiness($userId, $base, 'short-' . $itemId, 'insufficient_ingredients');
      $this->assertSame($before, $this->snapshot($userId));
      $this->pdo?->prepare('UPDATE `user_items` SET `quantity` = ? WHERE `user_id` = ? AND `item_id` = ?')
        ->execute([$quantity, $userId, $itemId]);
    }
    $this->unlock($userId, 'unlock.kin.pig');
    foreach (['unit_type.guardian', 'unit_type.missing'] as $type) {
      $request = $this->request($recipe, 'repeat_reconstruction', $items, $type);
      $before = $this->snapshot($userId);
      $this->expectBusiness($userId, $request, 'locked-' . $type, 'unit_type_unavailable');
      $this->assertSame($before, $this->snapshot($userId));
    }
    $this->expectBusiness($userId, $this->request('reconstruction_recipe.missing', 'repeat_reconstruction',
      $items, 'unit_type.bruiser'), 'unknown-recipe', 'reconstruction_recipe_not_found');
  }

  public function testCrossPlayerItemsAndUnlocksCannotSatisfyReconstruction(): void
  {
    [$recipe, , $items] = self::recipes()['Lizard'];
    $owner = $this->user($items);
    $other = $this->user([]);
    $this->unlock($other, 'unlock.capability.wrong_machine_access');
    $this->unlock($other, 'unlock.unit_type.bruiser');
    $this->pdo?->prepare('DELETE FROM `user_unlocks` WHERE `user_id` = ? AND `unlock_id` = ?')
      ->execute([$owner, 'unlock.capability.wrong_machine_access']);
    $request = $this->request($recipe, 'first_restoration', $items);
    $before = $this->snapshot($other);
    $this->expectBusiness($other, $request, 'other-items', 'insufficient_ingredients');
    $this->assertSame($before, $this->snapshot($other));
    $this->expectBusiness($owner, $request, 'other-unlock', 'reconstruction_unavailable');
  }

  public function testIncoherentRevisionAndInventoryQuantityRejectWithoutMutation(): void
  {
    [$recipe, , $items] = self::recipes()['Pig']; $userId = $this->user($items);
    $request = $this->request($recipe, 'first_restoration', $items);
    $this->pdo?->prepare('UPDATE `user_state` SET `player_revision` = ? WHERE `user_id` = ?')
      ->execute([ClientSafeInteger::MAXIMUM, $userId]);
    $before = $this->snapshot($userId);
    try { $this->command()->execute($userId, $request, 'reconstruction-bad-revision'); $this->fail('Expected integrity rejection.'); }
    catch (ReconstructionIntegrityException) { $this->addToAssertionCount(1); }
    $this->assertSame($before, $this->snapshot($userId));
    $this->pdo?->prepare('UPDATE `user_state` SET `player_revision` = 7 WHERE `user_id` = ?')->execute([$userId]);
    $this->pdo?->prepare('UPDATE `user_items` SET `quantity` = ? WHERE `user_id` = ? AND `item_id` = ?')
      ->execute([ClientSafeInteger::MAXIMUM + 1, $userId, 'item.pig_ear']);
    $before = $this->snapshot($userId);
    try { $this->command()->execute($userId, $request, 'reconstruction-bad-inventory'); $this->fail('Expected integrity rejection.'); }
    catch (ReconstructionIntegrityException) { $this->addToAssertionCount(1); }
    $this->assertSame($before, $this->snapshot($userId));
  }

  /** @dataProvider rollbackStages */
  public function testInjectedFailuresRollbackAllEffects(string $stage): void
  {
    [$recipe, , $items] = self::recipes()['Pig']; $userId = $this->user($items);
    $request = $this->request($recipe, 'first_restoration', $items);
    $before = $this->snapshot($userId);
    try {
      $this->command(null, static function (string $checkpoint) use ($stage): void {
        if ($checkpoint === $stage) throw new RuntimeException('Injected ' . $stage);
      })->execute($userId, $request, 'rollback-' . $stage);
      $this->fail('Expected rollback.');
    } catch (ReconstructionIntegrityException $e) {
      $this->assertSame('Injected ' . $stage, $e->getPrevious()?->getMessage());
    }
    $this->assertSame($before, $this->snapshot($userId));
    $this->assertSame('granted', $this->command()->execute($userId, $request, 'rollback-' . $stage)['kin_restoration']['outcome']);
  }

  /** @return array<string,array{string}> */
  public static function rollbackStages(): array
  { return ['debit' => ['after_debit'], 'unlock' => ['after_unlock'], 'unit' => ['after_unit_creation'], 'receipt' => ['before_commit']]; }

  public function testIdempotencyConflictAndReceiptFirstReplayAfterOwnershipChanges(): void
  {
    [$recipe, , $items] = self::recipes()['Pig']; $userId = $this->user($items);
    $request = $this->request($recipe, 'first_restoration', $items);
    $first = $this->command()->execute($userId, $request, 'stable-reconstruction-key');
    $before = $this->snapshot($userId);
    try {
      $this->command()->execute($userId, $this->request($recipe, 'repeat_reconstruction', $items, 'unit_type.bruiser'),
        'stable-reconstruction-key');
      $this->fail('Expected idempotency conflict.');
    } catch (IdempotencyConflictException) { $this->addToAssertionCount(1); }
    $this->assertSame($first, $this->command()->execute($userId, $request, 'stable-reconstruction-key'));
    $this->assertSame($before, $this->snapshot($userId));
  }

  public function testNewReconstructedUnitDoesNotJoinAnAlreadyActiveRun(): void
  {
    [$recipe, , $items] = self::recipes()['Pig']; $userId = $this->user($items);
    $content = ContentRegistry::load(dirname(__DIR__, 2) . '/content');
    (new ProvisionWarbandFixtureCommand($this->pdo, new WarbandFixtureRepository($this->pdo), $content))
      ->execute($userId);
    $services = ControllerServiceFactory::buildContentAware($this->pdo);
    $run = $services['startRunCommand']->execute($userId, ['region_id' => 'region.the_farm'], 'reconstruction-active-run');
    $runId = (int)$run['run']['id'];
    $participantCount = (int)$this->scalar('SELECT COUNT(*) FROM `run_unit_state` WHERE `run_id` = ?', [$runId]);
    $receipt = $this->command()->execute($userId, $this->request($recipe, 'first_restoration', $items),
      'reconstruction-while-running');
    $this->assertSame('kin.pig', $receipt['unit']['kin_id']);
    $this->assertSame($participantCount, (int)$this->scalar('SELECT COUNT(*) FROM `run_unit_state` WHERE `run_id` = ?', [$runId]));
    $this->assertSame('0', (string)$this->scalar('SELECT COUNT(*) FROM `run_unit_state` WHERE `run_id` = ? AND `unit_id` = ?',
      [$runId, (int)$receipt['unit']['id']]));
    $this->assertSame('active', $this->scalar('SELECT `status` FROM `runs` WHERE `id` = ?', [$runId]));
  }

  public function testEndpointRequiresAuthCsrfStrictBodyAndKey(): void
  {
    [$recipe, , $items] = self::recipes()['Pig'];
    $controller = new WrongMachineReconstructionController(); $request = $this->request($recipe, 'first_restoration', $items);
    $this->setJsonBody($request);
    $this->assertSame(401, $this->invoke(fn() => $controller->reconstruct())['status']);
    $userId = $this->user($items); $_SESSION['user_id'] = $userId;
    $_SESSION['csrf_token'] = 'csrf'; $_SERVER['HTTP_X_CSRF_TOKEN'] = 'bad';
    $this->setJsonBody($request);
    $this->assertSame(403, $this->invoke(fn() => $controller->reconstruct())['status']);
    $_SERVER['HTTP_X_CSRF_TOKEN'] = 'csrf';
    foreach ([[], array_replace($request, ['unit_type_id' => 'unit_type.bruiser']),
      $this->request($recipe, 'repeat_reconstruction', $items, 'invalid') ] as $bad) {
      $_SERVER['HTTP_IDEMPOTENCY_KEY'] = 'invalid-reconstruction-' . count($bad);
      $this->setJsonBody($bad);
      $response = $this->invoke(fn() => $controller->reconstruct());
      $this->assertSame([422, 'invalid_reconstruction'], [$response['status'], $response['body']['error']['code']]);
    }
    $_SERVER['HTTP_IDEMPOTENCY_KEY'] = 'missing-repeat-type';
    $this->setJsonBody($this->request($recipe, 'repeat_reconstruction', $items));
    $response = $this->invoke(fn() => $controller->reconstruct());
    $this->assertSame([422, 'invalid_reconstruction'], [$response['status'], $response['body']['error']['code']]);
    unset($_SERVER['HTTP_IDEMPOTENCY_KEY']); $this->setJsonBody($request);
    $response = $this->invoke(fn() => $controller->reconstruct());
    $this->assertSame([400, 'idempotency_key_invalid'], [$response['status'], $response['body']['error']['code']]);
    $_SERVER['HTTP_IDEMPOTENCY_KEY'] = 'api-reconstruction-key'; $this->setJsonBody($request);
    $response = $this->invoke(fn() => $controller->reconstruct());
    $this->assertSame(200, $response['status'], json_encode($response['body']));
    $this->assertSame('kin.pig', $response['body']['data']['unit']['kin_id']);
  }

  /** @param array<string,int> $items @return array<string,mixed> */
  private function request(string $recipe, string $mode, array $items, ?string $type = null): array
  {
    $ingredients = [];
    foreach ($items as $itemId => $quantity) $ingredients[] = ['item_id' => $itemId, 'quantity' => $quantity];
    $request = ['recipe_id' => $recipe, 'expected_mode' => $mode,
      'expected_price' => ['currency_id' => 'raw_chaos', 'amount' => 5], 'expected_ingredients' => $ingredients];
    if ($type !== null) $request['unit_type_id'] = $type;
    return $request;
  }

  private function command(?\Closure $choice = null, ?\Closure $checkpoint = null): ReconstructKinCommand
  {
    $content = ContentRegistry::load(dirname(__DIR__, 2) . '/content');
    return new ReconstructKinCommand($this->pdo, new PlayerStateRepository($this->pdo),
      new IdempotencyRequestRepository($this->pdo), new UserItemRepository($this->pdo),
      new UserUnlockRepository($this->pdo), new UnitTypeAvailabilityPolicy($content),
      new NormalUnitCreationService(new WarbandUnitRepository($this->pdo), $content), $content, $choice, $checkpoint);
  }

  /** @param array<string,int> $items */
  private function user(array $items): int
  {
    $this->pdo?->prepare('INSERT INTO `users` (`display_name`) VALUES (?)')->execute(['Reconstructor']);
    $id = (int)$this->pdo?->lastInsertId(); $this->trackUserId($id);
    $this->pdo?->prepare('INSERT INTO `user_state` (`user_id`, `raw_chaos`, `energy_current`, `player_revision`) VALUES (?, 12, 50, 7)')
      ->execute([$id]);
    $this->unlock($id, 'unlock.capability.wrong_machine_access');
    $this->unlock($id, 'unlock.unit_type.bruiser');
    foreach ($items as $itemId => $quantity) {
      $this->pdo?->prepare('INSERT INTO `user_items` (`user_id`, `item_id`, `quantity`) VALUES (?, ?, ?)')
        ->execute([$id, $itemId, $quantity]);
    }
    return $id;
  }

  private function unlock(int $userId, string $unlockId): void
  { (new UserUnlockRepository($this->pdo))->insertIfAbsent($userId, $unlockId); }

  /** @param array<string,mixed> $request */
  private function expectBusiness(int $userId, array $request, string $key, string $code): void
  {
    try { $this->command()->execute($userId, $request, 'reconstruction-' . str_replace(' ', '-', $key)); $this->fail('Expected rejection.'); }
    catch (ReconstructionException $e) { $this->assertSame($code, $e->errorCode); }
  }

  /** @return array<string,mixed> */
  private function snapshot(int $userId): array
  {
    return [
      'state' => $this->rows('SELECT `raw_chaos`, `player_revision` FROM `user_state` WHERE `user_id` = ?', [$userId]),
      'items' => $this->rows('SELECT `item_id`, `quantity` FROM `user_items` WHERE `user_id` = ? ORDER BY `item_id`', [$userId]),
      'unlocks' => (new UserUnlockRepository($this->pdo))->listIdsForUser($userId),
      'units' => $this->rows('SELECT `id`, `unit_type_id`, `kin_id`, `level`, `xp` FROM `unit_instances` WHERE `user_id` = ? ORDER BY `id`', [$userId]),
      'abilities' => $this->rows('SELECT ua.`unit_id`, ua.`ability_id` FROM `unit_abilities` ua
        JOIN `unit_instances` ui ON ui.`id` = ua.`unit_id` WHERE ui.`user_id` = ? ORDER BY ua.`unit_id`, ua.`ability_id`', [$userId]),
      'receipts' => $this->rows('SELECT `idempotency_key` FROM `idempotency_requests` WHERE `user_id` = ? ORDER BY `idempotency_key`', [$userId]),
    ];
  }

  /** @param list<int|string> $params @return list<array<string,mixed>> */
  private function rows(string $sql, array $params): array
  { $stmt = $this->pdo?->prepare($sql); $stmt?->execute($params); return $stmt?->fetchAll(PDO::FETCH_ASSOC) ?: []; }

}
