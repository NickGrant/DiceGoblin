# Balancing Strategy and Simulation
----

Status: active
Last Updated: 2026-07-25
Owner: Product + Systems Design + Engineering
Depends On: `documentation/02-systems-mvp/00-combat-system.md`, `documentation/02-systems-mvp/01-dice-system.md`, `documentation/02-systems-mvp/02-units-and-progression.md`, `documentation/02-systems-mvp/04-loot-and-drop-scope.md`, `documentation/02-systems-mvp/13-wrong-machine-and-kin.md`

## Purpose

- Define how Dice Goblins should evaluate balance while the game is still changing quickly.
- Separate numeric validation from player-feel validation.
- Establish a future simulation tool as part of the normal design workflow.

## Strategy

Balance work should be treated as an evidence loop:

1. Define the player outcome we want.
2. Measure the current behavior.
3. Change one or a small number of inputs.
4. Simulate enough samples to understand the numeric impact.
5. Playtest the result for clarity, pacing, and fun.
6. Record the decision in docs, seed data, or backlog notes.

Simulation should guide design decisions, not replace them. A result can be numerically fair and still feel confusing, slow, repetitive, or unrewarding.

## Primary Balance Questions

Early balancing should answer these questions before trying to tune every number perfectly:

- How often does a fresh starter squad win each region's normal combat nodes?
- How often does a fresh or appropriately leveled squad beat each region boss?
- How many nodes does an average successful run clear?
- How many rounds does an average battle last?
- How often does a unit die or end combat at low HP?
- How much XP, soft currency, Raw Chaos, and item progress does a player earn per run?
- How many runs does it take to unlock a new feature, region, kin, or promotion?
- How swingy are outcomes when the same setup is simulated across many deterministic seeds?
- Which unit, dice, ability, or kin choices dominate the result space?
- Which failures are caused by bad player choices versus impossible or hidden math?

## Metrics To Track

Combat simulation should report at least:

- win rate
- average rounds
- round distribution
- average unit HP remaining
- defeat count per battle
- damage dealt and received by unit
- ability use frequency
- dice contribution by size, rarity, and equipped slot
- enemy survival and damage contribution

Run simulation should report at least:

- completion rate
- average nodes resolved
- average rewards earned
- XP per deployed unit
- currency per run
- item quantities per run
- boss clear rate
- first-clear reward behavior
- run failure cause when available

Progression simulation should report at least:

- runs to next region
- runs to first promotion
- runs to Wrong Machine unlock
- runs to Pig Kin reconstruction
- material shortfall by item
- Raw Chaos availability
- p50, p75, p90, and worst-observed time-to-goal

## Balance Targets

Initial targets are intentionally broad. They should become narrower only after local playtests and early player data expose real pain points.

| Area | Starting Target |
| --- | --- |
| Early normal combat | Fresh starter squads should usually win, but with visible HP loss. |
| Early boss combat | First boss victories should feel earned and may require a few upgrades or better dice use. |
| Battle length | Most normal battles should resolve in a small number of rounds; bosses can last longer. |
| Required progression | Required unlocks should not depend on unprotected rare drops. |
| Kin unlock pacing | First Pig Kin reconstruction should be reachable soon after the Wrong Machine tutorial. |
| Basic Goblin viability | Basic Goblins should remain usable after Pig Kin unlocks. |
| Rewards | Repeated runs should produce visible progress even when they do not produce a major unlock. |
| Variance | Randomness should create memorable differences without making the player feel the outcome was predetermined by bad luck. |

## Simulation Tool Direction

Because combat and run resolution are backend-authoritative, the preferred first tool is a repository-local simulation command rather than an external balancing platform.

The first version runs inside the backend/Docker environment and supports deterministic seed batches such as:

```text
docker compose exec -T backend sh -lc "php bin/simulate.php --mode=battle --region=the_farm --node=combat --runs=100"
docker compose exec -T backend sh -lc "php bin/simulate.php --mode=run --region=the_farm --runs=100 --format=json"
docker compose exec -T backend sh -lc "php bin/simulate.php --mode=progression --goal=all --region=the_farm --runs=100 --max-runs=25 --format=json"
docker compose exec -T backend sh -lc "php bin/simulate.php --mode=run --region=the_farm --profile=pig_kin_starter --runs=100 --format=json"
```

Repository shortcuts cover the common farm suites used in PR validation:

```text
npm.cmd run test:db:reset:docker
npm.cmd run sim:balance:battle:farm:docker
npm.cmd run sim:balance:run:farm:docker
```

The tool outputs machine-readable JSON with `--format=json` and a concise human-readable summary by default. JSON output lets later work compare balance changes in CI, scripts, spreadsheets, or dashboards without rewriting the simulator.

Current modes:

- `battle`: resolves one node type repeatedly for a throwaway simulation account.
- `run`: resolves a representative mini-path containing combat, loot, hazard, shrine, and boss nodes.
- `progression`: repeatedly resolves the representative mini-path for each sample and reports named time-to-goal summaries.

