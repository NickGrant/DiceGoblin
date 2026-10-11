# Active Execution Issue

## Milestone 10 - Run Encounter Depth

### Milestone 10 Package 1 - Branch-capable run topology and route-choice foundation

**Status:** In Progress
**Priority:** High

#### Accepted baseline

Milestones 1-9 are complete. Milestone 9 technical closure is approved at `8e8701c7fb071b7513fbcac4ac3da62e266f6f8e` and focused manual UAT passed on 2026-10-10.

The existing vNext run architecture already owns authored region/run definitions, persisted generated runs and nodes, authoritative node resolution, combat/reward/terminal lifecycle, current-run queries, reconnect/resume, and Phaser `RunScene` presentation. Farm and Mountains are accepted production-composed regions using that shared architecture.

#### Problem

The current linear traversal cannot expose and persist an exploration frontier with multiple independently resolvable nodes.

#### Purpose

Establish the topology and authority boundary required for genuinely branch-capable runs before adding new Milestone 10 encounter families.

This package should make generated run graphs capable of exposing more than one legal next node and make the available frontier explicit, persisted, authoritative, idempotent/retry-safe, and reconnect-safe. Resolving one available node completes it and unlocks direct successors while all other available nodes remain available. Optional dead ends and converging routes are valid; one reachable terminal Exit remains required. It must preserve existing Farm/Mountains behavior and create a generic foundation for later Rest, hazard, shrine, Chaos, modifier, and multi-step encounter packages.

Do not add hazards, shrines, Chaos encounters, run modifiers, new consumable mechanics, knowledge/objectives, onboarding, Swamp/Frog Kin, or final visual redesign in this package.

#### Canonical design constraints

- Extend the existing authored region/run/node model; do not create a parallel run engine or branching-only schema.
- Canonical topology/generation rules belong in authored content and domain/application logic as appropriate, not client constants or SQL-authored catalogs.
- MySQL is authoritative for each generated run graph, resolved/current position, and durable route state.
- A node may be resolved only when it is currently reachable according to authoritative run state. Knowing another generated node ID must never authorize traversal.
- Route choice must survive reload/reconnect without rerolling or silently changing available choices.
- Completing one available node must not consume other available nodes. Any completed incoming route may unlock a convergent successor; an optional dead end adds no successors.
- Idempotent replay of a route/node action must return the finalized result without advancing twice.
- Phaser is a projection of server authority. It may render available branches and selected/current state but may not derive hidden reachability rules independently.
- Preserve one generic path across Farm and Mountains. Region-specific topology data is acceptable in authored content; region-specific traversal branches in controllers/application/runtime are not.
- Preserve active-run ownership/security and cross-player isolation.

#### Backend/content scope

1. Define the smallest canonical authored representation needed for branch-capable topology while retaining compatibility with current linear authored runs.
2. Extend generation so a run may persist a directed graph with one or more legal outgoing routes from the current/resolved position.
3. Define authoritative current-position/reachability semantics for:
   - initial run state;
   - unresolved current/available nodes;
   - resolving a chosen reachable node;
   - exposing its newly reachable successors while retaining the rest of the frontier;
   - completed/terminal runs;
   - reload/resume.
4. Ensure existing linear Farm/Mountains definitions continue to generate and resolve correctly without requiring duplicated content.
5. Reject attempts to resolve:
   - a node from another player/run;
   - a generated node that exists but is not currently reachable;
   - an already-resolved node except through valid idempotent replay;
   - any node after terminal completion.
6. Preserve existing node-specific resolution boundaries. This package changes traversal/topology authority, not the semantics of Combat/Loot/Rest/Boss/Exit themselves.
7. If schema changes are required, update the vNext baseline directly according to current rebuild migration policy and keep persistence generic to runs/nodes/edges or equivalent shared topology state.

#### API/read contract scope

Update authoritative current-run/run-state presentation so the client can render route choices without reconstructing graph rules. At minimum expose enough player-authorized information to identify:
- current/resolved position as needed for presentation;
- the currently legal next node or nodes;
- stable node identity and player-visible node presentation already accepted by the run contract;
- resolved/terminal state.

Do not expose hidden authored encounter data merely to enable route rendering.

Mutation requests must identify the intended reachable node through the accepted run/node resolution boundary. If the existing request shape already does this safely, extend semantics rather than adding redundant route-choice endpoints.

#### Phaser/runtime scope

Only make the minimum RunScene/runtime changes necessary to consume and prove the authoritative branching contract in this package.

