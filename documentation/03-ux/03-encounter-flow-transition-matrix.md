# Encounter Flow Transition Matrix - MVP
----

Status: active  
Last Updated: 2026-03-03  
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
- `RunEnd` (planned): failed/abandoned/completed terminal surface.

## Transition Matrix
| From | Trigger | To | Allowed | Notes |
| --- | --- | --- | --- | --- |
| RunMap | Click `available` node (`combat`) | EncounterStart | yes | Encounter data must exist for node. |
| RunMap | Click `available` node (`boss`) | EncounterStart | yes | Same as combat with boss styling/metadata. |
| RunMap | Click `available` node (`loot`) | RewardSurface | yes | Non-combat immediate reward surface. |
| RunMap | Click `available` node (`rest`) | RewardSurface | yes | Non-combat immediate recovery surface. |
| RunMap | Click `locked` node | RunMap | blocked | No API mutation allowed. |
| RunMap | Click `cleared` node | RunMap | blocked | Optional replay affordance only, no progression mutation. |
| EncounterStart | Confirm start | CombatViewer | yes | Applies to combat/boss nodes only. |
| EncounterStart | Cancel | RunMap | yes | Node remains `available`. |
| CombatViewer | Resolve complete | RewardSurface | yes | Uses stored server outcome/log only. |
| CombatViewer | Skip/exit before completion | CombatViewer | blocked | No early exit to prevent state ambiguity. |
| RewardSurface | Claim complete and node victory/non-combat | RunMap | yes | Node becomes `cleared`; unlock check runs. |
| RewardSurface | Claim complete with combat defeat and remaining units | RunMap | yes | Node remains `available` for retry. |
| RewardSurface | Claim complete with total defeat | RunEnd | yes | Run status becomes `failed`. |
| RunMap | Abandon run action | RunEnd | yes | Run status becomes `abandoned`. |
| RunEnd | Continue | Home/RegionSelect | yes | No return to active run. |

## Blocked Transition Rules
- No direct `RunMap -> CombatViewer` transition; encounter start gate is required for combat/boss.
- No `CombatViewer -> RunMap` before resolution payload is finalized.
- No mutation calls on `locked`/`cleared` node interactions.
- No post-claim return to an active run after terminal `failed`/`abandoned` outcome.

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
- Selecting an `available` rest node bypasses combat and opens recovery/reward surface.
- Claim applies rest effects and marks node `cleared`.
- Return to Run Map is immediate after claim success.

## Contract Checks
- `GET /api/v1/runs/current` must provide node statuses that match UI affordances (`locked|available|cleared`).
- `POST /api/v1/runs/:runId/nodes/:nodeId/resolve` must be idempotent for non-retry cases.
- `POST /api/v1/battles/:battleId/claim` must be idempotent and return run-resolution metadata when terminal.

## Out of Scope
- Cinematic branching transitions.
- Mid-combat reconnect resume to an in-progress simulation.
- Multi-encounter queueing.
