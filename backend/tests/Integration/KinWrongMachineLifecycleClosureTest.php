<?php
declare(strict_types=1);

namespace DiceGoblins\Tests\Integration;

use DiceGoblins\Application\Commands\ProvisionWarbandFixtureCommand;
use DiceGoblins\Application\Queries\WrongMachineQuery;
use DiceGoblins\Application\UnitTypeAvailabilityPolicy;
use DiceGoblins\Combat\Vnext\CombatInput;
use DiceGoblins\Combat\Vnext\CombatResolver;
use DiceGoblins\Combat\Vnext\CombatResult;
use DiceGoblins\Combat\Vnext\PlaybackRecorder;
use DiceGoblins\Content\ContentRegistry;
use DiceGoblins\Controllers\ControllerServiceFactory;
use DiceGoblins\Repositories\PlayerStateRepository;
use DiceGoblins\Repositories\UserItemRepository;
use DiceGoblins\Repositories\UserUnlockRepository;
use DiceGoblins\Repositories\WarbandFixtureRepository;
use DiceGoblins\Tests\Support\IntegrationTestCase;
use PDO;

/** Production-composed rewards, wallet, reconstruction, and persisted read closure for both authored Kin. */
final class KinWrongMachineLifecycleClosureTest extends IntegrationTestCase
{
  protected function supportsVnextBaseline(): bool { return true; }

