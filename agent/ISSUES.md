# ISSUES FILE
----
Active issues only. Move completed entries to `agent/ISSUES_ARCHIVE.md`.

## Hybrid Seed Catalog Ownership

### HDC-001: Classify database tables by data ownership model

**Milestone:** Hybrid Seed Catalog Ownership
**Status:** Open
**Priority:** High

#### Problem

Seeded and runtime tables currently mix several kinds of data ownership: player state, authored catalog values, small constants, and behavior-bearing slugs. Without a shared classification, it is unclear which tables should stay database-backed, which values should be codified, and which tables need a hybrid contract between seeded data and executable code.

#### Acceptance Criteria

- Add a canonical architecture document that defines database, code/config, and hybrid ownership criteria.
- Classify every current table in the project by ownership model.
- Identify near-term candidates for codification or hybrid contract enforcement.
- Update the active roadmap so the seed browser is marked complete and hybrid catalog cleanup is the current planning lane.
- Preserve runtime/player-state tables as database-owned unless a concrete reason says otherwise.
