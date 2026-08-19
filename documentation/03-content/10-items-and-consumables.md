---
Title: "Item and Consumable Catalog"
Status: Canonical
Last Updated: 2026-08-02
Owner: Content Design
Depends On:
  - documentation/03-content/02-kin-types.md
  - documentation/03-content/06-biomes-and-regions.md
  - documentation/03-content/11-loot-and-reward-profiles.md
  - documentation/03-content/13-dialogue-and-lore.md
  - documentation/02-systems/09-kin-reconstruction.md
Category: 03-content
Tags:
  - content
  - items
  - consumables
  - progression
---

# Item and Consumable Catalog

## Purpose

Define the canonical non-dice items currently recognized as game content. This document owns item keys, display names, descriptions, category, rarity, visibility, spendability, authored effect, and progression role.

Inventory storage, transaction handling, shop pricing, grant APIs, and consumption validation belong in technical or system documentation.

## Reading the Catalog

- Every current item is stackable and spendable.
- **Visible before discovery** controls whether the item may be shown before the player owns it.
- **Primary progression** marks items required for a major permanent progression path.
- Acquisition sources are listed only where the current content explicitly guarantees or rolls the item.
- A repeatable reconstruction ingredient remains useful after first kin discovery because later recipes continue producing units.

## Kin Progression Materials

| Key | Display name | Description | Category | Rarity | Visible before discovery | Primary progression | Current acquisition | Authored use |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `pig_ear` | Pig Ear | A stubborn scrap of pig-kin possibility. The Wrong Machine will know what to do with it. | Lineage material | Common | Yes | Yes | Pig-family combat victory: `1`; pig-family boss victory: `2` | Spend `10` with one Crown Fragment and `25` Raw Chaos to produce one Pig Kin unit. |
| `mudking_crown_fragment` | Mudking Crown Fragment | A boss-won catalyst heavy with farmyard authority. | Boss catalyst | Rare | No | Yes | Mudking boss victory: `1` | Spend `1` with ten Pig Ears and `25` Raw Chaos to produce one Pig Kin unit. |

### Pig Kin Material Identity

- Pig Ears are repeatable faction materials and may be earned before the Wrong Machine is unlocked.
- The Mudking Crown Fragment is a boss-specific catalyst and should remain visually and narratively distinct from ordinary Pig Ears.
- The complete current Pig Kin recipe is `10` Pig Ears, `1` Mudking Crown Fragment, and `25` Raw Chaos.
- Every successful recipe completion produces one Pig Kin unit.
- The first produced Pig Kin also establishes Pig Kin discovery, Codex ownership, and ordinary reward-pool eligibility.
- Pig Ear and Crown Fragment rewards continue after discovery because the recipe remains repeatable.
- The one-time reconstruction tutorial may fill only missing Pig Ears and Raw Chaos required for the first recipe; it grants no surplus and does not duplicate the guaranteed Crown Fragment.

## Healing Consumables

| Key | Display name | Description | Category | Rarity | Effect | Use restrictions | Current acquisition |
| --- | --- | --- | --- | --- | --- | --- | --- |
| `field_poultice` | Field Poultice | A quick wrap for patching up a wounded unit between encounters. | Consumable | Common | Restores `10` HP to one run unit. | Active run; outside unresolved combat; target must be wounded. | Guaranteed quantity `1` from first completion of `mountains-llamaver-health-gift`. |
| `hearty_bone_broth` | Hearty Bone Broth | A stronger recovery draught that brings one run unit back from the edge. | Consumable | Uncommon | Restores `25` HP to one run unit. | Active run; outside unresolved combat; target must be wounded. | No canonical acquisition source currently defined. |

Healing cannot exceed the unit's maximum HP. A healing consumable may restore a defeated run unit when the resulting HP is above zero.

## Energy Consumables

| Key | Display name | Description | Category | Rarity | Effect | Use restrictions | Current acquisition |
| --- | --- | --- | --- | --- | --- | --- | --- |
| `travel_ration` | Travel Ration | A packed bite that restores a small amount of energy before the next run. | Consumable | Common | Restores `10` Energy. | Energy must be below the player's current maximum. | Guaranteed quantity `1` from first completion of `mountains-llamaver-energy-gift`. |
| `sparkroot_tonic` | Sparkroot Tonic | A sharp tonic that restores a larger amount of energy without exceeding the current cap. | Consumable | Uncommon | Restores `25` Energy. | Energy must be below the player's current maximum. | No canonical acquisition source currently defined. |

Energy restoration cannot exceed the player's current Energy cap.

## Consumable Introduction

Llamaver introduces the consumable system through two one-time traveler encounters in the Mountains:

- **A Brightly Misplaced Supply** grants Travel Ration x1 and introduces energy restoration.
- **The Other Useful Bundle** requires the first Llamaver encounter and grants Field Poultice x1 to introduce healing.

Each dialogue records its own seen state and cannot recur, so these are one-time introductory grants rather than renewable acquisition sources.

## Item Category Summary

| Category | Current items | Content role |
| --- | --- | --- |
| Lineage material | Pig Ear | Repeatable faction material spent each time the Wrong Machine produces a Pig Kin unit. |
| Boss catalyst | Mudking Crown Fragment | Repeatable boss-linked ingredient spent each time the Wrong Machine produces a Pig Kin unit. |
| Healing consumable | Field Poultice, Hearty Bone Broth | Preserve run-unit health between encounters. |
| Energy consumable | Travel Ration, Sparkroot Tonic | Restore the resource used to begin expeditions. |

## Legacy Region-Item Records

The names **Roc Egg** and **Gator Head** exist in older region-item data and older named loot-table descriptions. They are not current canonical items because they have no modern item definition, authored use, or active reward path.

| Legacy key | Display name | Former association | Current content status |
| --- | --- | --- | --- |
| `roc_egg` | Roc Egg | Mountains boss reward | Not current content |
| `gator_head` | Gator Head | Swamps boss reward | Not current content |

Reintroducing either item requires a complete entry in this catalog, including its purpose, rarity, visibility, acquisition, and spend behavior.

## Open Questions

- Hearty Bone Broth and Sparkroot Tonic have complete use effects but no canonical acquisition source. They need explicit shop, loot, crafting, or grant placement before they are normally obtainable.
- Field Poultice and Travel Ration have only one-time introductory grants. Renewable acquisition must be defined if they are intended to support sustained consumable use.
- Future biome materials should follow the generic item model rather than creating parallel region-item storage.
- Item-facing Codex presentation needs authored descriptions and icons to remain synchronized with this catalog.

## Maintenance Notes

- Add an item here before or alongside any grant, shop offer, crafting recipe, or progression requirement that uses it.
- Keep acquisition synchronized with the reward profile and dialogue catalogs.
- Keep kin materials synchronized with the kin, reconstruction, and biome catalogs.
- Treat storage-only item records as implementation drift until deliberately accepted as content.
- Keep inventory mutations, transactions, validation, and API contracts outside this document.
