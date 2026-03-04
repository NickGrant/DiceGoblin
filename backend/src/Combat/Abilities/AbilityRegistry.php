<?php
declare(strict_types=1);

/**
 * File: C:\xampp\htdocs\dice-goblin\backend\src\Combat\Abilities\AbilityRegistry.php
 * Purpose: Project PHP module.
 */

namespace DiceGoblins\Combat\Abilities;

use InvalidArgumentException;

final class AbilityRegistry
{
    /** @var array<string, AbilityDefinition> */
    private array $definitionsById;

    /**
     * Construct once (e.g., from a container) and reuse.
     * If you do not have a container yet, you can instantiate directly.
     */
    public function __construct()
    {
        $defs = self::buildDefinitions();

        // Safety: ensure unique IDs
        $byId = [];
        foreach ($defs as $def) {
            $id = $def->abilityId;
            if (isset($byId[$id])) {
                throw new InvalidArgumentException("Duplicate ability_id '{$id}' in AbilityRegistry.");
            }
            $byId[$id] = $def;
        }

        $this->definitionsById = $byId;
    }

    /**
     * @return array<string, AbilityDefinition>
     */
    public function all(): array
    {
        return $this->definitionsById;
    }

    public function has(string $abilityId): bool
    {
        return isset($this->definitionsById[$abilityId]);
    }

    public function get(string $abilityId): AbilityDefinition
    {
        $def = $this->definitionsById[$abilityId] ?? null;
        if ($def === null) {
            throw new InvalidArgumentException("Unknown ability_id '{$abilityId}'.");
        }
        return $def;
    }

    public function assertKnown(string $abilityId): void
    {
        if (!$this->has($abilityId)) {
            throw new InvalidArgumentException("Unknown ability_id '{$abilityId}'.");
        }
    }

    public function assertActive(string $abilityId): void
    {
        $def = $this->get($abilityId);
        if ($def->type !== AbilityType::Active) {
            throw new InvalidArgumentException("Ability '{$abilityId}' is not active.");
        }
    }

    public function assertPassive(string $abilityId): void
    {
        $def = $this->get($abilityId);
        if ($def->type !== AbilityType::Passive) {
            throw new InvalidArgumentException("Ability '{$abilityId}' is not passive.");
        }
    }

    /**
     * Catalog payload for clients. Keep it stable and additive.
     *
     * @return array{catalog_version:int, abilities:list<array<string,mixed>>}
     */
    public function toCatalogPayload(): array
    {
        // Stable order: sort by type, then id (deterministic for caching/debug)
        $defs = array_values($this->definitionsById);
        usort($defs, function (AbilityDefinition $a, AbilityDefinition $b): int {
            $t = strcmp($a->type->value, $b->type->value);
            if ($t !== 0) return $t;
            return strcmp($a->abilityId, $b->abilityId);
        });

        $abilities = array_map(
            static fn (AbilityDefinition $d) => $d->toCatalogArray(),
            $defs
        );

        return [
            'catalog_version' => 1,
            'abilities' => $abilities,
        ];
    }

