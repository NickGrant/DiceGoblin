# Active Execution Issue

## Milestone 5 - Complete Farm

### Milestone 5 Package 5 - Boss-node authoritative resolution + finalized Farm boss rewards/XP + Mountains unlock

**Status:** Open
**Priority:** High

#### Problem
Packages 1-4 established the reusable finalized reward pipeline, authoritative Combat/Loot/Rest resolution, canonical Mudking combat content, and a persisted Farm Boss encounter identity.

The Farm path is now:

`Combat -> Loot -> Rest -> Boss -> Exit`

After Rest, Boss becomes available, but the live node-resolution command intentionally still rejects Boss as unsupported.

Package 5 connects the authored Mudking encounter to the existing authoritative bodyless node-resolution transaction, persists/replays the battle exactly like ordinary Combat, applies the successful boss-completion reward event in that same transaction, grants participating-unit XP and permanent Mountains access, unlocks only Exit on victory, and presents the finalized boss battle/reward result through the existing BattleScene lifecycle.

Exit resolution and successful Farm termination remain Package 6.

#### Authority
Use current vNext source plus:
- `documentation/02-systems/combat-resolution.md`;
- `documentation/02-systems/unit-stat-advancement.md`;
- `documentation/07-development-path/vnext-reward-unlock-model.md`;
- `documentation/07-development-path/vnext-progression-state-model.md`;
- `documentation/07-development-path/vnext-backend-internal-architecture.md`;
- `documentation/07-development-path/vnext-phaser-client-architecture.md`.

Prototype behavior is evidence only. The retained Mudking content establishes the boss XP identity of **16 XP**. Do not revive prototype claim flow, random unit/die drops, item grants, SQL-authored reward catalogs, variable Teeth rewards, or `DeterministicRunNodeResolver`.

#### Accepted Farm boss completion event
Add canonical server-only:

`event.farm_boss_completed`

referencing:

`reward_definition.farm_boss_completed`

The reward definition has exactly two ordered deterministic entries:

1. `xp`
   - probability = 10000 basis points;
   - reward type = `unit_xp`;
   - target scope = `participating_units`;
   - amount = **16 XP per participating unit**.
2. `mountains`
   - probability = 10000 basis points;
   - reward type = `unlock`;
   - unlock = `unlock.region.mountains`.

No other Farm boss reward is accepted in Package 5.

In particular do not grant:
- Teeth;
- Raw Chaos;
- units;
- dice;
- items/Pig Ears/Crown Fragments;
- Codex entries;
- Shop/Tooth Collector/Wrong Machine unlocks.

Those systems are not part of this package.

Both entries still flow through Package 2 finalization. Production therefore consumes one cryptographic basis-point roll for each authored entry even though both probabilities are 100%.

#### Persist Boss event identity
Set the authored Farm Boss node's optional `event_id` to:

`event.farm_boss_completed`

The already-authored/persisted Boss `encounter_id` remains:

`encounter.the_farm_mud_boss_1`

Fresh run generation/persistence must therefore retain both durable identities on the Boss `run_nodes` row.

Update the Farm structural content contract accordingly:
- Combat -> `encounter.the_farm_mud_combat_1`;
- Loot -> `event.farm_loot_completed`;
- Rest -> no encounter/event;
- Boss -> `encounter.the_farm_mud_boss_1` + `event.farm_boss_completed`;
- Exit -> no event yet.

Encounter/event IDs remain private and absent from current-run/client content projection.

#### Shared endpoint and transaction boundary
Continue using only:

`POST /api/v1/runs/:runId/nodes/:nodeId/resolve`

with the existing auth, CSRF, canonical-ID, bodyless-request, and 8-128-character idempotency-key contract.

Register authoritative Boss support inside the existing `ResolveRunNodeCommand` transaction boundary.

Preserve the shared transaction ordering established by Package 3:
1. HTTP validation;
2. begin transaction;
3. lock `user_state`;
4. exact idempotency receipt lookup before lifecycle rejection;
5. lock owned run;
6. lock exact node;
7. lifecycle validation;
8. execute Boss handler;
9. complete node;
10. on victory unlock only directly outgoing persisted child nodes;
11. increment `player_revision` exactly once;
12. persist exact response receipt;
13. commit.

A Boss handler/service must not commit the parent transaction.

#### Shared combat execution
Boss combat uses the exact Package 4 authored encounter and Milestone 4 deterministic combat engine.

Do not fork a second combat implementation.

Prefer extracting/reusing a cohesive internal battle-resolution component if necessary so ordinary Combat and Boss share:
- persisted encounter identity validation;
- deterministic seed derivation;
- authoritative snapshot assembly;
- CombatEngine invocation;
- immutable battle persistence;
- terminal player HP persistence;
- battle response facts.

Alternative structure is acceptable if it avoids duplicating or diverging the combat algorithm/orchestration.

Ordinary `run_node_type.combat` behavior must remain unchanged.

#### Boss victory transaction
When the deterministic battle outcome is `victory`, inside the same parent node-resolution transaction:

1. persist the finalized boss battle and terminal run HP;
2. require valid persisted `event.farm_boss_completed`;
3. assemble the exact authoritative `RewardContext` from locked state:
   - wallet balances;
   - every run-participating unit's current persisted level/XP, including units ending the boss fight at 0 HP;
   - current owned unlock IDs;
4. invoke Package 2 reward application using:
   - persisted event ID;
   - source type `run_node`;
   - source ID `run_node:<nodeId>`;
5. apply exactly 16 XP to every participating unit through the canonical XP resolver;
6. apply the Mountains unlock, or finalize it as `already_owned` when already owned;
7. complete Boss;
8. unlock only its persisted direct child, Exit;
9. increment player revision once;
10. finalize the idempotency receipt and commit.

XP level-ups do **not** heal or proportionally adjust `run_unit_state.current_hp`. Terminal combat HP remains the run HP, even when the XP grant increases max HP.

The run remains `active`.

#### Boss defeat/stalemate
For `defeat` or `stalemate`:
- persist the finalized battle and terminal player HP according to existing Combat semantics;
- complete Boss;
- fail/end the run using the existing combat failure behavior;
- do not resolve `event.farm_boss_completed`;
- do not grant XP;
- do not grant Mountains;
- do not unlock Exit;
- increment revision once;
- persist the exact idempotency result.

No reward row should exist for a failed boss fight.

#### Reward finalization and rollback
The boss event is an additional exactly-once safety boundary, not a substitute for command idempotency.

A failure in any of these steps must roll back the complete Boss command:
- battle persistence;
- terminal HP;
- reward finalization;
- XP transitions;
- Mountains unlock;
- node completion;
- Exit availability;
- run lifecycle;
- revision;
- idempotency receipt.

A forced failure after reward application must leave no battle, no applied/finalized boss event, no XP change, no Mountains grant, no node/Exit change, and no revision/receipt change.

#### Idempotency
Continue operation identity:

`resolve_run_node`

with the current run/node fingerprint.

Same run/node/key replay must occur before ordinary completed-node rejection and return the exact original Boss response without:
- rerunning CombatEngine;
- creating another battle;
- rerolling either reward entry;
- applying XP twice;
- leveling twice;
- granting Mountains twice;
- unlocking Exit twice;
- changing HP/timestamps/revision.

A different key for an already-completed Boss returns the established already-resolved conflict.

If Mountains was already owned before the first Boss resolution, the finalized unlock result is `already_owned`; do not reroll, substitute, or invent another reward.

#### Player-safe Boss response
Extend the discriminated node-resolution contract with:

`resolution_type: "boss"`

The Boss response should contain the existing player-safe combat facts:
- battle ID/outcome/versions/ending round/tick;
- terminal player HP;
- node completion;
- newly available node IDs;
- run status/ended-at;
- player revision;

plus a narrow finalized reward projection.

For victory, expose exact player-visible progression facts without private roll/config data, for example:
- ordered XP transitions for every participating unit:
  - unit ID;
  - XP amount = 16;
  - level before;
  - XP before;
  - level after;
  - XP after;
- Mountains region unlock result:
  - region ID = `region.mountains`;
  - outcome = `granted` or `already_owned`.

For defeat/stalemate, the Boss response has no granted XP/unlock progression.

Do not expose:
- event ID;
- reward-definition ID;
- `unlock.region.mountains`;
- probability;
- roll;
- source identity;
- private reward config.

The exact field naming may follow current transport conventions, but TypeScript parsing must remain strict.

#### Phaser Boss action and retry semantics
An available Boss node becomes actionable in RunScene with a clear fight action.

Use the same bodyless resolve transport and generalized `RunNodeResolutionAttempt`.

For one Boss action:
- one run/node/idempotency-key identity;
- duplicate clicks suppressed;
- ambiguous network/5xx/malformed responses reuse the same key;
- semantically wrong resolution type/run ID/node ID is ambiguous and also retains the same key;
- a definitive successful Boss result establishes the retained battle marker and enters BattleScene.

Do not create a second retry system for bosses.

Completed Boss nodes with a persisted battle must support the same replay/reload-safe BattleScene behavior as completed ordinary Combat without issuing another resolve POST.

#### Battle presentation
Generalize the retained battle-presentation result type from Combat-only to battle-bearing Combat-or-Boss without changing the persisted marker identity unless necessary.

BattleScene continues to fetch authoritative persisted playback; it never simulates the Boss locally.

At battle completion, a Boss victory must present the finalized player-visible reward facts from the retained Boss resolution:
- 16 XP per participating unit with any resulting level changes;
- Mountains unlocked, or already owned.

Do not calculate XP/levels client-side.

A reload may lose the transient Boss reward card if only the persisted battle marker survives; that is acceptable. The battle, XP, unlock, and node state remain authoritative.

#### Return-to-run reconciliation
After BattleScene Continue, keep the existing authoritative reconciliation shape:
- perform current-run GET;
- do not resolve Boss again;
- reconcile persisted node/graph/HP from current-run authority;
- on Boss victory, RunScene shows Boss completed and Exit available;
- on Boss defeat/stalemate, current run is terminal/null and return to Camp follows the existing failed-combat behavior.