  public function testFarmAndMountainsRewardsFundBothFirstAndRepeatKinReconstructions(): void
  {
    $content = ContentRegistry::load(dirname(__DIR__, 2) . '/content');
    $services = ControllerServiceFactory::buildContentAware($this->pdo, null, $content, $this->victoryResolver());
    $userId = $services['accountCreationService']->createLocal(
      'kin-closure-' . bin2hex(random_bytes(4)) . '@example.test', password_hash('test-password', PASSWORD_DEFAULT), 'Restorer');
    $this->trackUserId($userId);
    (new ProvisionWarbandFixtureCommand($this->pdo, new WarbandFixtureRepository($this->pdo), $content))->execute($userId);
    $unlocks = new UserUnlockRepository($this->pdo);
    $this->assertTrue($unlocks->insertIfAbsent($userId, 'unlock.capability.wrong_machine_access'));
    $this->assertTrue($unlocks->insertIfAbsent($userId, 'unlock.unit_type.bruiser'));

    // The player starts with fixture Warband assets; Raw Chaos is earned through the production salvage command.
    $fixture = new WarbandFixtureRepository($this->pdo);
    for ($index = 0; $index < 5; $index++) {
      $dieId = $fixture->insertDie($userId, 6, 'dice_profile.wood_plain');
      $salvage = $services['diceLifecycleCommand']->salvage($userId, $dieId, 'kin-closure-salvage-' . $index);
      $this->assertSame(4, $salvage['raw_chaos_awarded']);
    }
    $this->assertSame(20, (int)$this->scalar('SELECT `raw_chaos` FROM `user_state` WHERE `user_id` = ?', [$userId]));

    $farmOne = $this->completeRun($services, $userId, 'region.the_farm', 'farm-one');
    $this->assertSame([['item_id' => 'item.mudking_crown_fragment', 'quantity' => 1, 'owned_after' => 1],
      ['item_id' => 'item.pig_ear', 'quantity' => 2, 'owned_after' => 3]], $farmOne[3]['item_grants']);
    $this->completeRun($services, $userId, 'region.the_farm', 'farm-two');
    $mountainsOne = $this->completeRun($services, $userId, 'region.mountains', 'mountains-one');
    $this->assertSame([['item_id' => 'item.kobold_scale', 'quantity' => 1, 'owned_after' => 1]],
      $mountainsOne[0]['item_grants']);
    $this->assertSame([['item_id' => 'item.chief_engineer_lens', 'quantity' => 1, 'owned_after' => 1]],
      $mountainsOne[5]['item_grants']);
    $this->completeRun($services, $userId, 'region.mountains', 'mountains-two');

    $read = $this->read($userId, $content);
    $this->assertSame(20, $read['raw_chaos']);
    $this->assertSame(['item.chief_engineer_lens' => 2, 'item.kobold_scale' => 6,
      'item.mudking_crown_fragment' => 2, 'item.pig_ear' => 6], $this->ownedItems($userId));
    $this->assertCount(2, $read['recipes']);
    foreach ($read['recipes'] as $recipe) {
      $this->assertSame(['first_restoration', 'random_unlocked', true, true],
        [$recipe['mode'], $recipe['unit_type_selection'], $recipe['prerequisites_met'], $recipe['reconstructable']]);
      $this->assertContains('unit_type.bruiser', $recipe['eligible_unit_type_ids']);
    }

    $initialUnitCount = (int)$this->scalar('SELECT COUNT(*) FROM `unit_instances` WHERE `user_id` = ?', [$userId]);
    foreach (['reconstruction_recipe.reconstruct_pig_kin' => 'kin.pig',
      'reconstruction_recipe.reconstruct_lizard_kin' => 'kin.lizard_kin'] as $recipeId => $kinId) {
      foreach (['first_restoration', 'repeat_reconstruction'] as $mode) {
        // Fresh query and command instances prove that each transition survived MySQL persistence.
        $before = $this->read($userId, $content);
        $recipe = $this->recipe($before, $recipeId);
        $this->assertSame([$mode, true], [$recipe['mode'], $recipe['reconstructable']]);
        $typeId = $mode === 'repeat_reconstruction' ? 'unit_type.bruiser' : null;
        $request = $this->request($recipe, $typeId);
        $key = 'kin-closure-' . $kinId . '-' . $mode;
        $receipt = ControllerServiceFactory::buildContentAware($this->pdo, null, $content)['reconstructKinCommand']
          ->execute($userId, $request, $key);
        $this->assertSame([$mode, $kinId, 1, 0, 'active'], [$receipt['mode'], $receipt['unit']['kin_id'],
          $receipt['unit']['level'], $receipt['unit']['xp'], $receipt['unit']['lifecycle_status']]);
        $this->assertContains($receipt['unit']['unit_type_id'], $recipe['eligible_unit_type_ids']);
        if ($typeId !== null) $this->assertSame($typeId, $receipt['unit']['unit_type_id']);
        $this->assertSame($mode === 'first_restoration' ? 'granted' : 'already_owned',
          $receipt['kin_restoration']['outcome']);
        $this->assertSame([$before['raw_chaos'] - 5, $before['player_revision'] + 1],
          [$receipt['spend']['balance_after'], $receipt['player_revision']]);
        $after = $this->read($userId, $content);
        $this->assertSame([$receipt['spend']['balance_after'], $receipt['player_revision']],
          [$after['raw_chaos'], $after['player_revision']]);
        $this->assertSame(['repeat_reconstruction', true],
          [$this->recipe($after, $recipeId)['mode'], $this->recipe($after, $recipeId)['kin_restored']]);
        $this->assertSame('1', (string)$this->scalar('SELECT COUNT(*) FROM `unit_instances` WHERE `id` = ? AND `user_id` = ?
          AND `kin_id` = ? AND `unit_type_id` = ? AND `level` = 1 AND `xp` = 0',
          [(int)$receipt['unit']['id'], $userId, $kinId, $receipt['unit']['unit_type_id']]));
        $this->assertSame('1', (string)$this->scalar('SELECT COUNT(*) FROM `user_unlocks` WHERE `user_id` = ? AND `unlock_id` = ?',
          [$userId, 'unlock.' . $kinId]));
        $persisted = [$this->ownedItems($userId), $this->unitIds($userId), $after];
        $replay = ControllerServiceFactory::buildContentAware($this->pdo, null, $content)['reconstructKinCommand']
          ->execute($userId, $request, $key);
        $this->assertSame($receipt, $replay);
        $this->assertSame($persisted, [$this->ownedItems($userId), $this->unitIds($userId), $this->read($userId, $content)]);
      }
    }
    $this->assertCount($initialUnitCount + 4, $this->unitIds($userId));
    $this->assertSame(0, $this->read($userId, $content)['raw_chaos']);
    $this->assertSame([], $this->ownedItems($userId));
    foreach ($this->read($userId, $content)['recipes'] as $recipe) {
      $this->assertSame([0, 0], array_column($recipe['ingredients'], 'owned'));
      $this->assertFalse($recipe['reconstructable']);
    }
  }

