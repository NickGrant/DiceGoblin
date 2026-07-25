# ISSUES FILE
----
Active issues only. Move completed entries to `agent/ISSUES_ARCHIVE.md`.

## Developer Support: Seed Catalog Browser

### DSB-001: Add read-only seeded table browser to debug panel

**Milestone:** Developer Support: Seed Catalog Browser
**Status:** Open
**Priority:** Medium

#### Problem

Seeded catalog data is spread across migrations and database tables, so reviewing current unit, enemy, dice, region, bounty, and encounter seed values requires direct SQL access or source spelunking.

#### Acceptance Criteria

- Add a read-only backend debug endpoint for allowlisted seeded tables.
- Include table metadata such as supported table names, labels, row counts, and columns.
- Add a debug-panel UI for choosing a table and inspecting seeded rows.
- Refuse unknown table names and avoid any edit/delete/write behavior.
- Document the seeded table browser contract and add backend/frontend coverage.
