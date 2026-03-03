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

---
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

## Active Issues

### Functional

---
title: Persist run-scoped unit attrition state across encounters and resume
status: unstarted
priority: high
execution: deferred
ready: no
owner: unassigned
milestone: Milestone 3 - Run Progression and Attrition
created: 2026-03-02
updated: 2026-03-02
description: |
  Implement and persist run-scoped unit state (HP, cooldowns, status effects, defeated flags) so attrition behavior matches `documentation/02-systems-mvp/05-save-and-resume-scope.md` and survives reconnect/resume.
---
title: Implement run failure and abandonment resolution rules
status: unstarted
priority: high
execution: deferred
ready: no
owner: unassigned
milestone: Milestone 3 - Run Progression and Attrition
created: 2026-03-02
updated: 2026-03-02
description: |
  Implement terminal run resolution (total defeat and abandon) including XP reset rules for defeated units and post-run cleanup/recovery rules defined in `documentation/02-systems-mvp/06-run-resolution-scope.md`.
---
title: Implement encounter retry flow for partial defeat scenarios
status: unstarted
priority: medium
execution: deferred
ready: no
owner: unassigned
milestone: Milestone 3 - Run Progression and Attrition
created: 2026-03-02
updated: 2026-03-02
description: |
  Support retrying encounters after partial defeat using remaining undefeated run units, with no extra energy cost, consistent with run resolution scope documentation.
### Documentation
