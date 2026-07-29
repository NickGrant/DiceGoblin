# Swamps Pattern-V1 Gate Evidence

Status: Draft
Last Updated: 2026-07-29
Owner: Engineering
Depends On: `agent/ISSUES.md`, `documentation/09-active-system-structure/05-run-node-generation.md`

## Purpose

Capture automated evidence for the Swamps `pattern-v1` rollout gate before any live region opt-in.

## Commands

Run from the repository root:

```bash
npm.cmd run run-patterns:validate:docker
npm.cmd run run-patterns:gate:swamps:docker
```

## Gate Result

The 2026-07-29 Swamps gate run passed when executed sequentially against the Docker test database.

| Metric | Result |
| --- | ---: |
| Runs | `25` |
| Successes | `25` |
| Success rate | `1.0` |
| Fallback rate | `0.0` |
| Validation failures | `0` |
| Branch count min/max/avg | `4 / 4 / 4.0` |
| Backtracks min/max/avg | `0 / 0 / 0.0` |
| Node count min/max/avg | `33 / 34 / 33.12` |
| Edge count min/max/avg | `36 / 37 / 36.12` |
| Spine depth min/max/avg | `20 / 21 / 20.12` |
| Max straight spine nodes min/max/avg | `2 / 2 / 2.0` |
| Boss path start-to-boss min/max/avg | `19 / 20 / 19.12` |

## Distribution Notes

The gate output includes boss, exit, chaos, combat, hazard, loot, rest, and start nodes across the sampled seed batch. Pattern frequency includes:

- `shared_start_single@1`
- `shared_combat_step@1`
- `shared_hazard_rest@1`
- `shared_chaos_step@1`
- `shared_shrine_combat_loot_branch@1`
- `shared_boss_exit_terminal@1`

This confirms the automated Swamps catalog path can satisfy required rest, chaos, boss, exit, reconnecting branch, and branch reward contracts for the committed gate seed suite.

The raised profile budget and branch topology pass intentionally move Swamps closer to the old `lane-v1` graph scale while keeping pattern-v1 less sprawling. The current gate keeps four reconnecting branch paths, branch-local loot, a boss route around 19-20 steps, and no more than two same-row spine nodes in a row.

## Operational Note

The Mountains and Swamps gate scripts both sync the pattern catalog into the same Docker test database. Run them sequentially during local review to avoid transient catalog-sync deadlocks.

## Remaining Rollout Work

- Compare player-facing pacing against current `lane-v1` samples.
- Complete manual sample review for readability, route choice, repeated motifs, recovery placement, and boss approach pacing.
- Complete a manual map review before enabling `RUN_PATTERN_V1_REGIONS=swamps`.
