<?php
declare(strict_types=1);

/**
 * File: C:\xampp\htdocs\dice-goblin\backend\src\Combat\Abilities\AbilityConfigMerger.php
 * Purpose: Project PHP module.
 */

namespace DiceGoblins\Combat\Abilities;

use InvalidArgumentException;

final class AbilityConfigMerger
{
    /**
     * Merge an ACTIVE ability config.
     *
     * Expected override input shape (from unit_types.ability_set_json):
     * [
     *   'slot' => 1,
     *   'ability_id' => 'basic_attack_melee',
     *   'speed' => 4,               // optional override
     *   'dice_cost' => 0,           // optional override
     *   'order' => 10,              // optional override
     *   'target' => 'enemy_front_prefer', // optional override
     *   'tags' => [...],            // optional additive/override behavior (see below)
     *   'params' => [...],          // optional override/merge
     * ]
     *
     * @param array<string,mixed> $override
     * @return array<string,mixed>
     */
    public static function mergeActive(AbilityDefinition $def, array $override): array
    {
        if ($def->type !== AbilityType::Active) {
            throw new InvalidArgumentException("mergeActive called with non-active definition '{$def->abilityId}'.");
        }

        $speed = array_key_exists('speed', $override) ? (int)$override['speed'] : (int)$def->speed;
        $diceCost = array_key_exists('dice_cost', $override) ? (int)$override['dice_cost'] : (int)$def->diceCost;
        $order = array_key_exists('order', $override) ? (int)$override['order'] : (int)$def->order;

        $targetStr = array_key_exists('target', $override)
            ? (string)$override['target']
            : (string)$def->defaultTarget?->value;

        if ($targetStr === '') {
            throw new InvalidArgumentException("Active '{$def->abilityId}' missing target after merge.");
        }

        // Params: deep merge is usually NOT desired; prefer shallow merge:
        // - default keys are present
        // - overrides replace values
        $params = self::mergeAssoc(
            $def->defaultParams,
            is_array($override['params'] ?? null) ? (array)$override['params'] : []
        );

        // Tags:
        // Option: union(defaultTags, overrideTags) to keep classification stable.
        $tags = self::unionTags(
            $def->tags,
            is_array($override['tags'] ?? null) ? (array)$override['tags'] : []
        );

        // Validate speed bounds (combat invariant)
        if ($speed < 1 || $speed > 20) {
            throw new InvalidArgumentException("Active '{$def->abilityId}' speed must be 1..20 (got {$speed}).");
        }
        if ($diceCost < 0) {
            throw new InvalidArgumentException("Active '{$def->abilityId}' dice_cost must be >= 0 (got {$diceCost}).");
        }
        if ($order < 0) {
            throw new InvalidArgumentException("Active '{$def->abilityId}' order must be >= 0 (got {$order}).");
        }

        return [
            'slot' => (int)($override['slot'] ?? 0),
            'ability_id' => $def->abilityId,
            'type' => $def->type->value,
            'speed' => $speed,
            'dice_cost' => $diceCost,
            'order' => $order,
            'target' => $targetStr,
            'tags' => $tags,
            'params' => $params,
        ];
    }

    /**
     * Merge a PASSIVE ability config.
     *
     * Expected override input:
     * [
     *   'passive_id' => 'thick_hide',
     *   'order' => 0,      // optional override
     *   'tags' => [...],   // optional
     *   'params' => [...], // optional
     * ]
     *
     * @param array<string,mixed> $override
     * @return array<string,mixed>
     */
    public static function mergePassive(AbilityDefinition $def, array $override): array
    {
        if ($def->type !== AbilityType::Passive) {
            throw new InvalidArgumentException("mergePassive called with non-passive definition '{$def->abilityId}'.");
        }

        $order = array_key_exists('order', $override) ? (int)$override['order'] : (int)($def->order ?? 0);
        if ($order < 0) {
            throw new InvalidArgumentException("Passive '{$def->abilityId}' order must be >= 0 (got {$order}).");
        }

        $params = self::mergeAssoc(
            $def->defaultParams,
            is_array($override['params'] ?? null) ? (array)$override['params'] : []
        );

        $tags = self::unionTags(
            $def->tags,
            is_array($override['tags'] ?? null) ? (array)$override['tags'] : []
        );

        return [
            'passive_id' => $def->abilityId,
            'type' => $def->type->value,
            'order' => $order,
            'tags' => $tags,
            'params' => $params,
        ];
    }

    /** @param array<string,mixed> $base @param array<string,mixed> $over */
    private static function mergeAssoc(array $base, array $over): array
    {
        // Shallow merge: override keys replace base keys.
        foreach ($over as $k => $v) {
            $base[$k] = $v;
        }
        return $base;
    }

    /** @param list<string> $a @param list<string> $b @return list<string> */
    private static function unionTags(array $a, array $b): array
    {
        $set = [];
        foreach ($a as $t) $set[(string)$t] = true;
        foreach ($b as $t) $set[(string)$t] = true;
        return array_values(array_keys($set));
    }
}
