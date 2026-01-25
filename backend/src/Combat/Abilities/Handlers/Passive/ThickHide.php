<?php
declare(strict_types=1);

namespace DiceGoblins\Combat\Abilities\Handlers\Passive;

use DiceGoblins\Combat\Abilities\Handlers\PassiveAbilityHandlerInterface;
use DiceGoblins\Combat\Engine\DerivedStats;
use DiceGoblins\Combat\Engine\UnitRef;

final class ThickHide implements PassiveAbilityHandlerInterface
{
    public function id(): string { return 'thick_hide'; }

    public function apply(DerivedStats $stats, UnitRef $unit, array $cfg): DerivedStats
    {
        $params = (array)($cfg['params'] ?? []);
        $flat = (int)($params['defense_flat'] ?? 2);

        $stats->defense += $flat;
        return $stats;
    }
}