    /**
     * Define your abilities here (code-first catalog).
     * This is where you add your first 12 definitions.
     *
     * @return list<AbilityDefinition>
     */
    private static function buildDefinitions(): array
    {
        return [
            // --- Actives ---
            AbilityDefinition::active(
                abilityId: 'basic_attack_melee',
                speed: 4,
                diceCost: 0,
                order: 10,
                defaultTarget: AbilityTarget::EnemyFrontPrefer,
                displayName: 'Basic Attack (Melee)',
                shortDesc: 'Deals standard damage to a front enemy.',
                iconKey: 'icon_ability_basic_attack_melee',
                tags: ['melee', 'damage', 'baseline'],
                defaultParams: ['power_ratio' => 1.0],
            ),
            AbilityDefinition::active(
                abilityId: 'basic_attack_ranged',
                speed: 4,
                diceCost: 0,
                order: 10,
                defaultTarget: AbilityTarget::EnemyBackPrefer,
                displayName: 'Basic Attack (Ranged)',
                shortDesc: 'Deals standard damage to a back enemy.',
                iconKey: 'icon_ability_basic_attack_ranged',
                tags: ['ranged', 'damage', 'baseline'],
                defaultParams: ['power_ratio' => 1.0],
            ),
            AbilityDefinition::active(
                abilityId: 'heavy_strike',
                speed: 8,
                diceCost: 1,
                order: 20,
                defaultTarget: AbilityTarget::EnemyFrontPrefer,
                displayName: 'Heavy Strike',
                shortDesc: 'A slower, harder-hitting melee attack.',
                iconKey: 'icon_ability_heavy_strike',
                tags: ['melee', 'damage', 'burst'],
                defaultParams: ['power_ratio' => 1.6],
            ),
            AbilityDefinition::active(
                abilityId: 'aimed_shot',
                speed: 8,
                diceCost: 1,
                order: 20,
                defaultTarget: AbilityTarget::EnemyBackPrefer,
                displayName: 'Aimed Shot',
                shortDesc: 'A slower, harder-hitting ranged attack.',
                iconKey: 'icon_ability_aimed_shot',
                tags: ['ranged', 'damage', 'burst'],
                defaultParams: ['power_ratio' => 1.6],
            ),
            AbilityDefinition::active(
                abilityId: 'shield_up',
                speed: 10,
                diceCost: 1,
                order: 5,
                defaultTarget: AbilityTarget::Self,
                displayName: 'Shield Up',
                shortDesc: 'Bolsters your defenses for a short time.',
                iconKey: 'icon_ability_shield_up',
                tags: ['tank', 'defense', 'bolster'],
                defaultParams: [
                    'bolster_defense_pct' => 0.25,
                    'duration_rounds' => 2
                ],
            ),
            AbilityDefinition::active(
                abilityId: 'bolster_ally',
                speed: 10,
                diceCost: 1,
                order: 5,
                defaultTarget: AbilityTarget::AllyLowestHpPct,
                displayName: 'Bolster Ally',
                shortDesc: 'Bolsters an ally’s defenses for a short time.',
                iconKey: 'icon_ability_bolster_ally',
                tags: ['support', 'defense', 'bolster'],
                defaultParams: [
                    'bolster_defense_pct' => 0.25,
                    'duration_rounds' => 2
                ],
            ),
            AbilityDefinition::active(
                abilityId: 'poison_stab',
                speed: 10,
                diceCost: 1,
                order: 15,
                defaultTarget: AbilityTarget::EnemyFrontPrefer,
                displayName: 'Poison Stab',
                shortDesc: 'A light strike that applies poison.',
                iconKey: 'icon_ability_poison_stab',
                tags: ['melee', 'debuff', 'poison'],
                defaultParams: [
                    'power_ratio' => 0.6,
                    'poison_damage_ratio' => 0.2,
                    'status_speed' => 5,
                    'duration_rounds' => 3
                ],
            ),
            AbilityDefinition::active(
                abilityId: 'poison_arrow',
                speed: 10,
                diceCost: 1,
                order: 15,
                defaultTarget: AbilityTarget::EnemyBackPrefer,
                displayName: 'Poison Arrow',
                shortDesc: 'A light shot that applies poison.',
                iconKey: 'icon_ability_poison_arrow',
                tags: ['ranged', 'debuff', 'poison'],
                defaultParams: [
                    'power_ratio' => 0.6,
                    'poison_damage_ratio' => 0.2,
                    'status_speed' => 5,
                    'duration_rounds' => 3
                ],
            ),
            AbilityDefinition::active(
                abilityId: 'sleep_dart',
                speed: 12,
                diceCost: 2,
                order: 25,
                defaultTarget: AbilityTarget::EnemyBackPrefer,
                displayName: 'Sleep Dart',
                shortDesc: 'Puts an enemy to sleep (ends on damage).',
                iconKey: 'icon_ability_sleep_dart',
                tags: ['control', 'sleep', 'debuff'],
                defaultParams: [
                    'duration_rounds' => 2
                ],
            ),

            // --- Passives ---
            AbilityDefinition::passive(
                abilityId: 'thick_hide',
                displayName: 'Thick Hide',
                shortDesc: 'Gain a flat defense bonus.',
                iconKey: 'icon_passive_thick_hide',
                order: 0,
                tags: ['tank', 'defense'],
                defaultParams: ['defense_flat' => 2],
            ),
            AbilityDefinition::passive(
                abilityId: 'sharpshooter',
                displayName: 'Sharpshooter',
                shortDesc: 'Deal increased damage with ranged attacks.',
                iconKey: 'icon_passive_sharpshooter',
                order: 0,
                tags: ['ranged', 'damage'],
                defaultParams: ['ranged_damage_pct' => 0.15],
            ),
            AbilityDefinition::passive(
                abilityId: 'toxic_training',
                displayName: 'Toxic Training',
                shortDesc: 'Your poison effects are more potent.',
                iconKey: 'icon_passive_toxic_training',
                order: 0,
                tags: ['poison', 'debuff'],
                defaultParams: ['poison_damage_pct' => 0.15],
            ),
        ];
    }
}
