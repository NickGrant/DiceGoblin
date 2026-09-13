# Active Execution Issue

`ISSUES.md` contains only the current execution-ready package. Future packages stay summarized in `agent/MILESTONES.md` and the roadmap until promoted. Do not implement them early.

## Milestone 3 - Enter Farm

### Establish authoritative run start, Energy spend, and idempotency

**Status:** In Progress
**Priority:** High

#### Problem
Packages 1 and 2 established the normalized run persistence boundary plus canonical private Farm generation content and a pure deterministic `FixedGraphRunGenerator`. No authoritative player command yet turns those pieces into a real run.

This package implements the first vNext run-creation mutation. Starting a run must be one idempotent transaction that validates current authoritative player/Warband state, materializes and spends Energy correctly, generates and persists the exact Farm graph, records participating units, increments `player_revision` once, and returns the finalized result.

This package is backend/API only. Current-run reads, abandonment, bootstrap active-run hydration, Warband active-run locks, `RunScene`, and the Phaser map belong to later packages.

#### Required Context
Read before implementation:
- `documentation/07-development-path/vnext-energy-model.md`
- `documentation/07-development-path/vnext-api-contract-model.md`
- `documentation/07-development-path/vnext-endpoint-inventory.md`
- `documentation/07-development-path/vnext-backend-internal-architecture.md`
- `documentation/07-development-path/vnext-storage-model.md`
- `documentation/07-development-path/vnext-authored-content-model.md`
- `documentation/02-systems/run-node-generation.md`
- `documentation/02-systems/warband-and-formation.md`
- `documentation/02-systems/ability-loadouts-and-dice-binding.md`
- `documentation/07-development-path/vnext-prototype-code-disposition.md`
- Package 1 run persistence/tests
- Package 2 canonical Farm content, `FixedGraphRunGenerator`, and graph tests
- current `EnergyCalculator` / `EnergyView`
- current `PlayerStateRepository`
- current Squad/Unit/Dice repositories and Warband integrity helpers
- current `IdempotencyRequestRepository`, `IdempotencyKey`, and create-squad idempotency pattern
- current controllers/router/CSRF/service composition

Prototype run lifecycle/start services are evidence only. Do not route vNext through prototype `/profile`, region repositories, SQL encounter catalogs, or prototype `RunGraphGenerator`.

#### HTTP Contract
Implement:

`POST /api/v1/runs`

This is an authenticated player command.

Require:
- authenticated session;
- normal CSRF protection;
- `Idempotency-Key` header using the established bounded opaque-key contract.

Request body is exactly:

```json
{
  "region_id": "region.the_farm"
}
```

Reject missing/extra fields and malformed stable IDs.

The client does **not** submit:
- squad ID;
- unit IDs;
- loadouts;
- dice bindings;
- Energy amount/cost;
- generated graph;
- seed/topology.

All of those are server authority.

#### Region Eligibility
Milestone 3 currently supports only the authored starting Farm region.

Resolve the requested region through `ContentRegistry` and require it to be the current `config.gameplay.starting_region_id` / `region.the_farm` slice.

Do not invent generic region unlock persistence before the progression/unlock milestone owns it.

Unknown, wrong-type, or currently unsupported regions must fail before mutation.

Do not use prototype `RegionRepository` or SQL region catalogs.

#### Canonical Run Energy Cost
Add an authored gameplay balance value to canonical `config.gameplay`:

```text
run_energy_cost = 10
```

This is the initial ordinary-run cost for vNext.

With the already accepted baseline:
- starting Energy: 50;
- normal maximum: 50;
- regeneration: 12/hour;

this currently means five ordinary runs from a full bar and 50 minutes of natural regeneration per run. This is balance tuning, not architecture, and must remain easy to change in canonical JSON.

Requirements:
- structurally validate the value as a positive integer;
- expose it through a typed `ContentRegistry` accessor used by the command;
- include it in the deterministic global content revision;
- do not hard-code `10` inside the run-start application command;
- do not expose it in `game-content.json` in this package merely because it exists. Package 5 may deliberately surface cost to Camp UI through the appropriate authoritative/public contract.

Update existing Energy/run documentation only if needed to identify canonical JSON as the tuning authority; do not create a new balance document.

#### Transaction Ownership
Run start owns one application-level database transaction.

The command should follow the established mutation pattern:

1. parse/validate request and idempotency key;
2. begin transaction;
3. lock the player's `user_state` row first;
4. check finalized idempotency receipt;
5. validate current run eligibility and authoritative Warband configuration;
6. calculate/materialize effective Energy at the command time;
7. require enough effective Energy;
8. load validated private Farm generation definition;
9. generate the in-memory Farm graph;
10. persist run root, nodes, edges, and participating unit rows;
11. persist post-spend Energy + regeneration anchor;
12. increment `player_revision` exactly once as part of the authoritative mutation;
13. persist finalized idempotency receipt;
14. commit;
15. return finalized authoritative result.

If any step fails, roll back everything.

Do not let a repository independently commit/roll back.

#### Concurrency
Use the locked `user_state` row as the primary per-player mutation serialization boundary, consistent with Milestone 2 commands.

This must make concurrent starts safe even when they use different idempotency keys:
- one command may create the active run;
- the later command observes that authoritative active run and fails without another Energy spend or revision increment.

The Package 1 unique active-run invariant remains the database backstop, not the normal domain error mechanism.

#### Idempotency
Reuse the existing `idempotency_requests` persistence.

Use a concrete operation identity such as:

`start_run`

Canonical request hashing should depend on the normalized run-start request, currently the requested region ID.

Same user + same key + same accepted request:
- return the original finalized result exactly;
- do not generate another run;
- do not spend Energy again;
- do not increment revision again.

Same user + same key + different request/operation:
- return an idempotency conflict;
- mutate nothing.

Same key used by different users remains independent.

Missing/malformed/oversized key fails before mutation.

#### Active-Run Eligibility
After locking the player, verify there is no existing active run owned by that player.

Do not rely only on catching the database uniqueness exception.

If an active run already exists:
- fail with a narrow conflict/domain error;
- do not spend Energy;
- do not generate/persist another graph;
- do not increment revision.

Do not implement resume behavior here. Package 4 owns current-run reads.

#### Active Squad Selection
The run always uses the server-authoritative `user_state.active_squad_id`.

Do not accept a squad ID from the client.

Require:
- active squad is non-null;
- squad exists;
- squad belongs to the authenticated user;
- persisted active-squad relationship is not corrupt.

If no active squad exists, fail run creation without mutation.

#### Participating Formation
Load the active squad's persisted formation after the player row is locked.

Require at least one participating unit.

For every occupied position validate:
- unit exists;
- unit belongs to the authenticated player;
- unit lifecycle is `active`;
- no duplicate unit identity;
- persisted squad relationship is internally coherent.

Cross-owner squad/unit corruption must be treated as a server/data-integrity failure rather than returned to the player as usable state.

Do not copy the complete squad formation into the run root merely for lock enforcement.

Persist one `run_unit_state` row for each participating unit. `current_hp` remains `NULL` in Milestone 3 because the authoritative combat-stat/HP resolver remains deferred to Milestone 4.

#### Participating Unit Configuration
A unit entering a run must have a valid committed combat configuration.

For every participating unit validate the same core invariants established by the authoritative unit-detail/loadout slice:
- authored unit type exists and is correct type;
- authored kin exists and is correct type;
- durable `unit_abilities` relationships are valid;
- committed loadout is non-empty;
- committed equip order is contiguous and deterministic;
- every equipped ability is durably owned by that unit;
- equipped abilities are authored active abilities;
- every authored required dice slot is filled exactly once;
- each bound die exists, is active, belongs to the user, references a valid canonical profile, and uses an allowed size;
- physical die uniqueness is coherent;
- foreign/corrupt bindings fail safely.

Do not infer instance ability ownership from current unit type.

Do not automatically repair or provision missing loadouts/dice during run start.

Reuse existing narrow validation/assembly logic where ownership is already correct rather than implementing a second subtly different definition of a valid unit configuration.

#### Energy Calculation
Energy remains an authoritative pacing resource, not currency.

Use the accepted deterministic Energy arithmetic rather than prototype `EnergyService` transaction ownership.

At command time, calculate effective Energy from:
- persisted current;
- persisted regeneration anchor;
- authored normal maximum;
- authored regeneration rate;
- authoritative current UTC time.

Do not mutate Energy merely to read/check it.

If effective Energy is less than `run_energy_cost`:
- fail with an `insufficient_energy`-style domain error;
- persist no regeneration materialization;
- create no run;
- change no revision.

A later bootstrap/read can still derive the same effective Energy from the untouched persisted state.

#### Energy Anchor Semantics
A successful spend must persist both the post-spend Energy and the correct regeneration anchor without losing fractional regeneration progress or granting regeneration for time spent capped.

Preserve these rules explicitly.

