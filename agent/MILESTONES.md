# MILESTONES FILE
----
Active milestones only. Move completed entries to `agent/MILESTONES_ARCHIVE.md`.

## Slot-Machine-Style Random Encounters

**Status:** Active
**Purpose:** Turn the persisted chaos encounter foundation into a reachable run-node experience with readable reel results, bounded player agency, and clear risk/reward presentation.

### Goals

- Place chaos encounters into eligible procedural run maps.
- Surface persisted reel results and one-reroll agency in the frontend.
- Preserve backend authority and idempotent result generation.
- Keep reward and combat finalization as explicit follow-up scope unless the active issue expands.

### Current Code Context

The chaos foundation already persists generated reel outputs and one single-use reroll per run node. Run graphs currently place combat, loot, rest, boss, exit, dialogue, hazard, and shrine nodes, while docs identify chaos node placement, encounter finalization, and reward application as follow-up work.

### Exit Criteria

- Chaos nodes are reachable in eligible generated runs.
- The run-node UI presents the generated chaos result and reroll state clearly.
- Refreshing or revisiting the node preserves the existing generated result.
- Backend and frontend tests cover placement and the core player-facing chaos flow.

### Related Issues

- SME-002: Place and present chaos encounter nodes
