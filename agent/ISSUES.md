# Active Execution Issue

`ISSUES.md` contains only the current execution-ready package. Future packages stay summarized in `agent/MILESTONES.md` and the roadmap until promoted. Do not implement them early.

## Milestone 3 - Enter Farm

### Establish active-run persistence foundation

**Status:** Open
**Priority:** High

#### Problem
Milestone 2 is complete and passed manual user UAT. The vNext baseline now has authoritative player/Warband state but no run persistence. Milestone 3 needs a fresh, normalized persistence foundation capable of owning one resumable generated Farm run without importing the prototype run schema/catalog model or beginning generation/API/Phaser work early.

This package establishes only the durable relational boundary required by later Milestone 3 packages.

#### Required Context
Read before implementation:
- `documentation/07-development-path/vnext-storage-model.md`
- `documentation/07-development-path/vnext-endpoint-inventory.md`
- `documentation/07-development-path/vnext-energy-model.md`
- `documentation/07-development-path/vnext-authored-content-model.md`
- `documentation/07-development-path/vnext-backend-internal-architecture.md`
- `documentation/02-systems/run-node-generation.md`
- `documentation/02-systems/warband-and-formation.md`
- `documentation/07-development-path/vnext-prototype-code-disposition.md`
- current `backend/migrations/vnext_baseline.sql`
- current fresh-database/integration tests

Inspect retained prototype run schema/repositories/generator only as evidence for runtime facts that a resumable graph genuinely requires. Do not port its SQL-authored catalogs or service boundaries.

#### Architectural Boundary
Extend the single fresh vNext baseline. Do not create a prototype migration chain or migrate existing runtime/player data.

Add the concrete run-owned persistence required now:
- `runs`
- `run_nodes`
- `run_edges`
- `run_unit_state`

Do **not** add tables merely because they appear in the long-term conceptual inventory when Milestone 3 does not yet use them. In particular defer:
- `run_modifiers` until temporary run modifiers are implemented;
- `battles` and `battle_playback` until Milestone 4;
- `resolved_events` until reward-bearing resolution requires it.

`idempotency_requests` already exists and is reused later by run creation; do not create a second run-idempotency table in this package.

#### `runs`
Persist the authoritative run root with enough identity/lifecycle information to support later create/current/abandon commands.

At minimum the model must represent:
- run ID;
- owning user ID;
- authored `region_id` stable string ID;
- participating/saved `squad_id` reference;
- lifecycle `status`;
- created/start timestamp;
- terminal timestamp when applicable.

Ordinary operational fields are acceptable when they have a concrete resumability/debugging purpose, for example a generation seed/version or authored-content revision that later generation/start packages will genuinely consume.

Do not add authored region catalog FKs/tables. Region IDs remain JSON-authored stable strings.

Design for **at most one active run per user**. Prefer a real database invariant when it can be expressed cleanly in MySQL 8 without preventing multiple historical terminal runs. Do not use `UNIQUE(user_id, status)`, because that would incorrectly permit only one historical run for each terminal status.

The application layer will still validate active-run eligibility later even when a DB guard exists.

#### Squad relationship
A run identifies the squad participating in it.

The eventual application layer will forbid editing/deleting participating configuration while the run is active.

Choose FK/delete behavior deliberately so historical/terminal run retention does not permanently make a saved squad undeletable, while an accidental delete does not become the intended way to terminate an active run.

Do not duplicate the entire squad configuration into the run root solely for lock enforcement.

#### `run_nodes`
Persist generated node instances as run-owned runtime state.

The table must support later representation of:
- stable run-local node identity/order/index;
- authored node/type/encounter references as stable strings where applicable;
- runtime node status/availability/completion state;
- nullable completion timestamp;
- generated placement/render/path metadata needed to reproduce the same map after reload.

A JSON column for genuinely generated per-node metadata is acceptable when the alternative would be speculative columns for every future node mechanic.

Do not use JSON as an arbitrary replacement for the node's core relational identity/lifecycle fields.

Do not add SQL-authored node/encounter/template catalogs.

#### `run_edges`
Persist generated graph connectivity.

Each edge must belong to one run and identify its from/to run-node endpoints.

Enforce that the persisted edge cannot point at arbitrary nonexistent nodes.

Prevent duplicate logical edges within one run where appropriate.

Generated rendering/path metadata is acceptable only when needed to reproduce generated map geometry; it must not become an authored path catalog.

#### `run_unit_state`
Persist participation/run-scoped unit state separately from permanent `unit_instances`.

At this stage the table must at least establish:
- run ID;
- participating unit ID;
- one row per participating unit.

Do not invent final combat-stat or progression formulas in this package.

The accepted unit-stat contract says exact resolved combat-stat formulas are deliberately reconciled when progression/combat owns them. Therefore it is acceptable for `current_hp` to be nullable/deferred during Milestone 3 persistence if no canonical resolver currently exists. Do not fake max/current HP from incomplete math merely to make the column non-null.