Generalize `GameStore.reconcileBattleReturn` so the retained battle relationship may be a completed Combat **or Boss** node with the expected persisted battle ID.

Do not fetch bootstrap/Warband merely to synchronize the run.

Package 5 does not need to make the same-runtime Camp/region-selection UI immediately reflect Mountains or reconcile every Warband level/XP cache after the boss. Package 6 owns terminal Exit plus authoritative Camp/RunScene/unlock reconciliation. The Boss result itself must nevertheless show the authoritative progression facts.

#### Tests
At minimum prove:

##### Content
- Boss node references both canonical boss encounter and completion event;
- event references the exact two-entry reward definition;
- XP entry is 100% participating-unit 16 XP;
- Mountains entry is 100% `unlock.region.mountains`;
- malformed/missing/wrong Boss event references fail validation;
- generated/persisted Boss retains both encounter and event IDs;
- event/reward/private identities remain absent from client projection/current-run.

##### Backend victory
Real MySQL integration:
- available Boss invokes deterministic combat exactly once;
- victory persists one immutable battle;
- exact terminal player HP persists;
- every participating unit receives exactly 16 XP, including a defeated participant;
- level rollover uses the canonical `100 × current level` curve;
- level-up does not heal/change terminal run HP;
- Mountains is granted exactly once;
- pre-owned Mountains finalizes `already_owned` without duplicate ownership;
- Boss completes and only Exit becomes available;
- run remains active;
- revision increments once;
- event is finalized/applied with exact private result facts;
- response exposes only the accepted safe reward projection.

##### Backend failure/idempotency
- defeat and stalemate persist battle/failure semantics but produce no boss event/XP/Mountains/Exit;
- same-key replay returns exact response, CombatEngine remains once, and rewards do not reroll/reapply;
- different key cannot resolve completed Boss again;
- malformed/missing persisted encounter/event fails atomically;
- forced failure after combat and after reward application rolls back the complete transaction.

##### Regression
- ordinary Combat behavior remains unchanged;
- Loot and Rest Package 3 behavior remains unchanged;
- battle playback ownership/reload/replay remains green.

##### Frontend
- available Boss shows Fight and sends one bodyless resolve POST;
- duplicate/ambiguous/semantic mismatch retry retains exact attempt identity;
- valid Boss result enters BattleScene and retains the correct battle marker;
- BattleScene playback uses existing server playback;
- Boss victory result displays exact XP/level transitions and Mountains outcome from the response;
- no client XP calculation or reward rolling;
- Continue performs current-run GET only and returns to Exit-available RunScene;
- completed Boss Replay does not resolve again;
- defeat/stalemate returns through existing terminal combat behavior;
- ordinary Combat BattleScene flow remains green.

#### Verification
Run applicable `agent/QUALITY_GATES.md` gates.

At minimum report:
- content validation/revision;
- focused boss event/reward content tests;
- focused Boss MySQL node-resolution/controller tests;
- Package 2 reward application regression;
- Milestone 4 Combat/playback regressions;
- Package 3 Loot/Rest regressions;
- focused RunScene/BattleScene/API/GameStore/contract tests;
- complete backend Docker suite;
- complete frontend suite;
- production frontend build;
- bundle check where applicable;
- deterministic captures for Boss available, Mudking playback/result, and Exit available after victory at Standard plus Compact/Wide where useful;
- `npm run llm:check`;
- `npm run docs:lint`;
- `git diff --check`.

If practical, extend a real-stack verifier through:

`Combat -> Loot -> Rest -> Boss Fight -> Battle Continue -> Exit available`

Do not claim unavailable CI or host-only gates passed.

#### Explicitly out of scope
Do not implement:
- Exit-node resolution;
- successful Farm termination;
- Camp return after successful Exit;
- general same-runtime Mountains region selection/reconciliation;
- Mountains run generation/gameplay;
- boss Teeth/Raw Chaos/item/unit/die/Codex rewards;
- Pig Ear/Crown Fragment inventory;
- Tooth Collector/Shop/Wrong Machine progression;
- claim/acknowledgement lifecycle;
- general visual overhaul;
- Milestone 6.

#### Completion requirements
Before architectural review:
1. implement only Package 5;
2. preserve one parent transaction and all existing Combat/Loot/Rest authority/idempotency behavior;
3. use the persisted authored Boss event + Package 2 reward pipeline rather than direct XP/unlock writes;
4. keep Exit unresolved and the run active after Boss victory;
5. run/report gates honestly;
6. leave Package 5 **In Progress**;
7. do not promote Package 6;
8. report:
   - exact implementation SHA;
   - shared combat/Boss handler structure;
   - persisted Boss event/encounter identity path;
   - exact reward definition and safe response projection;
   - XP/level/HP behavior;
   - Mountains unique-unlock behavior;
   - replay/rollback/idempotency evidence;
   - frontend battle/reward/reconciliation behavior;
   - exact verification results.
