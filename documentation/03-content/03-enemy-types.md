---
Title: "Enemy Type Catalog"
Status: Canonical
Last Updated: 2026-08-02
Owner: Content Design
Depends On:
  - documentation/03-content/00-content-source-map.md
  - documentation/07-development-path/01-base-game-content-roster.md
  - documentation/07-development-path/02-night-expansion-content-roster.md
Category: 03-content
Tags:
  - content
  - enemies
  - combat
---

# Enemy Type Catalog

## Purpose

Define the canonical current enemy types, their faction identity, combat values, rewards, and ability packages. Implementation data must remain consistent with this catalog.

Encounter composition, placement, targeting, and combat formulas belong in system or encounter documentation. This document defines which concrete enemy units currently exist and the authored values assigned to them.

Approved future enemy families are recorded separately. A family name and biome pairing do not create current enemy units without complete entries.

## Scope

- Content category: Enemy types.
- Player-facing surfaces: Combat encounters, region runs, rewards, and Codex displays.
- Related system docs: Combat resolution, encounter generation, targeting, abilities, and regions.

## Reading the Catalog

- Base stats use `Attack / Defense / Max HP / Precision / Resolve` order.
- Ability names are canonical content keys. Their mechanical definitions belong in the ability catalog and combat system documentation.
- Tier indicates the enemy's general content tier, not an encounter's total difficulty.
- XP is the base reward assigned to defeating one instance of the enemy.
- Approved planning families do not yet define unit slugs, roles, stats, abilities, XP, bosses, or encounters.

## Current Entries

### Pigs

| Key | Display name | Tier | Role | Base stats | Actives | Passives | XP | Archetype | Content status | Notes |
| --- | --- | ---: | --- | --- | --- | --- | ---: | --- | --- | --- |
| `mudwrestler` | Mudwrestler | 1 | Frontline | `3 / 2 / 16 / 5 / 5` | `basic_attack_melee`, `wrestle` | — | 8 | Grunt | Active | Melee Farm enemy that controls space through wrestling attacks. |
| `mudslinger` | Mudslinger | 1 | Backline | `4 / 1 / 14 / 6 / 4` | `basic_attack_ranged`, `mud_sling` | — | 8 | Grunt | Active | Ranged Farm enemy that attacks from behind the frontline. |
| `mudking` | Mudking | 2 | Frontline | `5 / 4 / 30 / 5 / 7` | `basic_attack_melee`, `wrestle`, `mud_slam` | `thick_hide` | 16 | Boss | Active | Boss of The Farm and leader of the pig enemies. |

### Kobolds

| Key | Display name | Tier | Role | Base stats | Actives | Passives | XP | Archetype | Content status | Notes |
| --- | --- | ---: | --- | --- | --- | --- | ---: | --- | --- | --- |
| `kobold_skirmisher` | Kobold Skirmisher | 1 | Backline | `6 / 2 / 18 / 6 / 4` | `bomb_toss`, `basic_attack_ranged` | `sharpshooter` | 10 | Grunt | Active | Mobile bomb-throwing ranged enemy. |
| `kobold_shieldbearer` | Kobold Shieldbearer | 1 | Frontline | `3 / 6 / 28 / 4 / 6` | `basic_attack_melee`, `taunting_guard` | `shield_set`, `wall_of_scrap`, `unmoving` | 10 | Grunt | Active | Defensive frontline enemy that protects the formation. |
| `kobold_sharpshooter` | Kobold Sharpshooter | 2 | Backline | `9 / 3 / 22 / 7 / 4` | `basic_attack_ranged`, `disarming_shot`, `aimed_shot` | `sharpshooter`, `clean_shot` | 15 | Elite | Active | Precision-focused ranged elite. |
| `kobold_warchief` | Kobold Warchief | 3 | Backline | `11 / 4 / 42 / 7 / 5` | `bomb_toss`, `basic_attack_ranged`, `aimed_shot` | `sharpshooter`, `patient_aim`, `dumb_luck` | 30 | Boss | Active | Kobold boss combining explosives with accurate ranged attacks. |

### Frogmen

| Key | Display name | Tier | Role | Base stats | Actives | Passives | XP | Archetype | Content status | Notes |
| --- | --- | ---: | --- | --- | --- | --- | ---: | --- | --- | --- |
| `frogman_bruiser` | Frogman Bruiser | 1 | Frontline | `4 / 5 / 30 / 5 / 6` | `basic_attack_melee`, `bog_splash` | `thick_hide`, `brawl_hardened` | 10 | Grunt | Active | Durable frontline enemy built for extended fights. |
| `frogman_spearhunter` | Frogman Spearhunter | 1 | Frontline | `7 / 3 / 24 / 5 / 5` | `basic_attack_melee`, `reed_spear`, `heavy_strike` | `find_the_gap` | 10 | Grunt | Active | Offensive melee enemy focused on exploiting weak defenses. |
| `frogman_wardrummer` | Frogman Wardrummer | 2 | Support | `4 / 4 / 28 / 5 / 6` | `basic_attack_melee`, `swamp_holler` | `chant_of_violence`, `morale_goblin` | 15 | Elite | Active | Support elite that strengthens the frogman formation. |
| `frogman_bog_tyrant` | Bog Tyrant | 3 | Frontline | `8 / 7 / 54 / 5 / 8` | `basic_attack_melee`, `bog_splash`, `skullcrack` | `thick_hide`, `crowd_favorite` | 30 | Boss | Active | Frogman boss built around durability and heavy melee pressure. |