Milestone 4 may tighten/initialize combat HP once the authoritative stat resolver is deliberately established.

Do not persist Attack/Defense/Precision/Resolve snapshots just to anticipate combat.

#### Authored references
MySQL must not recreate authored catalogs or add FKs to content that lives in JSON.

Stable string IDs may be persisted for authored concepts such as:
- region;
- node definition/type;
- encounter reference where the generated graph needs one.

Semantic authored-reference validation belongs to the later generation/start/query application layers and ContentRegistry.

#### Ownership and integrity
Use normal relational integrity for actual persisted entities:
- user;
- squad;
- run;
- unit;
- run-node endpoints.

Cross-owner relationships that cannot be elegantly encoded without denormalizing ownership remain application-level validation, consistent with the Warband architecture.

Do not denormalize `user_id` into every child table just to construct composite ownership FKs unless a concrete integrity need justifies it.

#### Lifecycle/status
Keep status representation simple and server-owned.

The schema must support at least an active run and terminal abandonment. Do not pre-design every Milestone 4/5 completion/failure/reward state as separate tables or transitions.

Plain bounded strings plus application validation are acceptable and consistent with current vNext lifecycle fields.

#### Indexes
Add indexes supporting concrete upcoming operations such as:
- find active run by user;
- load all nodes for a run in stable order;
- load run edges;
- determine whether a unit participates in an active run;
- find run membership by squad as required for configuration locking.

Avoid speculative analytics indexes.

#### Fresh registration
Normal registration remains unchanged.

A new account must create:
- no run;
- no run nodes;
- no run edges;
- no run-unit rows.

Do not add starter/onboarding behavior.

#### Tests
Add focused real-MySQL baseline/integration coverage proving at minimum:
- fresh baseline provisions the new run tables successfully;
- expected Milestone 3 table inventory exists and no SQL gameplay catalogs are introduced;
- multiple users may each own an active run;
- one user cannot persist two simultaneous active runs when using the intended DB invariant;
- the same user may retain multiple terminal historical runs if terminal rows are retained;
- run user FK behavior;
- squad relationship/delete behavior matches the chosen contract;
- node ownership and run-local identity constraints;
- edge endpoints and duplicate-edge constraints;
- deleting/cleaning a run removes its nodes/edges/run-unit rows appropriately;
- one run has at most one row for a participating unit;
- unit FK behavior is deliberate;
- nullable/deferred `current_hp` behavior if used;
- representative authored stable string IDs persist without SQL catalog FKs;
- ordinary registration still produces zero run-owned records;
- existing Milestone 1/2 baseline/Warband tests remain green.

Test actual MySQL 8 behavior, especially any generated-column/functional uniqueness technique used to enforce one active run.

Do not rely on SQLite assumptions.

#### Prototype boundary
Do not modify/adapt the retained `RunGraphGenerator`, prototype run repositories, run-pattern catalogs, Angular run pages, or run API controllers in this package except for a genuinely necessary test/build correction caused by the baseline extension.

Package 2 owns generator/content adaptation.

Do not delete prototype run evidence yet; its useful behavior has not been mined into vNext.

#### Explicitly Out of Scope
Do not implement:
- Farm authored generation content;
- generation algorithm adaptation;
- run-start API/command;
- Energy spending/regeneration mutation;
- run creation idempotency behavior;
- `GET /runs/current`;
- abandon command;
- bootstrap active-run hydration;
- active-run Warband configuration locking;
- RunScene;
- run map;
- node resolution;
- combat;
- rewards;
- Rest/Chaos interactions;
- run modifiers;
- battles/playback;
- Milestone 4.

#### Verification
Run applicable gates from `agent/QUALITY_GATES.md`.

At minimum run:
- fresh DB reset/provision against MySQL;
- focused run-persistence integration tests;
- existing Warband/baseline integration regressions;
- Docker backend suite;
- docs/context checks if architecture docs change.

Frontend/build/capture work is not required because this package should not alter the client.

Do not claim a command passed unless it actually ran.

#### Review State
When complete:
- set this issue to `In Progress` if needed;
- leave it `In Progress`;
- do not mark it complete;
- do not promote Package 2;
- do not begin generator/content adaptation.

Architectural review decides completion.

#### Final Report
Report:
1. resulting commit SHA;
2. exact tables/columns/indexes/constraints added;
3. one-active-run DB invariant and why it does not block historical terminal runs;
4. squad FK/delete behavior;
5. node/edge integrity model;
6. run-unit-state shape and current-HP decision;
7. authored-ID persistence behavior;
8. fresh-registration impact;
9. focused/MySQL/Docker verification actually run and results;
10. any unresolved persistence concern.

Do not begin another package.
