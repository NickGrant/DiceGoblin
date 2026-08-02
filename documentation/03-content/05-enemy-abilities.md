---
Title: "Enemy Ability Catalog"
Status: Canonical
Last Updated: 2026-08-01
Owner: Content Design
Depends On:
  - documentation/03-content/03-enemy-types.md
  - documentation/03-content/04-unit-abilities.md
  - documentation/02-systems/03-combat-resolution.md
Category: 03-content
Tags:
  - content
  - enemies
  - abilities
  - combat
---

# Enemy Ability Catalog

## Purpose

Define the canonical abilities created specifically for enemy factions and identify the shared unit abilities currently used by enemies.

Shared abilities retain the exact definition established in the unit ability catalog. Enemy-exclusive entries in this document own their identity, authored timing, default targeting, base effect values, and faction availability.

Encounter composition, action scheduling, target resolution, die scaling, status processing, and combat formulas belong in system documentation. Implementation data must remain consistent with this catalog.

## Scope

- Content category: Enemy-exclusive abilities and enemy use of shared abilities.
- Player-facing surfaces: Combat actions, combat logs, enemy details, encounter previews, and the Codex.
- Related content docs: Enemy types and unit abilities.
- Related system docs: Combat resolution, targeting, statuses, and encounter composition.

## Reading the Catalog

- **Speed** is the number of ticks added to an active ability's cumulative action schedule. Lower values act sooner.
- **Dice cost** is the minimum number of dice consumed when the ability resolves.
- **Default target** is the normal target preference before passive abilities or encounter-specific targeting rules modify it.
- **Base effect** records authored values before die-roll scaling, status interactions, passive modifiers, and other combat-system adjustments.
- **Current users** lists the enemy types that natively possess the ability. Encounter or temporary effects may grant abilities through other systems.

## Enemy-Exclusive Active Abilities

### Farm Pigs

| Key | Display name | Speed | Dice cost | Default target | Base effect | Current users |
| --- | --- | ---: | ---: | --- | --- | --- |
| `wrestle` | Wrestle | 8 | 1 | Front enemy preferred | Deals `1.05x` melee damage and applies `wrestled` for `2` rounds, forcing the target's next eligible attack toward the wrestler. | Mudwrestler, Mudking |
| `mud_sling` | Mud Sling | 8 | 1 | Back enemy preferred | Deals `0.90x` ranged damage and applies `cracked_armor`, reducing Defense by `2` for `2` rounds. | Mudslinger |
| `mud_slam` | Mud Slam | 8 | 1 | Front enemy preferred | Deals `1.20x` melee damage and applies `cracked_armor`, reducing Defense by `3` for `2` rounds. | Mudking |

### Kobolds

| Key | Display name | Speed | Dice cost | Default target | Base effect | Current users |
| --- | --- | ---: | ---: | --- | --- | --- |
| `bomb_toss` | Bomb Toss | 8 | 1 | Back enemy preferred | Deals `0.75x` ranged damage and applies `fuse_lit`; after `1` round, the bomb detonates for a base `0.90x` damage ratio. | Kobold Skirmisher, Kobold Warchief |

### Frogmen

| Key | Display name | Speed | Dice cost | Default target | Base effect | Current users |
| --- | --- | ---: | ---: | --- | --- | --- |
| `bog_splash` | Bog Splash | 8 | 1 | Front enemy preferred | Deals `0.90x` melee damage and applies `cracked_armor`, reducing Defense by `2` for `2` rounds. | Frogman Bruiser, Bog Tyrant |
| `swamp_holler` | Swamp Holler | 8 | 1 | Highest-Attack ally | Applies `warcry`, increasing Attack by `14%` for `2` rounds. | Frogman Wardrummer |
| `reed_spear` | Reed Spear | 8 | 1 | Front enemy preferred | Deals `1.35x` melee damage and ignores `2` Defense. | Frogman Spearhunter |

## Shared Active Abilities Used by Enemies

The following abilities are canonically defined in `04-unit-abilities.md`. Enemy use does not create a separate version of the ability.

| Ability | Current enemy users |
| --- | --- |
| `basic_attack_melee` | Mudwrestler, Mudking, Kobold Shieldbearer, Frogman Bruiser, Frogman Spearhunter, Frogman Wardrummer, Bog Tyrant, Chaos Treasure Scavenger, Chaos Faultbrute |
| `basic_attack_ranged` | Mudslinger, Kobold Skirmisher, Kobold Sharpshooter, Kobold Warchief, Chaos Glass Cannon |
| `heavy_strike` | Frogman Spearhunter, Chaos Faultbrute |
| `aimed_shot` | Kobold Sharpshooter, Kobold Warchief, Chaos Glass Cannon |
| `skullcrack` | Bog Tyrant |
| `taunting_guard` | Kobold Shieldbearer |
| `disarming_shot` | Kobold Sharpshooter |

## Shared Passive Abilities Used by Enemies

The following passives are canonically defined in `04-unit-abilities.md`. Their values and behavior are identical for player units and enemies.

| Ability | Current enemy users |
| --- | --- |
| `thick_hide` | Mudking, Frogman Bruiser, Bog Tyrant, Chaos Faultbrute |
| `sharpshooter` | Kobold Skirmisher, Kobold Sharpshooter, Kobold Warchief, Chaos Glass Cannon |
| `shield_set` | Kobold Shieldbearer |
| `wall_of_scrap` | Kobold Shieldbearer |
| `unmoving` | Kobold Shieldbearer |
| `clean_shot` | Kobold Sharpshooter |
| `patient_aim` | Kobold Warchief |
| `dumb_luck` | Kobold Warchief |
| `brawl_hardened` | Frogman Bruiser |
| `find_the_gap` | Frogman Spearhunter |
| `chant_of_violence` | Frogman Wardrummer |
| `morale_goblin` | Frogman Wardrummer |
| `crowd_favorite` | Bog Tyrant |

## Faction Ability Identity

- **Farm pigs** use mud, forced engagement, and Defense reduction to disrupt formation stability.
- **Kobolds** combine explosives, accurate ranged attacks, and layered defensive passives.
- **Frogmen** combine durable melee pressure, armor exploitation, and formation-wide offensive support.
- **Chaos beings** currently use extreme stat profiles paired with shared baseline abilities rather than a distinct exclusive ability package.

## Open Questions

- Chaos beings do not yet have a faction-exclusive ability identity. Their current distinction comes from stat profiles and encounter composition.
- No current enemy-exclusive passive abilities exist. Future faction passives should be defined here rather than added as undocumented variations of shared unit passives.

## Maintenance Notes

- Add or revise enemy-exclusive abilities here before or alongside changes to enemy packages or combat implementation.
- Keep current users synchronized with the enemy type catalog.
- Do not duplicate shared ability definitions. Update the unit ability catalog when an ability's shared effect changes.
- Keep encounter lineups, targeting rules, die-scaling formulas, status processing, and combat formulas in their respective system documents.
