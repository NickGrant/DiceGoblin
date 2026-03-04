# MILESTONES FILE
----

## Purpose
- `MILESTONES.md` tracks active milestone groupings.
- A milestone is a named collection of issue titles plus a milestone status.
- Milestones are optional; issues do not need to belong to a milestone.

## How to Use
- Valid milestone statuses:
  - `not-started`
  - `in-progress`
  - `complete`
  - `blocked`
- Required milestone fields:
  - `name`
  - `status`
  - `execution_window` (`open` | `closed`)
  - `is_current` (`yes` | `no`)
  - `issues` (list of issue titles from `ISSUES.md`)
- Milestone rule:
  - if a milestone has no issues, status must be `not-started`
- When a milestone is complete:
  - set `status: complete`
  - append `Resolution:` (1-2 sentences)
  - move the full milestone entry to `MILESTONES_ARCHIVE.md`
  - remove the completed entry from this active file

## Milestone Template
Use this structure for new milestones:
```yaml
name: <milestone name>
status: not-started
execution_window: closed
is_current: no
issues:
  - <issue title>
description: |
  <scope and exit criteria summary>
entry_criteria: |
  * <required condition to start>
exit_criteria: |
  * <required condition to complete>
```

## Active Milestones
---
name: Milestone 11 - QA Coverage and Automation
status: in-progress
execution_window: open
is_current: yes
issues:
  - Add backend endpoint contract tests for session/profile/current-run success envelopes
  - Add end-to-end API integration test for start-run resolve-node claim-battle lifecycle
  - Add frontend apiClient mutation flow tests for CSRF and error handling behavior
  - Add reusable test DB reset/migration utility for backend integration tests
description: |
  Expand automated verification depth and establish repeatable CI-backed confidence gates.
entry_criteria: |
  * Target endpoint and lifecycle contracts for current implementation scope are defined.
  * Test database setup and harness expectations are documented.
exit_criteria: |
  * QA automation issues are complete and archived.
  * Required CI and integration verification gates are green.
