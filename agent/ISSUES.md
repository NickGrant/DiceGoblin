# ISSUES FILE
----
Active issues only. Move completed entries to `agent/ISSUES_ARCHIVE.md`.

## Expanded Run Encounters

### REE-002: Add expanded run encounter families

**Milestone:** Expanded Run Encounters
**Status:** Open
**Priority:** Medium

#### Problem

Run nodes now support hazard vocabulary, but the game still needs authored encounter families that create new decisions and biome identity.

#### Acceptance Criteria

- Implement at least one meaningful non-combat encounter family beyond the current dialogue/rest/loot/hazard baseline.
- Persist any generated encounter result before player resolution.
- Add player-facing copy and tests for the new encounter flow.
