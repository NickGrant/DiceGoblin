# Active Ability Status Audit

Source of truth: [backend/src/Combat/Abilities/AbilityRegistry.php](/abs/path/C:/xampp/htdocs/dice-goblin/backend/src/Combat/Abilities/AbilityRegistry.php)

Purpose: confirm that active abilities only apply statuses when the authored ability definition explicitly says so.

## Summary

- Active abilities audited: `26`
- Active abilities with explicit `status_id`: `19`
- Active abilities with no `status_id`: `7`
- Active abilities missing `duration_rounds` while also declaring a `status_id`: `0`

## Active Abilities With Explicit `status_id`

| ability_id | display_name | status_id | duration_rounds | notes |
| --- | --- | --- | --- | --- |
| `shield_up` | Shield Up | `bolstered` | `2` | Self defensive buff |
| `bolster_ally` | Bolster Ally | `bolstered` | `2` | Ally defensive buff |
| `poison_stab` | Poison Stab | `poison` | `3` | Also sets poison payload values |
| `poison_arrow` | Poison Arrow | `poison` | `3` | Also sets poison payload values |
| `sleep_dart` | Sleep Dart | `sleep` | `2` | Pure control action |
| `skullcrack` | Skullcrack | `cracked_skull` | `2` | Attack reduction debuff |
| `mark_target` | Mark Target | `marked` | `3` | Increased damage taken |
| `taunting_guard` | Taunting Guard | `taunting_guard` | `2` | Tank redirect / guard setup |
| `crack_armor` | Crack Armor | `cracked_armor` | `2` | Flat defense reduction |
| `wrestle` | Wrestle | `wrestled` | `2` | Forces next hostile attack target |
| `mud_sling` | Mud Sling | `cracked_armor` | `2` | Mud-flavored armor break |
| `mud_slam` | Mud Slam | `cracked_armor` | `2` | Stronger armor break |
| `warcry` | Warcry | `warcry` | `2` | Ally attack buff |
| `lucky_chant` | Lucky Chant | `lucky` | `2` | Ally next-action boost |
| `disarming_shot` | Disarming Shot | `disarmed` | `2` | Attack reduction debuff |
| `poison_cloud` | Poison Cloud | `poison` | `3` | Multi-target poison |
| `bomb_toss` | Bomb Toss | `fuse_lit` | `1` | Delayed explosive pressure |
| `bog_splash` | Bog Splash | `cracked_armor` | `2` | Swamp-flavored armor break |
| `swamp_holler` | Swamp Holler | `warcry` | `2` | Rough offensive support buff |

## Active Abilities With No `status_id`

These now rely entirely on their direct authored payload and should never apply a hidden combat status.

| ability_id | display_name | notes |
| --- | --- | --- |
| `basic_attack_melee` | Basic Attack (Melee) | Baseline direct damage only |
| `basic_attack_ranged` | Basic Attack (Ranged) | Baseline direct damage only |
| `heavy_strike` | Heavy Strike | Burst damage only |
| `aimed_shot` | Aimed Shot | Burst damage only |
| `desperate_swing` | Desperate Swing | Damage scales from self wounded state |
| `piercing_shot` | Piercing Shot | Uses direct defense ignore, not a status |
| `reed_spear` | Reed Spear | Uses direct defense ignore, not a status |

## Audit Notes

- The hidden resolver fallback that used to infer statuses from ability names has been removed.
- The generic basic-attack bleed fallback has been removed.
- Every active ability that currently authors a `status_id` also authors `duration_rounds`.
- `piercing_shot` is a useful example of a non-status secondary effect: it changes resolution with `ignore_defense_flat` and does not need a combat status.
- Future rule of thumb: if an effect should persist or be shown in status UI, author a `status_id`; if it is immediate resolution math only, keep it as direct params with no status.
