# Mountains Pattern-V1 Gate Evidence

Status: Draft
Last Updated: 2026-07-28
Owner: Engineering
Depends On: `agent/ISSUES.md`, `documentation/09-active-system-structure/05-run-node-generation.md`

## Purpose

Capture automated evidence for the Mountains `pattern-v1` rollout gate before any live region opt-in.

## Commands

Run from the repository root:

```bash
npm.cmd run run-patterns:validate:docker
npm.cmd run run-patterns:gate:mountains:docker
```

## Gate Result

The 2026-07-28 Mountains gate run passed.

| Metric | Result |
| --- | ---: |
| Runs | `25` |
| Successes | `25` |
| Success rate | `1.0` |
| Fallback rate | `0.0` |
| Validation failures | `0` |
| Branch count min/max/avg | `3 / 3 / 3.0` |
| Backtracks min/max/avg | `0 / 0 / 0.0` |
| Node count min/max/avg | `25 / 26 / 25.32` |
| Spine depth min/max/avg | `21 / 22 / 21.32` |

## Distribution Notes

The gate output includes boss, exit, chaos, combat, hazard, loot, rest, and start nodes across the sampled seed batch. Pattern frequency includes:

- `shared_start_single@1`
- `shared_combat_step@1`
- `shared_hazard_rest@1`
- `shared_chaos_step@1`
- `shared_loot_cap@1`
- `shared_boss_exit_terminal@1`

This confirms the automated Mountains catalog path can satisfy required rest, chaos, boss, exit, branch, and cap contracts for the committed gate seed suite.

The raised 2026-07-28 profile budget intentionally moves Mountains closer to the old `lane-v1` graph scale while keeping pattern-v1 less sprawling. The current gate keeps three optional cap branches and a boss route around 20-21 steps.

## Remaining Rollout Work

- Compare player-facing pacing against current `lane-v1` samples.
- Complete manual sample review for left-to-right readability, route choice, repeated motifs, and boss approach pacing.
- Mountains is enabled for local Docker UAT through `RUN_PATTERN_V1_REGIONS=mountains`; complete manual map review before accepting the rollout beyond UAT.
