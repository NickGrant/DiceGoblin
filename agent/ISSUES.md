# Active Execution Issue

## Milestone 10 - Run Encounter Depth

### Milestone 10 Package 3 - Hazard encounter foundation

**Status:** In Progress
**Priority:** High

#### Accepted baseline

Milestones 1-9 are complete. Milestone 10 Package 1 is approved at `86c88987aaf14391660cb3b868cd10e537dd19d8` after the exploration-frontier correction. Package 2 is approved through correction `f782b368e3bc1e4672bcec886bf3a29263e5baf8`.

Package 1 established the persisted exploration frontier: resolving one available node completes it, unlocks eligible successors, and preserves unrelated available nodes. Optional dead ends and convergence are valid.

Package 2 established the generalized authored non-combat interaction boundary. Available nodes with a valid persisted authored `non_combat` encounter can receive a narrow authorized interaction projection without `CurrentRunQuery` knowing the concrete node family. Effects remain server-private; action resolution stays inside the existing authoritative transaction/idempotency boundary.

Regional progression is intentional:
- Farm remains the fixed linear tutorial graph: Combat -> Loot -> Rest -> Boss -> Exit.
- Mountains is the first moderate expansion: Combat, Loot, Rest, Hazard, Boss, Exit. It is the first production region for branching/dead ends and environmental Hazards.
- Shrine and Chaos encounters are not Farm or Mountains content; Swamp is the first planned production region to add them.

#### Purpose

Add the first production Hazard encounter family through the Package 2 generalized non-combat contract, using Mountains as the proving ground while leaving Farm unchanged.

This package should establish the smallest authored/server/runtime Hazard semantics needed for immediate Mountains gameplay. Hazards are environmental risks or obstacles that offer one or more explicit player actions and resolve authoritatively. They may produce immediate run/unit/resource consequences and may complete as optional dead ends or as nodes on a continuing path, but this package must not introduce the later generic run-modifier or multi-step persistence systems prematurely.

Do not add Shrines, Chaos encounters, Swamp, run-scoped modifier infrastructure, new contextual consumable infrastructure, generic multi-step encounter persistence, knowledge/objectives, onboarding, or final visual redesign.

#### Canonical design constraints

- Reuse the Package 2 authored `non_combat` interaction projection and action-resolution boundary. Do not add Hazard-specific branching to `CurrentRunQuery`, controllers, or Phaser runtime dispatch merely to identify/render an authored interaction.
- Hazard authored definitions belong in canonical content JSON. Player-visible title/body/prompt/action labels may be projected only when authorized; effect semantics and hidden tuning remain server-private.
- The backend remains authoritative for action validity, random outcomes where applicable, damage/resource mutation, node completion, frontier changes, rewards, revision, and receipts.
- Hazard resolution must preserve Package 1 exploration-frontier semantics. Resolving a Hazard does not consume unrelated available nodes.
- Farm remains exactly its accepted fixed graph and encounter vocabulary. Do not add Hazards to Farm.
- Mountains is the production proving ground for Hazards. Region-specific authored Hazard content is expected; region-specific application/controller/runtime branches are not.
- Keep Package 3 effects no more expressive than needed for accepted Mountains Hazards. Do not build an arbitrary scripting language.
- If a Hazard needs an effect that conceptually belongs to Package 6 run-scoped modifiers or Package 7 contextual consumables/multi-step state, either choose a simpler Package 3 Hazard or explicitly defer that Hazard rather than smuggling later architecture into this package.

#### Backend/content scope

1. Add the canonical Hazard run-node/content association needed to flow through the generalized non-combat contract without changing the read boundary to know `run_node_type.hazard` explicitly.
2. Author a small production Mountains Hazard pool sufficient to prove the family. Prefer simple immediate effects that exercise useful shared primitives without requiring later-package systems. Examples of acceptable shapes include immediate participating-unit damage, a risk/reward choice resolved in one transaction, or a safe/no-reward versus risky/reward choice. Exact content/tuning should follow current canonical design docs and remain modest for Mountains.
3. Ensure Mountains generation/topology can place Hazard nodes while preserving a valid completion path and the accepted exploration-frontier model. Farm generation/content must remain unchanged.
4. Resolve Hazard actions through the shared action-aware run-node command boundary. Validate ownership, availability, persisted encounter association, encounter/action identity, region, and server-owned effect semantics.
5. Apply immediate Hazard consequences, rewards if any, node completion, successor unlocking, frontier retention, `player_revision`, and idempotent receipt atomically.
6. Exact-key replay must return the finalized result without rerolling, reapplying damage/rewards, completing twice, or changing the frontier twice.
7. Reload/re-query must reproduce authoritative post-Hazard run/unit/resource/frontier state.
8. Random Hazard outcomes, if used, must be server-owned and finalized once inside the authoritative/idempotent transaction. Replay must never reroll.
9. Do not add durable multi-step encounter progress in this package.