### Chaos Beings

| Key | Display name | Tier | Role | Base stats | Actives | Passives | XP | Archetype | Content status | Notes |
| --- | --- | ---: | --- | --- | --- | --- | ---: | --- | --- | --- |
| `chaos_treasure_scavenger` | Chaos Treasure Scavenger | 1 | Support | `1 / 0 / 6 / 1 / 1` | `basic_attack_melee` | — | 2 | Treasure | Active | Low-threat chaos being associated with Mystic Cave treasure encounters. |
| `chaos_faultbrute` | Chaos Faultbrute | 3 | Frontline | `10 / 8 / 58 / 5 / 7` | `basic_attack_melee`, `heavy_strike` | `thick_hide` | 35 | Elite | Active | Heavy chaos elite built to anchor mixed enemy formations. |
| `chaos_glass_cannon` | Chaos Glass Cannon | 3 | Backline | `14 / 1 / 24 / 8 / 4` | `basic_attack_ranged`, `aimed_shot` | `sharpshooter` | 35 | Elite | Active | High-damage ranged chaos elite with minimal defenses. |

## Approved Base-Game Enemy Families

| Biome | Enemy family | Planning status | High-level identity |
| --- | --- | --- | --- |
| The Farm | Pigs | Current | Farmyard occupiers led by the Mudking. |
| Mountains | Kobolds | Current | Tinkerers, trap makers, and scrap-built defenders. |
| Swamps | Frogmen | Current | Marsh fighters, hunters, supporters, and regional rulers. |
| Island | Monkeys | Approved post-demo planning | Agile thieves and climbers organized into harvesting crews, coastal patrols, and treetop guards. |
| Tundra | Walrusfolk | Approved post-demo planning | Large scavengers divided into hauling crews, hunters, and armored defenders. |
| Volcano | Salamanders | Approved post-demo planning | Heat-resistant scavengers forced into foundry and weapon-making roles. |
| Wasteland | Vultures | Approved post-demo planning | Carrion scavengers assigned to battlefield cleaning, corpse collection, and equipment reclamation. |
| River | Otters | Approved post-demo planning | Playful thieves forced to operate dams, ferries, locks, and checkpoints. |
| Orchard | Wasps | Approved post-demo planning | Fruit thieves reorganized into harvesting, pollination, and defense castes. |
| Savanna | Hyenas | Approved post-demo planning | Pack scavengers subjected to strict hunting ranks and coordinated formations. |

Mystic Cave uses chaos beings rather than a standard creature-family progression package. The Library uses The Archivist's order-aligned forces, including armored humanoid defenders, and requires a future concrete enemy roster.

The complete allocation is owned by `documentation/07-development-path/01-base-game-content-roster.md`.

## Night Expansion Enemy Families

| Biome | Enemy family | Planning status | High-level identity |
| --- | --- | --- | --- |
| Ruins | Raccoons | Reserved for Night | Artifact thieves forced to catalogue and preserve what they would rather dismantle or hoard. |
| Meadow | Moths | Reserved for Night | Nocturnal creatures made into lantern keepers and signal-tower attendants. |
| Cemetery | Crows | Reserved for Night | Scavengers turned into undertakers, grave wardens, and custodians of the dead. |

These families are excluded from base-game encounters, rewards, and Codex pools. Their allocation is owned by `documentation/07-development-path/02-night-expansion-content-roster.md`.

## Promotion Requirements

A planned enemy family becomes current only after this catalog defines concrete unit entries and the surrounding catalogs define:

- unit keys, display names, roles, tiers, stats, abilities, passives, XP, and archetypes;
- boss or climax identity;
- encounter formations and biome placement;
- reward, item, Codex, dialogue, and art integration;
- any family-specific ability definitions.

## Open Questions

- Concrete unit rosters and bosses for every post-Swamps base-game biome, The Library, and all Night regions remain future design work.

## Maintenance Notes

- Content changes should update this catalog before or alongside implementation changes.
- Update approved roster documents before changing future biome-family allocation.
- A mismatch between this catalog and runtime data is implementation drift; it does not redefine intended current enemy content.
- Keep encounter lineups, placement, targeting, and ability mechanics in their respective system or encounter documents.
- Keep Mountains and Swamps plural when naming the regions.
