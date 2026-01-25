<?php
declare(strict_types=1);

namespace DiceGoblins\Combat\Abilities;

enum AbilityType: string
{
    case Active = 'active';
    case Passive = 'passive';
}
