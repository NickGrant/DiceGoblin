<?php
declare(strict_types=1);

namespace DiceGoblins\Combat\Abilities\Handlers;

use InvalidArgumentException;

final class HandlerRegistry
{
    /** @var array<string, ActiveAbilityHandlerInterface> */
    private array $activeById = [];

    /** @var array<string, PassiveAbilityHandlerInterface> */
    private array $passiveById = [];

    /**
     * @param list<ActiveAbilityHandlerInterface>  $actives
     * @param list<PassiveAbilityHandlerInterface> $passives
     */
    public function __construct(array $actives, array $passives)
    {
        foreach ($actives as $h) {
            $id = $h->id();
            if (isset($this->activeById[$id]) || isset($this->passiveById[$id])) {
                throw new InvalidArgumentException("Duplicate handler id '{$id}'.");
            }
            $this->activeById[$id] = $h;
        }

        foreach ($passives as $h) {
            $id = $h->id();
            if (isset($this->passiveById[$id]) || isset($this->activeById[$id])) {
                throw new InvalidArgumentException("Duplicate handler id '{$id}'.");
            }
            $this->passiveById[$id] = $h;
        }
    }

    public function hasActive(string $abilityId): bool
    {
        return isset($this->activeById[$abilityId]);
    }

    public function hasPassive(string $abilityId): bool
    {
        return isset($this->passiveById[$abilityId]);
    }

    public function getActive(string $abilityId): ActiveAbilityHandlerInterface
    {
        $h = $this->activeById[$abilityId] ?? null;
        if ($h === null) {
            throw new InvalidArgumentException("Missing active handler for ability_id '{$abilityId}'.");
        }
        return $h;
    }

    public function getPassive(string $abilityId): PassiveAbilityHandlerInterface
    {
        $h = $this->passiveById[$abilityId] ?? null;
        if ($h === null) {
            throw new InvalidArgumentException("Missing passive handler for ability_id '{$abilityId}'.");
        }
        return $h;
    }

    /**
     * Useful during boot to ensure every definition has a handler.
     *
     * @param list<string> $expectedActiveIds
     * @param list<string> $expectedPassiveIds
     */
    public function assertCoverage(array $expectedActiveIds, array $expectedPassiveIds): void
    {
        $missing = [];

        foreach ($expectedActiveIds as $id) {
            if (!$this->hasActive($id)) $missing[] = "active:{$id}";
        }
        foreach ($expectedPassiveIds as $id) {
            if (!$this->hasPassive($id)) $missing[] = "passive:{$id}";
        }

        if ($missing !== []) {
            throw new InvalidArgumentException(
                "HandlerRegistry missing handlers: " . implode(', ', $missing)
            );
        }
    }
}
