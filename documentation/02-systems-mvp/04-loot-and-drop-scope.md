# Loot and Drop Scope - MVP

Status: active  
Last Updated: 2026-03-02  
Owner: Systems Design  
Depends On: `documentation/02-systems-mvp/01-dice-system.md`, `documentation/02-systems-mvp/03-encounter-scope.md`

This document defines the authoritative loot generation model for Dice Goblins MVP. It specifies reward categories, roll structure, and Tier 3 promotion-item sourcing.

## 1. Design Goals

The MVP loot system must:
- provide predictable but flexible reward pacing
- support unit and dice acquisition without over-saturation
- gate Tier 3 progression through biome-specific boss rewards
- remain data-driven and table-based

## 2. Loot Tables (Core Model)

All loot is generated via loot tables.

Key principles:
- encounters do not directly grant items
- encounters define how many rolls happen and which table tier is used
- loot tables define what can be rewarded
- encounter definitions define how much is rewarded

## 3. Loot Table Tiers

The MVP supports exactly two loot table tiers:
- Tier 1
- Tier 2

No Tier 3 loot table exists in MVP.

## 4. Loot Categories (Closed List)

Each loot table may roll from:
1. Dice
2. Units
3. Currency

Explicitly excluded:
- crafting materials
- consumables
- cosmetics
- meta-progression resources

## 5. Encounter -> Loot Mapping

Each encounter reward combines:
- loot table tier
- number of rolls

Example profiles:
- Easy reward:
  - 1 roll on Tier 1
- Medium reward:
  - 2-3 rolls on Tier 1
  - or 1 roll on Tier 2

## 6. Boss Encounter Rewards

Boss encounters follow standard loot rules plus biome-item chance.

Biome Tier 3 items:
- Mountains: Roc Egg
- Swamps: Gator Head

Rules:
- Tier 3 items only drop from bosses
- drop chance is configured per boss
- not guaranteed unless explicitly configured

## 7. Experience (XP) Rewards

XP is deterministic and separate from loot-table item rolls.

Rules:
- XP is awarded for Combat, Boss, and Loot encounters
- XP is not awarded for Rest encounters
- XP awarded equals sum of `xp_reward` for enemies in encounter
- XP is granted only to fielded surviving units
- XP is not split; all eligible units get full award
- units at max level do not gain XP
- Combat/Boss XP is applied via battle reward-claim flow
- Loot XP is applied via encounter resolution (non-claim path)

## 8. Units as Loot

When a roll yields a unit:
- generated at Tier 1
- starts at level 1
- no Tier 2/Tier 3 unit drops in MVP

## 9. Dice as Loot

When a roll yields a die:
- die size and rarity come from loot tables
- dice obey constraints in `documentation/02-systems-mvp/01-dice-system.md`
- no drop-time upgrading/modification in MVP

## 10. Currency as Loot

Currency is granted as flat amounts from loot tables.

Rules:
- no player-state scaling
- no multipliers in MVP

## 11. Explicit Non-Goals

The MVP loot system does not include:
- pity timers
- drop streak protection
- smart loot targeting
- inventory limits
- player choice during loot resolution

## 12. MVP Validation Criteria

Loot is MVP-complete when:
- tables can be tuned without code changes
- players reliably gain units and dice through play
- Tier 3 items feel rare but achievable
- reward pacing is consistent across runs
