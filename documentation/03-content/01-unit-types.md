---
Title: "Unit Type Catalog"
Status: Canonical
Last Updated: 2026-08-01
Owner: Content Design
Depends On:
  - documentation/03-content/00-content-source-map.md
Category: 03-content
Tags:
  - content
  - units
  - progression
---

# Unit Type Catalog

## Purpose

Define the canonical player unit types, their combat values, progression growth, roles, and ability packages. Implementation data must remain consistent with this catalog.

Combat formulas, promotion mechanics, and ability behavior belong in system documentation. This document defines which unit types exist and the authored values assigned to them.

## Scope

- Content category: Player unit types.
- Player-facing surfaces: Warband, Academy, unit advancement, promotion, and Codex displays.
- Related system docs: Unit progression, promotion, combat, targeting, and ability documentation.

## Reading the Catalog

- Base stats and growth use `Attack / Defense / Max HP / Precision / Resolve` order.
- All current unit types have a maximum level of 10.
- All current unit types gain `+1 Precision` and `+1 Resolve` per level.
- Ability names are canonical content keys. Their mechanical definitions belong in the ability catalog and combat system documentation.

## Entries

### Bruiser Family

| Key | Display name | Tier | Role | Base stats | Growth per level | Actives | Passives | Content status | Notes |
| --- | --- | ---: | --- | --- | --- | --- | --- | --- | --- |
| `frontline_bruiser_t1` | Bruiser | 1 | Frontline | `5 / 3 / 22 / 5 / 5` | `1 / 1 / 2 / 1 / 1` | `basic_attack_melee`, `heavy_strike` | `thick_hide` | Active | Base bruiser type. |
| `frontline_bruiser_t2` | Enforcer | 2 | Frontline | `7 / 5 / 30 / 5 / 6` | `1 / 1 / 3 / 1 / 1` | `skullcrack` | `menacing_follow_through` | Active | Primary tier-two bruiser path. |
| `frontline_pit_fighter_t2` | Pit Fighter | 2 | Frontline | `8 / 4 / 28 / 5 / 6` | `2 / 1 / 3 / 1 / 1` | `desperate_swing` | `counterpunch` | Active | Alternate tier-two bruiser path focused on aggression and risk. |
| `frontline_bruiser_t3` | Juggernaut | 3 | Frontline | `9 / 7 / 40 / 4 / 7` | `1 / 2 / 4 / 1 / 1` | `basic_attack_melee`, `heavy_strike` | `thick_hide` | Active | Tier-three bruiser type. |

### Guardian Family

| Key | Display name | Tier | Role | Base stats | Growth per level | Actives | Passives | Content status | Notes |
| --- | --- | ---: | --- | --- | --- | --- | --- | --- | --- |
| `frontline_guardian_t1` | Guardian | 1 | Frontline | `3 / 5 / 24 / 4 / 6` | `1 / 2 / 2 / 1 / 1` | `basic_attack_melee`, `shield_up` | `thick_hide` | Active | Base guardian type. |
| `frontline_guardian_t2` | Bulwark | 2 | Frontline | `4 / 7 / 32 / 4 / 7` | `1 / 2 / 3 / 1 / 1` | `taunting_guard` | `shield_set` | Active | Primary tier-two guardian path. |
| `frontline_shieldbreaker_t2` | Shieldbreaker | 2 | Frontline | `6 / 6 / 30 / 5 / 6` | `1 / 2 / 3 / 1 / 1` | `crack_armor` | `find_the_gap` | Active | Alternate tier-two guardian path focused on breaking defenses. |
| `frontline_guardian_t3` | Ironwall | 3 | Frontline | `5 / 10 / 44 / 3 / 8` | `1 / 3 / 3 / 1 / 1` | `basic_attack_melee`, `shield_up` | `thick_hide` | Active | Tier-three guardian type. |

### Marksman Family