#### API/read contract scope

Use the Package 2 current-run interaction projection and existing run-node resolution endpoint. Do not add a Hazard-specific endpoint unless an existing canonical contract makes the shared boundary impossible; if that occurs, stop and document the architectural conflict rather than inventing a parallel path.

The client receives only authorized visible interaction/action data before resolution and finalized authoritative outcome/state after resolution. Hidden effect configuration, odds, reward tables, and server-only authored data remain private unless a specific player-visible value is intentionally part of the encounter copy.

#### Phaser/runtime scope

Make the minimum RunScene changes required to present and resolve Mountains Hazards through the generalized non-combat interaction UI.

- Reuse the existing interaction presentation/action submission path where possible.
- Do not create a new Phaser Scene or Angular route.
- Do not hard-code Hazard effects/outcomes client-side.
- Block duplicate input while a mutation is pending.
- Reconcile finalized server state; do not optimistically apply damage/rewards or complete the node.
- Preserve accumulated-frontier behavior when Hazard is one of several available nodes.
- Reload/re-entry must reproduce unresolved interaction or finalized state from backend authority.
- Final visual polish remains deferred; clear functional differentiation from Rest is sufficient.

#### Verification requirements

Add focused automated coverage proving at minimum:
- authored Hazard definitions validate and malformed/unknown action/effect/reference combinations fail validation;
- Farm authored graph/content remains exactly free of Hazard nodes;
- Mountains production generation can produce/contain the accepted Hazard content while retaining a valid path to Boss/Exit;
- Hazard interaction reaches the client through the generic Package 2 projection without adding a `run_node_type.hazard` branch to `CurrentRunQuery`;
- at least one production-composed Mountains Hazard resolves successfully through the shared action boundary;
- immediate Hazard consequences persist correctly;
- a Hazard on an optional dead-end fixture completes without terminalizing the run or consuming unrelated frontier nodes;
- a Hazard on a continuing path unlocks eligible successors normally;
- invalid/locked/completed/foreign-run Hazard attempts do not mutate state;
- mismatched encounter/action IDs do not mutate state;
- exact-key replay does not reroll/reapply effects, increment revision twice, or alter frontier twice;
- reload/re-query preserves finalized Hazard consequences and frontier state;
- terminal-run protections remain intact;
- Farm/Mountains Rest and Combat/Loot/Boss/Exit regressions remain green;
- frontend contracts and RunScene tests cover Hazard presentation, action submission, pending blocking, authoritative reconciliation, frontier retention, and re-entry;
- server-private Hazard effect/tuning data is absent from browser-facing projections.

Run the applicable gates from `agent/QUALITY_GATES.md`. Any regression in Farm completion, Mountains completion, Rest, combat/rewards, exploration-frontier behavior, economy/material acquisition, active-run Warband restrictions, or content secrecy is a Package 3 defect.

#### Responsive verification

Because Hazard interaction is player-facing in RunScene, verify deterministic presentation at:
- Compact landscape 844x390;
- Standard 1600x900;
- Wide 2560x1080;
- portrait 390x844 rotate-device gate.

This is functional/responsive verification, not final visual polish.

#### Completion evidence

Report:
- implementation SHA;
- authored Mountains Hazard definitions and effect semantics;
- how Mountains topology/generation now includes Hazards while Farm remains unchanged;
- confirmation `CurrentRunQuery` and shared client interaction projection remain node-family agnostic;
- persisted/idempotent Hazard lifecycle evidence;
- optional-dead-end and continuing-path frontier evidence;
- invalid/mismatched/foreign/stale request evidence;
- secrecy evidence;
- Farm/Mountains and Rest/Combat/Loot/Boss/Exit regression evidence;
- Phaser Hazard/reconciliation evidence;
- focused and full quality-gate results;
- responsive capture evidence;
- confirmation no Shrine/Chaos/modifier/multi-step/Swamp/Milestone 11+ scope was introduced.

Leave Package 3 **In Progress** for architectural review. Do not promote Package 4 yourself.
