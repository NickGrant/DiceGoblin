# Active Execution Issue

## Milestone 10 - Run Encounter Depth

### Milestone 10 Package 4 - Shrine encounter foundation

**Status:** In Progress
**Priority:** High

#### Accepted baseline

Milestones 1-9 are complete. Milestone 10 Package 1 is approved at `86c88987aaf14391660cb3b868cd10e537dd19d8`. Package 2 is approved through `f782b368e3bc1e4672bcec886bf3a29263e5baf8`. Package 3 is approved through correction `31b53b106d84edf2db846ec971877080e08aac9f`.

The run model is an authoritative persisted exploration frontier. Package 2 established the generalized authored `non_combat` interaction projection and action-resolution boundary. Package 3 proved a second encounter family, Hazard, through that boundary without adding family-specific query/controller/client dispatch.

Regional vocabulary is intentional: Farm remains Combat/Loot/Rest/Boss/Exit; Mountains adds Hazard and exploration branching/dead ends; Shrine and Chaos are reserved for Swamp and later regions. Swamp production content itself remains Milestone 13.

#### Purpose

Establish the smallest reusable Shrine encounter-family contract needed by later Swamp content without adding Shrine nodes to Farm or Mountains and without prematurely implementing Swamp.

A Shrine is a beneficial or tradeoff-oriented non-combat encounter that presents one or more explicit authored choices and resolves an immediate authoritative effect. Package 4 should prove that the existing generic encounter architecture can represent this family without another parallel engine or family-specific read/client path.

Because no current production region should contain Shrines, this package should use validated test-only authored fixtures to prove the family. Do not add speculative production Shrine content merely to manufacture production coverage.

#### Canonical design constraints

- Reuse the Package 2 generalized authored non-combat projection and action-resolution boundary.
- Do not add Shrine-specific branching to `CurrentRunQuery`, controllers, API endpoints, or Phaser runtime interaction dispatch.
- Add only the authored node-family metadata and server resolution semantics genuinely needed for Shrine choices.
- Player-visible title/body/prompt/action labels may be projected when authorized. Effects, tuning, and hidden eligibility remain server-private.
- Backend authority owns action validity, effects/rewards, node completion, frontier changes, revision, and finalized receipt.
- Exact-key replay must return the finalized result without reapplying effects or changing the frontier twice.
- Preserve Farm and Mountains production content exactly; neither region receives Shrine nodes or Shrine encounters.
- Do not implement Swamp production topology/content, Frog Kin, Chaos, run-scoped modifiers, contextual consumables, or multi-step encounter persistence.
- Prefer immediate one-transaction Shrine effects that reuse accepted state mutation primitives. If a desired Shrine requires Package 6 modifiers or Package 7 multi-step/consumable infrastructure, defer that shape.

#### Backend/content scope

1. Add the minimal canonical Shrine run-node/encounter-family association needed to flow through the existing generic non-combat contract.
2. Define the smallest immediate Shrine effect vocabulary needed to prove meaningful player choice. Prefer reuse of existing primitives where semantically correct rather than duplicating Rest or Hazard implementations.
3. Use validated test-only authored fixtures to exercise Shrine interactions. Do not place Shrine definitions in Farm or Mountains production region content.
4. Resolve Shrine actions through the existing action-aware run-node command boundary. Validate ownership, node availability, persisted encounter association, encounter/action identity, region compatibility, family compatibility, and server-owned effect semantics.
5. Apply the immediate Shrine consequence, node completion, direct-successor unlocking, retained frontier, `player_revision`, and receipt atomically.
6. Preserve reload/re-query and exact-key replay semantics.
7. Keep browser projection narrow; do not expose effect types/amounts or canonical encounter catalogs.
8. Do not introduce durable per-encounter progress in this package.

#### Gameplay proof

The test-only Shrine fixture must demonstrate a genuine authored choice rather than a renamed Rest node. At minimum, provide two stable actions with distinguishable immediate outcomes that can be resolved through the same encounter definition. The choices should be simple enough to complete in one transaction and must not require run-scoped modifiers, consumables, or later-package state.

The implementation should not hard-code those action IDs into shared command/query/client infrastructure. The persisted authored encounter and selected authorized action remain the authority.

#### Phaser/runtime scope

Use the existing generic non-combat interaction UI and action submission path. No new Scene or Angular route is allowed.

Only make frontend changes if the existing generic contract cannot correctly render multiple authorized actions or reconcile the finalized Shrine result. Any such change must remain encounter-family agnostic.

Do not add production Shrine navigation or production-region captures. Test-only runtime/component coverage is sufficient where no production region can legitimately expose Shrine yet.

#### Verification requirements

Prove at minimum:
- Shrine authored definitions/family metadata validate and malformed family/action/effect/reference combinations fail validation;
- Farm contains no Shrine node/encounter and remains its fixed five-node graph;
- Mountains contains no Shrine node/encounter and retains its accepted Hazard topology;
- an available test-only Shrine reaches the authorized client projection through the generic Package 2 path with multiple actions;
- `CurrentRunQuery` has no `run_node_type.shrine` branch;
- both Shrine choices resolve successfully through the shared action-aware command boundary and produce their distinct authoritative outcomes;
- only the selected action's effect is applied;
- node completion/direct-successor unlocking/frontier retention are correct;
- invalid/mismatched/locked/completed/foreign/terminal requests do not mutate state;
- exact-key replay does not reapply the selected effect, increment revision twice, or alter frontier twice;
- reload/re-query reproduces finalized state;
- server-private effect/tuning data is absent from browser-facing projections;
- Rest and Hazard regressions remain green, including Hazard random-target receipt stability;
- Combat/Loot/Boss/Exit, Farm completion, Mountains completion, economy/material acquisition, and active-run Warband restrictions remain green;
- frontend generic interaction tests prove multiple Shrine actions can render/submit without family-specific client logic.

Run applicable gates from `agent/QUALITY_GATES.md`.

#### Scope exclusions

Do not add:
- production Shrine content to Farm or Mountains;
- Swamp production content/topology or Frog Kin;
- Chaos encounters;
- run-scoped modifiers;
- contextual consumable mechanics;
- multi-step encounter persistence;
- Milestone 11+ functionality;
- final visual redesign.

#### Completion evidence

Report:
- implementation SHA;
- Shrine authored family/fixture representation;
- immediate Shrine effect semantics and why they do not require later-package architecture;
- proof the generic read/client interaction path remains family agnostic;
- both-choice authoritative lifecycle evidence;
- invalid/idempotent/reload/frontier evidence;
- secrecy evidence;
- Farm/Mountains no-Shrine regression evidence;
- Rest/Hazard and broader run regressions;
- focused/full quality-gate results;
- confirmation no production Shrine, Swamp, Chaos, modifier, consumable, multi-step, or Milestone 11+ scope was introduced.

Leave Package 4 **In Progress** for architectural review. Do not promote Package 5 yourself.
