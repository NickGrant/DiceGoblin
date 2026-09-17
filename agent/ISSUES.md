# Active Execution Issue

## Milestone 5 - Complete Farm

### Milestone 5 Package 2 - Finalized reward results + transactional grant application

**Status:** Open
**Priority:** High

#### Problem
Package 1 established the authored `unlock` / `event` / `reward_definition` contracts, server-only exposure rules, minimal `user_unlocks` and `resolved_events` persistence, and real bootstrap unlock reads.

The next requirement is to turn an authored successful event into one immutable, replay-safe, exact reward result and apply that result transactionally to player state.

This package establishes the reusable reward engine/application boundary needed by Farm Loot and the Mudking boss. It does **not** attach rewards to a live Farm node yet.

#### Accepted flow
The canonical sequence is:

`successful gameplay fact -> authored event -> reward definition -> exact rolls -> exact finalized result -> transactional grants -> applied event record`

Rules:
- no ordinary claim step;
- a committed event result is never rerolled;
- a committed applied event is never re-applied;
- reward presentation later consumes finalized authoritative facts;
- parent gameplay commands own the transaction and the one player-revision increment;
- this package's reward services do not own/commit transactions or increment `player_revision`.

#### Required context
Read:
- `documentation/07-development-path/vnext-reward-unlock-model.md`;
- `documentation/07-development-path/vnext-progression-state-model.md`;
- `documentation/07-development-path/vnext-currency-economy-model.md`;
- `documentation/02-systems/unit-stat-advancement.md`;
- Package 1 content validation and persistence;
- current `PlayerStateRepository`, Warband unit persistence, and `UserUnlockRepository`;
- current transaction/locking patterns in StartRun and ResolveCombatNode;
- retained prototype progression/reward code only as behavioral evidence.

Do not revive prototype `battle_rewards`, claim semantics, SQL reward catalogs, tier-dependent XP curves, or `UserUnlockService` namespace storage as the vNext architecture.

#### XP / level semantics
Package 2 makes the following vNext rule canonical.

`unit_instances.xp` stores **progress within the unit's current level**, not lifetime cumulative XP.

For current level `L >= 1`:

`xp required for L -> L+1 = 100 * L`

When XP is granted:
1. add the grant to current XP;
2. while XP is at least the threshold for the current level:
   - subtract that threshold;
   - increment level;
   - recompute the next threshold;
3. persist the final level and remainder XP.

Examples:
- level 1, 0 XP + 99 -> level 1, 99 XP;
- level 1, 0 XP + 100 -> level 2, 0 XP;
- level 2, 150 XP + 50 -> level 3, 0 XP;
- level 1, 90 XP + 250 -> level 3, 40 XP.

Additional rules:
- unit type/tier does not affect the XP threshold;
- promotion does not reset level or XP;
- multiple levels may be gained from one reward;
- all exact participating units targeted by the reward receive the authored amount individually, including units that ended combat defeated;
- no maximum-level cap is enforced by this Milestone 5 resolver;
- arithmetic/SQL overflow must reject/rollback rather than clamp;
- level-up changes the persisted unit level immediately;
- level-up does **not** heal or proportionally adjust `run_unit_state.current_hp`;
- later combat in the same run resolves stats from the new persisted level while retaining the existing numeric run HP until a legitimate healing/effect changes it.

Update `documentation/02-systems/unit-stat-advancement.md` to make this canonical and remove its statement that the XP curve remains unresolved.

Implement the XP calculation as a small infrastructure-free domain resolver.

#### Reward roll source
Reward probabilities use the Package 1 integer basis-point contract.

Each authored entry consumes exactly one integer roll in `1..10000`.

The entry rolls when:

`roll <= probability_basis_points`

Therefore a 10000 entry always succeeds and a 1 entry succeeds only on roll 1.

Use a small injected roll-source interface:
- production uses a cryptographically appropriate server random source such as `random_int(1, 10000)`;
- tests use fixed/scripted rolls.

Do **not** derive production reward outcomes from public run/node IDs, timestamps, client values, or a predictable public seed.

The pure finalizer must be deterministic for identical normalized definitions + authoritative context + scripted roll sequence.

An already persisted event result consumes no new rolls.

#### Authoritative reward context
The finalizer consumes a normalized authoritative context supplied by the owning command/application layer.

For the current grant types it contains:
- current Teeth and Raw Chaos balances;
- exact participating owned units targeted by `participating_units`, each with unit ID, level, and XP;
- current owned unlock IDs.

Context is server state. The browser never submits reward context.

