# Backlog Operations
----

Status: active  
Last Updated: 2026-04-18  
Owner: Product + Engineering + QA  
Depends On: `agent/ISSUES.md`, `agent/ISSUES_BACKLOG.md`, `agent/MILESTONES.md`, `agent/MILESTONES_BACKLOG.md`, `AGENTS.md`

## Purpose
- Canonical policy for issue/milestone sequencing and triage.

## Active Milestone Order
1. Milestone 37 - Ability Loadout Rework Foundations
2. Milestone 38 - Combat Scheduler and Resolution Rewrite
3. Milestone 39 - Unit Details and Promotion UX
4. Milestone 40 - Rework Normalization Pass

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
- Mark a milestone `Active` when upstream blockers are resolved/accepted and at least three issues are actionable.
- Mark a milestone `Complete` when required scope is complete/deferred with rationale and no unresolved high-priority blockers remain.
- After closing the current milestone, automatically advance the next milestone in execution order unless the user explicitly pauses for re-evaluation.

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
- Prioritize work in this order unless the user overrides:
  - `In Progress`
  - `Open`
  - `Blocked`
- Within each status bucket, prioritize `high` before `medium` before `low`.
- Default auto-execution gate:
  - only auto-work issues in the current `Active` milestone
  - and only when issue `**Status:**` is `Open` or `In Progress`
- Status transitions:
  - `Open -> In Progress` when implementation begins
  - `In Progress -> Blocked` when blocked by dependency
  - `Blocked -> In Progress` when blocker clears
  - archive after verification and resolution logging
  - use `Open` again for reopened regressions unless a stronger distinction is needed later
- Completed issues should be archived promptly to keep `agent/ISSUES.md` lean.

## Milestone Rules
- Milestones are represented by markdown sections in `agent/MILESTONES.md`.
- Issues are grouped under matching milestone sections in `agent/ISSUES.md`.
- Only one milestone should be `Active`; others should normally be `Planned`, `Blocked`, or `Complete`.
- When the current milestone is completed, advance the next milestone in file order unless the user explicitly pauses for evaluation.

## Triage Cadence
- Weekly, at milestone boundaries, and after major roadmap/contract changes.

## Status Policy
- Issue flow: `Open -> In Progress -> Blocked/In Progress -> archived complete`.
- Milestone flow: `Planned -> Active -> Blocked/Active -> Complete`.

## Work Loop
- Select issue.
- Mark it `In Progress`.
- Implement and verify.
- Update resolution/status.
- Archive completed items.

## Batching
- Default to batches of 3-5 issues unless the user requests a different batch size.
- After each batch, report completed items, remaining items, and blockers before continuing.

## File Roles
- Active execution: `agent/ISSUES.md`, `agent/MILESTONES.md`.
- Deferred inventory: `agent/ISSUES_BACKLOG.md`, `agent/MILESTONES_BACKLOG.md`.
