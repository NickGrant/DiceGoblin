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
- Balance-affecting backend, reward, economy, or seed-data changes:
  - `npm.cmd run test:db:reset:docker`
  - `npm.cmd run sim:balance:battle:farm:docker`
  - `npm.cmd run sim:balance:run:farm:docker`
  - when the change affects Mountains, Swamps, or July roadmap pacing, also run `npm.cmd run sim:balance:run:uat-regions:docker`

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
- Balance-affecting changes should include simulation evidence from the repository simulator when the touched system can be represented by an existing suite. If no suite covers the change yet, PRs should say that directly and describe the expected balance impact plus any manual playtest coverage.

## Balance Report Format

Use this compact block in PR descriptions for balance-affecting changes:

```markdown
## Balance Impact
- Intent: Keep early farm combat winnable while reducing free reward drift.
- Expected player effect: Slightly slower teeth gain, no intended win-rate drop.

## Simulation
Command: `npm.cmd run sim:balance:run:farm:docker`
Before: completion_rate 0.92, soft_currency_per_sample 34.4, rounds_p90 3
After: completion_rate 0.92, soft_currency_per_sample 30.1, rounds_p90 3
Notes: No change to boss clear rate in this sample; manual smoke still needed for reward feel.
```

Keep pasted report lines to the summary values that explain the review decision. Attach or reference the full JSON only when the change is large enough that reviewers need deeper inspection.

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
