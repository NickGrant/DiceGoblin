# Active Execution Issue

## Milestone 5 - Complete Farm

### Milestone 5 Package 3 - Farm Loot + Rest authoritative node resolution

**Status:** Open
**Priority:** High

#### Problem
Packages 1-2 established the reusable server-only authored reward model, immutable finalized reward results, exact currency/XP/unlock application, and retry-safe resolved-event persistence.

The persisted Farm graph already contains:

`Combat -> Loot -> Rest -> Boss -> Exit`

but the live vNext node-resolution command supports only Combat. Package 3 makes the existing Loot and Rest nodes playable through the same authoritative bodyless resolution endpoint while preserving all Milestone 4 combat behavior.

This is the first live integration of the reward application service.

#### Accepted Farm behavior
For the current Farm vertical slice:

##### Loot
Completing the Farm Loot node emits:

`event.farm_loot_completed`

Its reward definition is deliberately simple:

- one ordered reward entry;
- 10000 basis points;
- currency = Teeth;
- amount = **8 Teeth**.

This retains the useful prototype Farm Loot baseline without reviving prototype SQL loot tables or its random unit/die loot behavior.

Do not grant units, dice, items, Raw Chaos, XP, Codex, or unlocks from this Loot node in Package 3.

##### Rest
Completing the Farm Rest node performs a direct full-recovery effect.

For every exact participating run unit:
- resolve the unit's current max HP from its current persisted level + current authored unit type using the canonical Package 1 combat-stat resolver;
- persist `run_unit_state.current_hp = resolved max HP`.

This includes a unit currently at 0 HP.

Rest is an **effect**, not a reward:
- no Teeth/XP/unlock reward;
- no artificial reward event merely to record healing;
- no claim/acknowledgement state.

Rest does not alter unit level/XP or Energy.

#### Authored Loot event
Add canonical server-only definitions for:

- `event.farm_loot_completed`;
- `reward_definition.farm_loot_completed`.

The reward definition contains the single deterministic 8-Teeth entry above.

Event/reward definitions remain absent from client content projection.

#### Persist event identity on generated run nodes
Do not hardcode `event.farm_loot_completed` inside the application handler.

Extend the current fixed-graph authored node contract with optional:

`event_id`

Rules:
- must be a stable `event.*` ID;
- must reference an authored event;
- it is private authored/runtime configuration;
- it is not added to the current-run client projection.

Persist the optional event identity on the generated `run_nodes` row in the fresh baseline.

Update run generation/persistence/validation accordingly.

Set the Farm Loot authored node's `event_id` to `event.farm_loot_completed`.

Combat/Rest/Boss/Exit do not need speculative event IDs in this package. Boss will receive its actual completion event in its owning package once that exact reward definition is accepted.

This persisted event reference is the durable source configuration needed so a later content change does not make an already-generated node silently become a different reward event.

#### One node-resolution endpoint
Continue using:

`POST /api/v1/runs/:runId/nodes/:nodeId/resolve`

Requirements remain:
- authentication;
- CSRF;
- canonical positive IDs;
- 8-128 character `Idempotency-Key`;
- **no request body**.

Do not create Loot/Rest-specific player mutation endpoints.

#### Generic vNext run-node command
Refactor the active vNext route/composition so it is no longer permanently wired to a command named/structured as Combat-only.

Prefer one authoritative `ResolveRunNodeCommand` transaction boundary with type-specific internal handlers/services for:
- Combat;
- Loot;
- Rest.

Alternative internal naming is acceptable if the architecture has the same properties.

Do **not** route through or revive the dormant prototype `RunNodeController` / `DeterministicRunNodeResolver`.

##### Shared transaction ordering
For every supported type:

1. validate canonical IDs/key/body at HTTP boundary;
2. begin transaction;
3. lock `user_state` first;
4. check exact idempotency receipt before ordinary lifecycle rejection;
5. lock owned run;
6. lock exact run node;
7. validate run/node lifecycle;
8. execute the selected authoritative handler;
9. complete node;
10. unlock only directly outgoing persisted child nodes;
11. increment `player_revision` exactly once;
12. persist exact idempotency response receipt;
13. commit.

A handler must not own/commit its own parent transaction.

Preserve Package 4 Combat semantics exactly, including battle persistence, terminal HP, defeat/stalemate run failure, combat seed, battle response facts, and replay/retry behavior.

#### Idempotency
Continue operation identity:

`resolve_run_node`

and the existing canonical run/node fingerprint.

Exact same-key replay occurs before ordinary already-resolved rejection and returns the exact original response without:
- rerunning combat;
- rerolling Loot;
- reapplying Teeth;
- healing again;
- unlocking again;
- changing completion timestamps;
- incrementing revision again.

A different key against a completed node returns the established already-resolved conflict and does not use the event record as a way to "resolve" the node again.

For Loot, `resolved_events` is an additional reward double-application/reroll safety boundary, not a replacement for command idempotency.

