# ISSUES FILE
----

## Purpose
- `ISSUES.md` tracks active work only.
- Keep this file small and current for day-to-day execution context.
- Historical completed issues are moved to `ISSUES_ARCHIVE.md`.

## How to Use
- Valid statuses for active items:
  - `unstarted`
  - `in-progress`
  - `reopened`
  - `blocked`
- Valid priorities for active items:
  - `low`
  - `medium`
  - `high`
- When an issue is resolved:
  - set `status: complete`
  - append `Resolution:` (1-2 sentences)
  - move the full completed entry to `ISSUES_ARCHIVE.md`
  - remove the completed entry from this active file

## Issue Template
Use this block for new issues:

```yaml
title: <short summary>
status: unstarted
priority: medium
execution: deferred
ready: no
owner: unassigned
milestone: unassigned
blocked_by:
  - <issue title>
enables:
  - <issue title>
created: 2026-03-02
updated: 2026-03-02
description: |
  <problem, impact, and expected outcome>
```

## Active Issues

### Functional
---
title: Add backend endpoint contract tests for session/profile/current-run success envelopes
status: in-progress
priority: high
execution: active
ready: yes
owner: unassigned
milestone: Milestone 11 - QA Coverage and Automation
created: 2026-03-02
updated: 2026-03-04
description: |
  [Role: QA Lead] Frontend contract validators exist, but backend-side regression tests for `GET /api/v1/session`, `GET /api/v1/profile`, and `GET /api/v1/runs/current` success envelopes are missing. Add endpoint-level tests to catch server payload drift at source.
---
title: Add end-to-end API integration test for start-run resolve-node claim-battle lifecycle
status: unstarted
priority: high
execution: deferred
ready: no
owner: unassigned
milestone: Milestone 11 - QA Coverage and Automation
created: 2026-03-02
updated: 2026-03-04
description: |
  [Role: QA Lead] Add an integration test that validates the full mutating lifecycle (`POST /runs` -> `POST /runs/:runId/nodes/:nodeId/resolve` -> `POST /battles/:battleId/claim`) including status transitions and reward/claim contract stability; coordinate expected assertions with Milestone 2 placeholder-to-real reward/combat changes.
---
title: Add frontend apiClient mutation flow tests for CSRF and error handling behavior
status: unstarted
priority: medium
execution: deferred
ready: no
owner: unassigned
milestone: Milestone 11 - QA Coverage and Automation
created: 2026-03-02
updated: 2026-03-04
description: |
  [Role: QA Lead] Add tests for `createTeam/activateTeam/updateTeam/createRun` mutation flows to validate CSRF sourcing behavior and error propagation semantics in `apiClient`.
---
title: Add reusable test DB reset/migration utility for backend integration tests
status: unstarted
priority: medium
execution: deferred
ready: no
owner: unassigned
milestone: Milestone 11 - QA Coverage and Automation
created: 2026-03-02
updated: 2026-03-04
description: |
  [Role: QA Lead] Integration tests currently assume schema is preloaded manually. Add a repeatable utility for initializing/resetting test DB state from versioned schema artifacts.
### Documentation
