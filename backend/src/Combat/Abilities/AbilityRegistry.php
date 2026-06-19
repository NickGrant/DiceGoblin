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
                diceCost: 1,
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
                diceCost: 1,
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
            AbilityDefinition::active(
                abilityId: 'skullcrack',
                speed: 8,
                diceCost: 1,
                order: 18,
                defaultTarget: AbilityTarget::EnemyFrontPrefer,
                displayName: 'Skullcrack',
                shortDesc: 'A heavy melee strike that weakens the target.',
                iconKey: 'icon_ability_skullcrack',
                tags: ['melee', 'damage', 'debuff'],
                defaultParams: [
                    'power_ratio' => 1.45,
                    'status_id' => 'cracked_skull',
                    'attack_reduction_pct' => 0.15,
                    'duration_rounds' => 2,
                ],
            ),
            AbilityDefinition::active(
                abilityId: 'desperate_swing',
                speed: 8,
                diceCost: 1,
                order: 18,
                defaultTarget: AbilityTarget::EnemyFrontPrefer,
                displayName: 'Desperate Swing',
                shortDesc: 'A melee strike that hits harder when wounded.',
                iconKey: 'icon_ability_desperate_swing',
                tags: ['melee', 'damage', 'wounded'],
                defaultParams: [
                    'power_ratio' => 1.25,
                    'self_wounded_bonus_pct' => 0.35,
                ],
            ),
            AbilityDefinition::active(
                abilityId: 'piercing_shot',
                speed: 8,
                diceCost: 1,
                order: 18,
                defaultTarget: AbilityTarget::EnemyBackPrefer,
                displayName: 'Piercing Shot',
                shortDesc: 'A ranged shot that ignores some defense.',
                iconKey: 'icon_ability_piercing_shot',
                tags: ['ranged', 'damage', 'piercing'],
                defaultParams: [
                    'power_ratio' => 1.5,
                    'ignore_defense_flat' => 2,
                ],
            ),
            AbilityDefinition::active(
                abilityId: 'mark_target',
                speed: 9,
                diceCost: 1,
                order: 16,
                defaultTarget: AbilityTarget::EnemyBackPrefer,
                displayName: 'Mark Target',
                shortDesc: 'Applies Marked with a light ranged hit.',
                iconKey: 'icon_ability_mark_target',
                tags: ['ranged', 'damage', 'debuff', 'mark'],
                defaultParams: [
                    'power_ratio' => 0.75,
                    'status_id' => 'marked',
                    'damage_taken_pct' => 0.15,
                    'duration_rounds' => 3,
                ],
            ),
            AbilityDefinition::active(
                abilityId: 'taunting_guard',
                speed: 9,
                diceCost: 1,
                order: 8,
                defaultTarget: AbilityTarget::Self,
                displayName: 'Taunting Guard',
                shortDesc: 'Taunts enemies and grants guard stacks.',
                iconKey: 'icon_ability_taunting_guard',
                tags: ['tank', 'support', 'guard'],
                defaultParams: [
                    'status_id' => 'taunting_guard',
                    'guard_stack_cap' => 4,
                    'guard_reduction_per_stack' => 1,
                    'duration_rounds' => 2,
                ],
            ),
            AbilityDefinition::active(
                abilityId: 'crack_armor',
                speed: 8,
                diceCost: 1,
                order: 17,
                defaultTarget: AbilityTarget::EnemyFrontPrefer,
                displayName: 'Crack Armor',
                shortDesc: 'A melee hit that lowers defense.',
                iconKey: 'icon_ability_crack_armor',
                tags: ['melee', 'damage', 'debuff'],
                defaultParams: [
                    'power_ratio' => 1.2,
                    'status_id' => 'cracked_armor',
                    'defense_reduction_flat' => 2,
                    'duration_rounds' => 2,
                ],
            ),
            AbilityDefinition::active(
                abilityId: 'warcry',
                speed: 9,
                diceCost: 1,
                order: 8,
                defaultTarget: AbilityTarget::AllyHighestAttack,
                displayName: 'Warcry',
                shortDesc: 'Bolsters an ally\'s attack.',
                iconKey: 'icon_ability_warcry',
                tags: ['support', 'buff'],
                defaultParams: [
                    'status_id' => 'warcry',
                    'attack_pct' => 0.18,
                    'duration_rounds' => 2,
                ],
            ),
            AbilityDefinition::active(
                abilityId: 'lucky_chant',
                speed: 9,
                diceCost: 1,
                order: 8,
                defaultTarget: AbilityTarget::AllyHighestAttack,
                displayName: 'Lucky Chant',
                shortDesc: 'Gives an ally a lucky boost to its next action.',
                iconKey: 'icon_ability_lucky_chant',
                tags: ['support', 'buff', 'luck'],
                defaultParams: [
                    'status_id' => 'lucky',
                    'lucky_bonus_flat' => 2,
                    'duration_rounds' => 2,
                ],
            ),
            AbilityDefinition::active(
                abilityId: 'disarming_shot',
                speed: 8,
                diceCost: 1,
                order: 17,
                defaultTarget: AbilityTarget::EnemyBackPrefer,
                displayName: 'Disarming Shot',
                shortDesc: 'A ranged attack that lowers enemy attack.',
                iconKey: 'icon_ability_disarming_shot',
                tags: ['ranged', 'damage', 'debuff'],
                defaultParams: [
                    'power_ratio' => 1.1,
                    'status_id' => 'disarmed',
                    'attack_reduction_pct' => 0.18,
                    'duration_rounds' => 2,
                ],
            ),
            AbilityDefinition::active(
                abilityId: 'poison_cloud',
                speed: 10,
                diceCost: 1,
                order: 16,
                defaultTarget: AbilityTarget::EnemyBackPrefer,
                displayName: 'Poison Cloud',
                shortDesc: 'Poisons multiple enemies.',
                iconKey: 'icon_ability_poison_cloud',
                tags: ['ranged', 'debuff', 'poison'],
                defaultParams: [
                    'power_ratio' => 0.5,
                    'status_id' => 'poison',
                    'poison_damage_ratio' => 0.25,
                    'status_speed' => 5,
                    'duration_rounds' => 3,
                    'target_count' => 2,
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
            AbilityDefinition::passive(
                abilityId: 'brawl_hardened',
                displayName: 'Brawl Hardened',
                shortDesc: 'Gain temporary damage-reducing stacks when attacked.',
                iconKey: 'icon_passive_brawl_hardened',
                order: 0,
                tags: ['tank', 'stacks'],
                defaultParams: ['stack_cap' => 3, 'damage_reduction_per_stack' => 1],
            ),
            AbilityDefinition::passive(
                abilityId: 'finisher',
                displayName: 'Finisher',
                shortDesc: 'Deal more damage to wounded enemies.',
                iconKey: 'icon_passive_finisher',
                order: 0,
                tags: ['melee', 'damage', 'wounded'],
                defaultParams: ['wounded_damage_pct' => 0.2],
            ),
            AbilityDefinition::passive(
                abilityId: 'menacing_follow_through',
                displayName: 'Menacing Follow-Through',
                shortDesc: 'Your active hits leave enemies vulnerable to melee damage.',
                iconKey: 'icon_passive_menacing_follow_through',
                order: 0,
                tags: ['melee', 'debuff'],
                defaultParams: ['applies_status_id' => 'menaced', 'damage_taken_melee_pct' => 0.12, 'duration_rounds' => 1],
            ),
            AbilityDefinition::passive(
                abilityId: 'no_mercy',
                displayName: 'No Mercy',
                shortDesc: 'Further increase damage against wounded enemies.',
                iconKey: 'icon_passive_no_mercy',
                order: 0,
                tags: ['melee', 'damage', 'wounded'],
                defaultParams: ['wounded_damage_pct' => 0.15],
            ),
            AbilityDefinition::passive(
                abilityId: 'brutal_suppression',
                displayName: 'Brutal Suppression',
                shortDesc: 'Active attacks apply extra attack reduction.',
                iconKey: 'icon_passive_brutal_suppression',
                order: 0,
                tags: ['debuff', 'melee'],
                defaultParams: ['attack_reduction_pct' => 0.08, 'duration_rounds' => 2],
            ),
            AbilityDefinition::passive(
                abilityId: 'counterpunch',
                displayName: 'Counterpunch',
                shortDesc: 'Once per round, retaliate after a melee hit.',
                iconKey: 'icon_passive_counterpunch',
                order: 0,
                tags: ['reaction', 'melee'],
                defaultParams: ['counter_ratio' => 0.7],
            ),
            AbilityDefinition::passive(
                abilityId: 'last_goblin_standing',
                displayName: 'Last Goblin Standing',
                shortDesc: 'Survive one otherwise fatal hit per battle.',
                iconKey: 'icon_passive_last_goblin_standing',
                order: 0,
                tags: ['survival'],
                defaultParams: ['survive_once' => true],
            ),
            AbilityDefinition::passive(
                abilityId: 'crowd_favorite',
                displayName: 'Crowd Favorite',
                shortDesc: 'Gain stacking damage after taking hits.',
                iconKey: 'icon_passive_crowd_favorite',
                order: 0,
                tags: ['stacks', 'damage'],
                defaultParams: ['stack_cap' => 5, 'damage_flat_per_stack' => 1],
            ),
            AbilityDefinition::passive(
                abilityId: 'patient_aim',
                displayName: 'Patient Aim',
                shortDesc: 'Aimed Shot prioritizes and punishes vulnerable targets.',
                iconKey: 'icon_passive_patient_aim',
                order: 0,
                tags: ['ranged', 'targeting'],
                defaultParams: ['aimed_shot_bonus_pct' => 0.18],
            ),
            AbilityDefinition::passive(
                abilityId: 'pick_your_mark',
                displayName: 'Pick Your Mark',
                shortDesc: 'Keep pressuring the same preferred target.',
                iconKey: 'icon_passive_pick_your_mark',
                order: 0,
                tags: ['ranged', 'targeting'],
                defaultParams: ['preferred_target_bonus_pct' => 0.1],
            ),
            AbilityDefinition::passive(
                abilityId: 'vantage_point',
                displayName: 'Vantage Point',
                shortDesc: 'Deal more ranged damage from safer backline positions.',
                iconKey: 'icon_passive_vantage_point',
                order: 0,
                tags: ['ranged', 'positioning'],
                defaultParams: ['ranged_damage_pct_per_row_ahead' => 0.08],
            ),
            AbilityDefinition::passive(
                abilityId: 'kill_lane',
                displayName: 'Kill Lane',
                shortDesc: 'Deal more damage into the enemy backline.',
                iconKey: 'icon_passive_kill_lane',
                order: 0,
                tags: ['ranged', 'backline'],
                defaultParams: ['backline_damage_pct' => 0.2],
            ),
            AbilityDefinition::passive(
                abilityId: 'armor_gap',
                displayName: 'Armor Gap',
                shortDesc: 'Ignore a small amount of enemy defense.',
                iconKey: 'icon_passive_armor_gap',
                order: 0,
                tags: ['ranged', 'piercing'],
                defaultParams: ['ignore_defense_flat' => 2],
            ),
            AbilityDefinition::passive(
                abilityId: 'treasure_sense',
                displayName: 'Treasure Sense',
                shortDesc: 'Can reveal a hidden treasure node during a run.',
                iconKey: 'icon_passive_treasure_sense',
                order: 0,
                tags: ['run', 'treasure'],
                defaultParams: ['reveal_chance' => 0.1, 'max_reveals_per_run' => 1],
            ),
            AbilityDefinition::passive(
                abilityId: 'exposed_weaknesses',
                displayName: 'Exposed Weaknesses',
                shortDesc: 'Deal more damage for each distinct debuff type on the target.',
                iconKey: 'icon_passive_exposed_weaknesses',
                order: 0,
                tags: ['damage', 'debuff'],
                defaultParams: ['bonus_damage_per_debuff_type' => 1, 'debuff_type_cap' => 3],
            ),
            AbilityDefinition::passive(
                abilityId: 'barbed_mark',
                displayName: 'Barbed Mark',
                shortDesc: 'Marked targets are also snared.',
                iconKey: 'icon_passive_barbed_mark',
                order: 0,
                tags: ['mark', 'debuff'],
                defaultParams: ['adds_status_id' => 'snared', 'duration_rounds' => 2],
            ),
            AbilityDefinition::passive(
                abilityId: 'bodyguard',
                displayName: 'Bodyguard',
                shortDesc: 'Protect the lowest-health ally.',
                iconKey: 'icon_passive_bodyguard',
                order: 0,
                tags: ['tank', 'support'],
                defaultParams: ['ally_damage_reduction_pct' => 0.15],
            ),
            AbilityDefinition::passive(
                abilityId: 'hold_the_line',
                displayName: 'Hold the Line',
                shortDesc: 'Gain more defense while anchored in the front row.',
                iconKey: 'icon_passive_hold_the_line',
                order: 0,
                tags: ['tank', 'positioning'],
                defaultParams: ['front_row_defense_flat' => 2, 'front_row_adjacent_bonus_flat' => 1],
            ),
            AbilityDefinition::passive(
                abilityId: 'shield_set',
                displayName: 'Shield Set',
                shortDesc: 'Gain defense stacks each time you are attacked.',
                iconKey: 'icon_passive_shield_set',
                order: 0,
                tags: ['tank', 'stacks'],
                defaultParams: ['stack_cap' => 3, 'defense_flat_per_stack' => 1],
            ),
            AbilityDefinition::passive(
                abilityId: 'unmoving',
                displayName: 'Unmoving',
                shortDesc: 'Taunting Guard redirects hit for less damage.',
                iconKey: 'icon_passive_unmoving',
                order: 0,
                tags: ['tank', 'guard'],
                defaultParams: ['taunt_damage_reduction_flat' => 2],
            ),
            AbilityDefinition::passive(
                abilityId: 'wall_of_scrap',
                displayName: 'Wall of Scrap',
                shortDesc: 'Shield Set can stack higher.',
                iconKey: 'icon_passive_wall_of_scrap',
                order: 0,
                tags: ['tank', 'stacks'],
                defaultParams: ['stack_cap_bonus' => 2],
            ),
            AbilityDefinition::passive(
                abilityId: 'find_the_gap',
                displayName: 'Find the Gap',
                shortDesc: 'Ignore a small amount of defense on attacks.',
                iconKey: 'icon_passive_find_the_gap',
                order: 0,
                tags: ['melee', 'piercing'],
                defaultParams: ['ignore_defense_flat' => 2],
            ),
            AbilityDefinition::passive(
                abilityId: 'shatter_plate',
                displayName: 'Shatter Plate',
                shortDesc: 'Crack Armor reduces more defense.',
                iconKey: 'icon_passive_shatter_plate',
                order: 0,
                tags: ['melee', 'debuff'],
                defaultParams: ['defense_reduction_flat_bonus' => 1],
            ),
            AbilityDefinition::passive(
                abilityId: 'break_open',
                displayName: 'Break Open',
                shortDesc: 'Allies deal more damage to Cracked Armor targets.',
                iconKey: 'icon_passive_break_open',
                order: 0,
                tags: ['team', 'debuff'],
                defaultParams: ['cracked_armor_bonus_damage_pct' => 0.12],
            ),
            AbilityDefinition::passive(
                abilityId: 'rally_rhythm',
                displayName: 'Rally Rhythm',
                shortDesc: 'Bolster effects also grant a small attack boost.',
                iconKey: 'icon_passive_rally_rhythm',
                order: 0,
                tags: ['support', 'buff'],
                defaultParams: ['bolster_attack_pct' => 0.1],
            ),
            AbilityDefinition::passive(
                abilityId: 'patch_job',
                displayName: 'Patch Job',
                shortDesc: 'Bolstering a wounded ally also helps it recover.',
                iconKey: 'icon_passive_patch_job',
                order: 0,
                tags: ['support', 'healing'],
                defaultParams: ['wounded_recovery_flat' => 2],
            ),
            AbilityDefinition::passive(
                abilityId: 'battle_tempo',
                displayName: 'Battle Tempo',
                shortDesc: 'Defeats while buffed spread momentum to allies.',
                iconKey: 'icon_passive_battle_tempo',
                order: 0,
                tags: ['support', 'team'],
                defaultParams: ['tempo_attack_pct' => 0.08],
            ),
            AbilityDefinition::passive(
                abilityId: 'chant_of_violence',
                displayName: 'Chant of Violence',
                shortDesc: 'Warcry grants a stronger attack bonus.',
                iconKey: 'icon_passive_chant_of_violence',
                order: 0,
                tags: ['support', 'buff'],
                defaultParams: ['warcry_attack_pct_bonus' => 0.08],
            ),
            AbilityDefinition::passive(
                abilityId: 'mob_mentality',
                displayName: 'Mob Mentality',
                shortDesc: 'Damaged enemies take more follow-up damage.',
                iconKey: 'icon_passive_mob_mentality',
                order: 0,
                tags: ['team', 'damage'],
                defaultParams: ['damaged_enemy_bonus_pct' => 0.12],
            ),
            AbilityDefinition::passive(
                abilityId: 'attention_hog',
                displayName: 'Attention Hog',
                shortDesc: 'Support effects on this unit spill to one ally.',
                iconKey: 'icon_passive_attention_hog',
                order: 0,
                tags: ['support', 'team'],
                defaultParams: ['echo_support_pct' => 0.5],
            ),
            AbilityDefinition::passive(
                abilityId: 'dumb_luck',
                displayName: 'Dumb Luck',
                shortDesc: 'Once per battle, improve a low result.',
                iconKey: 'icon_passive_dumb_luck',
                order: 0,
                tags: ['luck', 'reaction'],
                defaultParams: ['low_roll_threshold' => 2, 'bonus_flat' => 2],
            ),
            AbilityDefinition::passive(
                abilityId: 'morale_goblin',
                displayName: 'Morale Goblin',
                shortDesc: 'Defeating an enemy protects the weakest ally.',
                iconKey: 'icon_passive_morale_goblin',
                order: 0,
                tags: ['support', 'team'],
                defaultParams: ['victory_bolster_pct' => 0.12],
            ),
            AbilityDefinition::passive(
                abilityId: 'toxic_tools',
                displayName: 'Toxic Tools',
                shortDesc: 'Your debuffs are stronger.',
                iconKey: 'icon_passive_toxic_tools',
                order: 0,
                tags: ['debuff', 'poison'],
                defaultParams: ['status_potency_pct' => 0.15],
            ),
            AbilityDefinition::passive(
                abilityId: 'spiteful_reflex',
                displayName: 'Spiteful Reflex',
                shortDesc: 'Reflect one incoming debuff per round.',
                iconKey: 'icon_passive_spiteful_reflex',
                order: 0,
                tags: ['reaction', 'debuff'],
                defaultParams: ['reflects_debuffs' => true],
            ),
            AbilityDefinition::passive(
                abilityId: 'opportunist',
                displayName: 'Opportunist',
                shortDesc: 'Deal more damage to debuffed enemies.',
                iconKey: 'icon_passive_opportunist',
                order: 0,
                tags: ['ranged', 'damage', 'debuff'],
                defaultParams: ['debuffed_damage_pct' => 0.15],
            ),
            AbilityDefinition::passive(
                abilityId: 'disabling_hit',
                displayName: 'Disabling Hit',
                shortDesc: 'Disarming Shot reduces even more attack.',
                iconKey: 'icon_passive_disabling_hit',
                order: 0,
                tags: ['ranged', 'debuff'],
                defaultParams: ['attack_reduction_pct_bonus' => 0.08],
            ),
            AbilityDefinition::passive(
                abilityId: 'clean_shot',
                displayName: 'Clean Shot',
                shortDesc: 'Deal more damage to enemies hit by Disarming Shot.',
                iconKey: 'icon_passive_clean_shot',
                order: 0,
                tags: ['ranged', 'damage'],
                defaultParams: ['status_bonus_target' => 'disarmed', 'status_bonus_pct' => 0.18],
            ),
            AbilityDefinition::passive(
                abilityId: 'nerve_toxin',
                displayName: 'Nerve Toxin',
                shortDesc: 'Poisoned enemies deal less damage.',
                iconKey: 'icon_passive_nerve_toxin',
                order: 0,
                tags: ['poison', 'debuff'],
                defaultParams: ['poison_attack_reduction_pct' => 0.12],
            ),
            AbilityDefinition::passive(
                abilityId: 'lingering_cloud',
                displayName: 'Lingering Cloud',
                shortDesc: 'Poison Cloud lasts longer and hits harder.',
                iconKey: 'icon_passive_lingering_cloud',
                order: 0,
                tags: ['poison', 'damage'],
                defaultParams: ['poison_damage_pct' => 0.15, 'duration_rounds_bonus' => 1],
            ),
            AbilityDefinition::passive(
                abilityId: 'sickly_weakness',
                displayName: 'Sickly Weakness',
                shortDesc: 'Poisoned targets count as having an extra debuff type.',
                iconKey: 'icon_passive_sickly_weakness',
                order: 0,
                tags: ['poison', 'debuff'],
                defaultParams: ['counts_as_extra_debuff_type' => 1],
            ),
        ];
    }
}
