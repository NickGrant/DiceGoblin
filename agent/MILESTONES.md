# MILESTONES FILE
----
Active milestones only. Move completed entries to `agent/MILESTONES_ARCHIVE.md`.

## Expanded Run Encounters

**Status:** Active
**Purpose:** Add run encounter families that create decisions, risk profiles, and biome identity beyond the current node vocabulary.

### Goals

- Add at least one meaningful non-combat encounter family.
- Persist generated encounter state before player resolution.
- Keep encounter outcomes backend-authoritative and testable.
- Surface clear player-facing copy for the new encounter flow.

### Current Code Context

The roadmap foundation added hazard node vocabulary, while the existing run flow already supports combat, loot, rest, boss, exit, dialogue, and hazard node types. The next implementation should extend backend run-node resolution and frontend run-node presentation without changing unrelated combat or reward flows.

### Exit Criteria

- The new encounter family has durable backend state for generated results.
- Resolving the encounter is idempotent and fits the existing run-node lifecycle.
- The frontend presents the encounter decision and result clearly.
- Backend and frontend coverage protect the core encounter flow.

### Related Issues

- REE-002: Add expanded run encounter families
