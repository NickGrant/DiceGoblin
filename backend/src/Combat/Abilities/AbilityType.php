<?php
declare(strict_types=1);

/**
 * File: C:\xampp\htdocs\dice-goblin\backend\src\Combat\Abilities\AbilityType.php
 * Purpose: Project PHP module.
 */

namespace DiceGoblins\Combat\Abilities;

enum AbilityType: string
{
    case Active = 'active';
    case Passive = 'passive';
}
