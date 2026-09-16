<?php
declare(strict_types=1);

namespace DiceGoblins\Tests\Unit;

use DiceGoblins\Domain\Battles\FinalizedBattle;
use DiceGoblins\Persistence\BattleRecordCodec;
use DiceGoblins\Tests\Support\VnextBattleFixture;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class BattlePersistenceBoundaryTest extends TestCase
{
  public function testValidFinalizedBattlePreservesExactSnapshotsAndManifest(): void
  {
    $input = VnextBattleFixture::input();
    $result = VnextBattleFixture::result($input);
    $manifest = array_reverse(VnextBattleFixture::manifest());
    $battle = new FinalizedBattle(1, 1, $input, $manifest, $result);

    $this->assertSame($input, $battle->inputSnapshot);
    $this->assertSame($result, $battle->result);
    $this->assertSame($manifest, $battle->manifestArray());
  }

  /** @dataProvider invalidManifestProvider */
  public function testManifestRejectsInvalidCoverageAndIdentity(callable $mutate): void
  {
    $input = VnextBattleFixture::input();
    $manifest = VnextBattleFixture::manifest();
    $mutate($manifest);
    $this->expectException(InvalidArgumentException::class);
    new FinalizedBattle(1, 1, $input, $manifest, VnextBattleFixture::result($input));
  }

  public function invalidManifestProvider(): array
  {
    return [
      'missing key' => [static fn(array &$m) => array_pop($m)],
      'extra key' => [static fn(array &$m) => $m[] = array_merge($m[1], ['combatant_key' => 'extra_enemy'])],
      'duplicate key' => [static fn(array &$m) => $m[1]['combatant_key'] = $m[0]['combatant_key']],
      'wrong side' => [static fn(array &$m) => $m[0]['side'] = 'enemy'],
      'player without id' => [static fn(array &$m) => $m[0]['unit_id'] = null],
      'player enemy namespace' => [static fn(array &$m) => $m[0]['unit_type_id'] = 'enemy_unit_type.fake'],
      'enemy owned id' => [static fn(array &$m) => $m[1]['unit_id'] = 9],
      'enemy player type' => [static fn(array &$m) => $m[1]['unit_type_id'] = 'unit_type.bruiser'],
      'blank display' => [static fn(array &$m) => $m[1]['display_name'] = '  '],
      'blank art' => [static fn(array &$m) => $m[1]['art_key'] = ''],
    ];
  }

  /** @dataProvider invalidResultProvider */
  public function testResultRejectsMalformedPersistenceFacts(callable $mutate): void
  {
    $input = VnextBattleFixture::input();
    $result = VnextBattleFixture::result($input);
    $mutate($result);
    $this->expectException(InvalidArgumentException::class);
    new FinalizedBattle(1, 1, $input, VnextBattleFixture::manifest(), $result);
  }

  public function invalidResultProvider(): array
  {
    return [
      'version mismatch' => [static fn(array &$r) => $r['engine_version'] = 2],
      'outcome' => [static fn(array &$r) => $r['outcome'] = 'escaped'],
      'terminal key' => [static fn(array &$r) => $r['combatants'][0]['key'] = 'unknown'],
      'duplicate terminal key' => [static fn(array &$r) => $r['combatants'][1]['key'] = $r['combatants'][0]['key']],
      'missing terminal combatant' => [static fn(array &$r) => array_pop($r['combatants'])],
      'terminal side' => [static fn(array &$r) => $r['combatants'][1]['side'] = 'enemy'],
      'terminal max hp' => [static fn(array &$r) => $r['combatants'][0]['max_hp'] = 21],
      'terminal hp overflow' => [static fn(array &$r) => $r['combatants'][0]['current_hp'] = 21],
      'defeated mismatch' => [static fn(array &$r) => $r['combatants'][1]['is_defeated'] = true],
      'sequence gap' => [static fn(array &$r) => $r['events'][1]['sequence'] = 8],
      'event schema' => [static fn(array &$r) => $r['events'][1]['facts']['unexpected'] = true],
      'event reference' => [static fn(array &$r) => $r['events'][2]['facts']['actor_key'] = 'unknown'],
      'ending tick' => [static fn(array &$r) => $r['ending_tick'] = 2],
      'battle end outcome' => [static fn(array &$r) => $r['events'][count($r['events']) - 1]['facts']['outcome'] = 'defeat'],
      'prohibited timestamp' => [static fn(array &$r) => $r['events'][1]['facts']['timestamp'] = '2026-01-01'],
      'prohibited rewards' => [static fn(array &$r) => $r['events'][1]['facts']['rewards'] = []],
    ];
  }

  public function testUnsupportedStoredVersionsAreRejected(): void
  {
    $this->expectException(InvalidArgumentException::class);
    new FinalizedBattle(2, 1, VnextBattleFixture::input(), VnextBattleFixture::manifest(), VnextBattleFixture::result());
  }

  public function testManifestRejectsDuplicatePlayerUnitIdsAcrossDistinctCombatants(): void
  {
    $input = VnextBattleFixture::input();
    $second = $input['combatants'][0];
    $second['key'] = 'player_second';
    $second['position'] = ['x' => 0, 'y' => 0];
    $second['active_abilities'][0]['dice'][0]['key'] = 'player_second_d6';
    $input['combatants'][] = $second;
    $manifest = VnextBattleFixture::manifest();
    $manifest[] = array_merge($manifest[0], ['combatant_key' => 'player_second']);

    $this->expectException(InvalidArgumentException::class);
    new FinalizedBattle(1, 1, $input, $manifest, VnextBattleFixture::result($input));
  }

  public function testCodecRevalidatesHydratedJsonAndColumnVersions(): void
  {
    $codec = new BattleRecordCodec();
    $encoded = $codec->encode(VnextBattleFixture::battle());
    $row = array_merge(['id' => '7', 'run_id' => '8', 'run_node_id' => '9', 'created_at' => '2026-09-15 12:00:00'], $encoded);
    $hydrated = $codec->hydrate($row);
    $this->assertSame(VnextBattleFixture::input(), $hydrated->battle->inputSnapshot);
    $this->assertSame(VnextBattleFixture::result(), $hydrated->battle->result);

    $row['engine_version'] = 2;
    $this->expectException(RuntimeException::class);
    $codec->hydrate($row);
  }
}
