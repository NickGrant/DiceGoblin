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

The debug panel can inspect allowlisted seeded tables. The next slice should clarify which inspected values are meant to remain DB-authored, which should become code/config primitives, and which need slug parity between data rows and code handlers.

### Exit Criteria

- A canonical ownership matrix exists and covers all current tables.
- The roadmap points at hybrid catalog cleanup instead of the completed seed-browser work.
- Follow-up implementation candidates are explicit enough to promote into focused issues.

### Related Issues

- HDC-001: Classify database tables by data ownership model
