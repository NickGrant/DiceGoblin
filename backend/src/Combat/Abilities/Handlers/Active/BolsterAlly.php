<?php
declare(strict_types=1);

namespace DiceGoblins\Combat\Abilities\Handlers\Active;

use DiceGoblins\Combat\Abilities\AbilityTarget;
use DiceGoblins\Combat\Abilities\Handlers\ActiveAbilityHandlerInterface;
use DiceGoblins\Combat\Engine\CombatContext;
use DiceGoblins\Combat\Engine\UnitRef;

final class BolsterAlly implements ActiveAbilityHandlerInterface
{
    public function id(): string { return 'bolster_ally'; }

    public function resolve(CombatContext $ctx, UnitRef $actor, array $cfg): void
    {
        $targetEnum = AbilityTarget::from((string)$cfg['target']);
        $ally = $ctx->chooseTarget($actor, $targetEnum);
        if ($ally === null) return;

        $params = (array)($cfg['params'] ?? []);

        $ctx->applyStatus(
            target: $ally,
            statusId: 'bolstered',
            statusParams: [
                'defense_pct' => (float)($params['bolster_defense_pct'] ?? 0.25),
                'duration_rounds' => (int)($params['duration_rounds'] ?? 2),
            ],
            meta: ['ability_id' => $this->id()]
        );
    }
}
