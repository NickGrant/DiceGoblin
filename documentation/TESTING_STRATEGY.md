# Testing Strategy
----

Status: active  
Last Updated: 2026-05-29  
Owner: QA + Engineering  
Depends On: `agent/ISSUES.md`, `agent/MILESTONES.md`, `AGENTS.md`, `documentation/ENGINEERING_STANDARDS.md`

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

## Coverage Expectations
- New logic should ship with automated verification at the service, component, or integration level that owns that behavior.
- Bug fixes should add regression coverage when the issue can be automated.
- Presentational-only changes may rely on screenshot/manual verification if behavior and state handling did not change.
- High-risk flows should not rely on manual verification alone:
  - session bootstrap
  - run progression
  - purchases and inventory mutations
  - squad/unit mutation flows

## Release Blocking
- Blocking:
  - failed required tests/build checks
  - unresolved high-severity regressions in active scope
  - undocumented contract changes affecting clients
- Non-blocking:
  - low-risk cosmetic issues
  - minor wording/formatting drift

## References
- `documentation/ENGINEERING_STANDARDS.md`
- `documentation/01-architecture/03-backend-api-contracts.md`
- `documentation/01-architecture/02-frontend-state-and-scene-contracts.md`
- `documentation/05-playability-stability/00-release-gate-criteria.md`
