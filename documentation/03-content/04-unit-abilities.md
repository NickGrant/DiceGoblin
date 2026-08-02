---
Title: "Unit Ability Catalog"
Status: Canonical
Last Updated: 2026-08-01
Owner: Content Design
Depends On:
  - documentation/03-content/01-unit-types.md
  - documentation/02-systems/03-combat-resolution.md
Category: 03-content
Tags:
  - content
  - units
  - abilities
  - combat
---

# Unit Ability Catalog

## Purpose

Define the canonical active and passive abilities available to player units. This document owns ability identity, display name, authored timing, default targeting, base effect values, and native availability.

Combat scheduling, damage formulas, status resolution, targeting weights, inheritance, and promotion behavior belong in system documentation. Implementation data must remain consistent with this catalog.

Abilities defined here may also be assigned to enemies. Their effect remains the same unless an enemy-specific catalog entry explicitly defines a distinct ability.

## Scope

- Content category: Player and shared combat abilities.
- Player-facing surfaces: Unit details, ability loadouts, promotion choices, mastery choices, combat logs, and the Codex.
- Related content docs: Unit types and enemy abilities.
- Related system docs: Combat resolution, targeting, statuses, dice scaling, unit progression, and promotion.

## Reading the Catalog

- **Speed** is the number of ticks added to an active ability's cumulative action schedule. Lower values act sooner.
- **Dice cost** is the minimum number of equipped dice consumed when the ability resolves.
- **Default target** is the ability's normal target preference before passive abilities or other targeting rules modify it.
- **Base effect** records authored values before die-roll scaling, status interactions, passive modifiers, and other combat-system adjustments.
- **Availability** identifies the unit type that natively grants the ability or offers it as a level-10 mastery choice. Inherited abilities may appear on later unit types without being native to them.

## Active Abilities

| Key | Display name | Speed | Dice cost | Default target | Base effect | Native availability |
| --- | --- | ---: | ---: | --- | --- | --- |
| `basic_attack_melee` | Basic Attack (Melee) | 4 | 1 | Front enemy preferred | Deals `1.00x` melee damage. | Bruiser, Guardian, Bannerbearer, Juggernaut, Ironwall, Warchanter |
| `basic_attack_ranged` | Basic Attack (Ranged) | 4 | 1 | Back enemy preferred | Deals `1.00x` ranged damage. | Marksman, Saboteur, Sharpshot, Venomwright |
| `heavy_strike` | Heavy Strike | 8 | 1 | Front enemy preferred | Deals `1.60x` melee damage. | Bruiser, Juggernaut |
| `aimed_shot` | Aimed Shot | 8 | 1 | Back enemy preferred | Deals `1.60x` ranged damage. | Marksman, Sharpshot |
| `shield_up` | Shield Up | 10 | 1 | Self | Applies `bolstered`, increasing Defense by `25%` for `2` rounds. | Guardian, Ironwall |
| `bolster_ally` | Bolster Ally | 10 | 1 | Lowest-HP ally | Applies `bolstered`, increasing Defense by `25%` for `2` rounds. | Bannerbearer, Warchanter |
| `sleep_dart` | Sleep Dart | 12 | 2 | Back enemy preferred | Applies `sleep` for `2` rounds. Sleep ends when the target takes damage. | Saboteur, Venomwright |
| `skullcrack` | Skullcrack | 8 | 1 | Front enemy preferred | Deals `1.45x` melee damage and applies `cracked_skull`, reducing Attack by `15%` for `2` rounds. | Enforcer |
| `desperate_swing` | Desperate Swing | 8 | 1 | Front enemy preferred | Deals `1.25x` melee damage, increased by `35%` while the attacker is wounded. | Pit Fighter |
| `piercing_shot` | Piercing Shot | 8 | 1 | Back enemy preferred | Deals `1.50x` ranged damage and ignores `2` Defense. | Deadeye |
| `mark_target` | Mark Target | 9 | 1 | Back enemy preferred | Deals `0.75x` ranged damage and applies `marked`, increasing damage taken by `15%` for `3` rounds. | Trapper |
| `taunting_guard` | Taunting Guard | 9 | 1 | Self | Applies `taunting_guard` for `2` rounds, redirects an eligible attack, and grants up to `4` Guard stacks that each reduce damage by `1`. | Bulwark |
| `crack_armor` | Crack Armor | 8 | 1 | Front enemy preferred | Deals `1.20x` melee damage and applies `cracked_armor`, reducing Defense by `2` for `2` rounds. | Shieldbreaker |
| `warcry` | Warcry | 9 | 1 | Highest-Attack ally | Applies `warcry`, increasing Attack by `18%` for `2` rounds. | Warcaller, Warchanter |
| `lucky_chant` | Lucky Chant | 9 | 1 | Highest-Attack ally | Applies `lucky`, granting a base `+2` bonus to the ally's next eligible action for up to `2` rounds. | Mascot |
| `disarming_shot` | Disarming Shot | 8 | 1 | Back enemy preferred | Deals `1.10x` ranged damage and applies `disarmed`, reducing Attack by `18%` for `2` rounds. | Trickshot, Venomwright |
| `poison_cloud` | Poison Cloud | 10 | 1 | Back enemy preferred | Hits up to `2` enemies for `0.50x` damage and applies `poison` with a `0.25x` damage ratio for `3` rounds. | Plaguehand |