Simulation profiles:

- `fresh_starter`: the normal starter bootstrap profile.
- `basic_goblin_starter`: the starter squad forced to Basic Goblin kin for comparison.
- `pig_kin_starter`: the starter squad forced to Pig Kin with the Pig Kin lineage unlocked for comparison.

Progression mode supports `--goal=all`, `first_promotion`, `next_region`, `wrong_machine`, and `pig_kin`. It reports achievement rate, p50, p75, p90, worst observed runs, failure reasons, and aggregate shortfalls. Pig Kin reports Raw Chaos, Pig Ear, and Mudking Crown Fragment shortfalls from the live reconstruction cost. The report includes an `assumptions` block that names the profile fixture, selected region, max runs, run model, and goal thresholds.

The current progression model is intentionally conservative: it measures repeated representative run output, not a full player decision simulation with shop purchases, manual dice salvage, or roster promotion choices. That makes it useful for required-pacing sanity checks while keeping the report deterministic enough to compare in PRs.

## Kin Balance Review 2026-07-25

Command pattern:

```text
docker compose exec -T backend sh -lc "APP_ENV=test DB_DSN='mysql:host=db;port=3306;dbname=goblin_test;charset=utf8mb4' DB_USER='dice_test' DB_PASS='dicepass_test' php bin/simulate.php --mode=run --region=<region> --profile=<profile> --runs=25 --seed=kin-balance-2026-07-25 --format=json"
```

Observed representative run results:

| Region | Profile | Completion | Node win | Avg nodes | Avg rounds | HP remaining | Defeats/sample |
| --- | --- | ---: | ---: | ---: | ---: | ---: | ---: |
| Farm | Basic Goblin starter | 1.0000 | 1.0000 | 5.00 | 0.4320 | 0.9644 | 0.00 |
| Farm | Pig Kin starter | 1.0000 | 1.0000 | 5.00 | 0.4480 | 0.8815 | 0.00 |
| Mountains | Basic Goblin starter | 0.0000 | 0.8000 | 5.00 | 0.8640 | 0.7034 | 4.00 |
| Mountains | Pig Kin starter | 0.0000 | 0.8000 | 5.00 | 0.9360 | 0.6593 | 3.48 |
| Swamps | Basic Goblin starter | 0.0000 | 0.8000 | 5.00 | 1.3360 | 0.6630 | 5.92 |
| Swamps | Pig Kin starter | 0.0000 | 0.8000 | 5.00 | 1.3360 | 0.5988 | 5.88 |

Decision: no Pig Kin stat tuning is required from this pass. Pig Kin did not improve clear rate or node win rate over Basic Goblin in Farm, Mountains, or Swamps, and the HP remaining deltas point toward higher pressure rather than dominance. Keep Pig Kin's current numeric package while future kin passives are added behind the same profile-comparison gate.

Safety requirements:

- Simulations must not be exposed through public HTTP routes.
- The CLI must refuse to run unless `APP_ENV` is an explicit local/test environment or `SIMULATION_ENABLED=1` is deliberately set.
- Production deployments should avoid setting `SIMULATION_ENABLED=1`.
- Simulations should use bounded sample counts and throwaway users that are cleaned up after each sample.

## External Tools

External tools can still help, but they should support the repository workflow rather than replace it.

- Spreadsheets are useful for XP curves, costs, and quick expected-value checks.
- Dice probability tools are useful for isolated dice odds.
- Economy-flow tools can model sources, sinks, and long-term resource loops.
- Product analytics tools become useful after real players produce event data.

The custom simulator should remain the source of truth for backend-resolved combat, rewards, run nodes, kin gating, and progression pacing.

## Manual Playtest Pairing

Every simulation pass should be paired with at least one manual playtest path.

Manual tests should watch for:

- whether the UI explains why a result happened
- whether the player has a clear next goal
- whether a reward feels meaningful
- whether failure feels recoverable
- whether a new mechanic appears at the right moment
- whether a powerful option is also easy to understand

## Decision Rules

- Prefer changing authored seed data before changing engine code when the problem is numeric tuning.
- Prefer engine changes when many seed entries need the same workaround.
- Treat p90 progression pain as more important than average progression comfort for required unlocks.
- Do not buff rewards only because one short sample felt unlucky; inspect variance first.
- Do not nerf player power only because one setup wins often; check whether it is expensive, rare, understandable, and fun.
- Keep balance changes small enough that before/after simulation reports remain interpretable.

## Validation Rules

This balancing strategy is being followed when:

- balance changes name the intended player outcome
- simulations compare before and after behavior where feasible
- required progression unlocks include p50 and p90 pacing checks
- major combat changes include win-rate and battle-length checks
- manual playtest notes capture player clarity and feel
- simulation output is saved or summarized in PR descriptions for balance-affecting branches