- Render multiple available next nodes when the backend supplies them.
- Make only authoritative reachable choices interactive.
- Preserve current linear presentation when exactly one route is available.
- Prevent duplicate input while a choice/resolution mutation is pending.
- Reconcile the finalized server response rather than optimistically advancing the route.
- Reload/re-entry must reproduce the same available choices from backend state.
- Remain within the existing `RunScene`; do not create a new Phaser Scene or Angular gameplay route.

Detailed encounter interaction UX belongs to later Milestone 10 packages.

#### Verification requirements

Add focused automated coverage proving at minimum:
- legacy linear Farm generation/resolution remains valid;
- legacy linear Mountains generation/resolution remains valid;
- an authored branching fixture/production-safe test definition generates at least one point with two legal successors;
- both successors are exposed as legal choices before selection;
- selecting either legal successor completes it while leaving the other available;
- an optional dead end and an accumulating frontier remain resolvable;
- convergence unlocks a successor after any completed incoming route without duplicate unlock;
- a non-reachable generated node cannot be resolved by ID;
- reload/re-query preserves the same choices before selection and the selected path afterward;
- exact-key replay does not advance twice or alter the selected path;
- stale/invalid choice attempts do not mutate run state;
- cross-player/run node IDs cannot be used;
- terminal runs expose no legal next choices;
- Phaser contract/runtime tests cover multiple choices, pending-input blocking, authoritative reconciliation, and re-entry/reload state.

Run the applicable gates from `agent/QUALITY_GATES.md`. Any regressions in accepted combat, rewards, terminal run flow, economy/material acquisition, active-run Warband restrictions, or region generalization are Package 1 defects and should be corrected here.

#### Responsive verification

Because branching changes RunScene interaction, verify deterministic presentation at the accepted Compact landscape, Standard 1600x900, Wide landscape, and portrait rotate-device gate. This is functional/responsive verification, not final visual polish.

#### Completion evidence

Report:
- implementation SHA;
- authored topology representation chosen and why it is the minimal generic extension;
- any baseline/schema changes;
- production-composed or equivalent persisted branching lifecycle evidence;
- linear Farm/Mountains regression evidence;
- reachability/invalid-node/idempotency/reload/cross-player evidence;
- Phaser branching/reconciliation evidence;
- focused and full gate results;
- responsive capture evidence;
- confirmation no later Milestone 10 encounter mechanics or Milestone 11+ work was introduced.

Leave Package 1 **In Progress** for architectural review. Do not promote Package 2 yourself.

#### Package 1 implementation evidence (2026-10-11)

- The existing `fixed_graph_v1` node and directed-edge lists are the minimal authored topology representation. No new content fields or schema were needed. Validation requires a first-node start, an acyclic graph, every node reachable from start, and one reachable terminal Exit. Optional dead ends and convergence are legal. Node list order gives stable identity, not traversal direction. Farm and Mountains authored paths remain unchanged.
- The shared run/node/edge repositories persist the exploration frontier. Completing one available node unlocks only its direct locked successors and preserves all other available nodes. The `skipped` traversal status and exclusive-choice mutation were removed. A completed incoming edge suffices to unlock a convergent child; another parent's later completion does not duplicate it.
- Persisted lifecycle tests cover either branch order, accumulated `[other branch, successor]` frontier, optional dead-end completion while the run stays active, convergence, separate-connection reload, exact-key receipt replay and one revision increment, locked/early and stale/completed IDs, foreign ownership, and terminal rejection. Linear Farm/Mountains regression suites remain part of the full gate.
- `RunScene` consumes `available_node_ids`, blocks duplicate input/navigation during submitting or ambiguous outcomes, reconciles the server frontier, and keeps the other branch selectable after reload. Strict frontend parsing rejects `skipped` and mismatched available lists. Completed battle replay remains available.
- Focused backend: authored run validation 26 tests / 79 assertions; branching lifecycle 3 tests / 57 assertions; Mountains lifecycle 3 tests / 92 assertions. Focused browser run contracts, map model, and RunScene: 40 tests passed. Full `npm run verify:package`: PASS; backend 382 tests / 1,976 assertions; frontend 541 tests; context, docs, content/projection, production build, bundle, and whitespace gates passed. Production content revision: `9b3b0a14d8018319d4f59e16f6b376d70c8430476a671b18c082eb60d5419bd2`.
- Deterministic `run-branching` captures now show the accumulated frontier after one Loot resolves: the other Loot and Rest are both ready. Compact 844x390, Standard 1600x900, Wide 2560x1080, and portrait 390x844 rotate gate passed and were visually reviewed. Files: `artifacts/screenshots/m10-p1-run-branching-{compact,standard,wide,portrait}.png`.
- No later Milestone 10 encounter families or Milestone 11+ work was introduced. Status remains **In Progress** for architectural review.