#### Loot transaction
After shared locks/lifecycle validation:

1. require the persisted Loot node to have a valid persisted `event_id`;
2. assemble `RewardContext` from authoritative locked state:
   - wallet balances from already-locked `user_state`;
   - exact run-participating unit IDs with their current persisted level/XP;
   - current owned unlock IDs;
3. invoke Package 2 reward application inside the same parent transaction using:
   - event ID from the persisted node;
   - source type `run_node`;
   - source ID using the exact durable run-node identity, canonicalized as `run_node:<nodeId>`;
4. receive the exact finalized/applied result;
5. complete the Loot node and unlock only its direct child (Rest);
6. increment revision once and commit with the receipt.

Do not mutate Energy.

The current production Loot definition has one deterministic entry, but do not special-case "8 Teeth" in application logic. It must flow through authored event -> reward definition -> Package 2 finalization/application.

##### Loot response projection
Do **not** return the raw finalized reward result because that contains private probability and roll facts.

Return a narrow player-safe projection such as:
- resolution type = `loot`;
- node completion facts;
- newly available IDs;
- resulting run status;
- authoritative affected wallet balance(s);
- player-visible granted reward summary (for current content: 8 Teeth);
- player revision.

Do not expose:
- event ID;
- reward-definition ID;
- probability;
- roll;
- source identity;
- private reward config.

#### Rest transaction
After shared locks/lifecycle validation:

1. require node type Rest;
2. lock/read the exact run participating units and their current run HP;
3. load the exact owned active unit state needed for current unit type + level;
4. resolve each max HP using ContentRegistry + the canonical infrastructure-free `BaseLevelStatResolver`;
5. persist full HP for every run participant;
6. complete Rest;
7. unlock only its direct child (Boss);
8. increment revision once and commit with receipt.

Reject/rollback on:
- participant mismatch;
- missing/foreign/inactive unit;
- invalid authored unit type/stat content;
- invalid persisted HP.

Do not use prototype unit-stat services or SQL unit-type catalogs.

##### Rest response projection
Return a narrow player-safe projection including:
- resolution type = `rest`;
- node completion facts;
- newly available IDs;
- run status;
- exact ordered healing transitions:
  - unit ID;
  - HP before;
  - HP after/max HP;
- player revision.

Do not return hidden authored stat config.

#### Discriminated mutation response
The shared endpoint now returns more than Combat.

Add a strict top-level discriminator:

`resolution_type: "combat" | "loot" | "rest"`

Add `resolution_type: "combat"` to the existing Combat response while preserving all its other accepted fields/semantics.

The strict TypeScript transport/parser becomes a union keyed by `resolution_type`.

Existing Fight/BattleScene code must continue to accept only the Combat variant for Fight.

Loot/Rest RunScene actions accept only their matching variants.

Exact idempotency receipts persist the discriminated response.

#### Authoritative frontend reconciliation
Add RunScene interactions only for current available nodes:

- available Loot -> clear action such as **Collect Loot**;
- available Rest -> clear action such as **Rest**;
- Boss and Exit remain unavailable as actions until their later packages.

Use the same bodyless `resolveRunNode` API transport.

Prefer generalizing the existing logical node-resolution attempt/idempotency-key helper rather than creating incompatible duplicate retry machinery.

For one user action:
- create one run/node/key attempt;
- suppress duplicate submission;
- retain the same key across ambiguous network/5xx/malformed-response outcomes;
- exact retry reuses the same key.

No optimistic graph/HP/wallet mutation.

After a definitive successful Loot/Rest response:
1. retain its player-visible result locally for presentation;
2. apply only authoritative wallet/revision facts from the mutation response to the appropriate GameStore/bootstrap cache boundary;
3. force a fresh `GET /api/v1/runs/current`;
4. reconcile run graph/current HP through the existing strict current-run/GameStore boundary;
5. show the result and resulting map state.

Do not patch node availability or Rest HP locally from assumptions.

If the post-mutation current-run GET fails:
- the mutation is already finalized;
- keep an understandable local result/sync-error state;
- Retry Sync performs **current-run GET only**;
- do not resolve the node again;
- do not call bootstrap/Warband merely to synchronize the run.

Reload may lose the transient result card; that is acceptable because there is no claim/acknowledgement state. The authoritative completed node/reward/HP remain persisted.

#### Player-state cache after Loot
Loot changes Teeth.

The successful authoritative mutation response must include the resulting Teeth balance and revision needed for the client to adopt those exact player-state facts without a bootstrap refresh.

Do not increment or guess the balance client-side.

Rest leaves wallet state unchanged.

#### Run progression
For current Farm victory path:

Before Package 3:
- Combat completed;
- Loot available;
- Rest/Boss/Exit locked.

After resolving Loot:
- Combat completed;
- Loot completed;
- Rest available;
- Boss/Exit locked.

After resolving Rest:
- Combat completed;
- Loot completed;
- Rest completed;
- Boss available;
- Exit locked.

Run remains `active`.

