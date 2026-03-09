# Backlog Operations
----

Status: active  
Last Updated: 2026-03-09  
Owner: Product + Engineering + QA  
Depends On: `ISSUES.md`, `ISSUES_BACKLOG.md`, `MILESTONES.md`, `MILESTONES_BACKLOG.md`, `AGENTS.md`

## Purpose
- Canonical policy for issue/milestone sequencing and triage.

## Active Milestone Order
1. Milestone 15 - Backend Gameplay Completion
2. Milestone 16 - Frontend Gameplay Completion
3. Milestone 10 - Engineering Maintainability and Contracts
4. Milestone 13 - Player Experience and UX Flow

## Core Rules
- Exactly one milestone may be `is_current: yes`.
- Current milestone must be `execution_window: open`.
- Execute earliest available open milestone unless blocked or user-overridden.

## Open / Close Gates
- Open when upstream blockers are resolved/accepted and at least three issues are actionable.
- Close when required scope is complete/deferred with rationale and no unresolved high-priority blockers remain.

## Dependency Metadata
- Optional issue fields: `blocked_by`, `enables`.
- Use exact issue titles and keep dependency chains shallow.

## Triage Cadence
- Weekly, at milestone boundaries, and after major roadmap/contract changes.

## Status Policy
- `unstarted -> in-progress -> blocked/in-progress -> archived complete`.
- Use `reopened` for regressions.

## File Roles
- Active execution: `ISSUES.md`, `MILESTONES.md`.
- Deferred inventory: `ISSUES_BACKLOG.md`, `MILESTONES_BACKLOG.md`.
