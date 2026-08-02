---
Title: "Codex Entry Catalog"
Status: Canonical
Last Updated: 2026-08-01
Owner: Content Design + Narrative Design
Depends On:
  - documentation/03-content/01-unit-types.md
  - documentation/03-content/02-kin-types.md
  - documentation/03-content/03-enemy-types.md
  - documentation/03-content/06-biomes-and-regions.md
  - documentation/03-content/10-items-and-consumables.md
Category: 03-content
Tags:
  - content
  - codex
  - discovery
  - progression
---

# Codex Entry Catalog

## Purpose

Define the canonical categories and discovery rules for the Dice Goblins Codex. This document identifies which current content keys are eligible for Codex ownership, how each category is discovered, and which categories remain intentionally deferred.

Profile payload shape, persistence, synchronization jobs, deterministic rolls, and UI rendering belong in technical or UX documentation. The detailed content of a unit, enemy, kin, biome, or item remains owned by its primary catalog.

## Current Category Model

| Entry type | Current key source | Discovery rule | Current entry count | Status |
| --- | --- | --- | ---: | --- |
| `enemy` | Enemy Type Catalog | Deterministic combat-page drop or completed-biome boss award | 14 | Active |
| `biome` | Biome and Region Catalog | Complete the biome | 4 | Active |
| `feature` | Feature unlock catalog below | Receive the feature unlock | 12 | Active |
| `unit_type` | Unit Type Catalog | Unlock the unit type or own a unit of that type | 20 | Active |
| `kin` | Kin Type Catalog | Unlock the kin or own a unit of that kin | 2 | Active |
| `item` | Item and Consumable Catalog | Own at least one copy of the item | 6 | Active |
| `affix` | Future dice-affix catalog | Own a die carrying the affix | Not cataloged here | Deferred by scope |
| `lore` | Future dialogue and lore catalog | Receive the corresponding lore/dialogue unlock | Not cataloged here | Deferred by scope |

The current non-dice, non-dialogue Codex contains **58 canonical entry keys**.

## Enemy Entries

Every active enemy in the Enemy Type Catalog is eligible for a Codex page.

### Discovery

- Each defeated enemy copy in a victorious `combat`, `boss`, or `chaos` encounter has a `13%` deterministic chance to award its page if the player does not already own it.
- Copies roll independently, so formations containing repeated enemies provide multiple discovery opportunities.
- When a biome page is first awarded on successful completion, boss enemies defeated during that completed run are awarded directly.
- Enemy pages are permanent once discovered.

Current enemy keys are the fourteen entries defined in `03-enemy-types.md`.

## Biome Entries

| Key | Display name | Discovery condition |
| --- | --- | --- |
| `mystic_cave` | Mystic Cave | Complete a Mystic Cave run. |
| `the_farm` | The Farm | Complete a Farm run. |
| `mountains` | Mountains | Complete a Mountains run. |
| `swamps` | Swamps | Complete a Swamps run. |

Biome discovery records completion, not merely access or run creation.

## Feature Entries

| Key | Display name | Player-facing identity | Known discovery path |
| --- | --- | --- | --- |
| `shop` | Shop | Opens persistent buying, selling, and daily-deal surfaces. | Defeat the Farm boss or complete The Farm. |
| `academy` | Academy | Opens unit research, promotion planning, and mastery selection. | Purchase the Academy feature unlock. |
| `bigger_squad` | Bigger Squad | Raises squad capacity from `4` to `6`. | Purchase the feature unlock. |
| `biggerest_squad` | Biggerest Squad | Raises squad capacity from `6` to `9`. | Purchase after Bigger Squad. |
| `shop_discount` | Shop Discount | Improves future shop purchase pricing. | Purchase the feature unlock. |
| `sell_bonus` | Sell Bonus | Improves sale returns. | Purchase the feature unlock. |
| `market_mastery` | Market Mastery | Strengthens the shop economy upgrades. | Purchase after Shop Discount and Sell Bonus. |
| `second_daily_deal` | Second Daily Deal | Adds a second daily-deal slot. | Purchase the feature unlock. |
| `energy_cap_75` | Energy Capacity 75 | Raises maximum Energy to `75`. | Receive the corresponding feature unlock. |
| `energy_cap_100` | Energy Capacity 100 | Raises maximum Energy to `100`. | Receive the corresponding feature unlock. |
| `explode_d4s` | Exploding D4s | Gives eligible d4s one explosion when they roll their maximum value. | Receive the corresponding feature unlock. |
| `wrong_machine` | Wrong Machine | Opens Raw Chaos and kin-reconstruction progression. | Complete the Swamps. |

