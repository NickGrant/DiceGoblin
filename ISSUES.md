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
title: Implement rest workflow endpoints with transactional snapshot and squad sync
status: unstarted
priority: high
execution: active
ready: yes
owner: unassigned
milestone: Milestone 15 - Backend Gameplay Completion
created: 2026-03-04
updated: 2026-03-04
description: |
  Implement `rest/open`, `rest/state`, and `rest/finalize` APIs so rest nodes support non-consuming edit sessions and atomic dual-write updates to run snapshot + saved squad state.
---
title: Implement backend auto-level application at rest finalize and run cleanup
status: unstarted
priority: high
execution: active
ready: yes
owner: unassigned
milestone: Milestone 15 - Backend Gameplay Completion
created: 2026-03-04
updated: 2026-03-04
description: |
  Implement backend-authoritative level-up math execution as an automatic pass on rest finalization and run cleanup, rather than per-claim leveling.
---
title: Implement manual promotion endpoint with primary-secondary consume semantics
status: unstarted
priority: medium
execution: active
ready: yes
owner: unassigned
milestone: Milestone 15 - Backend Gameplay Completion
created: 2026-03-04
updated: 2026-03-04
description: |
  Add a promotion API that takes one primary unit id and two distinct secondary unit ids; primary persists/upgrades while secondaries are consumed. Promotion is allowed between runs or during open rest workflow, and must reject ineligible active-run snapshot participants.
---
title: Expose dice equip and unequip gameplay endpoints with rest-only run constraints
status: unstarted
priority: medium
execution: active
ready: yes
owner: unassigned
milestone: Milestone 15 - Backend Gameplay Completion
created: 2026-03-04
updated: 2026-03-04
description: |
  Expose backend equip/unequip endpoints that enforce per-unit slot caps and block active-run modifications outside rest workflow.

### Documentation