## Passive Abilities

### Shared Baseline Passives

| Key | Display name | Base effect | Native availability |
| --- | --- | --- | --- |
| `thick_hide` | Thick Hide | Grants `+2` Defense. | Bruiser, Guardian, Juggernaut, Ironwall |
| `sharpshooter` | Sharpshooter | Increases ranged damage by `15%`. | Marksman, Sharpshot |

### Bruiser Family

| Key | Display name | Base effect | Native availability |
| --- | --- | --- | --- |
| `brawl_hardened` | Brawl Hardened | When attacked, gains a damage-reduction stack, up to `3`. Each stack reduces the next incoming attack by `1`; all stacks are then consumed. | Bruiser mastery choice |
| `finisher` | Finisher | Increases damage against wounded enemies by `20%`. | Bruiser mastery choice |
| `menacing_follow_through` | Menacing Follow-Through | Active hits apply `menaced`, increasing melee damage taken by `12%` for `1` round. | Enforcer |
| `no_mercy` | No Mercy | Increases damage against wounded enemies by an additional `15%`. | Enforcer mastery choice |
| `brutal_suppression` | Brutal Suppression | Active attacks apply an additional `8%` Attack reduction for `2` rounds. | Enforcer mastery choice |
| `counterpunch` | Counterpunch | Once per round after taking a melee hit, retaliates for `0.70x` melee damage. | Pit Fighter |
| `last_goblin_standing` | Last Goblin Standing | Once per battle, survives an otherwise fatal hit at `1` HP. | Pit Fighter mastery choice |
| `crowd_favorite` | Crowd Favorite | Taking damage grants a stack, up to `5`; each stack adds `+1` damage for the remainder of the battle. | Pit Fighter mastery choice |

### Guardian Family

| Key | Display name | Base effect | Native availability |
| --- | --- | --- | --- |
| `bodyguard` | Bodyguard | Reduces damage taken by the lowest-HP ally by `15%` while the unit remains alive. | Guardian mastery choice |
| `hold_the_line` | Hold the Line | Grants `+2` Defense in the front row and an additional `+1` when adjacent to another front-row ally. | Guardian mastery choice |
| `shield_set` | Shield Set | Being attacked grants a Defense stack, up to `3`; each stack grants `+1` Defense. | Bulwark |
| `unmoving` | Unmoving | Reduces damage from attacks redirected by Taunting Guard by `2`. | Bulwark mastery choice |
| `wall_of_scrap` | Wall of Scrap | Increases Shield Set's stack cap by `2`. | Bulwark mastery choice |
| `find_the_gap` | Find the Gap | Attacks ignore `2` Defense. | Shieldbreaker |
| `shatter_plate` | Shatter Plate | Crack Armor reduces Defense by an additional `1`. | Shieldbreaker mastery choice |
| `break_open` | Break Open | Allies deal `12%` more damage to targets affected by `cracked_armor`. | Shieldbreaker mastery choice |

### Marksman Family

