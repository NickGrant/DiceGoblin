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
title: Add progression invariants test suite for claim and run-state mutation
status: unstarted
priority: high
execution: deferred
ready: no
owner: unassigned
milestone: Milestone 12 - Combat Determinism and Progression Integrity
created: 2026-03-02
updated: 2026-03-04
description: |
  [Role: Combat Systems Reviewer] Add invariant tests verifying XP/reward applications, run-unit state mutation, and no-duplication guarantees across repeated claim/resolve requests.
---
title: Add battle reward economy sanity validation fixtures
status: unstarted
priority: medium
execution: deferred
ready: no
owner: unassigned
milestone: Milestone 12 - Combat Determinism and Progression Integrity
created: 2026-03-02
updated: 2026-03-04
description: |
  [Role: Combat Systems Reviewer] Add fixture-based checks for reward output bounds and consistency to detect extreme or malformed reward payloads during progression work.
### Documentation
