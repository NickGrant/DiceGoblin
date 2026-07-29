# Release Readiness Validation
----

Status: active  
Last Updated: 2026-07-29  
Owner: Engineering + QA  
Depends On: `documentation/05-playability-stability/00-release-gate-criteria.md`, `documentation/05-playability-stability/06-july-roadmap-uat-balance-checklist.md`, `agent/ISSUES.md`

## Purpose
- Provide one repeatable validation entry point for the July roadmap UAT handoff.
- Keep generated frontend artifacts, active tracker state, and release documentation aligned before final release hardening.
- Separate quick hygiene checks from the heavier Docker/frontend gate pass.

## Quick Check

Run this before handing a PR or branch into UAT review:

```powershell
npm.cmd run release:check
```

This verifies:
- required active tracker and UAT documents exist
- `frontend/dist` has no uncommitted generated-artifact changes
- source, test, documentation, and agent tracker files have no uncommitted changes
- startup context validation passes
- backlog validation passes
- documentation lint runs without hard failure

## Full Check

Run this before final release handoff after UAT blockers are resolved:

```powershell
npm.cmd run release:check:full
```

This includes the quick check plus:
- Docker test DB reset from `backend/migrations/schema_all.sql`
- backend PHPUnit suite through Docker
- frontend unit tests in Chrome Headless
- frontend production build
- Mountains Pattern-V2 simulation gate
- Swamps Pattern-V2 simulation gate

## Evidence Block

Copy this into the UAT or release note when the check is run:

```yaml
release_readiness:
  branch:
  commit:
  command: npm.cmd run release:check | npm.cmd run release:check:full
  result: pass | fail
  generated_artifacts_clean: yes | no
  docker_test_db_reset: yes | no
  notes:
```
