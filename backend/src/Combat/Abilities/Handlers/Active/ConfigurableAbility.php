<?php
declare(strict_types=1);

namespace DiceGoblins\Combat\Abilities\Handlers\Active;

use DiceGoblins\Combat\Abilities\AbilityTarget;
use DiceGoblins\Combat\Abilities\Handlers\ActiveAbilityHandlerInterface;
use DiceGoblins\Combat\Engine\CombatContext;
use DiceGoblins\Combat\Engine\UnitRef;

final class ConfigurableAbility implements ActiveAbilityHandlerInterface
{
    public function __construct(
        private readonly string $abilityId,
        private readonly ?string $defaultTag = null,
    ) {
    }

    public function id(): string
    {
        return $this->abilityId;
    }

    public function resolve(CombatContext $ctx, UnitRef $actor, array $cfg): void
    {
        $targetEnum = AbilityTarget::from((string)$cfg['target']);
        $target = $ctx->chooseTarget($actor, $targetEnum);
        if ($target === null) {
            return;
        }

        $params = is_array($cfg['params'] ?? null) ? $cfg['params'] : [];
        $ratio = array_key_exists('power_ratio', $params) ? (float)$params['power_ratio'] : null;
        if ($ratio !== null && $ratio > 0) {
            $ctx->dealDamage($actor, $target, $ratio, [
                'ability_id' => $this->abilityId,
                'tag' => $this->defaultTag,
            ]);
        }

        $statusId = trim((string)($params['status_id'] ?? ''));
        if ($statusId !== '') {
            $statusParams = $this->statusParamsFromAbilityParams($params);
            $ctx->applyStatus($target, $statusId, $statusParams, [
                'ability_id' => $this->abilityId,
                'tag' => $this->defaultTag,
            ]);
        }
    }

    /**
     * @param array<string,mixed> $params
     * @return array<string,mixed>
     */
    private function statusParamsFromAbilityParams(array $params): array
    {
        $statusParams = [];
        foreach ([
            'duration_rounds',
            'attack_reduction_pct',
            'defense_reduction_flat',
            'damage_taken_pct',
            'damage_taken_melee_pct',
            'poison_damage_ratio',
            'status_speed',
            'attack_pct',
            'lucky_bonus_flat',
            'guard_stack_cap',
            'guard_reduction_per_stack',
            'target_count',
        ] as $key) {
            if (array_key_exists($key, $params)) {
                $statusParams[$key] = $params[$key];
            }
        }

        return $statusParams;
    }
}
