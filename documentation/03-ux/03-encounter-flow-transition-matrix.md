# Run, Encounter, Rest, and Summary UX Contract
----

Status: active  
Last Updated: 2026-05-29  
Owner: Product + Frontend  
Depends On: `documentation/01-architecture/02-frontend-state-and-scene-contracts.md`, `documentation/01-architecture/03-backend-api-contracts.md`, `documentation/02-systems-mvp/03-encounter-scope.md`, `documentation/02-systems-mvp/08-encounter-reward-surface-rules.md`

## Purpose

- Define the canonical player flow during an active run.
- Keep run map, node resolution, rest, and run-end behavior in one place.
- Document the preview, reward, failure, and recovery expectations that shape the run experience.

## Active Run Surfaces

- Run map:
  - choose the next available node
- Node resolution:
  - resolve combat, boss, loot, and exit nodes
- Rest management:
  - handle the run-scoped rest workflow
- Run summary:
  - present completed, failed, or abandoned outcomes

## Transition Matrix

| From | Trigger | To | Allowed | Notes |
| --- | --- | --- | --- | --- |
| Run map | Click available `combat` node | Node resolution | yes | Unified non-rest route. |
| Run map | Click available `boss` node | Node resolution | yes | Same route with boss metadata. |
| Run map | Click available `loot` node | Node resolution | yes | Non-combat outcome surface. |
| Run map | Click available `rest` node | Rest management | yes | Dedicated rest workflow. |
| Run map | Click available `exit` node | Node resolution | yes | Exit resolves then branches to summary. |
| Run map | Click locked node | Run map | blocked | No mutation allowed. |
| Run map | Click cleared node | Run map | blocked | Cleared nodes are informational only. |
| Node resolution | Non-terminal outcome | Run map | yes | Return to map with feedback. |
| Node resolution | Terminal outcome | Run summary | yes | Shared terminal shell. |
| Rest management | Finalize rest | Run map | yes | Apply backend-authoritative updates and return. |
| Rest management | Cancel rest edits | Run map | yes | No rest consumption. |
| Run map | Abandon run | Run summary | yes | Requires explicit confirmation. |
| Run summary | Continue | Home | yes | Active run is over. |

## Run Map Contract

The run map must communicate:

- node type
- node status: locked, available, or cleared
- unlock paths
- current warband condition
- distinct exit-node treatment

The map should also provide a compact preview surface for actionable nodes.

Preview content should communicate:

- node type
- qualitative risk
- qualitative reward intent
- unlock-path relationship when useful

Preview content should not expose:

- exact combat outcomes
- exact loot rolls
- hidden unrevealed future state

## Node Resolution Contract

Node resolution is the unified non-rest outcome surface.

It should:

- restate the selected node intent before or during resolve
- clearly show result state
- show rewards or an explicit no-reward fallback
- provide one primary next-step action
- branch back to map or forward to summary without ambiguity

For combat or boss outcomes, the surface may also include battle playback. If playback exists, it must stay subordinate to the outcome contract rather than becoming a separate navigation layer.

## Rest Management Contract

Rest is the only supported active-run management window.

The flow is:

- open
- inspect or edit allowed run-scoped management state
- finalize or cancel

Finalize should summarize:

- healing or recovery effects
- progression deltas
- any approved roster or equipment changes

## Run Summary Contract

Run summary uses one shared shell for:

- completed
- failed
- abandoned

Every summary should include:

- outcome
- rewards
- progression
- survivor and defeated-unit impact
- one clear return action

## Failure and Recovery Rules

- Defeat outcomes must clearly state whether the run continues or ends.
- Terminal failure uses distinct messaging from abandonment and completion.
- Abandonment must be clearly player-initiated and confirmed.
- Retry language should only appear when a true retry path exists.

## Shared UX Rules

- Blocked actions should remain visible but clearly unavailable.
- Error states should keep the player oriented and offer the next sensible action.
- Messaging should be short, concrete, and specific about what changed.
- Reward and progression terminology should match backend payload semantics.

## Current Scope Note

- The route and branching contract is active now.
- Rich battle playback remains the main presentation layer still open for refinement.
