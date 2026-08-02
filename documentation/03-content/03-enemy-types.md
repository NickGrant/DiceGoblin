---
Title: "Enemy Type Catalog"
Status: Canonical
Last Updated: 2026-08-01
Owner: Content Design
Depends On:
  - documentation/03-content/00-content-source-map.md
Category: 03-content
Tags:
  - content
  - enemies
  - combat
---

# Enemy Type Catalog

## Purpose

Catalog the enemy templates currently seeded by backend migrations. This document records enemy identity, stored combat values, and content grouping without duplicating encounter composition or combat rules.

## Scope

- Content category: Enemy templates.
- Current implementation source: `backend/migrations/31_seed_enemy_templates.sql`, `backend/migrations/43_seed_farm_tutorial_content.sql`, `backend/migrations/53_rebalance_farm_pigs.sql`, `backend/migrations/54_rebalance_kobolds_frogmen.sql`, `backend/migrations/62_seed_precision_resolve_stats.sql`, and `backend/migrations/86_seed_chaos_encounter_templates.sql`.
- Player-facing surface: Combat encounters, region runs, rewards, and Codex displays.
- Related system docs: Combat resolution, encounter generation, targeting, and region documentation.

## Reading the Catalog

- Base stats use `Attack / Defense / Max HP / Precision / Resolve` order.
- Active and passive abilities reproduce the current stored `ability_set_json` values.
- Faction and archetype values come from `tags_json`.
- Encounter membership is intentionally omitted; consult encounter template migrations and region documentation for formations.

## Entries

### Pigs

| Key | Display name | Tier | Role | Base stats | Actives | Passives | XP | Archetype | Primary seed | Status | Notes |
| --- | --- | ---: | --- | --- | --- | --- | ---: | --- | --- | --- | --- |
| `mudwrestler` | Mudwrestler | 1 | Frontline | `3 / 2 / 16 / 5 / 5` | `basic_attack_melee`, `wrestle` | — | 8 | Grunt | `43_seed_farm_tutorial_content.sql` | Implemented | Melee tutorial enemy; ability set revised by migration 53. |
| `mudslinger` | Mudslinger | 1 | Backline | `4 / 1 / 14 / 6 / 4` | `basic_attack_ranged`, `mud_sling` | — | 8 | Grunt | `43_seed_farm_tutorial_content.sql` | Implemented | Ranged tutorial enemy; ability set revised by migration 53. |
| `mudking` | Mudking | 2 | Frontline | `5 / 4 / 30 / 5 / 7` | `basic_attack_melee`, `wrestle`, `mud_slam` | `thick_hide` | 16 | Boss | `43_seed_farm_tutorial_content.sql` | Implemented | Farm boss; ability set revised by migration 53. |

### Kobolds

| Key | Display name | Tier | Role | Base stats | Actives | Passives | XP | Archetype | Primary seed | Status | Notes |
| --- | --- | ---: | --- | --- | --- | --- | ---: | --- | --- | --- | --- |
| `kobold_skirmisher` | Kobold Skirmisher | 1 | Backline | `6 / 2 / 18 / 6 / 4` | `bomb_toss`, `basic_attack_ranged` | `sharpshooter` | 10 | Grunt | `31_seed_enemy_templates.sql` | Implemented | Ranged bomb-throwing grunt; rebalanced by migration 54. |
| `kobold_shieldbearer` | Kobold Shieldbearer | 1 | Frontline | `3 / 6 / 28 / 4 / 6` | `basic_attack_melee`, `taunting_guard` | `shield_set`, `wall_of_scrap`, `unmoving` | 10 | Grunt | `31_seed_enemy_templates.sql` | Implemented | Defensive frontline grunt; rebalanced by migration 54. |
| `kobold_sharpshooter` | Kobold Sharpshooter | 2 | Backline | `9 / 3 / 22 / 7 / 4` | `basic_attack_ranged`, `disarming_shot`, `aimed_shot` | `sharpshooter`, `clean_shot` | 15 | Elite | `31_seed_enemy_templates.sql` | Implemented | Ranged elite; rebalanced by migration 54. |
| `kobold_warchief` | Kobold Warchief | 3 | Backline | `11 / 4 / 42 / 7 / 5` | `bomb_toss`, `basic_attack_ranged`, `aimed_shot` | `sharpshooter`, `patient_aim`, `dumb_luck` | 30 | Boss | `31_seed_enemy_templates.sql` | Implemented | Kobold boss; rebalanced by migration 54. |