##### Below maximum and not yet capped
If persisted Energy was below the normal maximum and elapsed regeneration raises it but **does not reach the maximum** before the spend:
- materialize the earned whole ticks;
- preserve fractional progress toward the next tick;
- advance the anchor by the number of whole earned tick intervals, rather than resetting it blindly to `now`.

Example at 12/hour:
- anchor 12:00;
- persisted 40;
- command 12:27;
- effective before spend 45;
- five whole ticks earned with two minutes of fractional progress remaining;
- post-materialization anchor is equivalent to 12:25, not 12:27.

##### Reached/full/over-cap before spend
If Energy is at/above normal maximum before the spend, or regeneration would have reached the maximum before `now`, natural regeneration has been paused while capped.

If the successful spend leaves Energy below the normal maximum, regeneration resumes from the spend time, so persist the anchor as `now` rather than granting stale capped elapsed time.

This also applies when an over-cap balance such as 57/50 is spent down below maximum.

##### Still at/above maximum after spend
Natural regeneration remains paused. Persist a coherent anchor that cannot later turn capped elapsed time into retroactive Energy when a future spend drops below maximum.

Prefer a small deterministic Energy mutation/calculation helper over scattering timestamp arithmetic through controller/repository code.

Test these cases directly with controlled timestamps.

#### Graph Generation
Load:
- the validated requested region;
- its private `run_generation` definition;

from the canonical `ContentRegistry`.

Invoke the pure Package 2 generator.

Do not call the prototype `Services\RunGraphGenerator`.

The generated Farm graph must be the exact validated five-node fixed graph currently produced by `FixedGraphRunGenerator`.

Do not regenerate after persistence merely to construct the response.

#### Graph Persistence
Persist the generated graph from Package 2 into the Package 1 tables.

Expected mapping:
- create the `runs` root with authenticated user, authored region ID, active squad, and active status;
- insert generated nodes preserving deterministic `node_index`, stable node-type ID, nullable encounter ID, initial status, and generated metadata;
- map generated node indexes to newly persisted node IDs;
- insert edges using those persisted node IDs while retaining run ownership;
- insert each participating unit into `run_unit_state` with deferred/null HP.

No SQL-authored content catalogs.

No prototype numeric encounter IDs.

No combat/reward/run-modifier state.

#### Run Repository Boundary
Introduce/adapt a narrow vNext run-persistence repository boundary as needed.

Repositories may:
- find active run identity/eligibility state;
- insert run root;
- insert generated nodes and return persisted IDs;
- insert edges;
- insert run-unit participation.

Repositories must not:
- load authored generation rules;
- calculate Energy;
- own the transaction;
- generate the graph;
- map HTTP responses;
- resolve combat/rewards.

Do not make the command depend on prototype Run/Node/Edge repositories if their row contracts still target the old schema.

#### Player State Persistence
Add only the narrow `PlayerStateRepository` mutation needed to atomically persist:
- Energy current;
- Energy regeneration anchor;
- one `player_revision` increment.

Do not increment revision in a separate unrelated transaction.

A real successful run creation increments exactly once.

Idempotent replay returns the original revision.

Failures and rollbacks do not increment.

#### Success Result
The command/HTTP response must return enough authoritative affected state for future client reconciliation without becoming the full Package 4 current-run payload.

Return a shape equivalent to:

```text
run:
  id
  region_id
  squad_id
  status
energy:
  current
  normal_maximum
  regeneration_per_hour
  regeneration_interval_seconds
  last_regeneration_at
  next_regeneration_at
  fully_regenerated_at
player_revision
```

Use the same Energy semantic shape as bootstrap so the later client can replace its cached Energy authoritatively.

Do not include hidden Farm generation topology merely because the command generated it.

Package 4 owns the complete current-run aggregate.

#### HTTP Errors
Follow existing vNext envelopes/conventions.

Provide narrow non-sensitive domain errors for at least:
- malformed request;
- missing/invalid idempotency key;
- idempotency conflict;
- unsupported/invalid region;
- no active squad;
- empty active squad;
- invalid/corrupt participating configuration;
- insufficient Energy;
- active run already exists;
- authentication/CSRF failure;
- internal persisted/content integrity failure.

Do not expose SQL exceptions, foreign asset identity, or internal configuration details to the player.

#### Security / Ownership
Be adversarial.

The command must not allow a player to create a run using:
- another player's squad;
- another player's unit;
- another player's die;
- corrupt cross-owner persisted relationships.

Because the client cannot submit squad/unit/die IDs, ordinary user input should not be able to select these assets at all. Persisted corruption must fail safely.