| Key | Display name | Base effect | Native availability |
| --- | --- | --- | --- |
| `patient_aim` | Patient Aim | Aimed Shot gains improved target priority and deals `18%` more damage. | Marksman mastery choice |
| `pick_your_mark` | Pick Your Mark | Single-target ranged attacks prefer the unit's previous valid target and deal `10%` more damage to that preferred target. | Marksman mastery choice |
| `vantage_point` | Vantage Point | Increases ranged damage by `8%` for each allied row positioned ahead of the unit. | Deadeye |
| `kill_lane` | Kill Lane | Increases damage dealt to enemy backline targets by `20%`. | Deadeye mastery choice |
| `armor_gap` | Armor Gap | Ranged attacks ignore `2` Defense. | Deadeye mastery choice |
| `treasure_sense` | Treasure Sense | Has a `10%` chance to reveal one hidden treasure node during a run, with a maximum of `1` reveal per run. | Trapper |
| `exposed_weaknesses` | Exposed Weaknesses | Adds `+1` damage for each distinct debuff type on the target, counting up to `3` types. | Trapper mastery choice |
| `barbed_mark` | Barbed Mark | Mark Target also applies `snared` for `2` rounds. | Trapper mastery choice |

### Banner Family

| Key | Display name | Base effect | Native availability |
| --- | --- | --- | --- |
| `rally_rhythm` | Rally Rhythm | Bolster effects also grant `+10%` Attack. | Bannerbearer mastery choice |
| `patch_job` | Patch Job | Bolstering a wounded ally restores `2` HP. | Bannerbearer mastery choice |
| `battle_tempo` | Battle Tempo | When a buffed ally defeats an enemy, spreads a momentum effect granting `+8%` Attack to another ally. | Warcaller, Warchanter |
| `chant_of_violence` | Chant of Violence | Increases Warcry's Attack bonus by an additional `8%`. | Warcaller mastery choice |
| `mob_mentality` | Mob Mentality | Increases damage against enemies already damaged during the round by `12%`. | Warcaller mastery choice |
| `attention_hog` | Attention Hog | Echoes `50%` of a support effect received by this unit to one ally. | Mascot |
| `dumb_luck` | Dumb Luck | Once per battle, a result of `2` or lower gains `+2`. | Mascot mastery choice |
| `morale_goblin` | Morale Goblin | Defeating an enemy bolsters the weakest ally by `12%`. | Mascot mastery choice |

### Saboteur Family

| Key | Display name | Base effect | Native availability |
| --- | --- | --- | --- |
| `toxic_tools` | Toxic Tools | Increases the potency of applied statuses by `15%`. | Saboteur mastery choice |
| `spiteful_reflex` | Spiteful Reflex | Reflects one incoming debuff per round. Reflected debuffs cannot recursively reflect. | Saboteur mastery choice |
| `opportunist` | Opportunist | Increases damage against debuffed enemies by `15%`. | Trickshot, Venomwright |
| `disabling_hit` | Disabling Hit | Disarming Shot reduces Attack by an additional `8%`. | Trickshot mastery choice |
| `clean_shot` | Clean Shot | Increases damage against `disarmed` enemies by `18%`. | Trickshot mastery choice |
| `nerve_toxin` | Nerve Toxin | Poisoned enemies deal `12%` less damage. | Plaguehand |
| `lingering_cloud` | Lingering Cloud | Increases Poison Cloud's poison damage by `15%` and its duration by `1` round. | Plaguehand mastery choice |
| `sickly_weakness` | Sickly Weakness | Poisoned targets count as having one additional distinct debuff type. | Plaguehand mastery choice |

## Open Questions

### Tier 3 Mastery Abilities

Tier 3 unit types do not yet have canonical mastery ability definitions. The following implementation placeholders are not current abilities and must not be exposed as selectable content until their names, effects, and authored values are defined here:

| Unit type | Undefined mastery keys |
| --- | --- |
| Juggernaut | `unstoppable_heap`, `skullquake` |
| Ironwall | `fortress_stance`, `last_wall` |
| Sharpshot | `perfect_lane`, `apex_predator` |
| Warchanter | `endless_chant`, `warband_legend` |
| Venomwright | `plague_mastery`, `cruel_setup` |

### Unassigned Ability Definitions

The implementation currently contains three definitions that are not assigned to any canonical unit or enemy type: `poison_stab`, `poison_arrow`, and `toxic_training`. They are not current content. A future content decision should either assign and document them or remove the implementation-only records.

## Maintenance Notes

- Add or revise an ability here before or alongside changes to unit packages, mastery choices, or combat implementation.
- Keep native availability synchronized with the unit type catalog.
- Treat implementation-only ability records as drift; they do not become current content until deliberately added to this catalog.
- Keep damage formulas, die-scaling formulas, status processing, targeting weights, and inheritance rules in system documentation.
