# Active Execution Issue

`ISSUES.md` contains only the current execution-ready package. Future packages stay summarized in `agent/MILESTONES.md` and the roadmap until promoted. Do not implement them early.

## Milestone 3 - Enter Farm

### Establish current-run lifecycle, bootstrap summary, and active-run Warband locks

**Status:** Open
**Priority:** High

#### Problem
Packages 1-3 now establish normalized run persistence, canonical/private Farm generation, and an authoritative transactional run-start command. A created run still cannot be queried/resumed or abandoned through vNext, bootstrap still reports no active-run state, and Milestone 2 Warband commands do not yet protect participating configuration from mutation while a run is active.

This package establishes the authoritative server-side active-run lifecycle boundary before any Phaser `RunScene` work begins.

It owns:
- `GET /api/v1/runs/current`;
- `POST /api/v1/runs/:runId/abandon`;
- bootstrap active-run summary hydration;
- backend active-run configuration locks for existing Warband mutations;
- strict frontend bootstrap parsing/storage for a non-null active-run summary only.

It does not implement run navigation, rendering, node resolution, combat, or rewards.

#### Required Context
Read before implementation:
- `documentation/07-development-path/vnext-api-contract-model.md`
- `documentation/07-development-path/vnext-endpoint-inventory.md`
- `documentation/07-development-path/vnext-storage-model.md`
- `documentation/07-development-path/vnext-energy-model.md`
- `documentation/07-development-path/vnext-authored-content-model.md`
- `documentation/07-development-path/vnext-backend-internal-architecture.md`
- `documentation/07-development-path/vnext-phaser-client-architecture.md`
- `documentation/02-systems/run-node-generation.md`
- `documentation/02-systems/warband-and-formation.md`
- `documentation/02-systems/ability-loadouts-and-dice-binding.md`
- `documentation/07-development-path/vnext-prototype-code-disposition.md`
- Package 1 run schema/tests
- Package 2 canonical run content/generator
- Package 3 run-start command/controller/tests
- current `GameBootstrapController` and frontend bootstrap parser/store
- current squad/unit mutation commands and their transaction/locking order

Do not route vNext through prototype run/profile/team controllers or services.

#### Current-run query
Implement:

`GET /api/v1/runs/current`

This is an authenticated read and must not mutate state, materialize Energy, increment revision, regenerate topology, or repair persisted run state.

Contract:
- when the player has no active run, return success with `run: null`;
- when an active run exists, return the authoritative persisted active-run aggregate needed by the later Farm map;
- return only the authenticated player's active run;
- persisted cross-owner/corrupt relationships fail as a narrow non-disclosing integrity error.

Do not return a foreign run or allow a run ID parameter for this query.

#### Current-run public shape
For the current Milestone 3 Farm slice, return a deliberate client-safe shape equivalent to:

```text
run:
  id
  region_id
  squad_id
  status
  created_at
  nodes:
    - id
      node_index
      node_type_id
      status
      completed_at
      position:
        column
        row
  edges:
    - from_node_id
      to_node_id
  units:
    - unit_id
      current_hp
player_revision
```

`current_hp` remains nullable during Milestone 3.

The exact envelope should follow existing vNext conventions.

Do not expose raw `generated_metadata` objects. Explicitly map only the safe Farm position metadata needed by the client.

Do not expose:
- `run_generation_id`;
- generator algorithm;
- authored local node keys;
- private generation definition/topology source;
- prototype encounter-template IDs;
- server-only encounter information not yet observable/required;
- SQL/internal IDs beyond the durable run/node/unit identities actually needed by gameplay.

The player may know the generated Farm map once the run exists. Returning persisted node type, position, status, and connectivity is therefore appropriate for this slice; do not expose the private authored generation source used to create it.

#### Persisted-run validation
Current-run assembly must validate persisted state independently of the fresh graph generator.

Do **not** call `GeneratedRunGraphValidator` against mutable persisted state because its `index 0 available / all later nodes locked` invariant is specifically a fresh-generation invariant.

