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

Catalog the unit type records currently seeded by backend migrations. This document records content values and identifiers; combat formulas and promotion rules belong in system documentation.

## Scope

- Content category: Player unit types.
- Current implementation source: `backend/migrations/30_seed_unit_types.sql`, `backend/migrations/50_seed_unit_progression_rework.sql`, `backend/migrations/51_seed_progression_branch_packages.sql`, `backend/migrations/62_seed_precision_resolve_stats.sql`, `backend/migrations/67_tier_three_progression_coverage.sql`, `backend/migrations/71_coalesce_unit_type_ability_sets.sql`, and `backend/migrations/77_unit_type_precision_resolve_growth.sql`.
- Player-facing surface: Warband, Academy, unit advancement, promotion, and Codex displays.
- Related system docs: Unit progression and combat documentation.

## Reading the Catalog

- Base stats and growth use `Attack / Defense / Max HP / Precision / Resolve` order.
- Every current unit type has a maximum level of 10 after migration 50.
- Every current unit type gains `+1 Precision` and `+1 Resolve` per level after migration 77.
- The ability columns reproduce the current stored `ability_set_json` package. They do not describe a unit instance's complete equipped loadout.

## Entries

### Bruiser Family

| Key | Display name | Tier | Role | Base stats | Growth per level | Stored actives | Stored passives | Primary seed | Status | Notes |
| --- | --- | ---: | --- | --- | --- | --- | --- | --- | --- | --- |
| `frontline_bruiser_t1` | Bruiser | 1 | Frontline | `5 / 3 / 22 / 5 / 5` | `1 / 1 / 2 / 1 / 1` | `basic_attack_melee`, `heavy_strike` | `thick_hide` | `30_seed_unit_types.sql` | Implemented | Base bruiser type. |
| `frontline_bruiser_t2` | Enforcer | 2 | Frontline | `7 / 5 / 30 / 5 / 6` | `1 / 1 / 3 / 1 / 1` | `skullcrack` | `menacing_follow_through` | `30_seed_unit_types.sql` | Implemented | Core tier-two bruiser package; ability package revised by migration 71. |
| `frontline_pit_fighter_t2` | Pit Fighter | 2 | Frontline | `8 / 4 / 28 / 5 / 6` | `2 / 1 / 3 / 1 / 1` | `desperate_swing` | `counterpunch` | `51_seed_progression_branch_packages.sql` | Implemented | Alternate tier-two bruiser package. |
| `frontline_bruiser_t3` | Juggernaut | 3 | Frontline | `9 / 7 / 40 / 4 / 7` | `1 / 2 / 4 / 1 / 1` | `basic_attack_melee`, `heavy_strike` | `thick_hide` | `30_seed_unit_types.sql` | Implemented | Tier-three bruiser type. |

### Guardian Family

| Key | Display name | Tier | Role | Base stats | Growth per level | Stored actives | Stored passives | Primary seed | Status | Notes |
| --- | --- | ---: | --- | --- | --- | --- | --- | --- | --- | --- |
| `frontline_guardian_t1` | Guardian | 1 | Frontline | `3 / 5 / 24 / 4 / 6` | `1 / 2 / 2 / 1 / 1` | `basic_attack_melee`, `shield_up` | `thick_hide` | `30_seed_unit_types.sql` | Implemented | Base guardian type. |
| `frontline_guardian_t2` | Bulwark | 2 | Frontline | `4 / 7 / 32 / 4 / 7` | `1 / 2 / 3 / 1 / 1` | `taunting_guard` | `shield_set` | `30_seed_unit_types.sql` | Implemented | Core tier-two guardian package; ability package revised by migration 71. |
| `frontline_shieldbreaker_t2` | Shieldbreaker | 2 | Frontline | `6 / 6 / 30 / 5 / 6` | `1 / 2 / 3 / 1 / 1` | `crack_armor` | `find_the_gap` | `51_seed_progression_branch_packages.sql` | Implemented | Alternate tier-two guardian package. |
| `frontline_guardian_t3` | Ironwall | 3 | Frontline | `5 / 10 / 44 / 3 / 8` | `1 / 3 / 3 / 1 / 1` | `basic_attack_melee`, `shield_up` | `thick_hide` | `30_seed_unit_types.sql` | Implemented | Tier-three guardian type. |

### Marksman Family

