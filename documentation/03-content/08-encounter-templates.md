---
Title: "Encounter Template Catalog"
Status: Canonical
Last Updated: 2026-08-01
Owner: Content Design
Depends On:
  - documentation/03-content/03-enemy-types.md
  - documentation/03-content/06-biomes-and-regions.md
  - documentation/03-content/11-loot-and-reward-profiles.md
Category: 03-content
Tags:
  - content
  - encounters
  - combat
  - runs
---

# Encounter Template Catalog

## Purpose

Define the canonical authored encounter templates currently used by playable regions. This document owns encounter keys, region assignment, encounter type, difficulty rating, enemy formation, and player-facing presentation text.

Run-map placement, node selection weights, path generation, combat resolution, and reward materialization belong in system documentation. Hazards and shrines are generated from their own catalogs and are not repeated here.

## Reading the Catalog

- Formation coordinates use `(x,y)` on the current `3 × 3` combat grid.
- Repeated enemies are listed separately when they occupy different cells.
- **Difficulty** is the authored encounter rating, not a calculated power score.
- Loot and rest templates have no enemy formation.
- Dialogue and exit nodes are outside this catalog.

## The Farm

| Key | Type | Difficulty | Formation | Player-facing presentation |
| --- | --- | ---: | --- | --- |
| `the_farm_mud_combat_1` | Combat | 1 | Mudwrestler `(2,1)`; Mudslinger `(0,1)` | A pair of pigs lurches out of the muck, giving the warband its first real skirmish. |
| `the_farm_loot_1` | Loot | 1 | — | A crate of feed and spare gear sits untouched beside the fence line. |
| `the_farm_rest_1` | Rest | 1 | — | The warband catches its breath at a dry patch of hay before the final push. |
| `the_farm_mud_boss_1` | Boss | 2 | Mudking `(2,1)` | The Mudking snorts, stamps, and charges to defend the whole sty. |

### Farm Encounter Intent

- The first combat teaches front- and back-positioned enemies with a minimal two-unit formation.
- The loot and rest templates establish the basic noncombat node vocabulary.
- The boss is deliberately isolated so the Mudking's size and identity dominate the encounter.

## Mountains

### Kobold Combat Encounters

| Key | Type | Difficulty | Formation | Player-facing presentation |
| --- | --- | ---: | --- | --- |
| `mountains_kobold_combat_1` | Combat | 1 | Kobold Shieldbearer `(0,1)`; Kobold Skirmisher `(2,0)`; Kobold Skirmisher `(2,2)` | Loose stones shift underfoot as a kobold warband scrambles into position. |
| `mountains_kobold_combat_2` | Combat | 2 | Kobold Shieldbearer `(0,1)`; Kobold Skirmisher `(2,0)`; Kobold Skirmisher `(2,2)`; Kobold Sharpshooter `(1,0)` | Loose stones shift underfoot as a kobold warband scrambles into position. |
| `mountains_kobold_combat_3` | Combat | 3 | Kobold Shieldbearer `(0,0)`; Kobold Shieldbearer `(0,2)`; Kobold Sharpshooter `(2,1)` | Loose stones shift underfoot as a kobold warband scrambles into position. |
| `mountains_kobold_boss_1` | Boss | 5 | Kobold Warchief `(2,1)`; Kobold Sharpshooter `(1,0)`; Kobold Skirmisher `(2,0)`; Kobold Shieldbearer `(0,1)` | A warhorn screams through the crags. The kobold command has taken the field. |

### Mountain Loot Encounters

| Key | Difficulty | Player-facing presentation |
| --- | ---: | --- |
| `mountains_kobold_loot_1` | 1 | Before you lies a pile of bones and scraps. Underneath, something glints—salvage worth keeping. |
| `mountains_kobold_loot_2` | 2 | A collapsed supply crate is wedged between rocks. Most of it is ruined, but not all. |
| `mountains_kobold_loot_3` | 3 | You find a scorched campsite and a half-buried satchel. Whatever happened here, it ended fast. |

### Mountain Rest Encounters

| Key | Difficulty | Player-facing presentation |
| --- | ---: | --- |
| `mountains_kobold_rest_1` | 1 | A sheltered ledge offers a moment to breathe. You patch gear and steady your hands. |
| `mountains_kobold_rest_2` | 2 | Warm air rises from a crack in the stone. It is not safe, but it is quiet—for now. |

### Mountain Encounter Intent

- Shieldbearers protect accurate or explosive backline attackers.
- Difficulty increases through formation composition rather than direct stat variants.
- The boss combines every major kobold combat identity in one command formation.

## Swamps

### Frogman Combat Encounters

