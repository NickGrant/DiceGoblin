---
Title: "Codex Entry Catalog"
Status: Canonical
Last Updated: 2026-08-23
Owner: Content Design + Narrative Design
Depends On:
  - documentation/03-content/01-unit-types.md
  - documentation/03-content/02-kin-types.md
  - documentation/03-content/03-enemy-types.md
  - documentation/03-content/06-biomes-and-regions.md
  - documentation/03-content/10-items-and-consumables.md
  - documentation/03-content/13-dialogue-and-lore.md
  - documentation/02-systems/kin-reconstruction.md
Category: 03-content
Tags:
  - content
  - codex
  - discovery
  - progression
---

# Codex Entry Catalog

## Purpose

Define the canonical Codex categories, eligible keys, and discovery rules. Profile payloads, persistence, synchronization, deterministic rolls, and UI rendering belong in technical or UX documentation. Detailed content remains owned by its primary catalog.

## Current Category Model

| Entry type | Key source | Discovery rule | Entry count | Status |
| --- | --- | --- | ---: | --- |
| `enemy` | Enemy Type Catalog | Deterministic combat-page drop or completed-biome boss award | 14 | Active |
| `biome` | Biome and Region Catalog | Complete the biome | 4 | Active |
| `feature` | Feature catalog below | Receive the feature unlock | 12 | Active |
| `unit_type` | Unit Type Catalog | Unlock the unit type or own a unit of that type | 20 | Active |
| `kin` | Kin Type Catalog | Own at least one unit of the kin | 2 | Active |
| `item` | Item and Consumable Catalog | Own at least one copy | 6 | Active |
| `lore` | Dialogue and Lore Catalog | Complete dialogue explicitly classified as Lore | 9 | Active |
| `affix` | Future dice-affix catalog | Own a die carrying the affix | Not cataloged here | Deferred by scope |

The current non-dice Codex contains **67 canonical entry keys**.

Dice materials remain part of die identity but do not replace the affix Codex category. A dedicated canonical dice/material/affix catalog has not yet been written.

## Enemy Entries

Every active enemy in the Enemy Type Catalog is eligible.

- Each defeated enemy copy in a victorious `combat`, `boss`, or `chaos` encounter has a `13%` deterministic discovery chance when not already owned.
- Copies roll independently.
- When a biome page is first awarded, defeated boss enemies from that completed run are awarded directly.
- Enemy pages are permanent.

## Biome Entries

| Key | Display name | Discovery |
| --- | --- | --- |
| `mystic_cave` | Mystic Cave | Complete a Mystic Cave run. |
| `the_farm` | The Farm | Complete a Farm run. |
| `mountains` | Mountains | Complete a Mountains run. |
| `swamps` | Swamps | Complete a Swamps run. |

Biome discovery records completion, not access or run creation.

## Feature Entries

| Key | Display name | Identity | Discovery path |
| --- | --- | --- | --- |
| `shop` | Shop | Persistent buying, selling, and daily deals. | Complete Farm progression. |
| `academy` | Academy | Unit research, promotion planning, and mastery selection. | Purchase the unlock. |
| `bigger_squad` | Bigger Squad | Raises squad capacity from `4` to `6`. | Purchase the unlock. |
| `biggerest_squad` | Biggerest Squad | Raises squad capacity from `6` to `9`. | Purchase after Bigger Squad. |
| `shop_discount` | Shop Discount | Improves purchase pricing. | Purchase the unlock. |
| `sell_bonus` | Sell Bonus | Improves sale returns. | Purchase the unlock. |
| `market_mastery` | Market Mastery | Strengthens shop economy upgrades. | Purchase after Shop Discount and Sell Bonus. |
| `second_daily_deal` | Second Daily Deal | Adds a second daily-deal slot. | Purchase the unlock. |
| `energy_cap_75` | Energy Capacity 75 | Raises maximum Energy to `75`. | Receive the unlock. |
| `energy_cap_100` | Energy Capacity 100 | Raises maximum Energy to `100`. | Receive the unlock. |
| `explode_d4s` | Exploding D4s | Gives eligible d4s one non-recursive explosion on maximum roll. | Receive the unlock. |
| `wrong_machine` | Wrong Machine | Opens Raw Chaos and kin reconstruction. | Complete `swamps-wrong-machine-recovered`. |

Dialogue seen-state keys are not features.

## Unit-Type Entries

Every active unit type is eligible.

- Award when the type is unlocked through research or progression.
- Also derive ownership when the player owns a unit of the type.
- Current details include role, tier, maximum level, stats, growth, and native abilities.

The catalog contains twenty unit types across Bruiser, Guardian, Marksman, Banner, and Saboteur families.

## Kin Entries

| Key | Display name | Discovery |
| --- | --- | --- |
| `basic_goblin` | Basic Goblin | Own a Basic Goblin unit; normally established by the initial goblin manifestation. |
| `pig_kin` | Pig Kin | Own the first Pig Kin unit; normally produced by `reconstruct_pig_kin`. |

Kin discovery is based on first durable ownership, not on paying for a standalone unlock.

For Pig Kin:

- every successful reconstruction produces one Pig Kin unit
- the first produced unit awards or derives the Pig Kin Codex entry
- later reconstructions create additional units without duplicating the page
- first ownership also enables Pig Kin for applicable future ordinary unit grants
- an account that already owns Pig Kin but lacks its page should be repaired by ownership synchronization

