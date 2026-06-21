# Backlog Operations
----

Status: active  
Last Updated: 2026-04-18  
Owner: Product + Engineering + QA  
Depends On: `agent/ISSUES.md`, `agent/ISSUES_BACKLOG.md`, `agent/MILESTONES.md`, `agent/MILESTONES_BACKLOG.md`, `AGENTS.md`

## Purpose
- Canonical policy for issue/milestone sequencing and triage.

## Current Sequencing Source
- Do not treat this file as the authoritative roadmap inventory.
- Define current and next milestone sequencing directly in `agent/MILESTONES.md` and `agent/MILESTONES_BACKLOG.md`.
- If both files are empty after a milestone close, pause automation and explicitly establish the next milestone before resuming implementation.

## Core Rules
- Exactly one milestone in `agent/MILESTONES.md` should have `**Status:** Active`.
- Treat the first `Active` milestone in file order as the current milestone for automation.
- Milestones below the active milestone should normally remain `Planned` unless the user intentionally wants parallel execution.
- Documentation work that directly supports an active milestone should stay inside that milestone rather than spawning a separate governance lane.

## Active Sources
- Active execution source of truth: `agent/ISSUES.md`, `agent/MILESTONES.md`.
- Deferred planning inventory: `agent/ISSUES_BACKLOG.md`, `agent/MILESTONES_BACKLOG.md`.
- Historical context on demand: `agent/ISSUES_ARCHIVE.md`, `agent/MILESTONES_ARCHIVE.md`.

## Open / Close Gates
- Mark a milestone `Active` when it is the next implementation-ready milestone.
- Mark a milestone `Complete` when it has no remaining active issues in `agent/ISSUES.md`.
- After closing the current milestone, automatically advance the next planned milestone only when one is explicitly defined in active or backlog milestone files.

## Dependency Metadata
- Optional issue fields: `blocked_by`, `enables`.
- Use exact issue titles and keep dependency chains shallow.

## Issue Rules
- Active issue entries should include:
  - milestone section placement
  - issue heading/title
  - `**Status:**`
  - `**Priority:**`
  - problem/acceptance context
- Default watcher selection is deterministic:
  - first `In Progress` issue in the current `Active` milestone by file order
  - otherwise first `Open` issue in the current `Active` milestone by file order
- Priority is planning metadata for humans unless the user explicitly wants the watcher to reprioritize by it.
- Default auto-execution gate:
  - only auto-work issues in the current `Active` milestone
  - and only when issue `**Status:**` is `Open` or `In Progress`
- Status transitions:
  - `Open -> In Progress` when implementation begins
  - `In Progress -> Blocked` when blocked by dependency
  - `Blocked -> In Progress` when blocker clears
  - completed issues move out of `agent/ISSUES.md` and into `agent/ISSUES_ARCHIVE.md`
  - use `Open` again for reopened regressions unless a stronger distinction is needed later
- Completed issues should be archived promptly to keep `agent/ISSUES.md` lean.

## Milestone Rules
- Milestones are represented by markdown sections in `agent/MILESTONES.md`.
- Issues are grouped under matching milestone sections in `agent/ISSUES.md`.
- Only one milestone should be `Active`; others should normally be `Planned`, `Blocked`, or `Complete`.
- When the current milestone has no remaining active issues, mark it `Complete` and advance the next explicitly defined `Planned` milestone unless the user explicitly pauses for evaluation.

## Triage Cadence
- Weekly, at milestone boundaries, and after major roadmap/contract changes.

## Status Policy
- Issue flow: `Open -> In Progress -> Blocked/In Progress -> archived complete`.
- Milestone flow: `Planned -> Active -> Blocked/Active -> Complete`.

## Work Loop
- Detect backlog changes from `agent/ISSUES.md` and `agent/MILESTONES.md`.
- Select the current execution target deterministically from the active milestone.
- Mark an `Open` target issue `In Progress` before implementation begins.
- Implement the selected issue only.
- Run verification until the required checks pass or a real blocker is found.
- Apply lint/cleanup appropriate to the touched area.
- Archive completed issues and keep active docs minimal.
- If the milestone has no remaining active issues, complete it and activate the next explicitly defined planned milestone.
- Commit finished work.
- Repeat until there is no active or planned implementation work left.

## Batching
- Watcher automation defaults to batch size `1` for deterministic execution and lower token spend.
- Human-driven backlog work can still batch 3-5 issues when the user explicitly wants grouped execution.

## File Roles
- Active execution: `agent/ISSUES.md`, `agent/MILESTONES.md`.
- Deferred inventory: `agent/ISSUES_BACKLOG.md`, `agent/MILESTONES_BACKLOG.md`.