| Key | Type | Difficulty | Formation | Player-facing presentation |
| --- | --- | ---: | --- | --- |
| `swamps_frogman_combat_1` | Combat | 1 | Frogman Bruiser `(0,1)`; Frogman Bruiser `(0,2)`; Frogman Spearhunter `(1,1)` | Wet reeds part and frogmen emerge—quiet, patient, and hard to kill. |
| `swamps_frogman_combat_2` | Combat | 2 | Frogman Bruiser `(0,1)`; Frogman Spearhunter `(1,0)`; Frogman Spearhunter `(1,2)`; Frogman Wardrummer `(2,1)` | Wet reeds part and frogmen emerge—quiet, patient, and hard to kill. |
| `swamps_frogman_combat_3` | Combat | 3 | Frogman Bruiser `(0,0)`; Frogman Bruiser `(0,2)`; Frogman Wardrummer `(2,1)` | Wet reeds part and frogmen emerge—quiet, patient, and hard to kill. |
| `swamps_frogman_boss_1` | Boss | 5 | Bog Tyrant `(0,1)`; Frogman Bruiser `(0,0)`; Frogman Bruiser `(0,2)`; Frogman Wardrummer `(2,1)` | The swamp goes still. Something immense rises from the black water. |

### Swamp Loot Encounters

| Key | Difficulty | Player-facing presentation |
| --- | ---: | --- |
| `swamps_frogman_loot_1` | 1 | A waterlogged bundle hangs from a dead branch. Inside: salvage, wrapped tight against the muck. |
| `swamps_frogman_loot_2` | 2 | You pry open a half-sunk chest. The hinges scream, but the contents are still usable. |
| `swamps_frogman_loot_3` | 3 | Something is tangled in the reeds—gear left behind in a hurry. You take what you can. |

### Swamp Rest Encounters

| Key | Difficulty | Player-facing presentation |
| --- | ---: | --- |
| `swamps_frogman_rest_1` | 1 | You find a dry patch of ground and hold still long enough to recover. The swamp watches. |
| `swamps_frogman_rest_2` | 2 | A ring of standing stones breaks the wind and the insects. You rest, but do not sleep. |

### Swamp Encounter Intent

- Bruisers establish a durable front while Spearhunters exploit weakened defenses.
- Wardrummers turn later encounters into formation-support problems.
- The boss formation surrounds the Bog Tyrant with durable bodies and offensive support.

## Chaos and Mixed-Faction Encounters

| Key | Region | Type | Difficulty | Formation | Player-facing presentation |
| --- | --- | --- | ---: | --- | --- |
| `chaos_treasure_combat_1` | Mystic Cave | Chaos combat | 1 | Chaos Treasure Scavenger `(2,0)`; Chaos Treasure Scavenger `(2,2)` | A barely hostile treasure crew tumbles out of the Wrong Machine wake. |
| `mountains_kobold_chaos_combat_1` | Mountains | Chaos combat | 4 | Kobold Shieldbearer `(0,0)`; Kobold Warchief `(1,1)`; Kobold Sharpshooter `(2,0)`; Kobold Skirmisher `(2,2)` | A chaos-skewed kobold formation spills across the mountain route. |
| `mountains_chaos_elite_combat_1` | Mountains | Chaos combat | 5 | Chaos Faultbrute `(0,1)`; Chaos Glass Cannon `(2,0)`; Kobold Sharpshooter `(2,2)` | A mountain fault opens into a machine-made elite formation. |
| `swamps_frogman_chaos_combat_1` | Swamps | Chaos combat | 4 | Bog Tyrant `(0,1)`; Frogman Spearhunter `(1,0)`; Frogman Wardrummer `(1,2)`; Frogman Spearhunter `(2,1)` | A chaos-skewed frogman formation pulls the route into the muck. |
| `swamps_chaos_elite_combat_1` | Swamps | Chaos combat | 5 | Chaos Faultbrute `(0,1)`; Frogman Wardrummer `(1,0)`; Chaos Glass Cannon `(2,2)` | The swamp buckles around a machine-made elite formation. |

### Chaos Encounter Intent

- Chaos encounters reuse established faction pieces in distorted or unusually difficult formations.
- Chaos Faultbrutes anchor the front while Chaos Glass Cannons create extreme backline pressure.
- Mystic Cave treasure combat is intentionally much weaker than the elite chaos formations in later biomes.

## Encounter Count Summary

| Region or family | Combat | Boss | Loot | Rest | Chaos combat | Total |
| --- | ---: | ---: | ---: | ---: | ---: | ---: |
| The Farm | 1 | 1 | 1 | 1 | 0 | 4 |
| Mountains | 3 | 1 | 3 | 2 | 2 | 11 |
| Swamps | 3 | 1 | 3 | 2 | 2 | 11 |
| Mystic Cave | 0 | 0 | 0 | 0 | 1 | 1 |
| **Total** | **7** | **3** | **7** | **5** | **5** | **27** |

## Open Questions

- The Farm uses only one combat template, one loot template, and one rest template. Repeat runs may need more authored variation.
- Mountain and Swamp combat templates reuse one description across all regular battles. Distinct presentation text could better communicate each formation's identity.
- Chaos templates currently use the normal enemy roster and shared ability system. Future chaos-exclusive mechanics should be added deliberately rather than inferred from node type.
- Encounter reward references contain older named loot-table identifiers that no longer determine active grants. Current reward behavior is defined in the reward profile catalog.

## Maintenance Notes

- Add or revise encounters here before or alongside changes to formations and presentation copy.
- Keep enemy names and keys synchronized with the enemy type catalog.
- Keep reward behavior in the reward profile catalog rather than duplicating it per encounter.
- Keep hazards, shrines, dialogue, exit nodes, and run-map placement in their respective catalogs or system documentation.
