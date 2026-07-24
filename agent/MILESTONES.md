# MILESTONES FILE
----
Active milestones only. Move completed entries to `agent/MILESTONES_ARCHIVE.md`.

## Backend Structural Cleanup Pass

**Status:** Active  
**Purpose:** Reduce backend orchestration drift by extracting shared lifecycle, grant, and policy services from controller-heavy flows before adding any broader event-style coordination.

### Goals

- Create a single canonical service path for run lifecycle transitions and cleanup.
- Consolidate unit and dice grant creation behind shared services.
- Reduce controller duplication for auth, JSON validation, transactions, and mutation guards.
- Prepare the backend for narrow domain events only where they provide clear value after service extraction.

### Current Code Context

Primary work will touch backend/src/Controllers, backend/src/Services, and selected repositories around runs, battles, shop, academy, and grants.

### Exit Criteria

- Run lifecycle transitions no longer require duplicated controller logic to stay consistent.
- Unit and dice creation paths are consolidated across battle rewards, shop flows, debug tools, and starter grants.
- Common controller plumbing is meaningfully reduced or centralized.
- Any event-style coordination introduced is narrow, synchronous, and built on top of cleaner service boundaries.

### Related Issues

- BSC-002: Centralize run lifecycle transitions
- BSC-003: Extract shared mutation guard and controller helpers
- BSC-004: Separate shop and academy domain services from controllers