Do not leak foreign IDs/names in error output.

#### Existing Fixture
The controlled Package 3/Milestone 2 Warband fixture should remain a practical development/test source of an active configured squad for run-start tests.

Do not change normal production registration to provision starter Warband assets.

Do not make run start secretly invoke the fixture or onboarding provisioning.

#### Tests
Use real MySQL integration coverage for transaction behavior.

At minimum prove:
- route authentication;
- CSRF required;
- exact request shape;
- valid `Idempotency-Key` required;
- canonical `run_energy_cost` is validated/read from content;
- successful Farm start uses the server active squad;
- no squad/unit/dice IDs accepted from client;
- empty/no active squad rejected;
- foreign/corrupt active squad rejected safely;
- foreign/corrupt participating unit relationship rejected safely;
- inactive/terminal unit rejected;
- invalid authored unit/kin rejected as integrity failure;
- empty/incomplete/passive/corrupt loadout rejected;
- missing/foreign/terminal/profile-invalid die rejected;
- existing active run rejected before spend;
- insufficient effective Energy rejected without state mutation;
- elapsed Energy regeneration is counted for eligibility;
- fractional regen progress is preserved after successful spend when below cap;
- time spent capped does not become retroactive regen after spend;
- over-cap spend semantics are correct;
- exact cost is spent once;
- run root persists correct region/squad;
- exact five nodes/edges persist and match generated graph;
- participating unit rows persist with null/deferred HP;
- generated graph is not regenerated into a different persisted topology;
- same idempotency key + same request replays original result with no second spend/run/revision;
- same key + different request/operation conflicts;
- identical keys for different users are independent;
- different concurrent/sequential keys cannot create two active runs for one user;
- generation failure rolls back run/Energy/revision/idempotency receipt;
- persistence failure rolls back run/Energy/revision/idempotency receipt;
- successful mutation increments revision exactly once;
- failed/replayed mutation does not increment;
- response contains authoritative Energy and run summary but no private generation topology;
- fresh registration remains run-empty;
- Package 1 persistence and Package 2 content/generator regressions remain green;
- Milestone 2 Warband commands/reads remain green.

Where concurrency is hard to induce deterministically, prove row-lock ordering/application behavior plus the database active-run uniqueness backstop with real MySQL coverage.

#### Documentation
Update current accepted documentation in place when this package makes a durable rule concrete, especially:
- canonical `run_energy_cost` tuning ownership;
- successful Energy spend/anchor materialization semantics;
- run-start request/response details if the endpoint inventory needs refinement.

Do not create package completion reports or legacy snapshots.

#### Explicitly Out of Scope
Do not implement:
- `GET /api/v1/runs/current`;
- abandon;
- bootstrap active-run hydration;
- Warband active-run configuration locks;
- frontend RuntimeApiClient run start;
- Camp start button behavior;
- RunScene;
- run map;
- node selection/resolution;
- combat;
- rewards;
- Rest/Chaos;
- current HP/stat resolution;
- run modifiers;
- battles/playback;
- Mountains/Swamps generation;
- Milestone 4.

Do not clean up prototype run code yet beyond a narrow documentation update. Package 4+ still need to mine lifecycle/read/locking edge cases.

#### Verification
Run applicable gates from `agent/QUALITY_GATES.md`.

At minimum:
- content validation/generation after adding run Energy cost;
- focused Energy arithmetic tests;
- focused run-start command/controller tests;
- real MySQL run-start transaction/idempotency tests;
- Package 1 run persistence regression;
- Package 2 content/generator regression;
- full backend Docker suite;
- existing Warband backend regressions;
- docs/context checks where changed.

Frontend tests/build are only required if generated content/client contracts unexpectedly change. `run_energy_cost` should remain server-side in this package, so no frontend contract change is expected.

No screenshot capture is required.

#### Review State
When complete:
- set this issue to `In Progress` if needed;
- leave it `In Progress`;
- do not mark it complete;
- do not promote Package 4;
- do not begin current-run/abandon/locking work.

Architectural review decides completion.

#### Final Report
Report:
1. resulting commit SHA;
2. canonical Energy-cost definition/accessor;
3. run-start request and response contract;
4. eligibility/Warband integrity rules;
5. Energy materialization/spend/anchor algorithm;
6. transaction and locking order;
7. idempotency semantics;
8. graph persistence mapping;
9. run-unit-state behavior;
10. revision behavior;
11. error/security behavior;
12. tests/verification actually run and results;
13. unresolved concern, if any.

Do not begin another package.
