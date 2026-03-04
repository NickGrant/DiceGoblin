# Encounter Flow Transition Matrix - MVP
----

Status: active  
Last Updated: 2026-03-04  
Owner: Product + Frontend  
Depends On: `documentation/01-architecture/02-frontend-state-and-scene-contracts.md`, `documentation/01-architecture/03-backend-api-contracts.md`, `documentation/02-systems-mvp/03-encounter-scope.md`

## Purpose
- Define the canonical encounter flow from Run Map through encounter resolution and return.
- Make allowed and blocked transitions explicit for each encounter type.
- Provide acceptance criteria that implementation and QA can validate against.

## States
- `RunMap`: map exploration and node selection.
- `EncounterStart` (planned): pre-encounter summary/confirmation surface.
- `CombatViewer` (planned): combat log playback surface.
- `RewardSurface` (planned): claim and summary surface.
- `RestManagement` (planned): rest edit/finalize management surface.
- `RunEnd` (planned): end-of-run summary surface for failed/abandoned/completed outcomes.

## Transition Matrix
| From | Trigger | To | Allowed | Notes |
| --- | --- | --- | --- | --- |
| RunMap | Click `available` node (`combat`) | EncounterStart | yes | Encounter data must exist for node. |
| RunMap | Click `available` node (`boss`) | EncounterStart | yes | Same as combat with boss styling/metadata. |
| RunMap | Click `available` node (`loot`) | RewardSurface | yes | Non-combat immediate reward surface. |
| RunMap | Click `available` node (`rest`) | RestManagement | yes | Rest opens dedicated management scene. |
| RunMap | Click `available` node (`exit`) | RunEnd | yes | Allowed only when boss path is cleared/unlocked. |
| RunMap | Click `locked` node | RunMap | blocked | No API mutation allowed. |
| RunMap | Click `cleared` node | RunMap | blocked | Optional replay affordance only, no progression mutation. |
| EncounterStart | Confirm start | CombatViewer | yes | Applies to combat/boss nodes only. |
| EncounterStart | Cancel | RunMap | yes | Node remains `available`. |
| CombatViewer | Resolve complete | RewardSurface | yes | Uses stored server outcome/log only. |
| CombatViewer | Skip/exit before completion | CombatViewer | blocked | No early exit to prevent state ambiguity. |
| RewardSurface | Claim complete and node victory/non-combat | RunMap | yes | Node becomes `cleared`; unlock check runs. |
| RewardSurface | Claim boss victory | RunMap | yes | Boss node clears and unlocks path to exit node. |
| RewardSurface | Claim complete with combat defeat and remaining units | RunMap | yes | Node remains `available` for retry. |
| RewardSurface | Claim complete with total defeat | RunEnd | yes | Run status becomes `failed`. |
| RestManagement | Finalize rest | RunMap | yes | Shows per-unit rest summary before return. |
| RestManagement | Cancel rest edits | RunMap | yes | No rest consumption, no mutations persisted. |
| RunMap | Abandon run action | RunEnd | yes | Run status becomes `abandoned`. |
| RunEnd | Continue | Home/RegionSelect | yes | No return to active run. |

## Blocked Transition Rules
- No direct `RunMap -> CombatViewer` transition; encounter start gate is required for combat/boss.
- No `CombatViewer -> RunMap` before resolution payload is finalized.
- No mutation calls on `locked`/`cleared` node interactions.
- No direct `RunMap -> RunEnd` via exit until boss path is unlocked.
- No post-claim return to an active run after terminal `failed`/`abandoned`/`completed` outcome.

## Node-Type Acceptance Criteria

### Combat
- Selecting an `available` combat node enters encounter flow and eventually yields `RewardSurface`.
- On victory, node status becomes `cleared` and downstream unlock checks execute.
- On defeat with remaining undefeated run units, node remains `available` and retry is possible without energy cost.
- On total defeat, run transitions to `failed` terminal state.

### Boss
- Follows combat transition rules.
- Uses boss encounter metadata/presentation and boss reward profile in reward surface.

### Loot
- Selecting an `available` loot node bypasses combat and opens reward surface.
- Claim completes node as `cleared` and returns to Run Map.
- No combat replay controls are shown.

### Rest
- Selecting an `available` rest node opens `RestManagement`.
- Rest management supports out-of-run actions in one place:
  - squad/formation edits,
  - promotion from unit details,
  - dice equip/unequip.
- Finalize shows summary of healed units and progression deltas (level/promotion), then returns to Run Map.

### Exit
- Exit node is always map-visible and uses a visually distinct presentation.
- Exit node remains unreachable until boss path is unlocked.
- Selecting unlocked exit transitions run to `completed` and opens `RunEnd` summary shell.

### RunEnd Summary
- Uses one shared shell for completed, failed, and abandoned outcomes with outcome-specific messaging.
- Summary content includes:
  - rewards,
  - XP/level progression,
  - surviving/defeated unit breakdown.
- Summary does not include recommendations/next-step prompts.

## Contract Checks
- `GET /api/v1/runs/current` must provide node statuses that match UI affordances (`locked|available|cleared`).
- `POST /api/v1/runs/:runId/nodes/:nodeId/resolve` must be idempotent for non-retry cases.
- `POST /api/v1/battles/:battleId/claim` must be idempotent and return run-resolution metadata when terminal.
- `POST /api/v1/runs/:runId/exit` must return terminal summary payload for completed runs.

## Out of Scope
- Cinematic branching transitions.
- Mid-combat reconnect resume to an in-progress simulation.
- Multi-encounter queueing.
- Post-run recommendations/next-step hints in run-end summary.
