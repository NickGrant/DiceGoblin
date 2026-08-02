---
Title: "First Release Manual Gate Evidence"
Status: Legacy Reference
Last Updated: 2026-08-01
Owner: Product + QA + Engineering
Depends On:
  - documentation/06-testing-release/01-release-gate-criteria.md
  - documentation/06-testing-release/02-critical-path-playtest-script.md
  - documentation/07-development-path/CHANGELOG.md
Category: 06-testing-release
Tags:
  - testing-release
  - evidence
---

# First Release Manual Gate Evidence

## Purpose
- Preserve historical manual evidence from an earlier first-release closeout pass.
- Do not use this as the current demo release checklist; use `../05-first-release-checklist.md` instead.

## Execution Summary
- Environment:
  - frontend: `http://localhost:5173`
  - backend: Docker local at `http://localhost:8080`
  - database: Docker local `dice_goblins`
- Auth/session setup:
  - used a local authenticated session for user `1` to execute API-backed release checks
  - validated reset/bootstrap against the live backend, not debug scene fixtures
- Result:
  - manual gate scenarios required for the historical milestone passed
  - one blocker was found during execution (`reset-account` 500 due to undeleted `shop_daily_deals` rows) and fixed in the same pass before final evidence was recorded

## Evidence

```yaml
playtest_id: M31-20260401-codex-01
build_ref: 2f7a3da + local milestone-closeout changes
environment:
  browser: API-driven local verification
  backend: docker-local
  db_state: reset during execution
result: pass
steps:
  session_bootstrap: pass
  profile_hydration: pass
  start_run: pass
  resolve_non_combat: pass
  resolve_combat: pass
  retry_failure_branch: pass
  resume_behavior: pass
notes: |
  Created a live authenticated session for local verification and ran the critical-path flow against the Docker backend.
  Reset-account initially failed with a 500 because shop_daily_deals rows were not deleted before dice cleanup; this was fixed in DevToolsService and revalidated.
  Fresh baseline after reset contained one active squad, four starter units, seven dice, zero currency, full energy, and only The Farm unlocked.
  Completed a full Farm run through combat, loot, rest, boss, and exit. The run ended successfully, currency increased to 18, a loot die was granted, and Mountains unlocked on profile refresh.
  Started a Mountains run afterward and confirmed the same run reloaded through a second request, validating resume continuity for active-run state.
  Forced the Mountains run into a low-HP state through controlled local test setup, resolved the first combat node, and claimed a defeat result. The claim returned run_resolution.status=failed and /runs/current returned run=null immediately afterward.
defects:
  - id: reset-account shop_daily_deals cleanup
    severity: high
    summary: reset-account failed until shop_daily_deals rows were added to account cleanup order; fixed during this pass.
```

## Scenario Notes

### Fresh Account Bootstrap
- Action:
  - executed `POST /api/v1/debug/reset-account`
  - fetched `GET /api/v1/profile`
- Observed:
  - response returned `squads: 1`, `units: 4`, `dice: 7`, `region_unlocks: 1`, `active_run: false`
  - profile showed only `the_farm` unlocked
  - energy restored to `50 / 50`
  - currency reset to `0`
- Result:
  - pass

### Successful Run
- Action:
  - started a Farm run
  - resolved combat node `546`, claimed battle `220`
  - resolved loot node `547`, claimed battle `221`
  - finalized rest node `548`
  - resolved boss node `549`, claimed battle `222`
  - exited run `57`
- Observed:
  - run completed successfully
  - profile afterward showed `mountains` unlocked
  - loot and currency rewards were persisted
  - no stale active run remained after exit
- Result:
  - pass

### Failed Run
- Action:
  - started Mountains run `58`
  - validated the run was resumable through a second `GET /api/v1/runs/current`
  - forced a low-HP scenario for the active run
  - resolved combat node `551` to battle `223`
  - claimed the defeat
- Observed:
  - claim returned `run_resolution.status = failed`
  - `GET /api/v1/runs/current` immediately returned `run: null`
  - profile preserved prior unit progression and returned to non-run state cleanly
- Result:
  - pass

### Resume Continuity
- Action:
  - after starting Mountains run `58`, fetched `GET /api/v1/runs/current` again using a separate request/session object
- Observed:
  - returned the same `run_id`
  - node statuses and run-unit HP matched the active in-progress state
- Result:
  - pass

### Reset Account Validation
- Action:
  - executed live `reset-account` before and after the cleanup fix
- Observed:
  - before fix: `500`
  - after fix: clean `200` with fresh baseline counts
- Result:
  - pass after fix

### Release Configuration Check
- Frontend dev panel:
  - [`devFlags.ts`](c:/xampp/htdocs/dice-goblin/frontend/src/debug/devFlags.ts) defaults to disabled unless `VITE_ENABLE_DEV_PANEL` is explicitly truthy.
  - Result: pass for default frontend behavior.
- Backend debug endpoints:
  - current local dev env still uses `ENABLE_DEBUG_ENDPOINTS=1` in [`backend/.env`](c:/xampp/htdocs/dice-goblin/backend/.env).
  - release checklist now explicitly requires `ENABLE_DEBUG_ENDPOINTS=0` for external/release deployment.
  - Result: checklist item recorded for release packaging.

## Linked Verification
- `npm.cmd run llm:check`
- `composer --working-dir=backend test`
- `npm.cmd --prefix frontend run test`
- `npm.cmd --prefix frontend run build`
