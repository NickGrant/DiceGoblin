# Testing Strategy
----

Status: active  
Last Updated: 2026-03-02  
Owner: QA + Engineering  
Depends On: `ISSUES.md`, `MILESTONES.md`, `AGENTS.md`

## Purpose
- Define how this repository verifies changes before merge/release.
- Standardize test coverage expectations by change type.
- Clarify what is release-blocking versus advisory.

## Test Tiers
1. Unit tests
   - Scope: pure logic and deterministic helpers.
   - Goal: fast regression checks at function/module level.
2. Integration tests
   - Scope: repository, service, and API endpoint flows with real persistence boundaries.
   - Goal: validate auth, CSRF, ownership, and state transitions.
3. Contract tests
   - Scope: API request/response envelope and required key invariants consumed by frontend.
   - Goal: prevent payload drift between backend and client.
4. Manual verification
   - Scope: scene flow, UX behavior, and high-risk end-to-end gameplay paths.
   - Goal: catch interaction defects not yet covered by automation.

## Ownership
- QA Lead
  - defines regression matrix and release gate requirements
  - audits risk coverage for active milestone scope
- Senior Developer
  - implements/maintains automated test suites
  - ensures new behavior has proportional automated coverage
- Technical Product Manager
  - confirms acceptance criteria are testable and documented
  - resolves ambiguity when expected behavior conflicts across docs

## Execution Cadence
- On every code change:
  - run targeted tests for touched area
  - run required lint/build checks where available
- Before milestone handoff:
  - run full relevant suite for that milestone scope
  - execute manual sanity pass for impacted UX flows
- Before release candidate:
  - run all active automated suites
  - execute critical-path manual checklist

## Verification Matrix
- Backend controller/repository changes:
  - required: backend integration tests for touched endpoints/repositories
  - required: auth/CSRF/ownership negative-path checks for mutating endpoints
- API payload/contract changes:
  - required: contract tests for key names/types and envelope shape
  - required: doc updates in architecture contract docs
- Frontend scene/interaction changes:
  - required: frontend interaction or state tests for changed behavior
  - required: manual scene sanity check for flow transitions and error handling
- Documentation-only changes:
  - required: `documentation/QA_CHECKLIST.md` pass

## Release-Blocking Criteria
- Blocking:
  - failing required automated tests
  - unresolved high-severity regressions in active milestone scope
  - undocumented API behavior changes that affect client contracts
- Non-blocking (time-boxed follow-up allowed):
  - low-risk cosmetic UI issues
  - non-critical docs formatting drift

## Current Gaps (Tracked)
- Backend integration harness and endpoint regression coverage are in-progress under Milestone 8.
- Frontend interaction-test harness is in-progress under Milestone 8.
- Contract test coverage for session/profile/run payload invariants is pending.

## References
- `documentation/QA_CHECKLIST.md`
- `documentation/01-architecture/03-backend-api-contracts.md`
- `documentation/01-architecture/02-frontend-state-and-scene-contracts.md`