Implementation-only splice records are not Codex content unless first added to the Kin Type Catalog.

## Item Entries

| Key | Display name | Discovery |
| --- | --- | --- |
| `pig_ear` | Pig Ear | Own at least one. |
| `mudking_crown_fragment` | Mudking Crown Fragment | Own at least one. |
| `field_poultice` | Field Poultice | Own at least one; first available through Llamaver's health gift. |
| `hearty_bone_broth` | Hearty Bone Broth | Own at least one. |
| `travel_ration` | Travel Ration | Own at least one; first available through Llamaver's energy gift. |
| `sparkroot_tonic` | Sparkroot Tonic | Own at least one. |

Roc Egg and Gator Head are obsolete records and are not current entries.

## Affix Entries

Affix Codex entries remain part of the current dice model, but their concrete authored catalog is intentionally deferred.

- Owning a die carrying an affix may discover that affix's page through the current acquisition/discovery flow.
- A die may carry multiple permanent affixes according to its rarity-driven capacity.
- Materials and affixes are separate die properties; material identity does not retire or replace affix identity.
- Do not infer a complete player-facing affix catalog from storage records alone. The dedicated dice-content catalog should reconcile keys, display names, effects, rarity, and presentation before this section becomes canonical content authority.

## Lore Entries

Only dialogue explicitly classified as Lore creates pages.

| Key | Display title | Participants | Discovery |
| --- | --- | --- | --- |
| `start-run-kickoff` | The Whim's First Fragment | Player Goblin, The Whim | Complete the Mystic Cave introduction. |
| `mystic-cave-wrong-machine-recovered` | The Machine Comes Home | Player Goblin, The Whim | Complete the Whim recovery reaction. |
| `farm-shop-unlock` | The Tooth Collector Freed | Player Goblin, Tooth Collector, Mudking | Complete the Farm rescue dialogue. |
| `mountains-archivist-first-contact` | The Archivist Takes Notice | Player Goblin, Archivist | Complete first contact. |
| `mountains-kobold-machine-trail` | Kobold Evidence | Player Goblin, Kobold Sentry | Complete the machine-trail dialogue. |
| `mountains-kobold-machine-recovered` | The Recovered Contraption | Player Goblin, Kobold Sentry | Complete the kobold recovery reaction. |
| `mountains-swamps-lead` | Toward the Swamps | Player Goblin, Archivist | Complete the Mountains exit dialogue. |
| `swamps-bog-tyrant-first-confrontation` | Contraband of the Bog | Player Goblin, Bog Tyrant | Complete the first confrontation. |
| `swamps-wrong-machine-recovered` | The Wrong Machine Reclaimed | Player Goblin, Bog Tyrant | Complete the first successful recovery dialogue. |

### Dialogue-Only Keys

These remain dialogue without Lore pages:

- `mystic-cave-wrong-machine-reminder`
- `farm-boss-intro`
- `farm-boss-intro-shop-unlocked`
- `mountains-wrong-machine-search-repeat`
- `mountains-llamaver-energy-gift`
- `mountains-llamaver-health-gift`
- `swamps-bog-tyrant-machine-defense-repeat`
- `swamps-bog-tyrant-rematch`

## Discovery Source Vocabulary

| Source | Meaning |
| --- | --- |
| `combat_drop` | Enemy page from a victorious encounter roll. |
| `completed_biome_boss` | Boss page awarded with first biome completion. |
| `completed_run` | Biome page awarded by successful completion. |
| `feature_unlock` | Feature page awarded by canonical feature ownership. |
| `unit_type_unlock` | Unit-type page awarded by research or progression. |
| `first_kin_ownership` | Kin page established by owning the first unit of that kin. |
| `owned_unit` | Unit-type or kin page derived or repaired from durable ownership. |
| `owned_item` | Item page derived from positive inventory quantity. |
| `dialogue` | Lore page awarded by canonical Lore dialogue completion. |
| `owned_die` | Affix page derived from an owned die; concrete affix content remains deferred. |

## Reconciliation Requirements

- Dialogue seen state and Lore ownership remain separate.
- Feature synchronization must not interpret dialogue state as features.
- Pig Kin Codex ownership must be created or repaired from first durable Pig Kin ownership, not from a recipe-unlock toggle alone.
- Later Pig Kin reconstructions must not create duplicate Codex awards.
- Affix discovery remains compatible with the existing material-plus-affix dice model.
- Field Poultice and Travel Ration have first-discovery paths through Llamaver's two consumable gifts; Hearty Bone Broth and Sparkroot Tonic still need acquisition sources.

## Open Questions

- Detailed payloads remain strongest for units and enemies; biome, feature, kin, item, Lore, and affix presentation need complete authored art and descriptive copy.
- Hearty Bone Broth and Sparkroot Tonic cannot normally be discovered until acquisition sources exist.
- Enemy page acquisition may remain uneven between common enemies and rare bosses.
- Affix entry definitions remain intentionally deferred and should not be inferred from storage records alone.

## Maintenance Notes

- Every key must exist in its primary canonical catalog.
- Add categories before exposing them through ownership or profile APIs.
- Keep discovery synchronized with rewards, progression, dialogue, dice affixes, and reconstruction.
- Persistence, synchronization, API behavior, and deterministic implementation remain outside this document.
- Do not promote storage keys into Codex content without authored player-facing identity.
