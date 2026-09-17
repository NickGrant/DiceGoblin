# Active Execution Issue

## Milestone 5 - Complete Farm

### Milestone 5 Package 1 - Reward/event authored model + persistence foundation

**Status:** Open
**Priority:** High

#### Problem
Milestone 4 proved authoritative combat, but intentionally stopped before rewards, XP, permanent unlock grants, Loot/Rest/Boss/Exit resolution, and successful Farm completion.

Milestone 5 needs one reusable event-to-reward path rather than independent special-case grant code for Combat, Loot, Boss, Shop, Academy, Wrong Machine, or later objectives. Before reward randomness/application is implemented, establish the stable authored-content and persistence boundaries that the finalization/application package can build against.

This package is **foundation only**. It must not invent Farm reward amounts, XP curves, Mudking mechanics, or node-resolution behavior.

#### Accepted product/architecture context
Read:
- `documentation/07-development-path/vnext-reward-unlock-model.md`;
- `documentation/07-development-path/vnext-progression-state-model.md`;
- `documentation/07-development-path/vnext-authored-content-model.md`;
- `documentation/07-development-path/vnext-storage-model.md`;
- `documentation/07-development-path/vnext-api-contract-model.md`;
- `documentation/07-development-path/vnext-endpoint-inventory.md`;
- current ContentRegistry/ContentValidator and client projection;
- current fresh `backend/migrations/vnext_baseline.sql`;
- current bootstrap unlock projection;
- retained prototype reward/unlock code only as behavioral evidence, not architecture.

The accepted reward model is:
`successful gameplay fact -> event -> reward definition -> finalized once -> grants applied transactionally`.

Ordinary rewards have no claim step.

#### Authored content families
Add current vNext authored-content support for:

1. `unlock`
2. `event`
3. `reward_definition`

Use stable IDs with the existing vNext conventions.

Recommended canonical shapes:

##### Unlock
An unlock definition identifies one permanent capability.

For the first required production definition, author:

`unlock.region.mountains`

representing access to:

`region.mountains`

A region unlock must semantically reference an existing authored region.

Do not add a separate `farm_completed` unlock/flag.

##### Event
An event definition represents a successful gameplay fact and references exactly one reward definition.

Representative shape:
- stable `event.*` ID;
- `type: "event"`;
- `reward_definition_id`.

Do not author `event.farm_boss_completed` yet unless its exact reward definition is simultaneously finalized by a later owning package. This package establishes the contract, not speculative Farm reward tuning.

##### Reward definition
A reward definition contains an ordered list of authored reward entries.

Each entry needs:
- a stable key unique inside the definition;
- an integer probability represented without floating-point ambiguity;
- a supported reward type;
- type-specific config.

Use integer basis points for probability:
- `1..10000`;
- `10000` means deterministic 100%;
- no separate deterministic reward mechanism.

For **Milestone 5 only**, establish semantic config contracts for the grant families actually needed to Complete Farm:
- currency;
- unit XP;
- permanent unlock.

Keep this extensible through later packages without pre-building unit/die/item/Codex grant application.

Suggested config semantics:
- currency: `currency_id` in the current explicit wallet vocabulary plus a positive integer amount;
- unit XP: positive integer amount plus explicit target scope `participating_units`;
- unlock: stable `unlock_id` referencing an authored unlock.

Do not decide the Farm boss reward amounts/chances in this package.

#### Content validation
Extend structural + semantic validation so startup/CI rejects:
- malformed stable IDs or duplicate IDs;
- malformed event/reward/unlock shapes;
- event -> missing reward-definition references;
- unlock -> missing target-region references;
- reward entry keys duplicated inside one definition;
- probability outside the accepted integer range;
- unsupported reward types;
- malformed type-specific config;
- unlock rewards referencing missing unlock definitions;
- unknown extra fields where the current validator convention requires exact shapes.

No scripting/executable reward behavior belongs in JSON.

#### Exposure boundary
Treat event/reward definitions as **server-only** in this package.

Do not expose:
- reward probabilities;
- reward configs;
- event-to-reward mappings;
- future grant details.

The owned unlock IDs in bootstrap remain player-visible state.

Do not expand the public/client content projection merely because unlock/event/reward definitions now exist.

Anything sent to the browser remains assumed readable.

#### Fresh baseline persistence
Update the single fresh `vnext_baseline.sql`; do not add a migration chain.

Add only the concrete storage needed by the accepted Complete Farm reward path.

##### `user_unlocks`
Persist unique permanent capability ownership.

Required properties:
- `user_id`;
- `unlock_id`;
- `granted_at`;
- uniqueness of `(user_id, unlock_id)`;
- user FK/cascade consistent with current baseline conventions.

Do not add region-completion/history columns.

##### `resolved_events`
Persist finalized reward-bearing event results long enough to prevent rerolls/double application.

Required concepts:
- durable row ID;
- owning user;
- authored event ID;
- explicit source type;
- stable source identity;
- immutable finalized-result JSON;
- lifecycle/status sufficient to distinguish finalized versus applied;
- `resolved_at`;
- nullable `applied_at`.

Enforce one resolution for the same user + event + source identity.

