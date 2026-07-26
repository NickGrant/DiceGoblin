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
  - CI or host tools: `npm.cmd run test:backend`
  - Local Docker: `npm.cmd run test:backend:docker`

Docker is the preferred local backend/PHP/database toolchain. CI/pipeline verification uses host PHP/Composer from GitHub Actions. If Docker is not running during local work, ask the user to start Docker before running backend or database verification.

## Verification Matrix
- Backend API/controller/repository changes:
  - integration tests for touched endpoints
  - auth/CSRF/ownership negative-path validation
- API contract changes:
  - contract validation + architecture doc updates
- Frontend scene/interaction changes:
  - interaction/state tests + manual scene sanity pass
  - if the change affects mobile layout, run `documentation/05-playability-stability/04-mobile-viewport-regression-checklist.md`
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
- Balance-affecting changes should include simulation evidence once the repository simulation tool exists. Until then, PRs should describe the expected balance impact and any manual playtest coverage.

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
- `documentation/02-systems-mvp/14-balancing-strategy-and-simulation.md`
- `documentation/05-playability-stability/00-release-gate-criteria.md`
- `documentation/05-playability-stability/04-mobile-viewport-regression-checklist.md`
