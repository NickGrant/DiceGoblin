<?php
declare(strict_types=1);

namespace DiceGoblins\Combat\Abilities\Handlers\Passive;

use DiceGoblins\Combat\Abilities\Handlers\PassiveAbilityHandlerInterface;
use DiceGoblins\Combat\Engine\DerivedStats;
use DiceGoblins\Combat\Engine\UnitRef;

final class Sharpshooter implements PassiveAbilityHandlerInterface
{
    public function id(): string { return 'sharpshooter'; }

    public function apply(DerivedStats $stats, UnitRef $unit, array $cfg): DerivedStats
    {
        $params = (array)($cfg['params'] ?? []);
        $pct = (float)($params['ranged_damage_pct'] ?? 0.15);

        // Convention: store multipliers as 1.0-based
        $current = (float)($stats->damageMultipliers['ranged'] ?? 1.0);
        $stats->damageMultipliers['ranged'] = $current * (1.0 + $pct);

        return $stats;
    }
}
