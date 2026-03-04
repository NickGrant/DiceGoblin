<?php
declare(strict_types=1);

/**
 * File: C:\xampp\htdocs\dice-goblin\backend\src\Combat\Abilities\Handlers\HandlerInterface.php
 * Purpose: Project PHP module.
 */

namespace DiceGoblins\Combat\Abilities\Handlers;

use DiceGoblins\Combat\Engine\CombatContext;
use DiceGoblins\Combat\Engine\UnitRef;
use DiceGoblins\Combat\Engine\DerivedStats;

interface ActiveAbilityHandlerInterface
{
    public function id(): string;

    /**
     * Resolve one execution of this ability for an acting unit.
     * $cfg is the merged config (definition defaults + unit overrides).
     *
     * @param array<string,mixed> $cfg
     */
    public function resolve(CombatContext $ctx, UnitRef $actor, array $cfg): void;
}

interface PassiveAbilityHandlerInterface
{
    public function id(): string;

    /**
     * Apply passive modifications to a unit’s derived stats.
     * Called during derived-stat assembly.
     *
     * @param array<string,mixed> $cfg
     */
    public function apply(DerivedStats $stats, UnitRef $unit, array $cfg): DerivedStats;
}
