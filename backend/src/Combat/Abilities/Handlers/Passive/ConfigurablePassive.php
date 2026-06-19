<?php
declare(strict_types=1);

namespace DiceGoblins\Combat\Abilities\Handlers\Passive;

use DiceGoblins\Combat\Abilities\Handlers\PassiveAbilityHandlerInterface;
use DiceGoblins\Combat\Engine\DerivedStats;
use DiceGoblins\Combat\Engine\UnitRef;

final class ConfigurablePassive implements PassiveAbilityHandlerInterface
{
    public function __construct(
        private readonly string $abilityId,
    ) {
    }

    public function id(): string
    {
        return $this->abilityId;
    }

    public function apply(DerivedStats $stats, UnitRef $unit, array $cfg): DerivedStats
    {
        $params = is_array($cfg['params'] ?? null) ? $cfg['params'] : [];

        $stats->attack += (int)($params['attack_flat'] ?? 0);
        $stats->defense += (int)($params['defense_flat'] ?? 0);
        $stats->maxHp += (int)($params['max_hp_flat'] ?? 0);

        if (isset($params['melee_damage_pct'])) {
            $stats->damageMultipliers['melee'] = ($stats->damageMultipliers['melee'] ?? 1.0) + (float)$params['melee_damage_pct'];
        }
        if (isset($params['ranged_damage_pct'])) {
            $stats->damageMultipliers['ranged'] = ($stats->damageMultipliers['ranged'] ?? 1.0) + (float)$params['ranged_damage_pct'];
        }
        if (isset($params['status_potency_pct'])) {
            $stats->statusPotency['generic'] = ($stats->statusPotency['generic'] ?? 1.0) + (float)$params['status_potency_pct'];
        }
        if (isset($params['poison_damage_pct'])) {
            $stats->statusPotency['poison'] = ($stats->statusPotency['poison'] ?? 1.0) + (float)$params['poison_damage_pct'];
        }

        return $stats;
    }
}
