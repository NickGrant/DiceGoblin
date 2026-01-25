<?php
declare(strict_types=1);

namespace DiceGoblins\Combat\Abilities\Handlers\Active;

use DiceGoblins\Combat\Abilities\AbilityTarget;
use DiceGoblins\Combat\Abilities\Handlers\ActiveAbilityHandlerInterface;
use DiceGoblins\Combat\Engine\CombatContext;
use DiceGoblins\Combat\Engine\UnitRef;

final class SleepDart implements ActiveAbilityHandlerInterface
{
    public function id(): string { return 'sleep_dart'; }

    public function resolve(CombatContext $ctx, UnitRef $actor, array $cfg): void
    {
        $targetEnum = AbilityTarget::from((string)$cfg['target']);
        $target = $ctx->chooseTarget($actor, $targetEnum);
        if ($target === null) return;

        $params = (array)($cfg['params'] ?? []);

        $ctx->applyStatus(
            target: $target,
            statusId: 'sleep',
            statusParams: [
                'duration_rounds' => (int)($params['duration_rounds'] ?? 2),
                // Your engine enforces canonical behavior: ends on damage, etc.
            ],
            meta: ['ability_id' => $this->id()]
        );
    }
}
