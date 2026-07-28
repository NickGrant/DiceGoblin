# Swamps Pattern-V1 Gate Evidence

Status: Draft
Last Updated: 2026-07-28
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

The 2026-07-28 Swamps gate run passed when executed sequentially against the Docker test database.

| Metric | Result |
| --- | ---: |
| Runs | `25` |
| Successes | `25` |
| Success rate | `1.0` |
| Fallback rate | `0.0` |
| Validation failures | `0` |
| Branch count min/max/avg | `3 / 3 / 3.0` |
| Backtracks min/max/avg | `0 / 0 / 0.0` |
| Node count min/max/avg | `17 / 18 / 17.44` |
| Spine depth min/max/avg | `13 / 14 / 13.44` |

## Distribution Notes

The gate output includes boss, exit, chaos, combat, hazard, loot, rest, and start nodes across the sampled seed batch. Pattern frequency includes:

- `shared_start_single@1`
- `shared_combat_step@1`
- `shared_hazard_rest@1`
- `shared_chaos_step@1`
- `shared_loot_cap@1`
- `shared_boss_exit_terminal@1`

This confirms the automated Swamps catalog path can satisfy required rest, chaos, boss, exit, branch, and cap contracts for the committed gate seed suite.

## Operational Note

The Mountains and Swamps gate scripts both sync the pattern catalog into the same Docker test database. Run them sequentially during local review to avoid transient catalog-sync deadlocks.

## Remaining Rollout Work

- Compare player-facing pacing against current `lane-v1` samples.
- Complete manual sample review for readability, route choice, repeated motifs, recovery placement, and boss approach pacing.
- Decide whether the current smaller pattern-v1 graph size and higher branch count are desirable before enabling `RUN_PATTERN_V1_REGIONS=swamps`.
