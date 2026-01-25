<?php
declare(strict_types=1);

namespace DiceGoblins\Combat\Abilities;

use InvalidArgumentException;

final readonly class AbilityDefinition
{
    /**
     * Stable dispatch id used for:
     * - handler lookup (server)
     * - catalog keys (client)
     * - unit type ability references (DB)
     */
    public string $abilityId;

    public AbilityType $type;

    // Active-only fields (null for passives)
    public ?int $speed;                 // 1..20
    public ?int $diceCost;              // >= 0
    public ?int $order;                 // >= 0
    public ?AbilityTarget $defaultTarget;

    // Optional display/catalog fields
    public string $displayName;
    public string $shortDesc;
    public ?string $iconKey;

    /** @var list<string> */
    public array $tags;

    /** @var array<string, mixed> */
    public array $defaultParams;

    private function __construct(
        string $abilityId,
        AbilityType $type,
        ?int $speed,
        ?int $diceCost,
        ?int $order,
        ?AbilityTarget $defaultTarget,
        string $displayName,
        string $shortDesc,
        ?string $iconKey,
        array $tags,
        array $defaultParams,
    ) {
        self::assertId($abilityId);
        self::assertTags($tags);

        if ($type === AbilityType::Active) {
            self::assertActiveFields($speed, $diceCost, $order, $defaultTarget);
        } else {
            self::assertPassiveFields($speed, $diceCost, $defaultTarget);
            // order is allowed for passives (stacking/derived-stat precedence), but optional
            if ($order !== null && $order < 0) {
                throw new InvalidArgumentException("Passive 'order' must be >= 0 (got {$order}).");
            }
        }

        $this->abilityId = $abilityId;
        $this->type = $type;
        $this->speed = $speed;
        $this->diceCost = $diceCost;
        $this->order = $order;
        $this->defaultTarget = $defaultTarget;

        $this->displayName = $displayName;
        $this->shortDesc = $shortDesc;
        $this->iconKey = $iconKey;

        $this->tags = array_values($tags);
        $this->defaultParams = $defaultParams;
    }

    /**
     * Factory: Active ability (tick-scheduled).
     */
    public static function active(
        string $abilityId,
        int $speed,
        int $diceCost,
        int $order,
        AbilityTarget $defaultTarget,
        string $displayName,
        string $shortDesc,
        ?string $iconKey = null,
        array $tags = [],
        array $defaultParams = [],
    ): self {
        return new self(
            abilityId: $abilityId,
            type: AbilityType::Active,
            speed: $speed,
            diceCost: $diceCost,
            order: $order,
            defaultTarget: $defaultTarget,
            displayName: $displayName,
            shortDesc: $shortDesc,
            iconKey: $iconKey,
            tags: $tags,
            defaultParams: $defaultParams,
        );
    }

    /**
     * Factory: Passive ability (always-on modifier).
     */
    public static function passive(
        string $abilityId,
        string $displayName,
        string $shortDesc,
        ?string $iconKey = null,
        ?int $order = 0,
        array $tags = [],
        array $defaultParams = [],
    ): self {
        return new self(
            abilityId: $abilityId,
            type: AbilityType::Passive,
            speed: null,
            diceCost: null,
            order: $order,
            defaultTarget: null,
            displayName: $displayName,
            shortDesc: $shortDesc,
            iconKey: $iconKey,
            tags: $tags,
            defaultParams: $defaultParams,
        );
    }

    /**
     * Payload intended for `/api/v1/abilities` or similar catalog endpoint.
     * No executable logic; safe for clients.
     *
     * @return array<string, mixed>
     */
    public function toCatalogArray(): array
    {
        $out = [
            'ability_id'   => $this->abilityId,
            'type'         => $this->type->value,
            'display_name' => $this->displayName,
            'short_desc'   => $this->shortDesc,
            'icon_key'     => $this->iconKey,
            'tags'         => $this->tags,
            'default_params' => $this->defaultParams,
        ];

        if ($this->type === AbilityType::Active) {
            $out['speed'] = $this->speed;
            $out['dice_cost'] = $this->diceCost;
            $out['order'] = $this->order;
            $out['default_target'] = $this->defaultTarget?->value;
        } else {
            // Passives may still expose order if you want consistent sorting on the client/debug UI.
            $out['order'] = $this->order;
        }

        return $out;
    }

    private static function assertId(string $id): void
    {
        if (!preg_match('/^[a-z][a-z0-9_]{2,63}$/', $id)) {
            throw new InvalidArgumentException("Invalid ability_id '{$id}'. Expected snake_case 3..64 chars.");
        }
    }

    private static function assertTags(array $tags): void
    {
        foreach ($tags as $t) {
            if (!is_string($t) || $t === '') {
                throw new InvalidArgumentException("Ability tag must be a non-empty string.");
            }
        }
    }

    private static function assertActiveFields(?int $speed, ?int $diceCost, ?int $order, ?AbilityTarget $target): void
    {
        if ($speed === null || $diceCost === null || $order === null || $target === null) {
            throw new InvalidArgumentException("Active abilities require speed, diceCost, order, and defaultTarget.");
        }
        if ($speed < 1 || $speed > 20) {
            throw new InvalidArgumentException("Active 'speed' must be 1..20 (got {$speed}).");
        }
        if ($diceCost < 0) {
            throw new InvalidArgumentException("Active 'diceCost' must be >= 0 (got {$diceCost}).");
        }
        if ($order < 0) {
            throw new InvalidArgumentException("Active 'order' must be >= 0 (got {$order}).");
        }
    }

    private static function assertPassiveFields(?int $speed, ?int $diceCost, ?AbilityTarget $target): void
    {
        if ($speed !== null || $diceCost !== null || $target !== null) {
            throw new InvalidArgumentException("Passive abilities must not define speed, diceCost, or defaultTarget.");
        }
    }
}
