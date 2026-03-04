# Milestone 6 Release Gate Criteria
----

Status: active  
Last Updated: 2026-03-04  
Owner: Product + Engineering + QA  
Depends On: `documentation/TESTING_STRATEGY.md`, `ISSUES.md`, `MILESTONES.md`

## Purpose
- Define objective release-readiness criteria for Milestone 6.
- Prevent ambiguous "seems stable" sign-off decisions.
- Align automated and manual verification expectations.

## Required Automated Gates
- `npm run llm:check` passes.
- `composer --working-dir=backend test` passes.
- `npm.cmd --prefix frontend run test` passes.
- `npm.cmd --prefix frontend run build` passes.
- No new high-severity test failures introduced by Milestone 6 changes.

## Required Manual Gates
- Execute `documentation/05-playability-stability/01-critical-path-playtest-script.md`.
- Capture evidence for each scenario:
  - pass/fail result,
  - observed behavior summary,
  - issue references for failures.
- Execute `documentation/05-playability-stability/03-stale-state-recovery-checklist.md`.

## Blocker Thresholds
- Release blocker:
  - any unresolved `priority: high` Milestone 6 issue,
  - any reproducible crash, hard-lock, or dead-end in critical path flows,
  - stale-state recovery failures that strand the player without a path to continue.
- Conditional blocker (requires explicit user acceptance):
  - unresolved `priority: medium` issues that materially degrade first-session clarity.

## Exit Decision Rules
- Milestone 6 can close only when:
  - all required automated gates pass,
  - manual gate evidence is recorded,
  - no unresolved release blockers remain.
- If exceptions are accepted, document them in `documentation/CHANGELOG.md` with rationale and follow-up issue links.
