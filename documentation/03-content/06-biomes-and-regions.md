---
Title: "Biome and Region Catalog"
Status: Canonical
Last Updated: 2026-08-02
Owner: Content Design + Narrative Design
Depends On:
  - documentation/03-content/02-kin-types.md
  - documentation/03-content/03-enemy-types.md
  - documentation/03-content/13-dialogue-and-lore.md
  - documentation/01-lore/01-story-and-biome-progression.md
  - documentation/07-development-path/01-base-game-content-roster.md
  - documentation/07-development-path/02-night-expansion-content-roster.md
Category: 03-content
Tags:
  - content
  - biomes
  - regions
  - progression
---

# Biome and Region Catalog

## Purpose

Define the canonical regions currently available in Dice Goblins and the authored identity of each current biome. This document owns current region keys, display names, progression order, entry cost, native enemy identity, bosses, completion rewards, and visual themes.

Run-map generation, node frequency, unlock persistence, and completion processing belong in system documentation. Approved future campaign concepts remain planning content until deliberately promoted into this catalog.

## Scope

- Content category: Current playable biomes and regions.
- Player-facing surfaces: Region selection, run maps, combat backgrounds, completion summaries, the Codex, and campaign progression.
- Related content docs: Enemy types, kin types, encounters, dialogue, hazards, shrines, items, and reward profiles.

## Region Naming Standard

The official region display names are:

- **Mountains**
- **Swamps**

Do not use Mountain or Swamp as replacement display names. Singular forms may still describe terrain, themes, or individual geographic features.

## Current Progression

```text
Mystic Cave → The Farm → Mountains → Swamps
```

| Key | Display name | Order | Theme | Recommended level | Energy cost | Native enemy identity | Boss or climax | Completion progression | Content status |
| --- | --- | ---: | --- | ---: | ---: | --- | --- | --- | --- |
| `mystic_cave` | Mystic Cave | 1 | Mystic Cave | 1 | 0 | No conventional native faction; associated with The Whim and chaos manifestations | The player's manifestation and stabilization into a Basic Goblin | Unlocks The Farm | Active |
| `the_farm` | The Farm | 2 | Farm | 1 | 3 | Pigs | Mudking | Unlocks Shop access, Mountains, and the Pig Kin progression path | Active |
| `mountains` | Mountains | 3 | Mountains | 1 | 5 | Kobolds | Kobold Warchief | Unlocks Swamps | Active |
| `swamps` | Swamps | 4 | Swamps | 1 | 5 | Frogmen | Bog Tyrant and recovery of the Wrong Machine | Unlocks the Wrong Machine during the first successful recovery sequence; currently ends the playable region sequence | Active |

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
- **Progression identity:** introduces Pig Ears and the Mudking Crown Fragment, rescues the Tooth Collector, opens the Shop, and establishes Pig Kin as the first reconstructable kin.

### Mountains

- **Gameplay role:** first branching expedition with more demanding formations and elevated encounter difficulty.
- **Visual identity:** exposed stone, steep paths, scrap-built defenses, traps, and kobold machinery.
- **Native faction:** kobolds.
- **Boss:** Kobold Warchief.
- **Progression identity:** advances the search for the Wrong Machine and opens the route to the Swamps.
- **Approved future kin relationship:** Lizard Kin, after its complete content package is promoted beyond the current demo phase.

### Swamps

- **Gameplay role:** branching marsh expedition and conclusion of the current opening campaign arc.
- **Visual identity:** black water, reeds, dry islands, standing stones, scavenged structures, and frogman territory.
- **Native faction:** frogmen.
- **Boss:** Bog Tyrant.
- **Progression identity:** the Bog Tyrant holds the Wrong Machine as dangerous contraband. The first successful Swamps run includes a before-exit recovery scene that returns the machine to goblin control and unlocks its feature. Later Swamps runs treat the Bog Tyrant as a recurring regional ruler and rematch boss.
- **Approved future kin relationship:** Frog Kin, after its complete content package is promoted beyond the current demo phase.

## Wrong Machine Recovery Boundary

- The Wrong Machine is not recovered merely by entering the Swamps.
- The first Bog Tyrant victory opens the one-time dialogue `swamps-wrong-machine-recovered` before the player exits the run.
- Completing that dialogue is the authored moment when the machine returns to goblin control and the Wrong Machine feature unlocks.
- Successful Swamps completion may verify or idempotently grant the same unlock, but it must not create a second narrative recovery.
- Subsequent Swamps dialogue and completion summaries assume the machine is already under goblin control.

## Current and Planned Kin Relationships

| Biome | Current relationship | Approved future relationship |
| --- | --- | --- |
| Mystic Cave | Establishes the player's Basic Goblin identity. | No standard enemy-derived kin pairing. |
| The Farm | Provides the materials and progression path for Pig Kin. | Pig Kin remains the approved association. |
| Mountains | No additional kin is current in the demo scope. | Lizard Kin. |
| Swamps | No additional kin is current in the demo scope. | Frog Kin. |

Lizard Kin and Frog Kin are approved base-game planning decisions, not current reconstruction, reward, or Codex content. Their future allocation is no longer an open biome decision.

## Approved Complete Base-Game Roster

The authoritative planned sequence after the current opening regions is:

```text
Island → Tundra → Volcano → Wasteland → River → Orchard → Savanna → The Library
```

The complete ten standard biomes, special-biome roles, native enemy families, and associated kin are owned by `documentation/07-development-path/01-base-game-content-roster.md`.

Future entries do not become current merely by appearing in that roster. Each must be promoted here with its stable key, progression values, boss, rewards, visual direction, and complete supporting content.

## Night Expansion Boundary

Ruins, Meadow, and Cemetery are reserved for the first expansion, **Night**. They are not base-game or current regions. Their enemy and kin pairings are owned by `documentation/07-development-path/02-night-expansion-content-roster.md`.

## Open Questions

- The current recommended level remains `1` for every active region. Rebalancing region progression should update this catalog before runtime values change.
- Mystic Cave currently combines introduction content with later chaos encounters. A future split between tutorial region and repeatable special biome may require separate region keys.
- Roc Egg and Gator Head remain legacy region-item concepts and are not current canonical rewards or items.
- Detailed progression, bosses, costs, and region mechanics for Island through Savanna and The Library remain future design work.

## Maintenance Notes

- Add a region here before or alongside its implementation, art, encounters, dialogue, and progression hooks.
- Keep native factions and bosses synchronized with the enemy and encounter catalogs.
- Keep story progression synchronized with the dialogue catalog.
- Keep kin relationships synchronized with the kin and item catalogs.
- Use the approved roster documents for planning allocation, then promote complete packages into current catalogs.
- Keep Mountains and Swamps plural in region display names.
- Keep run generation, unlock evaluation, and completion mechanics in system documentation.