  /** @param array<string,mixed> $services @return list<array<string,mixed>> */
  private function completeRun(array $services, int $userId, string $regionId, string $key): array
  {
    $start = $services['startRunCommand']->execute($userId, ['region_id' => $regionId], 'kin-closure-start-' . $key);
    $current = $services['currentRunQuery']->execute($userId)['run'];
    $this->assertSame($regionId, $current['region_id']);
    $results = [];
    foreach ($current['nodes'] as $index => $node) {
      $results[] = $services['resolveRunNodeCommand']->execute($userId, (int)$start['run']['id'],
        (int)$node['id'], 'kin-closure-node-' . $key . '-' . $index);
    }
    $this->assertSame('completed', $results[count($results) - 1]['run']['status']);
    return $results;
  }

  /** @return array<string,mixed> */
  private function read(int $userId, ContentRegistry $content): array
  {
    return (new WrongMachineQuery(new PlayerStateRepository($this->pdo), new UserUnlockRepository($this->pdo),
      new UserItemRepository($this->pdo), new UnitTypeAvailabilityPolicy($content), $content))->execute($userId);
  }

  /** @param array<string,mixed> $read @return array<string,mixed> */
  private function recipe(array $read, string $id): array
  {
    foreach ($read['recipes'] as $recipe) if ($recipe['recipe_id'] === $id) return $recipe;
    $this->fail('Expected production recipe ' . $id);
  }

  /** @param array<string,mixed> $recipe @return array<string,mixed> */
  private function request(array $recipe, ?string $typeId): array
  {
    $ingredients = array_map(static fn(array $row): array => ['item_id' => $row['item_id'], 'quantity' => $row['quantity']],
      $recipe['ingredients']);
    usort($ingredients, static fn(array $a, array $b): int => strcmp($a['item_id'], $b['item_id']));
    return ['recipe_id' => $recipe['recipe_id'], 'expected_mode' => $recipe['mode'],
      'expected_price' => $recipe['price'], 'expected_ingredients' => $ingredients]
      + ($typeId !== null ? ['unit_type_id' => $typeId] : []);
  }

  /** @return array<string,int> */
  private function ownedItems(int $userId): array
  {
    $statement = $this->pdo?->prepare('SELECT `item_id`, `quantity` FROM `user_items` WHERE `user_id` = ? ORDER BY `item_id`');
    $statement?->execute([$userId]);
    return array_map('intval', $statement?->fetchAll(PDO::FETCH_KEY_PAIR) ?: []);
  }

  /** @return list<string> */
  private function unitIds(int $userId): array
  {
    $statement = $this->pdo?->prepare('SELECT `id` FROM `unit_instances` WHERE `user_id` = ? ORDER BY `id`');
    $statement?->execute([$userId]);
    return array_map('strval', $statement?->fetchAll(PDO::FETCH_COLUMN) ?: []);
  }

  private function victoryResolver(): CombatResolver
  {
    return new class implements CombatResolver {
      public function resolve(CombatInput $input): CombatResult {
        $terminal = [];
        foreach ($input->combatants as $key => $unit) {
          $hp = $unit['side'] === 'enemy' ? 0 : $unit['current_hp'];
          $terminal[] = ['key' => $key, 'side' => $unit['side'], 'current_hp' => $hp, 'max_hp' => $unit['max_hp'],
            'is_defeated' => $hp === 0, 'statuses' => $unit['statuses']];
        }
        $events = new PlaybackRecorder();
        $events->add('battle_started', 0, 0, ['combatant_keys' => array_keys($input->combatants)]);
        $events->add('battle_ended', 1, 1, ['outcome' => 'victory']);
        return new CombatResult('victory', 1, 1, $terminal, $events->events());
      }
    };
  }
}