Only direct outgoing persisted graph edges control unlocking.

Do not reconstruct the Farm sequence in application code.

#### Failure and integrity behavior
Preserve non-disclosing owned run/node behavior.

Use established controlled errors where possible.

Reward/content/stat/persisted event inconsistencies become the existing run-data integrity boundary, not raw internal error text.

A reward failure, HP failure, node-completion failure, revision failure, or receipt failure rolls the complete node transaction back.

For Loot rollback must include:
- finalized/applied event;
- Teeth;
- node status;
- child unlock;
- revision;
- receipt.

For Rest rollback must include:
- all HP changes;
- node status;
- child unlock;
- revision;
- receipt.

#### Tests
At minimum prove:

##### Content/generation
- canonical Farm Loot node references `event.farm_loot_completed`;
- event references the accepted reward definition;
- reward is exactly one 100% 8-Teeth entry;
- malformed/missing node event references reject content;
- generated/persisted Loot retains event ID;
- event ID is absent from client current-run/content projection.

##### Backend Loot
Real MySQL integration:
- available Loot resolves to exactly +8 Teeth;
- resolved event becomes applied;
- result JSON retains exact finalized facts privately;
- node completes, only Rest unlocks;
- revision increments once;
- Energy unchanged;
- same-key replay returns exact response with no second Teeth/event/unlock/revision;
- different key cannot reroll/reapply;
- missing/malformed/wrong persisted event fails atomically;
- post-reward forced failure rolls event + Teeth + node + unlock + revision + receipt back.

##### Backend Rest
Real MySQL integration:
- partially injured, 0-HP, and healthy participants all end at exact current max HP;
- higher-level unit max HP uses current canonical level stats;
- no unit level/XP changes;
- no Energy/wallet changes;
- node completes, only Boss unlocks;
- revision increments once;
- exact replay does not heal/revise again;
- different key cannot resolve twice;
- invalid participant/content/state rolls back;
- forced post-heal failure rolls every HP/node/unlock/revision/receipt change back.

##### Combat regression
- existing Combat endpoint behavior remains intact except accepted `resolution_type: combat`;
- Combat still invokes CombatEngine exactly once;
- Fight retry/reload/replay semantics remain green;
- defeat/stalemate still terminate runs correctly;
- BattleScene does not receive Loot/Rest responses.

##### Frontend
- available Loot shows Collect Loot and sends one bodyless resolve POST;
- successful Loot shows +8 Teeth from response, adopts exact balance, and reconciles Rest availability through current-run GET;
- available Rest shows Rest and resolves once;
- Rest result presents full-recovery facts and reconciles Boss availability/current HP;
- Boss/Exit remain non-actionable;
- ambiguous POST retry reuses key;
- duplicate clicks suppressed;
- sync retry after committed mutation performs current-run GET only;
- no bootstrap/Warband refresh;
- no client reward roll or HP/max-stat calculation;
- existing Combat Fight/Replay/BattleScene tests remain green;
- pointer/disabled/portrait behavior follows current interaction conventions.

#### Verification
Run applicable `agent/QUALITY_GATES.md` gates.

At minimum report:
- content validation/revision;
- fixed-graph generation tests;
- focused Loot/Rest MySQL command/controller tests;
- Package 2 reward application regression;
- M4 combat resolution/playback regressions;
- focused RunScene/API/GameStore tests;
- complete backend Docker suite;
- complete frontend suite;
- production frontend build;
- bundle check;
- deterministic captures for available Loot, Loot result/Rest available, Rest result/Boss available in Standard plus Compact/Wide where useful;
- `npm run llm:check`;
- `npm run docs:lint`;
- `git diff --check`.

If practical extend a real-stack browser verifier through:

`Combat Continue -> Loot -> Rest -> Boss available`

Do not claim unavailable CI or host-only gates passed.

#### Explicitly out of scope
Do not implement:
- random unit/die/item Loot;
- Combat rewards;
- XP from live Farm nodes;
- Raw Chaos from Farm Loot;
- Rest choices/consumable spending;
- run modifiers;
- Mudking content or Boss resolution;
- Exit resolution;
- Farm successful termination;
- Mountains grant/gameplay;
- reward history/replay UI;
- claim/acknowledgement lifecycle;
- Milestone 6.

#### Completion requirements
Before architectural review:
1. implement only Package 3;
2. preserve all M4 Combat authority/idempotency/playback behavior;
3. use authored Loot event + Package 2 reward application rather than hardcoded grant logic;
4. keep Rest healing as a direct effect;
5. run/report gates honestly;
6. leave Package 3 **In Progress**;
7. do not promote Package 4;
8. report:
   - exact implementation SHA;
   - generic node-resolution transaction/handler structure;
   - persisted authored `event_id` path;
   - Loot event/reward definition;
   - Rest max-HP resolution;
   - mutation response union;
   - frontend retry/reconciliation/result behavior;
   - rollback/idempotency evidence;
   - exact verification results.
