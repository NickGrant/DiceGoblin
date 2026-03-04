# Critical Path Playtest Script
----

Status: active  
Last Updated: 2026-03-04  
Owner: QA  
Depends On: `documentation/03-ux/03-encounter-flow-transition-matrix.md`, `documentation/02-systems-mvp/06-run-resolution-scope.md`

## Purpose
- Provide a repeatable manual test flow for Milestone 6 stability checks.
- Standardize evidence capture across sessions.

## Execution Notes
- Run against the same build/version used for automated gate validation.
- Use a fresh account/session for first-session checks when possible.
- If a step cannot be completed, capture blocker details and stop only if progression is impossible.

## Script
1. Session bootstrap:
   - Load app from fresh tab.
   - Confirm session resolves and UI reaches a playable state.
2. Profile hydration:
   - Confirm squads, units, energy, and run summary surfaces render.
   - Confirm no console-visible contract errors.
3. Start run:
   - Select available region and start run.
   - Confirm transition to run map and available-node affordances.
4. Resolve non-combat node:
   - Resolve one `rest` or `loot` node.
   - Confirm reward surface consistency and safe return to map flow.
5. Resolve combat node:
   - Resolve one combat node through claim.
   - Confirm post-battle payload usage, progression update visibility, and no soft-lock.
6. Retry/failure branch:
   - Trigger partial-defeat or failure path when feasible.
   - Confirm expected retry/failure UX and progression handling.
7. Resume behavior:
   - Refresh/reopen app mid-run.
   - Confirm active run reload and map state continuity.

## Evidence Capture Template
Copy this block for each playtest execution:

```yaml
playtest_id: M6-YYYYMMDD-<initials>-<seq>
build_ref: <commit/tag/local>
environment:
  browser: <name/version>
  backend: <local/staging>
  db_state: <fresh/reused>
result: pass | fail | blocked
steps:
  session_bootstrap: pass | fail | blocked
  profile_hydration: pass | fail | blocked
  start_run: pass | fail | blocked
  resolve_non_combat: pass | fail | blocked
  resolve_combat: pass | fail | blocked
  retry_failure_branch: pass | fail | blocked
  resume_behavior: pass | fail | blocked
notes: |
  <observations>
defects:
  - id: <issue title or TBD>
    severity: low | medium | high
    summary: <brief defect summary>
```
