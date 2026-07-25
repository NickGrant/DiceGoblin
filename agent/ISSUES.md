# ISSUES FILE
----
Active issues only. Move completed entries to `agent/ISSUES_ARCHIVE.md`.

## Slot-Machine-Style Random Encounters

### SME-003: Finalize chaos encounter rewards

**Milestone:** Slot-Machine-Style Random Encounters
**Status:** Open
**Priority:** Medium

#### Problem

Chaos nodes are reachable and can generate persisted reel results, but the result does not yet resolve into a completed encounter with backend-authored rewards.

#### Acceptance Criteria

- Add a backend-authoritative finalize path for generated chaos results that completes the run node.
- Apply a bounded reward based on the persisted reel result, including the advertised reward multiplier.
- Keep finalize idempotent so retries return the same completed result without duplicating rewards.
- Add player-facing completion copy and backend/frontend coverage for the finalize flow.
