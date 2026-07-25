# MILESTONES FILE
----
Active milestones only. Move completed entries to `agent/MILESTONES_ARCHIVE.md`.

## Slot-Machine-Style Random Encounters

**Status:** Active
**Purpose:** Turn reachable chaos encounter nodes into a complete backend-authored encounter loop with persisted results, bounded player agency, and visible rewards.

### Goals

- Finalize generated chaos encounters through a backend-authoritative path.
- Apply rewards from the persisted reel result without regenerating risk/reward state.
- Preserve backend authority and idempotent result generation.
- Keep full chaos combat generation as explicit follow-up scope unless the active issue expands.

### Current Code Context

Chaos nodes are reachable in eligible procedural run maps and the frontend presents generated reel results with one single-use reroll. The remaining gap for the current milestone is completing the node with rewards derived from the persisted result.

### Exit Criteria

- Generated chaos results can be finalized exactly once.
- Finalize responses expose clear reward and completion state.
- Retries do not duplicate rewards or reroll the result.
- Backend and frontend tests cover the core finalize flow.

### Related Issues

- SME-003: Finalize chaos encounter rewards
