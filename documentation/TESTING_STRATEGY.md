# Testing Strategy
----

Status: active  
Last Updated: 2026-03-09  
Owner: QA + Engineering  
Depends On: `ISSUES.md`, `MILESTONES.md`, `AGENTS.md`

## Purpose
- Define minimum verification requirements by change type.
- Keep release-blocking criteria explicit.

## Required Checks
- Always run for code changes:
  - `npm run llm:check`
- Frontend changes:
  - `npm.cmd --prefix frontend run test`
  - `npm.cmd --prefix frontend run build`
- Backend changes:
  - `composer --working-dir=backend test` (or backend equivalent)

## Verification Matrix
- Backend API/controller/repository changes:
  - integration tests for touched endpoints
  - auth/CSRF/ownership negative-path validation
- API contract changes:
  - contract validation + architecture doc updates
- Frontend scene/interaction changes:
  - interaction/state tests + manual scene sanity pass
- Documentation-only changes:
  - `npm run llm:check` and reference consistency review

## Release Blocking
- Blocking:
  - failed required tests/build checks
  - unresolved high-severity regressions in active scope
  - undocumented contract changes affecting clients
- Non-blocking:
  - low-risk cosmetic issues
  - minor wording/formatting drift

## References
- `documentation/01-architecture/03-backend-api-contracts.md`
- `documentation/01-architecture/02-frontend-state-and-scene-contracts.md`
- `documentation/05-playability-stability/00-release-gate-criteria.md`
