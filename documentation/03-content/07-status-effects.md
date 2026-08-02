---
Title: "Status Effect Catalog"
Status: Canonical
Last Updated: 2026-08-01
Owner: Content Design + Systems Design
Depends On:
  - documentation/03-content/04-unit-abilities.md
  - documentation/03-content/05-enemy-abilities.md
  - documentation/02-systems/03-combat-resolution.md
Category: 03-content
Tags:
  - content
  - combat
  - statuses
---

# Status Effect Catalog

## Purpose

Define the canonical player-visible combat statuses used by current unit and enemy abilities. This document owns status identity, display name, buff or debuff classification, authored effect, normal duration, stacking behavior, and removal conditions.

Status resistance, tick processing, die scaling, application order, targeting resolution, and event-log implementation belong in system documentation.

## Reading the Catalog

- **Duration** is measured in combat rounds unless otherwise stated.
- **Source-defined strength** means more than one ability applies the same status at different values.
- A status may be refreshed or strengthened by another ability without becoming a new status type.
- Internal reaction-readiness records are not status content and are excluded from this catalog.

## Buffs and Beneficial Conditions

| Key | Display name | Authored effect | Normal duration | Stacking or consumption | Current sources |
| --- | --- | --- | --- | --- | --- |
| `bolstered` | Bolstered | Increases Defense. Bolster Ally and Shield Up currently provide a base `25%` Defense increase; passive effects may add an Attack bonus. | 2 | Reapplication refreshes duration and retains the strongest authored Defense and Attack values. | Shield Up, Bolster Ally, Rally Rhythm, Morale Goblin |
| `warcry` | Warcry | Increases Attack. Warcry provides a base `18%`; Swamp Holler provides a base `14%`. | 2 | Reapplication refreshes duration; source and passive modifiers determine strength. | Warcry, Swamp Holler, Chant of Violence |
| `lucky` | Lucky | Adds a base `+2` to the next eligible action result. | Up to 2 | Consumed by the next eligible action. | Lucky Chant |
| `guard_stacks` | Guard Stacks | Redirects the next eligible hostile attack to the guarded unit. Each stack reduces that attack's damage by `1`. | 2 | Taunting Guard grants a die-scaled number of stacks up to `4`; all stacks are consumed by the next incoming attack. | Taunting Guard, Unmoving |
| `shield_set` | Shield Set | Grants `+1` Defense per stack. | 1 | Gains one stack when attacked, up to `3`; Wall of Scrap raises the cap to `5`. | Shield Set, Wall of Scrap |
| `brawl_hardened_stacks` | Brawl Hardened | Each stack reduces damage from the next incoming attack by `1`. | Until consumed | Gains one stack when attacked, up to `3`; all stacks are consumed by the next incoming attack. | Brawl Hardened |
| `crowd_favorite` | Crowd Favorite | Adds `+1` damage per stack. | Battle | Gains one stack when damaged, up to `5`. | Crowd Favorite |

## Debuffs and Hostile Conditions

| Key | Display name | Authored effect | Normal duration | Stacking or removal | Current sources |
| --- | --- | --- | --- | --- | --- |
| `sleep` | Sleep | Prevents the affected unit from acting. | 2 | Removed immediately when the unit takes damage. | Sleep Dart |
| `poison` | Poison | Deals recurring damage using the source's Attack and poison ratio. Poison Cloud uses a base `0.25x` ratio and ticks every `5` status ticks; Nerve Toxin can also reduce the victim's Attack by `12%`. | 3 | Reapplication refreshes duration and updates potency. | Poison Cloud, Nerve Toxin, Lingering Cloud, Sickly Weakness |
| `marked` | Marked | Increases all damage taken by `15%`. | 3 | Reapplication refreshes duration; counts as one distinct debuff type. | Mark Target, Barbed Mark |
| `cracked_armor` | Cracked Armor | Reduces Defense. Most sources reduce it by `2`; Mud Slam reduces it by `3`. | 2 | Reapplication refreshes duration and retains the strongest reduction. | Crack Armor, Mud Sling, Mud Slam, Bog Splash, Shatter Plate, Break Open |
| `cracked_skull` | Cracked Skull | Reduces Attack by `15%`. | 2 | Reapplication refreshes duration. | Skullcrack |
| `disarmed` | Disarmed | Reduces Attack by `18%`. | 2 | Reapplication refreshes duration; Disabling Hit can strengthen the reduction. | Disarming Shot, Disabling Hit, Clean Shot |
| `menaced` | Menaced | Increases melee damage taken by `12%`. | 1 | Reapplication refreshes duration. | Menacing Follow-Through |
| `snared` | Snared | Counts as a distinct debuff type for debuff-sensitive effects. | 2 | Reapplication refreshes duration. No additional independent effect is currently authored. | Barbed Mark |
| `wrestled` | Wrestled | Forces the target's next eligible hostile action toward the wrestler. | 2 | Consumed when the forced hostile action occurs. | Wrestle |
| `fuse_lit` | Fuse Lit | Detonates when the status expires, dealing damage equal to a base `0.90x` of the source's Attack. | 1 | Reapplication refreshes the fuse; detonation removes it. | Bomb Toss |

## Status Classification Summary

| Classification | Statuses |
| --- | --- |
| Buffs | `bolstered`, `warcry`, `lucky` |
| Defensive stack conditions | `guard_stacks`, `shield_set`, `brawl_hardened_stacks` |
| Offensive stack conditions | `crowd_favorite` |
| Control debuffs | `sleep`, `wrestled`, `snared` |
| Damage or vulnerability debuffs | `poison`, `marked`, `cracked_armor`, `menaced`, `fuse_lit` |
| Attack-reduction debuffs | `cracked_skull`, `disarmed` |

## Excluded Internal Combat State

The following records may appear in combat state but are not independent player-facing status effects:

- `counterpunch_ready`
- `dumb_luck_ready`
- `last_goblin_standing_ready`
- `spiteful_reflex`
- preferred-target and reaction bookkeeping

They track whether a passive can trigger or has already triggered. Their player-facing content is defined by the corresponding passive ability, not by a separate status entry.

## Open Questions

- `snared` is current content but has no unique mechanical effect beyond counting as a distinct debuff. It needs an authored gameplay identity or an explicit decision to remain a setup-only marker.
- Combat implementation supports a `bleeding` vulnerability status, but no current non-dice unit or enemy ability canonically applies it. It is not current status content until an ability or encounter deliberately introduces it.
- Status presentation needs a consistent player-facing rule for showing exact remaining rounds, stack counts, and source-modified strength.

## Maintenance Notes

- Add a status here before or alongside any ability, encounter, or item that applies it.
- Reference one status key across player and enemy abilities when the behavior is shared.
- Keep base values synchronized with the ability catalogs.
- Keep resistance formulas, timing phases, reapplication algorithms, and event processing in system documentation.
- Do not promote internal trigger-state records into content unless they become visible, independently meaningful conditions.
