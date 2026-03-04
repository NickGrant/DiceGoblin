# Stale-State Recovery Validation Checklist
----

Status: active  
Last Updated: 2026-03-04  
Owner: QA  
Depends On: `documentation/01-architecture/02-frontend-state-and-scene-contracts.md`, `documentation/03-ux/03-encounter-flow-transition-matrix.md`

## Purpose
- Validate that stale/partial run-state conditions degrade gracefully.
- Ensure users are not trapped when API payloads are delayed, partial, or out-of-sync.

## Pass Criteria
- No unrecoverable scene lockups.
- Clear user-facing fallback messaging when data is incomplete.
- Recovery path available within one user action (retry, refresh state, return to safe scene).

## Checklist
1. Partial current-run payload handling:
   - Simulate missing `map.nodes` or `map.edges` in current-run response.
   - Expect safe fallback UI and no crash.
2. Missing run payload with active scene:
   - Simulate `run: null`, `map: null` while map scene is active.
   - Expect transition to safe non-run state or explicit stale-state message.
3. Stale node state after action:
   - Resolve node, then reload stale map snapshot.
   - Expect re-fetch or guarded recovery path instead of invalid interaction.
4. Claim response drift handling:
   - Simulate partial claim payload fields.
   - Expect explicit handling and non-blocking fallback behavior.
5. Session continuity after refresh:
   - Refresh during run and verify state rehydration path.
   - Expect no dead-end where player cannot resume or safely exit.

## Evidence Template
```yaml
check_id: M6-STALE-<seq>
scenario: <checklist item>
result: pass | fail | blocked
observed_behavior: |
  <what happened>
expected_behavior: |
  <what should happen>
issue_link: <issue title or TBD>
```