The future owning run-node command must obtain/lock those facts using the established user-first lock order before finalization.

Package 2 tests may use a narrow integration harness/transaction to prove the service without adding a player endpoint.

#### Versioned finalized result
Add a strict infrastructure-free value/codec for **version 1** finalized reward events.

The authoritative persisted result must carry enough exact information to replay/application-check without consulting current probabilities or recomputing XP.

At minimum:

- `version: 1`;
- `event_id`;
- `reward_definition_id`;
- exact source identity: `source_type` + `source_id`;
- ordered resolved entry results in the same order as authored entries.

Every entry records:
- authored entry `key`;
- `reward_type`;
- `probability_basis_points`;
- exact `roll`;
- outcome;
- exact typed grant/application facts when applicable.

Allowed outcomes for Package 2:
- `not_rolled`;
- `granted`;
- `already_owned` for a rolled unique unlock that produces no grant.

Typed exact facts:

##### Currency
For a granted currency entry record:
- currency ID;
- authored amount;
- balance before;
- balance after.

Multiple successful entries for the same currency must chain through a local projected balance in authored entry order.

##### Unit XP
For a granted XP entry record:
- target scope;
- amount per unit;
- exact ordered unit results.

Each unit result records:
- unit ID;
- level before;
- XP before;
- level after;
- XP after.

Multiple successful XP entries must chain through projected unit state in authored entry order.

Use deterministic ascending canonical unit-ID order inside each XP grant result regardless of incidental query order.

##### Unlock
For a rolled unlock entry record the target unlock ID.

If not previously/planned-owned:
- outcome `granted`.

If already owned:
- outcome `already_owned`;
- no durable grant is produced.

If two ordered entries in the same finalized reward definition target the same unique unlock, the first successful planned grant makes that unlock owned in the finalizer's projected state so a later successful entry finalizes as `already_owned`.

#### Strict result validation
The result value/codec must reject incoherent payloads, including:
- unsupported version/event/source identity;
- duplicate/missing/reordered entry keys relative to the finalized payload contract;
- roll outside 1..10000;
- outcome inconsistent with roll and probability;
- unsupported reward type/outcome combinations;
- malformed currency before/after math;
- malformed XP before/after transitions;
- duplicate XP unit IDs;
- unordered XP unit IDs;
- XP transitions that do not match the canonical XP resolver;
- `already_owned` on non-unlock rewards;
- malformed unlock IDs;
- integer overflow/negative values.

Do not rerun randomness during decode/hydration.

The persisted result should remain understandable without current authored reward probabilities. It is historical operational evidence.

#### Resolved-event repository
Now that the finalized result boundary is concrete, add a typed persistence repository/codec boundary for `resolved_events`.

Required primitives:
- retrieve by exact user + event + source type + source ID;
- insert a caller-supplied finalized value;
- mark the exact finalized row applied without rewriting `result_json`;
- hydrate through the strict finalized-result codec.

Validate row columns against JSON:
- event ID must match;
- source type/ID must match;
- status/timestamps coherent;
- applied rows have `applied_at`;
- result JSON remains immutable.

Repositories do not own transactions, RNG, ContentRegistry, grant decisions, or revision increments.

#### Reward resolution/application service
Introduce an application service that operates **inside a caller-owned transaction**.

It may use ContentRegistry + persistence repositories, but must not begin/commit/rollback the parent transaction itself.

For an exact event/source identity:

1. look for an existing resolved event;
2. if existing and `applied`, return its decoded exact result with no RNG or player mutation;
3. if an existing `finalized` but unapplied row is encountered at the start of a new application call, treat it as integrity failure rather than guessing whether grants partially committed;
4. otherwise:
   - load/validate authored event + reward definition;
   - validate the authoritative reward context;
   - finalize the exact result using the injected roll source;
   - insert the `finalized` result;
   - apply exactly the `granted` entries;
   - mark the event `applied`;
   - return the exact finalized result.

Normal production commands must insert finalization + all grants + applied transition inside the same outer transaction. Therefore a failed grant rolls back the new event row too.

The unique `resolved_events` identity is a database concurrency backstop; user-first parent locking remains the primary serialization strategy.

#### Grant application
Apply only the Package 1 supported families.

##### Currency
Support both current wallet currencies:
- `teeth`;
- `raw_chaos`.

Apply the finalized exact before/after state. Reject stale/mismatched balances or overflow rather than silently recalculating a different result.

Do not modify Energy.

##### Unit XP
Apply the exact finalized level/XP transition to the exact owned unit.

Use ownership/current-state checks and reject stale/mismatched `level/xp` rather than recomputing a different result.

