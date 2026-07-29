# Chaos Reel Combat Authoring
----

Status: active  
Last Updated: 2026-07-29  
Owner: Design + Engineering  
Depends On: `documentation/02-systems-mvp/14-balancing-strategy-and-simulation.md`, `documentation/09-active-system-structure/05-run-node-generation.md`  

## Purpose

Chaos nodes are battle-backed encounters. The reels are not direct payout buttons; they are an authoring layer that selects combat pressure and reward hooks before the normal battle playback and reward claim flow.

## Finalization Contract

When a chaos result is finalized:

- The result status becomes `confirmed`.
- The run node receives a concrete `encounter_template_id`.
- The selected reels are copied into battle log metadata.
- Any chaos bonus rewards are stored under `rewards.chaos_bonus`.
- Rewards are claimed through the normal battle claim endpoint.

Finalization must be idempotent. Re-finalizing the same node should return the same confirmed reel result and should not duplicate battles, rewards, node unlocks, or currency grants.

## Reel Roles

### Enemy Family

The enemy family reel chooses the preferred enemy catalog:

- `pigs` prefers pig-family encounter templates.
- `kobolds` prefers kobold-family encounter templates.
- `frogmen` prefers frogman-family encounter templates.
- `mixed` may use the broadest regional combat pool.
- `mudkin`, `rust_cult`, `strays`, `bogbound`, `summit_raiders`, and `echoes` are launch-breadth entries that currently fall back through the normal template selection path until dedicated families are seeded.

If no family-specific template exists, the backend should fall back to regional combat templates, then global combat templates.

### Encounter Shape

The encounter shape reel describes the fight's tactical texture:

- `horde` biases toward more enemy bodies.
- `armored_frontline` biases toward tougher front-rank enemies.
- `ranged_backline` biases toward protected ranged pressure.
- `ambush` biases toward a dangerous opening state.
- `split_lane`, `heavy_anchor`, `glass_cannon`, `staggered_wave`, `shield_wall`, and `isolated_elite` are launch-breadth entries that currently express metadata pressure before bespoke shape filters land.

The first implementation may map shape onto existing templates. Richer implementations should encode the selected shape in resolver metadata and apply it as a deterministic modifier.

### Rule and Reward

The rule/reward reel chooses the special battle hook:

- `bolstered_enemies` can grant enemies a starting buff.
- `volatile_dice` can increase variance or roll pressure.
- `guaranteed_loot` can add a loot reward hook after victory.
- `raw_chaos_spark` can add Raw Chaos to the chaos bonus when the player has unlocked the relevant system.
- `teeth_rain`, `wounded_start`, `lucky_break`, `double_or_nothing`, `scrap_cache`, and `spiteful_rules` are launch-breadth entries that currently affect risk, multiplier, copy, and future hook metadata.

Rule effects should be represented in battle metadata even before every rule has bespoke combat logic. This keeps playback, reward previews, and future balancing tools aligned.

Current launch breadth:

| Reel | Enabled entries | Launch interpretation |
| --- | ---: | --- |
| Enemy family | 10 | Existing family filters where possible, then regional/global combat fallback. |
| Encounter shape | 10 | Metadata and risk contribution until bespoke template filters land. |
| Rule/reward | 10 | Reward multiplier, copy, and existing Raw Chaos/loot hooks where supported. |

## Battle Metadata

Battle logs for chaos nodes should include:

- `reward_multiplier`
- `summary.title`
- `summary.effect`
- `symbols`
- `reels`
- `rewards`

Future resolver modifiers should add a separate `modifiers` object rather than changing the original reel payload. The reel payload is the player's locked result; the modifier object is the backend's battle interpretation.

## Backlog Hooks

Backlog-ready extensions:

- Apply `bolstered_enemies` as a deterministic opening status package.
- Apply `ambush` as an opening initiative or targeting disadvantage.
- Apply `horde`, `armored_frontline`, and `ranged_backline` as encounter-template filters.
- Gate `raw_chaos_spark` payout behind Wrong Machine recovery.
- Promote `guaranteed_loot` into an explicit reward table entry rather than only soft currency.
- Add balance simulation coverage for reel combinations by region.

## Test Expectations

Coverage should prove:

- Generated and manipulated reels persist.
- Finalization is idempotent.
- Finalization binds an encounter template.
- The resulting battle log includes chaos metadata.
- Chaos bonus rewards are stored with battle rewards and claimed only once.