Validate what the persisted active aggregate actually requires:
- active run belongs to authenticated user;
- `region_id` resolves to the expected authored region type;
- active run has a non-null participating squad;
- participating squad still exists and belongs to the user;
- nodes belong to this run;
- node indexes are unique, contiguous, deterministic, and start at zero;
- every node type resolves to authored `run_node_type` content;
- node status uses the persisted runtime vocabulary currently supported by the schema;
- completion timestamp/status relationship is coherent where applicable;
- Farm position metadata has the expected safe integer `{column,row}` shape;
- edges connect persisted nodes in this same run;
- no self/duplicate edge is returned;
- persisted graph remains connected/reachable enough to represent a coherent run;
- run-unit rows belong to this run;
- each participating unit exists and belongs to the run owner;
- duplicate participating units are impossible/rejected;
- current HP may be null in Milestone 3.

Do not invent future node-resolution state rules beyond what the current schema can honestly represent.

#### Current-run repository/query boundary
Add/adapt narrow vNext repository methods needed to read:
- active run root by user;
- run root by owner/id where needed for abandon;
- run nodes;
- run edges;
- run-unit participation.

Application query/lifecycle code owns:
- authored-content validation;
- ownership/cross-aggregate integrity;
- DTO/public projection;
- lifecycle rules.

Repositories remain persistence-only.

Do not use prototype `RunRepository`, `RunNodeRepository`, or `RunEdgeRepository` if their row contracts still target the old schema.

#### Abandon command
Implement:

`POST /api/v1/runs/:runId/abandon`

Require:
- authenticated session;
- CSRF;
- canonical positive run ID.

No `Idempotency-Key` is required because the command has a naturally idempotent retry contract and performs no randomness/resource refund.

Transaction/locking order:
1. parse route ID;
2. begin transaction;
3. lock `user_state` first;
4. load/lock the target run through an owner-scoped lookup;
5. apply lifecycle rule;
6. persist terminal state when required;
7. increment `player_revision` exactly once for the real transition;
8. commit;
9. return authoritative result.

Every Warband mutation lock introduced by this package must use the same player-first serialization order so abandon and configuration commands cannot race inconsistently.

#### Abandon lifecycle contract
For an owned **active** run:
- set status to `abandoned`;
- set terminal `ended_at` using authoritative UTC time;
- retain run graph and run-unit history;
- do not refund Energy;
- do not modify Energy or its regeneration anchor;
- increment `player_revision` exactly once.

For the same owned run already in `abandoned` state:
- return successful authoritative no-op;
- do not change `ended_at`;
- do not increment revision again.

This makes ordinary retry safe without a separate receipt.

For a missing or foreign run ID:
- return the same non-disclosing not-found behavior.

If future persisted terminal statuses somehow reach this command, do not rewrite them to abandoned. Return a narrow invalid-state/conflict response.

Do not delete run history on abandon.

#### Abandon response
Return affected authoritative state suitable for later client reconciliation, for example:

```text
run:
  id
  region_id
  squad_id
  status: abandoned
  ended_at
active_run: null
player_revision
```

Do not return/refund Energy because abandonment does not mutate Energy.

A repeated already-abandoned no-op returns the existing terminal state and current revision.

#### Bootstrap active-run summary
Upgrade:

`GET /api/v1/game/bootstrap`

Fresh/no active run:
- `active_run: null`.

Active run:
- return only a compact authoritative summary sufficient for startup routing:
  - run ID;
  - region ID;
  - squad ID;
  - status.

Do not put nodes/edges/run-unit state into bootstrap.

Bootstrap remains read-only.

Bootstrap must validate the active-run root/ownership/content relationship enough to fail safely on corrupt state rather than silently reporting a bad run.

Do not regenerate topology during bootstrap.

#### Frontend bootstrap contract
Update the framework-neutral frontend bootstrap parser/state to strictly accept:
- `active_run: null`; or
- the exact compact active-run summary above.

Validate:
- positive canonical IDs;
- stable region ID;
- `status === 'active'` for a bootstrap active run;
- no extra fields.