| Key | Display name | Tier | Role | Base stats | Growth per level | Actives | Passives | Content status | Notes |
| --- | --- | ---: | --- | --- | --- | --- | --- | --- | --- |
| `backline_marksman_t1` | Marksman | 1 | Backline | `6 / 2 / 18 / 6 / 4` | `2 / 1 / 2 / 1 / 1` | `basic_attack_ranged`, `aimed_shot` | `sharpshooter` | Active | Base marksman type. |
| `backline_marksman_t2` | Deadeye | 2 | Backline | `8 / 3 / 24 / 7 / 4` | `2 / 1 / 2 / 1 / 1` | `piercing_shot` | `vantage_point` | Active | Primary tier-two marksman path. |
| `backline_trapper_t2` | Trapper | 2 | Backline | `7 / 3 / 24 / 7 / 4` | `2 / 1 / 2 / 1 / 1` | `mark_target` | `treasure_sense` | Active | Alternate tier-two marksman path focused on setup and utility. |
| `backline_marksman_t3` | Sharpshot | 3 | Backline | `11 / 4 / 32 / 8 / 4` | `3 / 1 / 2 / 1 / 1` | `basic_attack_ranged`, `aimed_shot` | `sharpshooter` | Active | Tier-three marksman type. |

### Banner Family

| Key | Display name | Tier | Role | Base stats | Growth per level | Actives | Passives | Content status | Notes |
| --- | --- | ---: | --- | --- | --- | --- | --- | --- | --- |
| `support_banner_t1` | Bannerbearer | 1 | Support | `2 / 4 / 20 / 5 / 6` | `1 / 2 / 2 / 1 / 1` | `basic_attack_melee`, `bolster_ally` | — | Active | Base banner support type. |
| `support_banner_t2` | Warcaller | 2 | Support | `3 / 6 / 30 / 5 / 7` | `1 / 2 / 3 / 1 / 1` | `warcry` | `battle_tempo` | Active | Primary tier-two banner path. |
| `support_mascot_t2` | Mascot | 2 | Support | `2 / 5 / 28 / 5 / 6` | `1 / 2 / 3 / 1 / 1` | `lucky_chant` | `attention_hog` | Active | Alternate tier-two banner path focused on luck and morale. |
| `support_banner_t3` | Warchanter | 3 | Support | `4 / 8 / 38 / 5 / 8` | `1 / 2 / 3 / 1 / 1` | `basic_attack_melee`, `bolster_ally`, `warcry` | `battle_tempo` | Active | Tier-three banner support type. |

### Saboteur Family

| Key | Display name | Tier | Role | Base stats | Growth per level | Actives | Passives | Content status | Notes |
| --- | --- | ---: | --- | --- | --- | --- | --- | --- | --- |
| `control_saboteur_t1` | Saboteur | 1 | Utility | `4 / 3 / 18 / 6 / 4` | `2 / 1 / 2 / 1 / 1` | `basic_attack_ranged`, `sleep_dart` | — | Active | Base saboteur type. |
| `control_saboteur_t2` | Trickshot | 2 | Utility | `6 / 4 / 26 / 7 / 4` | `2 / 1 / 3 / 1 / 1` | `disarming_shot` | `opportunist` | Active | Primary tier-two saboteur path. |
| `control_plaguehand_t2` | Plaguehand | 2 | Utility | `5 / 4 / 24 / 6 / 4` | `2 / 1 / 2 / 1 / 1` | `poison_cloud` | `nerve_toxin` | Active | Alternate tier-two saboteur path focused on poison and debuffs. |
| `control_saboteur_t3` | Venomwright | 3 | Utility | `8 / 5 / 32 / 7 / 6` | `2 / 1 / 2 / 1 / 1` | `basic_attack_ranged`, `sleep_dart`, `disarming_shot` | `opportunist` | Active | Tier-three saboteur type. |

## Open Questions

- None.

## Maintenance Notes

- Content changes should update this catalog before or alongside implementation changes.
- A mismatch between this catalog and runtime data is implementation drift; it does not redefine the intended content.
- Keep promotion formulas, advancement behavior, capstone rules, and ability mechanics in their respective system documents.