Do not:
- heal current run HP;
- promote the unit;
- grant abilities;
- alter unit type/kin/loadout/dice.

##### Unlock
Before finalization, validate every authored unlock reward target through ContentRegistry.

For a finalized `granted` unlock, the idempotent insert is expected to create the row. If it unexpectedly already exists after finalization planned a grant, fail integrity/rollback rather than silently changing the finalized outcome.

`already_owned` performs no insert.

Do not grant un-authored IDs.

#### Player revision
This service does not increment `player_revision`.

The future owning gameplay command will perform all direct effects + reward grants + exactly one revision increment in the same transaction.

Package 2 integration tests should prove reward application alone leaves revision unchanged.

#### No live Farm integration yet
Do not:
- attach an event to Combat/Loot/Rest/Boss/Exit;
- author production Farm reward amounts/chances merely to exercise this package;
- change node-resolution responses;
- add reward UI;
- alter BattleScene;
- unlock Mountains for a fixture/account;
- create a reward endpoint.

Use controlled test authored definitions and a narrow transaction harness for integration proof.

#### Tests
At minimum prove:

##### XP domain
- threshold boundaries;
- carry remainder;
- multiple level-ups;
- level/tier independence;
- overflow rejection;
- the four examples above;
- run current HP is not part of XP resolution.

##### Pure finalization
- exact 1 and 10000 probability boundaries;
- one roll consumed per entry;
- authored entry order preserved;
- fixed roll sequence produces deep-equal result;
- currency entries chain balances;
- XP entries chain each unit's projected level/XP;
- participant target IDs sort canonically;
- duplicate/planned unlock behavior;
- already-owned unlock;
- no roll/application when existing applied result is reused.

##### Codec/value
- exact valid round trip;
- every incoherence class above rejects;
- no timestamps/free-form presentation text/random source state inside deterministic result.

##### MySQL/application
Using actual MySQL 8:
- finalized row + currency/XP/unlock grants + applied transition commit together;
- applied exact retry returns the same result with no rolls or second grants;
- no `player_revision` change;
- no Energy change;
- current run HP unchanged by XP level-up;
- other user's units/unlocks cannot be targeted;
- stale wallet or stale unit state rolls back event/grants;
- forced failure after event insertion/grants rolls back event + wallet + XP + unlock together;
- a preexisting unexpected `finalized` row fails safely without application;
- raw authored unlock validation prevents arbitrary DB IDs.

##### Regression
- Package 1 bootstrap unlock behavior remains correct;
- M1-M4 tests remain green;
- content remains server-private for events/reward definitions.

#### Documentation
Update current canonical docs where concrete behavior is now resolved:
- unit-stat advancement XP semantics;
- reward/unlock finalization/application details if useful;
- storage documentation if the typed resolved-event boundary needs clarification.

Do not add historical/prototype docs back.

#### Explicitly out of scope
Do not implement or scaffold:
- live Farm event definitions/reward tuning;
- Loot node resolution;
- Rest healing;
- Mudking content;
- Boss/Exit resolution;
- Mountains grant in actual gameplay;
- Mountains run generation;
- item/die/unit/Codex reward grants;
- Shop/Academy/Wrong Machine use of the service;
- objectives;
- reward/result Phaser UI;
- claim/acknowledgement endpoints;
- max-level/promotion eligibility beyond the XP rules above;
- Milestone 6.

#### Verification gates
Run applicable gates from `agent/QUALITY_GATES.md`.

At minimum report:
- focused XP/finalizer/codec unit tests;
- focused resolved-event/grant MySQL integration tests;
- Package 1 content/bootstrap regressions;
- fresh DB reset if baseline/test fixtures changed;
- complete backend Docker suite;
- content validation;
- frontend suite only if a client-facing contract/file changes unexpectedly;
- `npm run llm:check`;
- `npm run docs:lint`;
- `git diff --check`.

Do not claim GitHub CI or unsupported host-only gates passed.

#### Completion requirements
Before architectural review:
1. implement only this package;
2. preserve caller-owned transaction/revision boundaries;
3. update canonical XP/reward docs;
4. run/report applicable gates honestly;
5. leave Package 2 **In Progress**;
6. do not promote Package 3;
7. report:
   - exact implementation SHA;
   - XP resolver semantics;
   - finalized-result schema/codec;
   - roll-source design;
   - resolved-event repository;
   - application service and transaction assumptions;
   - exact currency/XP/unlock grant behavior;
   - replay/stale/failure handling;
   - exact tests/gates;
   - deferred decisions for Package 3.