Retain this summary in GameStore/bootstrap state for Package 5.

Do not implement frontend current-run requests, run-start requests, abandon requests, Camp buttons, or RunScene yet.

#### Active-run lock policy
Introduce one narrow reusable backend policy/service over authoritative persisted active-run state.

The policy answers concrete questions such as:
- does this user currently have an active run?;
- what squad participates?;
- does this unit participate?;

It must not become a generic workflow/event service.

It must validate ownership/corruption safely and must participate in the caller's existing transaction rather than creating its own transaction.

#### Squad activation lock
While a run is active:
- activating a **different** squad is rejected with a narrow configuration-locked conflict;
- re-activating the already-active participating squad remains the existing successful no-op and does not increment revision.

Do not silently switch the run's squad.

#### Participating squad update lock
The active run locks participating **formation membership/positions**, not cosmetic squad naming.

For `PUT /api/v1/squads/:squadId` when that squad is the active run's participating squad:
- if the submitted nine-position formation differs from the persisted committed formation, reject with the configuration-locked conflict and mutate nothing;
- if formation is identical but only the squad name changes, allow the normal rename/update mutation;
- an exactly identical name+formation remains the existing no-op behavior if already supported.

Updates to other saved squads remain legal.

Do not copy squad formation into the run merely to enforce this. Compare against the authoritative persisted squad configuration.

#### Participating squad delete lock
While a run is active:
- deleting the participating squad is rejected regardless of whether it is the only saved squad or another squad exists;
- deleting a non-participating squad remains subject to the existing normal squad rules.

Abandon the run first if the player wants to delete its participating squad.

#### Unit loadout/dice lock
While a run is active:
- `PUT /api/v1/units/:unitId/loadout` is rejected if that unit participates in the active run;
- loadout changes for non-participating units remain legal.

The error is server-authoritative even if the later client disables controls.

Do not snapshot/copy the loadout into the run in this package.

#### Unit rename
`PATCH /api/v1/units/:unitId/name` remains legal during an active run, including for participating units.

A display name is not combat configuration.

Do not over-lock harmless identity/presentation changes.

#### Other Warband commands
Creating a new squad remains legal during a run.

This package does not implement promotion, die sale/salvage, Academy, Shop, or other future commands. Their owning milestones must consult the same active-run configuration rules when they can mutate participating combat state.

#### Lock error contract
Use one narrow non-sensitive error code for active-run configuration protection, such as:

`active_run_configuration_locked`

Use conflict semantics (`409`) unless an established convention strongly requires an equivalent status.

Do not expose foreign run/unit/squad IDs in error details.

#### Concurrency requirements
Be deliberate about races between:
- abandon and squad activation/update/delete;
- abandon and unit loadout replacement;
- run start and Warband mutations.

All these mutations already or should lock `user_state` first. After that lock is held, inspect active-run state and then target configuration rows.

Required behavior:
- if configuration mutation obtains player lock before run start, it may complete before the run is created and run start must validate the resulting committed configuration;
- if run start commits first, subsequent participating configuration mutation observes the active run and is rejected;
- if abandon commits first, subsequent configuration mutation may proceed;
- if configuration mutation sees the still-active run first, it is rejected and does not race abandonment into an inconsistent partial state.

Do not introduce another locking order that creates avoidable deadlock risk.

#### Player revision
Current-run GET/bootstrap reads do not increment revision.

Real abandon transition increments once.

Repeated already-abandoned abandon does not increment.

Blocked active-run configuration commands do not increment.

Allowed real Warband mutations retain their existing exactly-once/no-op revision behavior.

#### Energy
Abandon does not refund or otherwise mutate Energy.

Current-run GET and bootstrap derive/read state only; they do not materialize Energy.

Do not change Package 3 Energy behavior in this package unless a concrete defect is discovered.

#### Tests
Use real MySQL integration coverage for lifecycle/locking behavior.

