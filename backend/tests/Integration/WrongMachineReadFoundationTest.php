<?php
declare(strict_types=1);

namespace DiceGoblins\Tests\Integration;

use DiceGoblins\Controllers\WrongMachineReadController;
use DiceGoblins\Repositories\UserUnlockRepository;
use DiceGoblins\Tests\Support\IntegrationTestCase;

final class WrongMachineReadFoundationTest extends IntegrationTestCase
{
  protected function supportsVnextBaseline(): bool { return true; }

  public function testEndpointRequiresAuthentication(): void
  {
    $response = $this->invoke(fn() => (new WrongMachineReadController())->catalog());
    $this->assertSame(401, $response['status']);
    $this->assertSame('unauthorized', $response['body']['error']['code']);
  }

  public function testKinOwnershipPersistsPerPlayerInTheExistingUnlockTable(): void
  {
    $owner = $this->user('Kin Owner');
    $other = $this->user('Kin Other');
    $unlocks = new UserUnlockRepository($this->pdo);
    $this->assertTrue($unlocks->insertIfAbsent($owner, 'unlock.kin.pig'));
    $this->assertFalse($unlocks->insertIfAbsent($owner, 'unlock.kin.pig'));
    $this->assertTrue((new UserUnlockRepository($this->pdo))->owns($owner, 'unlock.kin.pig'));
    $this->assertFalse((new UserUnlockRepository($this->pdo))->owns($other, 'unlock.kin.pig'));
    $this->assertSame('1', (string)$this->scalar(
      'SELECT COUNT(*) FROM `user_unlocks` WHERE `user_id` = ? AND `unlock_id` = ?',
      [$owner, 'unlock.kin.pig']));
  }

  public function testReadDerivesPrerequisitesWalletIngredientsAndFirstRepeatModesForBothKin(): void
  {
    $owner = $this->user('Wrong Machine Owner');
    $other = $this->user('Wrong Machine Other');
    $_SESSION['user_id'] = $owner;
    $unlocks = new UserUnlockRepository($this->pdo);
    $unlocks->insertIfAbsent($owner, 'unlock.unit_type.bruiser');
    foreach (['pig_ear' => 3, 'mudking_crown_fragment' => 1,
      'kobold_scale' => 3, 'chief_engineer_lens' => 1] as $item => $quantity) {
      $this->item($owner, $item, $quantity);
    }
    $locked = $this->read();
    $this->assertSame(5, $locked['raw_chaos']);
    $this->assertSame(7, $locked['player_revision']);
    $this->assertCount(2, $locked['recipes']);
    foreach ($locked['recipes'] as $recipe) {
      $this->assertFalse($recipe['kin_restored']);
      $this->assertSame('first_restoration', $recipe['mode']);
      $this->assertSame('random_unlocked', $recipe['unit_type_selection']);
      $this->assertSame(['unit_type.bruiser'], $recipe['eligible_unit_type_ids']);
      $this->assertFalse($recipe['prerequisites_met']);
      $this->assertFalse($recipe['reconstructable']);
      $this->assertSame([3, 1], array_column($recipe['ingredients'], 'owned'));
    }
    $unlocks->insertIfAbsent($owner, 'unlock.capability.wrong_machine_access');
    $available = $this->read();
    foreach ($available['recipes'] as $recipe) {
      $this->assertTrue($recipe['prerequisites_met']);
      $this->assertTrue($recipe['reconstructable']);
    }
    $unlocks->insertIfAbsent($owner, 'unlock.kin.pig');
    $this->pdo?->exec("UPDATE `user_state` SET `raw_chaos` = 4 WHERE `user_id` = {$owner}");
    $after = array_column($this->read()['recipes'], null, 'kin_id');
    $this->assertTrue($after['kin.pig']['kin_restored']);
    $this->assertSame('repeat_reconstruction', $after['kin.pig']['mode']);
    $this->assertSame('chosen_unlocked', $after['kin.pig']['unit_type_selection']);
    $this->assertFalse($after['kin.pig']['reconstructable']);
    $this->assertSame('first_restoration', $after['kin.lizard_kin']['mode']);
    $this->assertFalse($after['kin.lizard_kin']['reconstructable']);
    $this->pdo?->exec("UPDATE `user_state` SET `raw_chaos` = 5 WHERE `user_id` = {$owner}");
    $this->pdo?->prepare('UPDATE `user_items` SET `quantity` = 2 WHERE `user_id` = ? AND `item_id` = ?')
      ->execute([$owner, 'item.kobold_scale']);
    $short = array_column($this->read()['recipes'], null, 'kin_id');
    $this->assertTrue($short['kin.pig']['reconstructable']);
    $this->assertFalse($short['kin.lizard_kin']['reconstructable']);
    $this->assertSame(2, $short['kin.lizard_kin']['ingredients'][0]['owned']);
    $_SESSION['user_id'] = $other;
    $otherRead = array_column($this->read()['recipes'], null, 'kin_id');
    $this->assertFalse($otherRead['kin.pig']['kin_restored']);
    $this->assertSame('first_restoration', $otherRead['kin.pig']['mode']);
    $this->assertSame(0, $otherRead['kin.pig']['ingredients'][0]['owned']);
    $this->assertFalse($otherRead['kin.pig']['reconstructable']);
  }

  /** @return array<string,mixed> */
  private function read(): array
  {
    $response = $this->invoke(fn() => (new WrongMachineReadController())->catalog());
    $this->assertSame(200, $response['status'], json_encode($response['body']));
    return $response['body']['data'];
  }

  private function item(int $userId, string $itemId, int $quantity): void
  {
    $statement = $this->pdo?->prepare('INSERT INTO `user_items` (`user_id`, `item_id`, `quantity`) VALUES (?, ?, ?)');
    $statement?->execute([$userId, 'item.' . $itemId, $quantity]);
  }

  private function user(string $name): int
  {
    $statement = $this->pdo?->prepare('INSERT INTO `users` (`display_name`) VALUES (?)');
    $statement?->execute([$name]);
    $id = (int)$this->pdo?->lastInsertId();
    $this->trackUserId($id);
    $this->pdo?->prepare('INSERT INTO `user_state` (`user_id`, `raw_chaos`, `energy_current`, `player_revision`) VALUES (?, 5, 50, 7)')->execute([$id]);
    return $id;
  }
}
