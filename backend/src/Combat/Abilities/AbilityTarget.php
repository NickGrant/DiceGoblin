<?php
declare(strict_types=1);

namespace DiceGoblins\Combat\Abilities;

enum AbilityTarget: string
{
    case Self = 'self';

    case AllyFrontPrefer = 'ally_front_prefer';
    case AllyBackPrefer = 'ally_back_prefer';
    case AllyLowestHpPct = 'ally_lowest_hp_pct';
    case AllyHighestAttack = 'ally_highest_attack';
    case AllyRandom = 'ally_random';

    case EnemyFrontPrefer = 'enemy_front_prefer';
    case EnemyBackPrefer = 'enemy_back_prefer';
    case EnemyLowestHp = 'enemy_lowest_hp';
    case EnemyHighestThreat = 'enemy_highest_threat';
    case EnemyRandom = 'enemy_random';
}
