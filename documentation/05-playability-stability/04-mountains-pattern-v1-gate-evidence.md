# Mountains Pattern-V1 Gate Evidence

Status: Draft
Last Updated: 2026-07-29
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

The 2026-07-29 Mountains gate run passed.

| Metric | Result |
| --- | ---: |
| Runs | `25` |
| Successes | `25` |
| Success rate | `1.0` |
| Fallback rate | `0.0` |
| Validation failures | `0` |
| Branch count min/max/avg | `3 / 3 / 3.0` |
| Backtracks min/max/avg | `0 / 0 / 0.0` |
| Node count min/max/avg | `26 / 27 / 26.24` |
| Edge count min/max/avg | `28 / 29 / 28.24` |
| Spine depth min/max/avg | `16 / 17 / 16.24` |
| Max straight spine nodes min/max/avg | `2 / 2 / 2.0` |
| Boss path start-to-boss min/max/avg | `15 / 16 / 15.24` |

## Distribution Notes

The gate output includes boss, exit, chaos, combat, hazard, loot, rest, and start nodes across the sampled seed batch. Pattern frequency includes:

- `shared_start_single@1`
- `shared_combat_step@1`
- `shared_hazard_rest@1`
- `shared_chaos_step@1`
- `shared_shrine_combat_loot_branch@1`
- `shared_boss_exit_terminal@1`

This confirms the automated Mountains catalog path can satisfy required rest, chaos, boss, exit, reconnecting branch, and branch reward contracts for the committed gate seed suite.

The raised profile budget and branch topology pass intentionally move Mountains closer to the old `lane-v1` graph scale while keeping pattern-v1 less sprawling. The current gate keeps three reconnecting branch paths, branch-local loot, a boss route around 15-16 steps, and no more than two same-row spine nodes in a row.

## Remaining Rollout Work

- Compare player-facing pacing against current `lane-v1` samples.
- Complete manual sample review for left-to-right readability, route choice, repeated motifs, and boss approach pacing.
- Mountains is enabled for local Docker UAT through `RUN_PATTERN_V1_REGIONS=mountains`; complete manual map review before accepting the rollout beyond UAT.
