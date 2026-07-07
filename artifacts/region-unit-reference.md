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
| `kobold_shieldbearer` | 26 | 3 | 6 | `thick_hide` | `4:basic_attack_melee, 14:shield_up, 18:basic_attack_melee` |
| `kobold_skirmisher` | 16 | 6 | 2 | `sharpshooter` | `4:basic_attack_ranged, 12:aimed_shot, 16:basic_attack_ranged, 20:basic_attack_ranged` |
| `kobold_sharpshooter` | 22 | 9 | 3 | `sharpshooter` | `4:basic_attack_ranged, 12:aimed_shot, 16:basic_attack_ranged, 20:basic_attack_ranged` |
| `kobold_warchief` | 40 | 11 | 4 | `sharpshooter` | `4:basic_attack_ranged, 12:aimed_shot, 16:basic_attack_ranged, 20:basic_attack_ranged` |

## Swamps

| unit_type | hp | attack | defense | passive_abilities | action sequence |
| --- | ---: | ---: | ---: | --- | --- |
| `frogman_bruiser` | 28 | 4 | 5 | `thick_hide` | `4:basic_attack_melee, 12:heavy_strike, 16:basic_attack_melee, 20:basic_attack_melee` |
| `frogman_spearhunter` | 24 | 6 | 4 | none | `4:basic_attack_melee, 12:heavy_strike, 16:basic_attack_melee, 20:basic_attack_melee` |
| `frogman_wardrummer` | 26 | 3 | 5 | none | `4:basic_attack_melee, 14:bolster_ally, 18:basic_attack_melee` |
| `frogman_bog_tyrant` | 50 | 8 | 7 | `thick_hide` | `4:basic_attack_melee, 12:heavy_strike, 16:basic_attack_melee, 20:basic_attack_melee` |
