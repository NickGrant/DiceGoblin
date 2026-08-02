---
Title: "Item and Consumable Catalog"
Status: Canonical
Last Updated: 2026-08-01
Owner: Content Design
Depends On:
  - documentation/03-content/02-kin-types.md
  - documentation/03-content/06-biomes-and-regions.md
  - documentation/03-content/11-loot-and-reward-profiles.md
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

## Kin Progression Materials

| Key | Display name | Description | Category | Rarity | Visible before discovery | Primary progression | Current acquisition | Authored use |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `pig_ear` | Pig Ear | A stubborn scrap of pig-kin possibility. The Wrong Machine will know what to do with it. | Lineage material | Common | Yes | Yes | Pig-family combat victory: `1`; pig-family boss victory: `2` | Material for Pig Kin reconstruction. |
| `mudking_crown_fragment` | Mudking Crown Fragment | A boss-won catalyst heavy with farmyard authority. | Boss catalyst | Rare | No | Yes | Mudking boss victory: `1` | Boss catalyst for Pig Kin reconstruction. |

### Pig Kin Material Identity

- Pig Ears are repeatable faction materials and may be earned before the Wrong Machine is unlocked.
- The Mudking Crown Fragment is a boss-specific catalyst and should remain visually and narratively distinct from ordinary Pig Ears.
- Exact reconstruction costs belong in Wrong Machine system documentation, but both items are canonically tied to Pig Kin.

## Healing Consumables

| Key | Display name | Description | Category | Rarity | Effect | Use restrictions | Current acquisition |
| --- | --- | --- | --- | --- | --- | --- | --- |
| `field_poultice` | Field Poultice | A quick wrap for patching up a wounded unit between encounters. | Consumable | Common | Restores `10` HP to one run unit. | Active run; outside unresolved combat; target must be wounded. | No canonical acquisition source currently defined. |
| `hearty_bone_broth` | Hearty Bone Broth | A stronger recovery draught that brings one run unit back from the edge. | Consumable | Uncommon | Restores `25` HP to one run unit. | Active run; outside unresolved combat; target must be wounded. | No canonical acquisition source currently defined. |

Healing cannot exceed the unit's maximum HP. A healing consumable may restore a defeated run unit when the resulting HP is above zero.

## Energy Consumables

| Key | Display name | Description | Category | Rarity | Effect | Use restrictions | Current acquisition |
| --- | --- | --- | --- | --- | --- | --- | --- |
| `travel_ration` | Travel Ration | A packed bite that restores a small amount of energy before the next run. | Consumable | Common | Restores `10` Energy. | Energy must be below the player's current maximum. | No canonical acquisition source currently defined. |
| `sparkroot_tonic` | Sparkroot Tonic | A sharp tonic that restores a larger amount of energy without exceeding the current cap. | Consumable | Uncommon | Restores `25` Energy. | Energy must be below the player's current maximum. | No canonical acquisition source currently defined. |

Energy restoration cannot exceed the player's current Energy cap.

## Item Category Summary

| Category | Current items | Content role |
| --- | --- | --- |
| Lineage material | Pig Ear | Repeatable faction material used to reconstruct a kin type. |
| Boss catalyst | Mudking Crown Fragment | Rare boss-specific requirement for permanent progression. |
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

- The four consumables have complete use effects but no canonical acquisition source. They need explicit shop, loot, crafting, or grant placement before they are normally obtainable.
- Pig Kin reconstruction costs and the division of cost between Pig Ears, Crown Fragments, and Raw Chaos need a canonical system-level definition.
- Future biome materials should follow the generic item model rather than creating parallel region-item storage.
- Item-facing Codex presentation needs authored descriptions and icons to remain synchronized with this catalog.

## Maintenance Notes

- Add an item here before or alongside any grant, shop offer, crafting recipe, or progression requirement that uses it.
- Keep acquisition synchronized with the reward profile catalog.
- Keep kin materials synchronized with the kin and biome catalogs.
- Treat storage-only item records as implementation drift until deliberately accepted as content.
- Keep inventory mutations, transactions, validation, and API contracts outside this document.
