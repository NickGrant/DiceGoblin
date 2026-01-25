<?php
declare(strict_types=1);

namespace DiceGoblins\Combat\Abilities\Handlers\Active;

use DiceGoblins\Combat\Abilities\AbilityTarget;
use DiceGoblins\Combat\Abilities\Handlers\ActiveAbilityHandlerInterface;
use DiceGoblins\Combat\Engine\CombatContext;
use DiceGoblins\Combat\Engine\UnitRef;

final class BasicAttackMelee implements ActiveAbilityHandlerInterface
{
    public function id(): string { return 'basic_attack_melee'; }

    public function resolve(CombatContext $ctx, UnitRef $actor, array $cfg): void
    {
        $targetEnum = AbilityTarget::from((string)$cfg['target']);
        $target = $ctx->chooseTarget($actor, $targetEnum);
        if ($target === null) return;

        $params = (array)($cfg['params'] ?? []);
        $ratio = (float)($params['power_ratio'] ?? 1.0);

        $ctx->dealDamage($actor, $target, $ratio, ['ability_id' => $this->id(), 'tag' => 'melee']);
    }
}