The Codex records feature ownership. Costs, prerequisites, and mechanical execution remain in feature-system documentation.

## Unit-Type Entries

Every active unit type in the Unit Type Catalog is eligible for a Codex entry.

### Discovery

- Awarded when the unit type is unlocked through research or progression.
- Also derived when the player owns a unit instance of the type.
- Current entry details include role, tier, maximum level, base stats, growth, and native ability package.

The current key set contains twenty unit types across the Bruiser, Guardian, Marksman, Banner, and Saboteur families.

## Kin Entries

| Key | Display name | Discovery condition |
| --- | --- | --- |
| `basic_goblin` | Basic Goblin | Receive the lineage unlock or own a Basic Goblin unit. |
| `pig_kin` | Pig Kin | Receive the Pig Kin unlock or own a Pig Kin unit. |

Implementation-only splice records are not valid Codex content unless they are first added to the Kin Type Catalog.

## Item Entries

| Key | Display name | Discovery condition |
| --- | --- | --- |
| `pig_ear` | Pig Ear | Own at least one Pig Ear. |
| `mudking_crown_fragment` | Mudking Crown Fragment | Own at least one Crown Fragment. |
| `field_poultice` | Field Poultice | Own at least one Field Poultice. |
| `hearty_bone_broth` | Hearty Bone Broth | Own at least one Hearty Bone Broth. |
| `travel_ration` | Travel Ration | Own at least one Travel Ration. |
| `sparkroot_tonic` | Sparkroot Tonic | Own at least one Sparkroot Tonic. |

The obsolete Roc Egg and Gator Head records are not Codex entries because they are not current canonical items.

## Discovery Source Vocabulary

| Source key | Meaning |
| --- | --- |
| `combat_drop` | Enemy page awarded from a victorious encounter roll. |
| `completed_biome_boss` | Boss enemy page awarded with first completion of its biome. |
| `completed_run` | Biome page awarded by successful completion. |
| `feature_unlock` | Feature page awarded when the feature is unlocked. |
| `unit_type_unlock` | Unit-type page awarded by research or progression unlock. |
| `lineage_unlock` | Kin page awarded by lineage unlock. |
| `owned_unit` | Unit-type or kin page derived from current ownership. |
| `owned_item` | Item page derived from positive inventory quantity. |
| `owned_die` | Affix page derived from an owned die; deferred from this catalog. |
| `dialogue` | Lore page derived from dialogue progression; deferred from this catalog. |

## Open Questions

- Detailed Codex payloads are currently authored for unit types and enemies, while biome, feature, kin, and item entries may rely on prettified keys. These categories need dedicated title, description, art, and summary presentation.
- Consumable item pages cannot normally be discovered until acquisition sources are authored.
- Enemy discovery at `13%` per defeated copy may create uneven page acquisition across rare bosses and common grunts. Boss completion awards mitigate this only for defeated biome bosses.
- Feature keys involving dice behavior remain valid feature entries, but their detailed mechanical explanations should be reconciled when the dice catalog is written.
- Affix and lore entry sets are intentionally deferred and should not be inferred from storage records alone.

## Maintenance Notes

- A Codex entry key must refer to content already defined in its primary canonical catalog.
- Add new entry categories here before exposing them through ownership or profile APIs.
- Keep discovery conditions synchronized with reward and progression catalogs.
- Do not duplicate full unit, enemy, biome, kin, or item definitions here.
- Keep persistence, synchronization, API details, and deterministic roll implementation outside this document.
