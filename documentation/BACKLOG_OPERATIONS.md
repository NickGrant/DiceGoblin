# Backlog Operations
----

Status: active  
Last Updated: 2026-03-02  
Owner: Product + Engineering + QA  
Depends On: `ISSUES.md`, `ISSUES_BACKLOG.md`, `MILESTONES.md`, `MILESTONES_BACKLOG.md`, `AGENTS.md`

## Purpose
- Define one canonical operations guide for milestone sequencing, issue dependencies, and backlog triage.
- Reduce policy duplication across roadmap/backlog governance docs.

## Milestone Execution Order (Open Milestones)
1. Milestone 15 - Backend Gameplay Completion
2. Milestone 16 - Frontend Gameplay Completion
3. Milestone 10 - Engineering Maintainability and Contracts
4. Milestone 13 - Player Experience and UX Flow

Completed milestones are tracked in `MILESTONES_ARCHIVE.md` and are excluded from active execution order.

## Current Milestone Rules
- Exactly one milestone may be `is_current: yes`.
- The current milestone must have `execution_window: open`.
- Prefer the earliest not-complete milestone in execution order unless:
  - a blocking dependency requires a different order, or
  - the user explicitly overrides sequence.

## Milestone Open/Close Criteria
- Open (`execution_window: open`) when:
  - upstream blockers are complete or explicitly accepted as deferred risk,
  - at least three issues are actionable (`execution: active`, `ready: yes`) or ready to become actionable,
  - acceptance/verification expectations are documented.
- Close (`execution_window: closed`) when:
  - required issues are complete/archived or explicitly deferred with rationale,
  - no unresolved high-priority blockers remain in milestone scope,
  - milestone resolution is recorded before archival.

## Cross-Milestone Dependency Map
- Milestone 2 -> Milestones 3, 4, 11, 12, 13
- Milestone 3 -> Milestones 4, 6, 13
- Milestone 4 -> Milestones 6, 13
- Milestone 5 -> Milestones 6, 13
- Milestone 11 -> Milestones 6, 12
- Milestone 15 -> Milestones 16, 13
- Milestone 16 -> Milestone 13

## Issue Dependency Metadata
- `blocked_by` and `enables` are optional issue fields.
- Use exact issue titles for references.
- Add them when sequencing is non-obvious or cross-milestone coupling exists.
- Keep references direct (avoid long transitive chains in one issue).

## Triage Cadence
- Weekly:
  - review all `in-progress`, `blocked`, and high-priority `unstarted` items,
  - refresh stale metadata (`owner`, `updated`, dependency fields).
- Milestone boundaries:
  - run at milestone open and milestone close.
- Event-driven:
  - run after major roadmap/doc-contract changes.

## Status Transition Policy
- `unstarted -> in-progress` when active implementation begins.
- `in-progress -> blocked` when a dependency prevents progress.
- `blocked -> in-progress` when blocker is cleared.
- `in-progress -> archived complete` after verification and resolution logging.
- any status -> `reopened` for regressions or incomplete prior fixes.

## Active vs Deferred Files
- `ISSUES.md` and `MILESTONES.md` are active execution surfaces.
- `ISSUES_BACKLOG.md` and `MILESTONES_BACKLOG.md` hold deferred planning inventory.
- Promote deferred items to active files before execution.

## Override Rule
- The user may override execution order/current milestone at any time.
- Record rationale in `documentation/CHANGELOG.md` when override changes current lane.
