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
- Exactly one milestone may be `is_current: yes`.
- Current milestone must be `execution_window: open`.
- Execute earliest available open milestone unless blocked or user-overridden.
- Documentation work that directly supports an active milestone should stay inside that milestone rather than spawning a separate governance lane.

## Active Sources
- Active execution source of truth: `agent/ISSUES.md`, `agent/MILESTONES.md`.
- Deferred planning inventory: `agent/ISSUES_BACKLOG.md`, `agent/MILESTONES_BACKLOG.md`.
- Historical context on demand: `agent/ISSUES_ARCHIVE.md`, `agent/MILESTONES_ARCHIVE.md`.

## Open / Close Gates
- Open when upstream blockers are resolved/accepted and at least three issues are actionable.
- Close when required scope is complete/deferred with rationale and no unresolved high-priority blockers remain.
- After closing the current milestone, automatically advance the next milestone in execution order unless the user explicitly pauses for re-evaluation.

## Dependency Metadata
- Optional issue fields: `blocked_by`, `enables`.
- Use exact issue titles and keep dependency chains shallow.

## Issue Rules
- Active issue entries should include:
  - `title`
  - `status`
  - `priority`
  - `execution`
  - `ready`
  - `description`
- Prioritize work in this order unless the user overrides:
  - `reopened`
  - `in-progress`
  - `unstarted`
  - `blocked`
- Within each status bucket, prioritize `high` before `medium` before `low`.
- Default auto-execution gate:
  - only auto-work issues where `execution: active` and `ready: yes`
  - and either the issue milestone is unassigned, or the linked milestone is current and open
- Status transitions:
  - `unstarted -> in-progress` when implementation begins
  - `in-progress -> blocked` when blocked by dependency
  - `blocked -> in-progress` when blocker clears
  - archive after verification and resolution logging
  - use `reopened` for regressions
- Completed issues should be archived promptly to keep `agent/ISSUES.md` lean.

## Milestone Rules
- Milestones reference issue titles from `agent/ISSUES.md`.
- If a milestone has no issues, it must remain `status: not-started`.
- Issues mapped to milestones with `execution_window: closed` are planning-only unless the user explicitly asks to work them now.
- When a current milestone is archived complete, automatically advance the next milestone in execution order unless the user explicitly pauses for evaluation.

## Triage Cadence
- Weekly, at milestone boundaries, and after major roadmap/contract changes.

## Status Policy
- `unstarted -> in-progress -> blocked/in-progress -> archived complete`.
- Use `reopened` for regressions.

## Work Loop
- Select issue.
- Mark it `in-progress`.
- Implement and verify.
- Update resolution/status.
- Archive completed items.

## Batching
- Default to batches of 3-5 issues unless the user requests a different batch size.
- After each batch, report completed items, remaining items, and blockers before continuing.

## File Roles
- Active execution: `agent/ISSUES.md`, `agent/MILESTONES.md`.
- Deferred inventory: `agent/ISSUES_BACKLOG.md`, `agent/MILESTONES_BACKLOG.md`.
