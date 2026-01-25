<?php
declare(strict_types=1);

namespace DiceGoblins\Combat\Engine;

use DiceGoblins\Combat\Abilities\AbilityTarget;

final class UnitRef
{
    public function __construct(public readonly string $unitInstanceId) {}
}

final class DerivedStats
{
    public function __construct(
        public int $attack,
        public int $defense,
        public int $maxHp,
        /** @var array<string,float> */
        public array $damageMultipliers = [],   // e.g. ['ranged' => 1.15]
        /** @var array<string,float> */
        public array $statusPotency = [],       // e.g. ['poison' => 1.15]
    ) {}
}

final class CombatContext
{
    public function chooseTarget(UnitRef $actor, AbilityTarget $target): ?UnitRef
    {
        // Engine decides; return null if no valid target.
        return null;
    }

    public function dealDamage(UnitRef $source, UnitRef $target, float $powerRatio, array $meta = []): void
    {
        // Engine computes final damage and logs events deterministically.
    }

    public function applyStatus(UnitRef $target, string $statusId, array $statusParams, array $meta = []): void
    {
        // Engine applies canonical status behavior and logs events.
    }
}
