---
Title: "Codex Entry Catalog"
Status: Canonical
Last Updated: 2026-08-02
Owner: Content Design + Narrative Design
Depends On:
  - documentation/03-content/01-unit-types.md
  - documentation/03-content/02-kin-types.md
  - documentation/03-content/03-enemy-types.md
  - documentation/03-content/06-biomes-and-regions.md
  - documentation/03-content/10-items-and-consumables.md
  - documentation/03-content/13-dialogue-and-lore.md
  - documentation/03-content/14-dice-materials.md
  - documentation/02-systems/09-kin-reconstruction.md
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
| `material` | Dice Material Catalog | Own a die made from the material | 32 | Active |

The current Codex contains **99 canonical entry keys**.

Permanent die affixes are not a Codex category. Existing affix storage records must not create pages.

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

Dialogue seen-state keys are not features. Global dice features remain feature pages; they are not permanent materials or affixes.

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
| `field_poultice` | Field Poultice | Own at least one; first available through Far Gifts. |
| `hearty_bone_broth` | Hearty Bone Broth | Own at least one. |
| `travel_ration` | Travel Ration | Own at least one; first available through Far Gifts. |
| `sparkroot_tonic` | Sparkroot Tonic | Own at least one. |

Roc Egg and Gator Head are obsolete records and are not current entries.

## Material Entries

Owning any valid die made from an enabled material discovers that material's page.

- A material has one page regardless of supported or owned die sizes.
- Owning multiple sizes or copies does not create duplicate entries.
- Pages show rarity, effect, active allowed sizes, stacking, valuation class, tags, and representative art.
- Independent rarity values and legacy affix combinations are not identities.
- No material page may advertise `d12`, `d20`, or another inactive size.

Cardboard is the neutral baseline material. Its page records that it is Common, supports every active size, and has no special effect; it is still an explicit material identity rather than missing data.

### Common

- `cardboard`
- `bone`
- `iron`
- `copper`
- `clay`
- `flint`
- `chalk`
- `leather`

### Uncommon

- `peach_pit`
- `glass`
- `lead`
- `obsidian`
- `rubber`
- `amber`
- `salt`
- `brass`

### Rare

- `powder_keg`
- `butchers_tooth`
- `bloodstone`
- `sporewood`
- `moonstone`
- `rusted_iron`
- `gold`

### Epic

- `porcelain`
- `diamond`
- `voidstone`
- `phoenix_ash`
- `clockwork_brass`

### Legendary

- `chaos_shard`
- `living_bone`
- `star_metal`
- `worldroot`

The Dice Material Catalog owns the titles, effects, allowed sizes, stacking, valuation, and tags for these entries.

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
- `mountains-traveler-consumable-gifts`
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
| `owned_die` | Material page derived from an owned die's canonical material key. |

## Reconciliation Requirements

- Dialogue seen state and Lore ownership remain separate.
- Feature synchronization must not interpret dialogue state as features.
- Pig Kin Codex ownership must be created or repaired from first durable Pig Kin ownership, not from a recipe-unlock toggle alone.
- Later Pig Kin reconstructions must not create duplicate Codex awards.
- Material synchronization uses the 32 canonical material keys, not rarity or affixes.
- One material page is awarded per material regardless of die size.
- Field Poultice and Travel Ration have first-discovery paths through Far Gifts; Hearty Bone Broth and Sparkroot Tonic still need acquisition sources.

## Open Questions

- Detailed payloads remain strongest for units and enemies; biome, feature, kin, item, Lore, and material presentation need complete authored art and descriptive copy.
- Hearty Bone Broth and Sparkroot Tonic cannot normally be discovered until acquisition sources exist.
- Enemy page acquisition may remain uneven between common enemies and rare bosses.
- Feature interactions with material behavior require implementation reconciliation.

## Maintenance Notes

- Every key must exist in its primary canonical catalog.
- Add categories before exposing them through ownership or profile APIs.
- Keep discovery synchronized with rewards, progression, dialogue, materials, and reconstruction.
- Persistence, synchronization, API behavior, and deterministic implementation remain outside this document.
- Do not promote storage keys into Codex content without authored player-facing identity.
