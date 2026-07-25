# MILESTONES FILE
----
Active milestones only. Move completed entries to `agent/MILESTONES_ARCHIVE.md`.

## Hybrid Seed Catalog Ownership

**Status:** Active
**Purpose:** Establish clear ownership rules for database-backed, code-backed, and hybrid seeded data so future cleanup work can move constants and behavior-bearing catalog values intentionally.

### Goals

- Define criteria for deciding whether data should live in the database, code/config, or a hybrid model.
- Classify every current table against those criteria.
- Identify the safest near-term cleanup candidates.
- Keep the read-only seed browser as the inspection surface for database-backed catalog values.

### Current Code Context

The seed catalog ownership matrix exists, and `unit_types` now uses `ability_set_json` as the single authored ability package source. The next slice should add parity tests for behavior-bearing seeded slugs before more catalog values move out of raw SQL.

### Exit Criteria

- A canonical ownership matrix exists and covers all current tables.
- The roadmap points at hybrid catalog cleanup instead of the completed seed-browser work.
- Follow-up implementation candidates are explicit enough to promote into focused issues.
- Behavior-bearing seeded catalog slugs are protected by parity tests.

### Related Issues

- HDC-003: Add hybrid catalog slug parity tests
