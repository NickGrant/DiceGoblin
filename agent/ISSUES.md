# Active Execution Issue

## Milestone 10 - Run Encounter Depth

### Milestone 10 Package 2 - Generalized non-combat encounter contract and Rest migration

**Status:** In Progress
**Priority:** High

#### Accepted baseline

Milestones 1-9 are complete. Milestone 10 Package 1 is approved at `86c88987aaf14391660cb3b868cd10e537dd19d8` after the exploration-frontier correction.

Package 1 established the authoritative persisted exploration graph/frontier: resolving one available node completes it, unlocks eligible direct successors, and preserves every other available node. Optional dead ends and convergence are valid. The existing Farm and Mountains production definitions remain linear and valid.

The existing vNext runtime already resolves Combat, Loot, Rest, Boss, and Exit nodes through the shared run-node command/handler boundary. Rest currently has accepted player-facing behavior, but Milestone 10 needs a reusable authored non-combat encounter contract before Hazard, Shrine, and Chaos families are added.

#### Purpose

Establish the smallest generic authored/runtime contract needed for interactive non-combat encounters, then migrate the existing Rest node behavior through that contract without changing its accepted gameplay outcome.

This package is architectural generalization, not new encounter-family content. It should prove that a non-combat node can obtain its player-visible interaction from authored encounter data, resolve authoritatively through the existing run lifecycle, persist/reconcile safely, and coexist with the Package 1 exploration frontier.

Do not add Hazard, Shrine, Chaos, run modifiers, new consumable mechanics, multi-step persistence, Swamp/Frog Kin, knowledge/objectives, onboarding, or final visual redesign in this package.

#### Canonical design constraints

- Extend the existing run/node/encounter/content architecture; do not create a parallel event engine or Rest-specific second path.
- Static encounter definitions and player-visible encounter copy/options belong in validated authored JSON. Do not move encounter catalogs into SQL or client constants.
- The backend remains authoritative for eligibility, effects, rewards/state mutation, node completion, successor unlocking, the retained exploration frontier, and idempotent receipts.
- The client may present only server-authorized encounter information/actions. It must not derive effect outcomes or hidden eligibility from authored data shipped wholesale to the browser.
- Preserve the existing generic run-node resolution transaction/idempotency boundary where semantically valid.
- Preserve Package 1 semantics: resolving Rest must not consume unrelated available nodes; direct successors unlock normally; dead-end/convergent topology remains generic.
- Preserve Farm and Mountains as generic authored regions. Do not add region-specific Rest branches in controllers/application/runtime.
- Keep this contract no more expressive than needed to support accepted Rest now and the immediate Hazard/Shrine/Chaos packages next. Avoid speculative scripting languages or arbitrary client-authored effect graphs.

#### Backend/content scope

1. Inventory the current Rest implementation and existing authored `encounters` content before choosing the generalized shape. Reuse accepted content fields/boundaries where possible rather than creating duplicate concepts.
2. Define and validate the minimal canonical non-combat encounter representation needed for:
   - stable encounter identity;
   - player-visible title/body/prompt as actually needed by the current UI;
   - one or more stable player action/choice identifiers if interaction requires them;
   - server-owned effect/resolution semantics;
   - safe association from a generated run node/encounter reference.
3. Migrate existing Farm/Mountains Rest definitions/behavior through the generalized contract without changing accepted Rest balance/effect semantics unless a current canonical document explicitly requires a correction.
4. Resolve the selected non-combat action authoritatively. Validate that the run/node belongs to the player, the node is currently available, the encounter/action is valid for that node, and the request cannot choose hidden/unauthorized effects.
5. Apply Rest effects, node completion, successor unlocking, frontier retention, `player_revision`, and finalized idempotency receipt atomically using existing shared boundaries.
6. Exact-key replay must return the finalized result without reapplying healing/effects, completing twice, or changing the exploration frontier.
7. Reload/re-query after resolution must reproduce authoritative unit HP/run state and frontier state.
8. Do not introduce durable per-encounter progress/state in this package unless Rest genuinely requires it. Multi-step encounter persistence belongs to Package 7.
9. Keep authored encounter data server-private by default. If new player-visible fields must reach Phaser, expose a narrow authorized runtime projection/read contract rather than publishing the canonical encounter catalog wholesale.