### Frogmen

| Key | Display name | Tier | Role | Base stats | Actives | Passives | XP | Archetype | Primary seed | Status | Notes |
| --- | --- | ---: | --- | --- | --- | --- | ---: | --- | --- | --- | --- |
| `frogman_bruiser` | Frogman Bruiser | 1 | Frontline | `4 / 5 / 30 / 5 / 6` | `basic_attack_melee`, `bog_splash` | `thick_hide`, `brawl_hardened` | 10 | Grunt | `31_seed_enemy_templates.sql` | Implemented | Durable frontline grunt; rebalanced by migration 54. |
| `frogman_spearhunter` | Frogman Spearhunter | 1 | Frontline | `7 / 3 / 24 / 5 / 5` | `basic_attack_melee`, `reed_spear`, `heavy_strike` | `find_the_gap` | 10 | Grunt | `31_seed_enemy_templates.sql` | Implemented | Offensive melee grunt; rebalanced by migration 54. |
| `frogman_wardrummer` | Frogman Wardrummer | 2 | Support | `4 / 4 / 28 / 5 / 6` | `basic_attack_melee`, `swamp_holler` | `chant_of_violence`, `morale_goblin` | 15 | Elite | `31_seed_enemy_templates.sql` | Implemented | Buffing support elite; rebalanced by migration 54. |
| `frogman_bog_tyrant` | Bog Tyrant | 3 | Frontline | `8 / 7 / 54 / 5 / 8` | `basic_attack_melee`, `bog_splash`, `skullcrack` | `thick_hide`, `crowd_favorite` | 30 | Boss | `31_seed_enemy_templates.sql` | Implemented | Frogman boss; rebalanced by migration 54. |

### Chaos

| Key | Display name | Tier | Role | Base stats | Actives | Passives | XP | Archetype | Primary seed | Status | Notes |
| --- | --- | ---: | --- | --- | --- | --- | ---: | --- | --- | --- | --- |
| `chaos_treasure_scavenger` | Chaos Treasure Scavenger | 1 | Support | `1 / 0 / 6 / 1 / 1` | `basic_attack_melee` | — | 2 | Treasure | `86_seed_chaos_encounter_templates.sql` | Implemented | Low-threat Mystic Cave treasure encounter enemy. |
| `chaos_faultbrute` | Chaos Faultbrute | 3 | Frontline | `10 / 8 / 58 / 5 / 7` | `basic_attack_melee`, `heavy_strike` | `thick_hide` | 35 | Elite | `86_seed_chaos_encounter_templates.sql` | Implemented | Heavy chaos elite used in mixed-region formations. |
| `chaos_glass_cannon` | Chaos Glass Cannon | 3 | Backline | `14 / 1 / 24 / 8 / 4` | `basic_attack_ranged`, `aimed_shot` | `sharpshooter` | 35 | Elite | `86_seed_chaos_encounter_templates.sql` | Implemented | High-attack, low-defense chaos elite. |

## Migration Overlays

- Migration 43 introduces the three Farm pig enemies.
- Migration 53 replaces the pig ability packages with their current themed abilities.
- Migration 54 replaces kobold and frogman base stats and ability packages with their current faction-specific values.
- Migration 62 adds Precision and Resolve to all pig, kobold, and frogman enemy templates.
- Migration 86 introduces the three chaos enemy templates with all five base stats already present.

## Open Questions

- Migration 86 seeds `ability_set_json` for chaos enemies but does not populate `equipped_abilities_json`, which was introduced earlier. Confirm whether runtime fallback is the intended permanent behavior or whether a later migration should normalize these loadouts.

## Maintenance Notes

- Add or remove entries when a migration inserts, deletes, or disables an `enemy_templates` record.
- Update current values when a later migration changes stats, abilities, rewards, tags, or display names.
- Keep encounter lineups and placement in encounter or region documentation rather than duplicating them here.
