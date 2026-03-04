<?php
declare(strict_types=1);

/**
 * File: C:\xampp\htdocs\dice-goblin\backend\src\Combat\Abilities\Handlers\Passive\ToxicTraining.php
 * Purpose: Project PHP module.
 */

namespace DiceGoblins\Combat\Abilities\Handlers\Passive;

use DiceGoblins\Combat\Abilities\Handlers\PassiveAbilityHandlerInterface;
use DiceGoblins\Combat\Engine\DerivedStats;
use DiceGoblins\Combat\Engine\UnitRef;

final class ToxicTraining implements PassiveAbilityHandlerInterface
{
    public function id(): string { return 'toxic_training'; }

    public function apply(DerivedStats $stats, UnitRef $unit, array $cfg): DerivedStats
    {
        $params = (array)($cfg['params'] ?? []);
        $pct = (float)($params['poison_damage_pct'] ?? 0.15);

        $current = (float)($stats->statusPotency['poison'] ?? 1.0);
        $stats->statusPotency['poison'] = $current * (1.0 + $pct);

        return $stats;
    }
}