#### API/read contract scope

Use the existing run/current-run and run-node resolution boundaries where they remain semantically clean. Do not add a redundant endpoint solely because Rest has moved behind the generalized encounter contract.

The client must receive enough authorized information to render the current non-combat interaction and submit a stable action identifier where needed. The response must contain enough finalized authoritative state for the existing GameRuntime/GameStore reconciliation pattern.

Strictly validate request/response contracts. Reject unknown encounter/action identifiers and mismatched node/encounter combinations without mutation.

#### Phaser/runtime scope

Make only the minimum RunScene/runtime changes necessary to consume the generalized non-combat encounter contract and prove migrated Rest.

- Rest remains in the existing `RunScene`; do not add a Phaser Scene or Angular gameplay route.
- Render player-visible Rest interaction from the authorized server contract rather than Rest-specific hard-coded effect logic.
- Prevent duplicate input while mutation is pending.
- Reconcile the finalized server response; do not optimistically heal or complete the node.
- Preserve Package 1 accumulated-frontier behavior when Rest is one of several available nodes.
- Reload/re-entry must reproduce the authoritative unresolved/resolved state.
- Preserve current responsive/rotate behavior. Final visual polish remains deferred.

#### Verification requirements

Add focused automated coverage proving at minimum:
- authored encounter validation accepts the migrated Rest definitions and rejects malformed/unknown references/actions/effect semantics;
- Farm Rest preserves its accepted healing/effect behavior through the generalized contract;
- Mountains Rest preserves its accepted healing/effect behavior through the same generic path;
- no region-specific application/controller/runtime branch is required for Farm versus Mountains;
- resolving Rest completes only that node, unlocks eligible direct successors, and preserves unrelated available frontier nodes;
- Rest on a legal dead-end fixture completes without terminalizing the run or consuming the remaining frontier;
- invalid/locked/completed/foreign-run Rest attempts do not mutate state;
- invalid or mismatched encounter/action identifiers do not mutate state;
- exact-key replay does not heal/apply effects twice, increment revision twice, or alter frontier twice;
- reload/re-query preserves finalized run HP/unit HP and frontier state;
- terminal run behavior remains protected;
- existing Combat/Loot/Boss/Exit resolution regressions remain green;
- frontend contracts reject malformed/unauthorized encounter payloads;
- RunScene tests cover Rest presentation, pending-input blocking, authoritative reconciliation, accumulated-frontier retention, and reload/re-entry.

Run the applicable gates from `agent/QUALITY_GATES.md`. Any regression in Farm/Mountains completion, combat/rewards, Package 1 branching/frontier behavior, economy/material acquisition, active-run Warband restrictions, or content secrecy is a Package 2 defect.

#### Responsive verification

Because the generalized encounter interaction is player-facing in RunScene, verify deterministic presentation at:
- Compact landscape 844x390;
- Standard 1600x900;
- Wide 2560x1080;
- portrait 390x844 rotate-device gate.

This is functional/responsive verification, not final visual polish.

#### Completion evidence

Report:
- implementation SHA;
- generalized non-combat encounter representation and why it is the minimal reusable contract;
- authored Rest migration details for Farm and Mountains;
- any API/runtime projection changes and secrecy rationale;
- persisted/idempotent Rest lifecycle evidence;
- Package 1 frontier-retention/dead-end evidence through Rest;
- invalid/mismatched/foreign/stale request evidence;
- Farm/Mountains and Combat/Loot/Boss/Exit regression evidence;
- Phaser Rest/reconciliation evidence;
- focused and full quality-gate results;
- responsive capture evidence;
- confirmation no Hazard/Shrine/Chaos/modifier/multi-step/Milestone 11+ scope was introduced.

Leave Package 2 **In Progress** for architectural review. Do not promote Package 3 yourself.
