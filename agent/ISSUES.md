# ISSUES FILE
----
Active issues only. Move completed entries to `agent/ISSUES_ARCHIVE.md`.

## Slot-Machine-Style Random Encounters

### SME-002: Place and present chaos encounter nodes

**Milestone:** Slot-Machine-Style Random Encounters
**Status:** Open
**Priority:** Medium

#### Problem

Chaos encounter results can be generated and rerolled, but chaos encounters are not yet reachable through ordinary run maps or presented as a coherent player-facing node flow.

#### Acceptance Criteria

- Add chaos run-node placement to eligible procedural run graphs without disrupting existing combat, shrine, rest, loot, boss, exit, dialogue, or hazard flows.
- Present chaos nodes in the run-node UI with generated reel results, readable risk/reward copy, and the one-reroll agency mechanic.
- Keep generation idempotent: refreshing or revisiting a chaos node must not regenerate an existing result.
- Add backend and frontend coverage for chaos node placement and the visible generate/reroll flow.