At minimum prove:
- unauthenticated current-run query rejected;
- no active run returns successful `run: null`;
- current-run query returns the exact persisted five-node/four-edge Farm graph from Package 3 without regenerating it;
- current-run response exposes safe position/node presentation references but not private generation source fields;
- query is read-only and does not change revision/Energy/timestamps;
- invalid authored region/node-type persisted references fail as integrity errors;
- corrupt cross-owner run squad fails safely;
- corrupt cross-owner run-unit relationship fails safely;
- malformed/cross-run graph relationship fails safely where application validation can observe it;
- current-run validation accepts legitimate future mutable status arrangements allowed by schema rather than applying the fresh-generation availability rule;
- abandon requires auth + CSRF;
- missing/foreign abandon ID is non-disclosing not-found;
- active owned run becomes abandoned with terminal timestamp;
- abandon retains nodes/edges/run-unit rows;
- abandon refunds no Energy and does not alter Energy anchor;
- real abandon increments revision once;
- repeated abandon is successful no-op with same terminal timestamp and no second revision;
- bootstrap fresh account still has `active_run: null`;
- bootstrap active run returns only compact summary;
- bootstrap does not return graph topology;
- frontend parser accepts valid active summary and rejects malformed/extra-field variants;
- activating another squad during active run is rejected;
- activating participating/current squad remains no-op;
- participating formation change rejected;
- participating squad name-only change allowed when formation unchanged;
- participating squad delete rejected, including last-squad case;
- non-participating squad update/delete follows existing rules;
- participating unit loadout replacement rejected;
- non-participating unit loadout replacement remains legal;
- participating unit rename remains legal;
- blocked lock commands do not change `player_revision`;
- abandon followed by the previously blocked configuration mutation succeeds;
- Package 3 run-start regression remains green;
- Milestone 2 squad/unit configuration regression remains green.

Where practical, add transaction-order/concurrency characterization proving the shared player-row lock gives deterministic start/abandon/configuration behavior.

#### Documentation
Update existing canonical docs only where this package finalizes current-run/abandon/lock behavior.

Do not create package-report/history/UAT documents.

#### Explicitly Out of Scope
Do not implement:
- Phaser run-start UI;
- frontend run-start mutation;
- frontend current-run query;
- frontend abandon mutation;
- Camp Start/Resume controls;
- `RunScene`;
- Farm map rendering;
- node selection/resolution;
- combat;
- rewards;
- Rest/Loot/Boss/Exit resolution;
- run modifiers;
- current HP calculation;
- battle persistence/playback;
- promotion;
- Shop/Academy/Wrong Machine;
- Milestone 4.

Do not use this package as an excuse to migrate prototype run UI or lifecycle services wholesale.

#### Verification
Run applicable gates from `agent/QUALITY_GATES.md`.

At minimum:
- focused current-run query tests;
- abandon lifecycle/transaction tests;
- active-run lock tests across squad/unit commands;
- bootstrap backend tests;
- frontend bootstrap parser/store tests;
- Package 3 run-start integration regression;
- Milestone 2 Warband regression;
- full Docker backend suite;
- full frontend suite because bootstrap client contract changes;
- production frontend build;
- bundle check;
- docs/context checks when documentation changes.

No Phaser screenshots are required because this package must not alter presentation.

Do not claim a gate passed unless it actually ran.

#### Review State
When complete:
- leave Package 4 **In Progress**;
- do not mark it complete;
- do not promote Package 5;
- do not begin RunScene or Camp run navigation.

Architectural review decides completion.

#### Final Report
Report:
1. commit SHA;
2. exact `GET /runs/current` response shape and no-run behavior;
3. persisted aggregate validation/integrity behavior;
4. abandon transaction and retry contract;
5. bootstrap active-run summary shape;
6. frontend bootstrap parser/store changes;
7. active-run lock policy and affected commands;
8. participating-squad name-only behavior;
9. unit rename/loadout behavior while active;
10. concurrency/locking order;
11. exact `player_revision` behavior;
12. Energy behavior on abandon/read;
13. tests/gates actually run;
14. unresolved concern, if any.

Do not begin another package.
