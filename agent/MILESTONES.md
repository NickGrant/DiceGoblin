# MILESTONES FILE
----
Active milestones only. Move completed entries to `agent/MILESTONES_ARCHIVE.md`.

## Developer Support: Seed Catalog Browser

**Status:** Active
**Purpose:** Add a read-only developer-panel view for seeded catalog tables so content and balance data can be inspected without direct SQL access.

### Goals

- Expose selected seeded tables through a read-only debug API.
- Present table selection, row counts, and seeded values in the Angular debug panel.
- Keep unknown tables and all mutation attempts out of scope.
- Document the supported debug contract.

### Current Code Context

The debug panel already loads a compact catalog and supports dev-only account mutations. The next slice should add read-only seeded-table inspection without changing existing grant/reset tools.

### Exit Criteria

- Developers can inspect supported seeded tables from `/debug`.
- The backend allowlists table names and returns rows without write behavior.
- Frontend and backend coverage protect the read-only browser flow.

### Related Issues

- DSB-001: Add read-only seeded table browser to debug panel
