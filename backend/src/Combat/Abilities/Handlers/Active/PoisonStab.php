<?php
declare(strict_types=1);

/**
 * File: C:\xampp\htdocs\dice-goblin\backend\src\Combat\Abilities\Handlers\Active\PoisonStab.php
 * Purpose: Project PHP module.
 */

namespace DiceGoblins\Combat\Abilities\Handlers\Active;

use DiceGoblins\Combat\Abilities\AbilityTarget;
use DiceGoblins\Combat\Abilities\Handlers\ActiveAbilityHandlerInterface;
use DiceGoblins\Combat\Engine\CombatContext;
use DiceGoblins\Combat\Engine\UnitRef;

final class PoisonStab implements ActiveAbilityHandlerInterface
{
    public function id(): string { return 'poison_stab'; }

    public function resolve(CombatContext $ctx, UnitRef $actor, array $cfg): void
    {
        $targetEnum = AbilityTarget::from((string)$cfg['target']);
        $target = $ctx->chooseTarget($actor, $targetEnum);
        if ($target === null) return;

        $params = (array)($cfg['params'] ?? []);

        $ctx->dealDamage(
            $actor,
            $target,
            (float)($params['power_ratio'] ?? 0.6),
            ['ability_id' => $this->id(), 'tag' => 'melee_poison']
        );

        $ctx->applyStatus(
            target: $target,
            statusId: 'poison',
            statusParams: [
                'poison_damage_ratio' => (float)($params['poison_damage_ratio'] ?? 0.2),
                'status_speed' => (int)($params['status_speed'] ?? 5),
                'duration_rounds' => (int)($params['duration_rounds'] ?? 3),
            ],
            meta: ['ability_id' => $this->id()]
        );
    }
}
