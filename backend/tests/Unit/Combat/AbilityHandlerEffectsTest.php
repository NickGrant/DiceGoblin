<?php
declare(strict_types=1);

namespace DiceGoblins\Tests\Unit\Combat;

require_once __DIR__ . '/../../../src/Combat/Engine/placeholders.php';

use DiceGoblins\Combat\Abilities\AbilityTarget;
use DiceGoblins\Combat\Abilities\Handlers\Active\BasicAttackMelee;
use DiceGoblins\Combat\Abilities\Handlers\Active\ShieldUp;
use DiceGoblins\Combat\Abilities\Handlers\Active\SleepDart;
use DiceGoblins\Combat\Abilities\Handlers\Passive\Sharpshooter;
use DiceGoblins\Combat\Abilities\Handlers\Passive\ThickHide;
use DiceGoblins\Combat\Abilities\Handlers\Passive\ToxicTraining;
use DiceGoblins\Combat\Engine\CombatContext;
use DiceGoblins\Combat\Engine\DerivedStats;
use DiceGoblins\Combat\Engine\UnitRef;
use PHPUnit\Framework\TestCase;

final class AbilityHandlerEffectsTest extends TestCase
{
  public function testBasicAttackMeleeDealsDamageToChosenEnemyTarget(): void
  {
    $ctx = new SpyCombatContext(new UnitRef('enemy-1'));
    $actor = new UnitRef('ally-1');

    (new BasicAttackMelee())->resolve($ctx, $actor, [
      'target' => 'enemy_front_prefer',
      'params' => ['power_ratio' => 1.25],
    ]);

    $this->assertSame(AbilityTarget::EnemyFrontPrefer, $ctx->chosenTargetType);
    $this->assertNotNull($ctx->damageCall);
    $this->assertSame('ally-1', $ctx->damageCall['source']);
    $this->assertSame('enemy-1', $ctx->damageCall['target']);
    $this->assertSame(1.25, $ctx->damageCall['ratio']);
    $this->assertSame('basic_attack_melee', $ctx->damageCall['meta']['ability_id'] ?? null);
    $this->assertSame('melee', $ctx->damageCall['meta']['tag'] ?? null);
  }

  public function testShieldUpAppliesBolsteredStatusToActor(): void
  {
    $ctx = new SpyCombatContext(null);
    $actor = new UnitRef('ally-1');

    (new ShieldUp())->resolve($ctx, $actor, [
      'params' => ['bolster_defense_pct' => 0.5, 'duration_rounds' => 3],
    ]);

    $this->assertNotNull($ctx->statusCall);
    $this->assertSame('ally-1', $ctx->statusCall['target']);
    $this->assertSame('bolstered', $ctx->statusCall['status_id']);
    $this->assertSame(0.5, $ctx->statusCall['params']['defense_pct'] ?? null);
    $this->assertSame(3, $ctx->statusCall['params']['duration_rounds'] ?? null);
    $this->assertSame('shield_up', $ctx->statusCall['meta']['ability_id'] ?? null);
  }

  public function testSleepDartAppliesSleepStatusToChosenEnemy(): void
  {
    $ctx = new SpyCombatContext(new UnitRef('enemy-2'));
    $actor = new UnitRef('ally-2');

    (new SleepDart())->resolve($ctx, $actor, [
      'target' => 'enemy_random',
      'params' => ['duration_rounds' => 2],
    ]);

    $this->assertSame(AbilityTarget::EnemyRandom, $ctx->chosenTargetType);
    $this->assertNotNull($ctx->statusCall);
    $this->assertSame('enemy-2', $ctx->statusCall['target']);
    $this->assertSame('sleep', $ctx->statusCall['status_id']);
    $this->assertSame(2, $ctx->statusCall['params']['duration_rounds'] ?? null);
    $this->assertSame('sleep_dart', $ctx->statusCall['meta']['ability_id'] ?? null);
  }

  public function testPassiveHandlersModifyDerivedStatsAsExpected(): void
  {
    $unit = new UnitRef('ally-3');

    $thickHide = new ThickHide();
    $statsA = $thickHide->apply(new DerivedStats(10, 5, 20), $unit, ['params' => ['defense_flat' => 4]]);
    $this->assertSame(9, $statsA->defense);

    $sharpshooter = new Sharpshooter();
    $statsB = $sharpshooter->apply(new DerivedStats(10, 5, 20, ['ranged' => 1.0]), $unit, [
      'params' => ['ranged_damage_pct' => 0.2],
    ]);
    $this->assertSame(1.2, $statsB->damageMultipliers['ranged']);

    $toxicTraining = new ToxicTraining();
    $statsC = $toxicTraining->apply(new DerivedStats(10, 5, 20, [], ['poison' => 1.0]), $unit, [
      'params' => ['poison_damage_pct' => 0.3],
    ]);
    $this->assertSame(1.3, $statsC->statusPotency['poison']);
  }
}

final class SpyCombatContext extends CombatContext
{
  public ?AbilityTarget $chosenTargetType = null;
  /** @var array{source:string,target:string,ratio:float,meta:array<string,mixed>}|null */
  public ?array $damageCall = null;
  /** @var array{target:string,status_id:string,params:array<string,mixed>,meta:array<string,mixed>}|null */
  public ?array $statusCall = null;

  public function __construct(private readonly ?UnitRef $chosenTarget) {}

  public function chooseTarget(UnitRef $actor, AbilityTarget $target): ?UnitRef
  {
    $this->chosenTargetType = $target;
    return $this->chosenTarget;
  }

  public function dealDamage(UnitRef $source, UnitRef $target, float $powerRatio, array $meta = []): void
  {
    $this->damageCall = [
      'source' => $source->unitInstanceId,
      'target' => $target->unitInstanceId,
      'ratio' => $powerRatio,
      'meta' => $meta,
    ];
  }

  public function applyStatus(UnitRef $target, string $statusId, array $statusParams, array $meta = []): void
  {
    $this->statusCall = [
      'target' => $target->unitInstanceId,
      'status_id' => $statusId,
      'params' => $statusParams,
      'meta' => $meta,
    ];
  }
}
