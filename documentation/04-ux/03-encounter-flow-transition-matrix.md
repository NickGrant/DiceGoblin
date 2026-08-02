---
Title: "Run, Encounter, Rest, and Summary UX Contract"
Status: Canonical
Last Updated: 2026-08-01
Owner: Product + UX
Depends On:
  - documentation/05-technical/02-frontend-state-and-scene-contracts.md
  - documentation/05-technical/03-backend-api-contracts.md
  - documentation/02-systems/mvp-reference/03-encounter-scope.md
  - documentation/02-systems/mvp-reference/08-encounter-reward-surface-rules.md
Category: 04-ux
Tags:
  - ux
---

# Run, Encounter, Rest, and Summary UX Contract

## Purpose

- Define the canonical player flow during an active run.
- Keep run map, node resolution, rest, and run-end behavior in one place.
- Document the preview, reward, failure, and recovery expectations that shape the run experience.

## Active Run Surfaces

- Run map:
  - choose the next available node
- Node resolution:
  - resolve combat, boss, loot, and exit nodes
- Rest recovery:
  - heal run units and continue the map
- Run summary:
  - present completed, failed, or abandoned outcomes

## Transition Matrix

| From | Trigger | To | Allowed | Notes |
| --- | --- | --- | --- | --- |
| Run map | Click available `combat` node | Node resolution | yes | Unified non-rest route. |
| Run map | Click available `boss` node | Node resolution | yes | Same route with boss metadata. |
| Run map | Click available `loot` node | Node resolution | yes | Non-combat outcome surface. |
| Run map | Click available `rest` node | Rest recovery | yes | Dedicated recovery flow. |
| Run map | Click available `exit` node | Node resolution | yes | Exit resolves then branches to summary. |
| Run map | Click locked node | Run map | blocked | No mutation allowed. |
| Run map | Click cleared node | Run map | blocked | Cleared nodes are informational only. |
| Node resolution | Non-terminal outcome | Run map | yes | Return to map with feedback. |
| Node resolution | Terminal outcome | Run summary | yes | Shared terminal shell. |
| Rest recovery | Rest | Run map | yes | Heal run units, consume the node, and return. |
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

## Rest Recovery Contract

Rest is a recovery node, not a roster or loadout management window.

The flow is:

- open
- inspect current run-unit recovery state
- rest

Finalize should summarize:

- healing or recovery effects
- progression deltas

Rest does not allow:

- squad changes
- formation changes
- promotion
- dice or loadout changes

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

## Motion Vocabulary

- Route entry:
  - use a fast shell-level screen enter on authenticated route changes
- Surface reveal:
  - use a lighter reveal for shared page frames and major scene wrappers
- Panel stagger:
  - use short staggered panel reveals for card groups, tile groups, and battle-log sections when it improves readability
- Reduced motion:
  - all route and panel motion must disable cleanly when the user prefers reduced motion

## Current Scope Note

- The route and branching contract is active now.
- Rich battle playback remains the main presentation layer still open for refinement.