| Key | Display name | Tier | Role | Base stats | Growth per level | Stored actives | Stored passives | Primary seed | Status | Notes |
| --- | --- | ---: | --- | --- | --- | --- | --- | --- | --- | --- |
| `backline_marksman_t1` | Marksman | 1 | Backline | `6 / 2 / 18 / 6 / 4` | `2 / 1 / 2 / 1 / 1` | `basic_attack_ranged`, `aimed_shot` | `sharpshooter` | `30_seed_unit_types.sql` | Implemented | Base marksman type. |
| `backline_marksman_t2` | Deadeye | 2 | Backline | `8 / 3 / 24 / 7 / 4` | `2 / 1 / 2 / 1 / 1` | `piercing_shot` | `vantage_point` | `30_seed_unit_types.sql` | Implemented | Core tier-two marksman package; ability package revised by migration 71. |
| `backline_trapper_t2` | Trapper | 2 | Backline | `7 / 3 / 24 / 7 / 4` | `2 / 1 / 2 / 1 / 1` | `mark_target` | `treasure_sense` | `51_seed_progression_branch_packages.sql` | Implemented | Alternate tier-two marksman package. |
| `backline_marksman_t3` | Sharpshot | 3 | Backline | `11 / 4 / 32 / 8 / 4` | `3 / 1 / 2 / 1 / 1` | `basic_attack_ranged`, `aimed_shot` | `sharpshooter` | `30_seed_unit_types.sql` | Implemented | Tier-three marksman type. |

### Banner Family

| Key | Display name | Tier | Role | Base stats | Growth per level | Stored actives | Stored passives | Primary seed | Status | Notes |
| --- | --- | ---: | --- | --- | --- | --- | --- | --- | --- | --- |
| `support_banner_t1` | Bannerbearer | 1 | Support | `2 / 4 / 20 / 5 / 6` | `1 / 2 / 2 / 1 / 1` | `basic_attack_melee`, `bolster_ally` | — | `30_seed_unit_types.sql` | Implemented | Base banner support type. |
| `support_banner_t2` | Warcaller | 2 | Support | `3 / 6 / 30 / 5 / 7` | `1 / 2 / 3 / 1 / 1` | `warcry` | `battle_tempo` | `30_seed_unit_types.sql` | Implemented | Core tier-two banner package; ability package revised by migration 71. |
| `support_mascot_t2` | Mascot | 2 | Support | `2 / 5 / 28 / 5 / 6` | `1 / 2 / 3 / 1 / 1` | `lucky_chant` | `attention_hog` | `51_seed_progression_branch_packages.sql` | Implemented | Alternate tier-two banner package. |
| `support_banner_t3` | Warchanter | 3 | Support | `4 / 8 / 38 / 5 / 8` | `1 / 2 / 3 / 1 / 1` | `basic_attack_melee`, `bolster_ally`, `warcry` | `battle_tempo` | `67_tier_three_progression_coverage.sql` | Implemented | Tier-three banner support type. |

### Saboteur Family

| Key | Display name | Tier | Role | Base stats | Growth per level | Stored actives | Stored passives | Primary seed | Status | Notes |
| --- | --- | ---: | --- | --- | --- | --- | --- | --- | --- | --- |
| `control_saboteur_t1` | Saboteur | 1 | Utility | `4 / 3 / 18 / 6 / 4` | `2 / 1 / 2 / 1 / 1` | `basic_attack_ranged`, `sleep_dart` | — | `30_seed_unit_types.sql` | Implemented | Base saboteur type. |
| `control_saboteur_t2` | Trickshot | 2 | Utility | `6 / 4 / 26 / 7 / 4` | `2 / 1 / 3 / 1 / 1` | `disarming_shot` | `opportunist` | `30_seed_unit_types.sql` | Implemented | Core tier-two saboteur package; ability package revised by migration 71. |
| `control_plaguehand_t2` | Plaguehand | 2 | Utility | `5 / 4 / 24 / 6 / 4` | `2 / 1 / 2 / 1 / 1` | `poison_cloud` | `nerve_toxin` | `51_seed_progression_branch_packages.sql` | Implemented | Alternate tier-two saboteur package. |
| `control_saboteur_t3` | Venomwright | 3 | Utility | `8 / 5 / 32 / 7 / 6` | `2 / 1 / 2 / 1 / 1` | `basic_attack_ranged`, `sleep_dart`, `disarming_shot` | `opportunist` | `67_tier_three_progression_coverage.sql` | Implemented | Tier-three saboteur type. |

## Migration Overlays

- Migration 50 normalizes all unit types to level 10 and introduces promotion and capstone metadata.
- Migration 51 adds the five alternate tier-two packages and revises the core tier-two ability packages.
- Migration 62 adds Precision and Resolve to every unit type that existed at that point.
- Migration 67 adds Warchanter and Venomwright and completes tier-three progression metadata.
- Migration 71 coalesces promotion-granted abilities into `ability_set_json` and removes `promotion_grants_json`.
- Migration 77 adds `precision_per_level` and `resolve_per_level`, both defaulting to 1.

## Open Questions

- None identified from the current migration set.

## Maintenance Notes

- Add or remove entries when a migration inserts, deletes, or disables a `unit_types` record.
- Update current values when a later migration changes base stats, ability packages, level caps, or growth fields.
- Keep promotion formulas, advancement behavior, and capstone rules in system documentation rather than duplicating them here.
