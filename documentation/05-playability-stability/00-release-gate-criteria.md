# First Pig Kin Demo Release Gate Criteria
----

Status: active
Last Updated: 2026-07-30
Owner: Product + Engineering + QA
Depends On: `documentation/05-playability-stability/01-critical-path-playtest-script.md`, `agent/ISSUES.md`, `agent/MILESTONES.md`

## Purpose

Define objective release-readiness criteria for the formal demo whose player-facing endpoint is first Pig Kin creation.

## Required Automated Gates

- `npm.cmd run llm:check` passes.
- Backend PHP tests relevant to touched areas pass through Docker.
- Frontend tests relevant to touched areas pass.
- `npm.cmd run build:frontend` passes.
- `npm.cmd run docs:lint` passes.
- `npm.cmd run backlog:validate` passes.
- `git diff --check` passes.

## Required Manual Gates

- Execute `documentation/05-playability-stability/01-critical-path-playtest-script.md`.
- Capture evidence for each scenario:
  - pass/fail result;
  - observed behavior summary;
  - build reference;
  - production migration status;
  - issue references for failures.
- Include refresh/resume checks during active runs, after Wrong Machine recovery, and after Pig Kin creation.

## Blocker Thresholds

Release blockers:

- unresolved high-priority issue in the first Pig Kin demo path;
- reproducible crash, 500, hard-lock, or dead end;
- missing migration or production schema mismatch;
- inability to create first Pig Kin from a fresh-account path;
- reward, Raw Chaos, or material loss that blocks required progression.

Conditional blockers:

- unresolved medium-priority issue that materially weakens first-session clarity;
- confusing dialogue, objective, or Wrong Machine copy that requires outside explanation;
- mobile layout defect on a required demo surface.

## Exit Decision

The demo milestone can close only when all required gates pass and blockers are resolved or explicitly accepted in release notes.