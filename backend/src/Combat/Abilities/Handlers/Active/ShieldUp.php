<?php
declare(strict_types=1);

namespace DiceGoblins\Combat\Abilities\Handlers\Active;

use DiceGoblins\Combat\Abilities\Handlers\ActiveAbilityHandlerInterface;
use DiceGoblins\Combat\Engine\CombatContext;
use DiceGoblins\Combat\Engine\UnitRef;

final class ShieldUp implements ActiveAbilityHandlerInterface
{
    public function id(): string { return 'shield_up'; }

    public function resolve(CombatContext $ctx, UnitRef $actor, array $cfg): void
    {
        $params = (array)($cfg['params'] ?? []);

        $ctx->applyStatus(
            target: $actor,
            statusId: 'bolstered',
            statusParams: [
                'defense_pct' => (float)($params['bolster_defense_pct'] ?? 0.25),
                'duration_rounds' => (int)($params['duration_rounds'] ?? 2),
            ],
            meta: ['ability_id' => $this->id()]
        );
    }
}