The source boundary must be reusable for later event producers without becoming general event sourcing. For current run nodes, the future source can identify the exact durable run node; later purchase/Academy/Wrong Machine commands may use their own durable transaction identity.

Do not add:
- generic player event history;
- reward claim tables;
- reward-entry rows merely to decompose JSON;
- payout ledger/event sourcing;
- speculative Codex/items/objectives persistence unless another package concretely needs it.

Package 2 will define the exact versioned finalized reward-result domain/codec before application code starts writing meaningful production results. Therefore, keep `result_json` opaque at the SQL layer beyond valid JSON; do not invent a conflicting SQL projection of reward outcomes.

#### Persistence boundaries
Add small persistence-only repository primitives where useful, but do not implement reward finalization/application yet.

Appropriate primitives include:
- list/check owned unlock IDs for a user;
- insert an unlock idempotently or report whether insertion occurred;
- retrieve a resolved event by exact user/event/source identity;
- insert a caller-supplied finalized event record;
- transition a finalized event to applied without rewriting its finalized result.

Repositories must not:
- own transactions;
- roll rewards;
- inspect ContentRegistry;
- calculate XP;
- increment levels;
- mutate Teeth;
- increment player revision;
- resolve run nodes.

If the finalized-result domain boundary is not yet concrete enough for a clean repository insert API, it is acceptable for this package to establish the table and defer the resolved-event repository write method to Package 2 rather than accept untyped arbitrary arrays throughout the application.

#### Bootstrap unlock ownership
Replace any placeholder/hardcoded bootstrap unlock list with a real read from `user_unlocks`.

Bootstrap remains GET/read-only:
- no implicit grant;
- no provisioning side effect;
- no revision increment.

Existing accounts with no unlock rows return an empty list.

Sort unlock IDs deterministically.

No new bootstrap reward/event payload is added.

#### Mountains boundary
Authoring/storing `unlock.region.mountains` does **not** implement Mountains gameplay.

Package 1 must not:
- allow starting a Mountains run merely because the unlock definition exists;
- add Mountains enemies/run generation/combat;
- create Farm-completion state;
- grant the Mountains unlock to any player.

The actual grant happens through the Farm boss event in a later Package 5 package.

#### Tests
At minimum prove:

##### Authored content
- valid unlock/event/reward-definition fixtures load;
- region unlock references a real region;
- event references its reward definition;
- ordered reward entries retain order;
- probability bounds reject malformed definitions;
- duplicate entry keys reject;
- unsupported reward types/config reject;
- missing unlock/reward/region references reject;
- event/reward content remains absent from client projection;
- unchanged content gives deterministic revision.

##### MySQL baseline
Using actual MySQL 8:
- fresh reset applies;
- `user_unlocks` uniqueness works;
- `resolved_events` source/event uniqueness works;
- valid JSON/status/timestamp constraints behave as intended;
- user cascade behavior is correct;
- no reward/claim/event-history tables beyond the accepted `resolved_events` foundation appear.

##### Bootstrap
- no unlock rows -> `unlock_ids: []`;
- owned unlock rows -> deterministic real IDs;
- another user's unlocks never leak;
- bootstrap read mutates no player state/revision.

##### Regression
- M1-M4 backend/content tests remain green;
- existing Farm start/combat/playback behavior remains unchanged;
- no client contract regression from bootstrap's now-real unlock data.

#### Explicitly out of scope
Do not implement or scaffold:
- reward RNG/finalization algorithm;
- finalized reward-result codec beyond what Package 1 persistence strictly requires;
- Teeth/Raw Chaos mutation from rewards;
- XP application or level-up rules;
- reward application transactions;
- Farm Loot reward amounts;
- Rest healing;
- `event.farm_boss_completed` reward tuning;
- Mudking content/combat;
- Boss or Exit node resolution;
- successful run completion;
- actual Mountains unlock grant;
- Mountains run implementation;
- reward/result Phaser UI;
- unit/die/item/Codex reward application;
- claims/acknowledgements;
- Milestone 6 work.

#### Verification gates
Run applicable gates from `agent/QUALITY_GATES.md`.

At minimum report actual results for:
- content validation;
- focused ContentRegistry/validator tests;
- fresh MySQL Docker baseline reset;
- focused unlock/resolved-event persistence tests;
- focused bootstrap integration tests;
- complete backend Docker suite;
- affected frontend/bootstrap tests if any;
- full frontend suite if the client bootstrap fixture/contracts change;
- `npm run llm:check`;
- `npm run docs:lint`;
- `git diff --check`.

Do not report unavailable GitHub CI or unsupported host-only gates as passed.

#### Completion requirements
Before architectural review:
1. implement only this package;
2. update current canonical docs if the concrete schema/content contract materially changes accepted detail;
3. run/report the applicable gates honestly;
4. leave Package 1 **In Progress**;
5. do not promote Package 2;
6. report:
   - exact implementation SHA;
   - authored content shapes/types and validation;
   - client exposure boundary;
   - baseline schema/constraints;
   - bootstrap unlock read path;
   - repository methods introduced;
   - exact tests/gates;
   - unresolved decisions deliberately deferred to Package 2.
