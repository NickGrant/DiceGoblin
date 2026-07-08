# Region Unit Reference

Action sequences below use the current 20-tick scheduler:

- Queue the authored active loadout once in order.
- Spend leftover ticks on repeatable hostile actions that still fit.
- Do not repeat self-targeted or ally-targeted utility actions as filler.

Stats below are the authored template stats from `enemy_templates`. Passive effects are listed separately rather than folded into the base stat columns.

## The Farm

| unit_type | hp | attack | defense | passive_abilities | action sequence |
| --- | ---: | ---: | ---: | --- | --- |
| `mudwrestler` | 16 | 3 | 2 | none | `4:basic_attack_melee, 12:wrestle, 16:basic_attack_melee, 20:basic_attack_melee` |
| `mudslinger` | 14 | 4 | 1 | none | `4:basic_attack_ranged, 12:mud_sling, 16:basic_attack_ranged, 20:basic_attack_ranged` |
| `mudking` | 30 | 5 | 4 | `thick_hide` | `4:basic_attack_melee, 12:wrestle, 20:mud_slam` |

## Mountains

| unit_type | hp | attack | defense | passive_abilities | action sequence |
| --- | ---: | ---: | ---: | --- | --- |
| `kobold_shieldbearer` | 28 | 3 | 6 | `shield_set`, `wall_of_scrap`, `unmoving` | `4:basic_attack_melee, 13:taunting_guard, 17:basic_attack_melee` |
| `kobold_skirmisher` | 18 | 6 | 2 | `sharpshooter` | `8:bomb_toss, 12:basic_attack_ranged, 20:bomb_toss` |
| `kobold_sharpshooter` | 22 | 9 | 3 | `sharpshooter`, `clean_shot` | `4:basic_attack_ranged, 12:disarming_shot, 20:aimed_shot` |
| `kobold_warchief` | 42 | 11 | 4 | `sharpshooter`, `patient_aim`, `dumb_luck` | `8:bomb_toss, 12:basic_attack_ranged, 20:aimed_shot` |

## Swamps

| unit_type | hp | attack | defense | passive_abilities | action sequence |
| --- | ---: | ---: | ---: | --- | --- |
| `frogman_bruiser` | 30 | 4 | 5 | `thick_hide`, `brawl_hardened` | `4:basic_attack_melee, 12:bog_splash, 16:basic_attack_melee, 20:basic_attack_melee` |
| `frogman_spearhunter` | 24 | 7 | 3 | `find_the_gap` | `4:basic_attack_melee, 12:reed_spear, 20:heavy_strike` |
| `frogman_wardrummer` | 28 | 4 | 4 | `chant_of_violence`, `morale_goblin` | `4:basic_attack_melee, 12:swamp_holler, 16:basic_attack_melee, 20:basic_attack_melee` |
| `frogman_bog_tyrant` | 54 | 8 | 7 | `thick_hide`, `crowd_favorite` | `4:basic_attack_melee, 12:bog_splash, 20:skullcrack` |
