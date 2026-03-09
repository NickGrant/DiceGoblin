# Milestone 6 Release Gate Criteria
----

Status: active  
Last Updated: 2026-03-09  
Owner: Product + Engineering + QA  
Depends On: `documentation/TESTING_STRATEGY.md`, `ISSUES.md`, `MILESTONES.md`

## Purpose
- Define objective release-readiness criteria for Milestone 6.

## Required Automated Gates
- `npm run llm:check` passes.
- `composer --working-dir=backend test` passes.
- `npm.cmd --prefix frontend run test` passes.
- `npm.cmd --prefix frontend run build` passes.

## Required Manual Gates
- Execute `documentation/05-playability-stability/01-critical-path-playtest-script.md`.
- Capture evidence for each scenario:
  - pass/fail result
  - observed behavior summary
  - issue references for failures
- Include stale/partial run-state recovery checks inside the same playtest evidence.

## Blocker Thresholds
- Release blocker:
  - unresolved `priority: high` issue in scope
  - reproducible crash, hard-lock, or dead-end in critical-path flows
  - stale-state recovery failure that strands the player
- Conditional blocker:
  - unresolved `priority: medium` issues that materially degrade first-session clarity

## Exit Decision
- Milestone can close only when all required gates pass and blockers are resolved or explicitly accepted.
- Any accepted exception must be logged in `documentation/CHANGELOG.md` with rationale.
