---
Title: "Biome and Region Catalog"
Status: Canonical
Last Updated: 2026-08-01
Owner: Content Design + Narrative Design
Depends On:
  - documentation/03-content/02-kin-types.md
  - documentation/03-content/03-enemy-types.md
  - documentation/01-lore/01-story-and-biome-progression.md
Category: 03-content
Tags:
  - content
  - biomes
  - regions
  - progression
---

# Biome and Region Catalog

## Purpose

Define the canonical regions currently available in Dice Goblins and the authored identity of each biome. This document owns region keys, display names, progression order, entry cost, native enemy identity, bosses, completion rewards, and visual themes.

Run-map generation, node frequency, unlock persistence, and completion processing belong in system documentation. Future campaign concepts do not become current biomes until they are deliberately added here.

## Scope

- Content category: Current playable biomes and regions.
- Player-facing surfaces: Region selection, run maps, combat backgrounds, completion summaries, the Codex, and campaign progression.
- Related content docs: Enemy types, kin types, encounters, hazards, shrines, items, and reward profiles.

## Current Progression

```text
Mystic Cave → The Farm → Mountains → Swamps
```

| Key | Display name | Order | Theme | Recommended level | Energy cost | Native enemy identity | Boss or climax | Completion progression | Content status |
| --- | --- | ---: | --- | ---: | ---: | --- | --- | --- | --- |
| `mystic_cave` | Mystic Cave | 1 | Mystic cave | 1 | 0 | No conventional native faction; associated with The Whim and chaos manifestations | The player's manifestation and stabilization into a Basic Goblin | Unlocks The Farm | Active |
| `the_farm` | The Farm | 2 | Farm | 1 | 3 | Pigs | Mudking | Unlocks shop access, Mountains, and the Pig Kin progression path | Active |
| `mountains` | Mountains | 3 | Mountain | 1 | 5 | Kobolds | Kobold Warchief | Unlocks Swamps | Active |
| `swamps` | Swamps | 4 | Swamp | 1 | 5 | Frogmen | Bog Tyrant | Unlocks the Wrong Machine feature; currently ends the playable region sequence | Active |

## Biome Identities

### Mystic Cave

- **Gameplay role:** zero-cost introduction and narrative staging area.
- **Visual identity:** unstable stone, purple-blue chaos light, and ancient goblin machinery.
- **Content identity:** The Whim, the player's first manifestation, Basic Goblin identity, and later chaos-related encounters.
- **Run shape:** a deliberately short introduction rather than a normal combat expedition.
- **Current special encounter:** a low-threat Chaos Treasure Scavenger formation may appear as Mystic Cave combat content.

### The Farm

- **Gameplay role:** first full combat biome and tutorial for combat, loot, rest, bosses, currency, and persistent progression.
- **Visual identity:** muddy fields, fences, feed stores, hay, farm structures, and pig-controlled territory.
- **Native faction:** pigs.
- **Boss:** Mudking.
- **Progression identity:** introduces Pig Ears and the Mudking Crown Fragment, rescues the Tooth Collector, opens the shop, and establishes Pig Kin as the first unlockable kin.

### Mountains

- **Gameplay role:** first branching expedition with more demanding formations and elevated encounter difficulty.
- **Visual identity:** exposed stone, steep paths, scrap-built defenses, traps, and kobold machinery.
- **Native faction:** kobolds.
- **Boss:** Kobold Warchief.
- **Progression identity:** advances the search for the Wrong Machine and opens the route to the Swamps.

### Swamps

- **Gameplay role:** branching marsh expedition and conclusion of the current opening campaign arc.
- **Visual identity:** black water, reeds, dry islands, standing stones, scavenged structures, and frogman territory.
- **Native faction:** frogmen.
- **Boss:** Bog Tyrant.
- **Progression identity:** returns the Wrong Machine to goblin control and enables later kin-reconstruction systems.

## Current Kin Relationships

| Biome | Current canonical kin relationship |
| --- | --- |
| Mystic Cave | Establishes the player's Basic Goblin identity. |
| The Farm | Provides the materials and progression path for Pig Kin. |
| Mountains | No additional kin type is currently canonical. |
| Swamps | No additional kin type is currently canonical. |

Planning references to Lizard Kin, Frog Kin, or later biome lineages are future design directions. They are not current content until added to the kin catalog.

## Open Questions

- Mountains and Swamps need explicit kin decisions before either biome can advertise a lineage reward.
- The current recommended level remains `1` for every region. Rebalancing region progression should update this catalog before runtime values change.
- Mystic Cave currently combines introduction content with later chaos encounters. A future split between tutorial region and repeatable special biome may require separate region keys.
- Roc Egg and Gator Head remain legacy region-item concepts and are not current canonical rewards or items.

## Maintenance Notes

- Add a region here before or alongside its implementation, art, encounters, and progression hooks.
- Keep native factions and bosses synchronized with the enemy and encounter catalogs.
- Keep kin relationships synchronized with the kin and item catalogs.
- Treat planning-only chapter names as non-current until promoted into this catalog.
- Keep run generation, unlock evaluation, and completion mechanics in system documentation.
