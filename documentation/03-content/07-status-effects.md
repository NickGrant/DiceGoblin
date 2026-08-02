---
Title: "Status Effect Catalog"
Status: Canonical
Last Updated: 2026-08-02
Owner: Content Design + Systems Design
Depends On:
  - documentation/03-content/04-unit-abilities.md
  - documentation/03-content/05-enemy-abilities.md
  - documentation/03-content/14-dice-materials.md
  - documentation/02-systems/03-combat-resolution.md
Category: 03-content
Tags:
  - content
  - combat
  - statuses
---

# Status Effect Catalog

## Purpose

Define canonical player-visible combat statuses used by unit abilities, enemy abilities, and dice materials. This document owns status identity, display name, classification, authored effect, duration, stacking, and removal.

Resistance, tick processing, die scaling, application order, targeting, and event-log implementation belong in system documentation.

## Reading the Catalog

- Duration is measured in combat rounds unless stated otherwise.
- Source-defined strength means different sources may apply different values.
- Reapplication may refresh or strengthen a shared status without creating a new type.
- Internal reaction-readiness records and material-only temporary state are excluded.

## Buffs and Beneficial Conditions

| Key | Display name | Authored effect | Duration | Stacking or consumption | Current sources |
| --- | --- | --- | --- | --- | --- |
| `bolstered` | Bolstered | Increases Defense. Shield Up and Bolster Ally provide `25%`; passives may add Attack. | 2 | Refresh duration and retain strongest authored Defense and Attack values. | Shield Up, Bolster Ally, Rally Rhythm, Morale Goblin |
| `warcry` | Warcry | Increases Attack. Warcry provides `18%`; Swamp Holler provides `14%`. | 2 | Refresh duration; source and passives determine strength. | Warcry, Swamp Holler, Chant of Violence |
| `lucky` | Lucky | Adds `+2` to the next eligible action result. | Up to 2 | Consumed by next eligible action; reapplication refreshes without stacking. | Lucky Chant, Moonstone dice material |
| `taunting_guard` | Taunting Guard | Redirects the next eligible hostile attack to the guarded unit. Each Guard stack reduces damage by `1`. | 2 | Grants die-scaled stacks to `4`; all consumed by redirected attack. | Taunting Guard, Unmoving |
| `shield_set` | Shield Set | Grants `+1` Defense per stack. | 1 | Gain one when attacked to `3`; Wall of Scrap raises cap to `5`. | Shield Set, Wall of Scrap |
| `brawl_hardened_stacks` | Brawl Hardened | Each stack reduces the next incoming attack by `1`. | Until consumed | Gain one when attacked to `3`; all consumed by next attack. | Brawl Hardened |
| `crowd_favorite` | Crowd Favorite | Adds `+1` damage per stack. | Battle | Gain one when damaged to `5`. | Crowd Favorite |

## Debuffs and Hostile Conditions

| Key | Display name | Authored effect | Duration | Stacking or removal | Current sources |
| --- | --- | --- | --- | --- | --- |
| `sleep` | Sleep | Prevents acting. | 2 | Removed immediately when unit takes damage. | Sleep Dart |
| `poison` | Poison | Recurring damage using source Attack and poison ratio. Poison Cloud uses `0.25x`; Sporewood uses `0.15x`. | Source-defined; normally 2–4 | Reapplication refreshes duration and updates potency. | Poison Cloud, Nerve Toxin, Lingering Cloud, Sickly Weakness, Sporewood dice material |
| `marked` | Marked | Increases all damage taken by `15%`. | 3 | Refreshes duration; one distinct debuff type. | Mark Target, Barbed Mark |
| `cracked_armor` | Cracked Armor | Reduces Defense. Most abilities apply `-2`; Mud Slam applies `-3`; Rusted Iron applies up to `-2` from one ability. | 2 | Refresh duration and retain strongest reduction. | Crack Armor, Mud Sling, Mud Slam, Bog Splash, Shatter Plate, Break Open, Rusted Iron dice material |
| `cracked_skull` | Cracked Skull | Reduces Attack by `15%`. | 2 | Refreshes duration. | Skullcrack |
| `disarmed` | Disarmed | Reduces Attack by `18%`. | 2 | Refreshes duration; Disabling Hit may strengthen it. | Disarming Shot, Disabling Hit, Clean Shot |
| `menaced` | Menaced | Increases melee damage taken by `12%`. | 1 | Refreshes duration. | Menacing Follow-Through |
| `snared` | Snared | Counts as a distinct debuff type for debuff-sensitive effects. | 2 | Refreshes duration; no other current independent effect. | Barbed Mark |
| `wrestled` | Wrestled | Forces next eligible hostile action toward the wrestler. | 2 | Consumed by the forced action. | Wrestle |
| `fuse_lit` | Fuse Lit | Detonates on expiry for `0.90x` source Attack damage. | 1 | Reapplication refreshes fuse; detonation removes it. | Bomb Toss |

## Status Classification Summary

| Classification | Statuses |
| --- | --- |
| Buffs | `bolstered`, `warcry`, `lucky` |
| Defensive stack conditions | `taunting_guard`, `shield_set`, `brawl_hardened_stacks` |
| Offensive stack conditions | `crowd_favorite` |
| Control debuffs | `sleep`, `wrestled`, `snared` |
| Damage or vulnerability debuffs | `poison`, `marked`, `cracked_armor`, `menaced`, `fuse_lit` |
| Attack-reduction debuffs | `cracked_skull`, `disarmed` |

## Excluded Combat State

These records are not independent player-facing statuses:

- `counterpunch_ready`
- `dumb_luck_ready`
- `last_goblin_standing_ready`
- `spiteful_reflex`
- preferred-target and reaction bookkeeping
- Leather's Material Guard
- Gold battle reward markers
- Phoenix Ash and Living Bone once-per-battle trigger state

They are presented through their owning ability or material rather than separate Codex/status content.

## Material Duration Interaction

Amber adds `+1` round only to statuses with a numeric round duration. It does not extend:

- consumed-on-use conditions such as Lucky
- battle-long conditions such as Crowd Favorite
- stacks that last until consumed
- internal readiness or reward markers
- untimed state

## Open Questions

- Combat currently stores Taunting Guard under internal `guard_stacks`; implementation should translate or align it.
- `snared` has no unique behavior beyond debuff classification.
- Combat supports `bleeding`, but no current unit, enemy, or material canonically applies it.
- Presentation needs consistent remaining-round, stack-count, source, and strength display.

## Maintenance Notes

- Add a status here before or alongside any ability, enemy, material, encounter, or item that applies it.
- Reference one key across all sources sharing the behavior.
- Keep base values synchronized with ability and material catalogs.
- Keep formulas, timing, reapplication, and event processing in systems documentation.
- Do not promote internal trigger state into content unless it becomes independently visible and meaningful.
