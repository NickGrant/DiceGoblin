# Active Context Snapshot
----

Status: active  
Last Updated: 2026-03-02  
Owner: Product + Engineering  
Depends On: `ISSUES.md`, `documentation/README.md`

## Purpose
- Provide a fast, high-signal view of current priorities and blockers.
- Minimize startup context loading for coding sessions.

## Current Focus
- Keep planning and execution tracking in `ISSUES.md` (worklist deprecated).
- Track roadmap grouping in `MILESTONES.md`.
- Close high-priority gameplay correctness gaps:
  - deterministic run node battle resolution
  - non-placeholder reward and XP application
- Reduce doc/code drift:
  - align API contract docs with implemented backend routes and payloads

## Top Priorities
1. Implement deterministic combat engine integration in node resolution flow.
2. Implement real reward + XP application on battle claim.
3. Align backend API contract docs and warband UX/system docs with current implementation.

## Known Blockers / Risks
- Placeholder combat/reward implementations can hide regressions in run progression.
- API contract drift increases coordination cost between frontend, backend, and docs.
- Limited automated tests around squad mutation/idempotency increase regression risk.

## Working Agreements
- `ISSUES.md` is active backlog source of truth.
- `MILESTONES.md` groups issues into delivery milestones.
- Completed items move to `ISSUES_ARCHIVE.md` immediately.
- Load archive docs only when historical context is explicitly needed.
