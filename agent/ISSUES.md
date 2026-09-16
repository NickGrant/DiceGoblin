# Active Execution Issue

## Milestone 4 - Combat

### Milestone 4 Package 4 - Authoritative combat-node resolution + persisted run/battle state

**Status:** Open
**Priority:** High

#### Problem
Packages 1-3 now provide all lower-level prerequisites for real combat resolution:
- canonical level-derived player stats and authoritative `run_unit_state.current_hp`;
- canonical Farm enemy/encounter/ability content;
- a deterministic infrastructure-free combat kernel;
- one immutable finalized battle persistence record per run node.

What does not yet exist is the authoritative application mutation that takes one persisted available Farm combat node, assembles its complete player/enemy snapshot from server-owned state, executes combat exactly once, persists the finalized battle, applies player HP and node/run lifecycle changes transactionally, and survives retries without rerolling.

This package owns that mutation and backend HTTP contract. It does **not** implement battle read/reconnect APIs or Phaser playback; those remain Packages 5-7.

#### HTTP contract
Implement the accepted endpoint:

`POST /api/v1/runs/:runId/nodes/:nodeId/resolve`

For Package 4 it supports only ordinary combat nodes with a canonical `encounter_id`.

Requirements:
- authenticated session;
- CSRF protection;
- required `Idempotency-Key` using the same strict key rules as other retry-sensitive vNext commands;
- no request body for this first combat-node command; path identity is the complete player intent;
- positive canonical run/node IDs;
- thin controller delegating one complete application intention.

Do not add a separate battle resolve endpoint. Combat resolution belongs to node resolution.

Do not support loot, rest, boss, exit, Chaos, hazards, rewards, or other node behavior in this package. A node type not owned by this package returns a narrow unsupported/not-resolvable domain error and performs no mutation.

#### Ownership and non-disclosure
The authenticated user must own the run. The node must belong to that exact run.

Missing/foreign run IDs and missing/foreign node IDs must use the established non-disclosing not-found behavior rather than revealing whether another player's run/node exists.

Persisted cross-owner corruption or impossible relational/configuration state must fail as a non-disclosing integrity error and roll back.

Never accept squad ID, unit IDs, dice IDs, encounter IDs, seed, stats, HP, abilities, or enemy data from the request.

#### Eligibility
Before combat can execute, prove under the transaction that:
- the owned run exists and is `active`;
- the node belongs to that run;
- the node status is `available`;
- `node_type_id` is exactly the supported ordinary combat node type;
- the node has a valid canonical encounter reference;
- no finalized battle already exists for the run node unless the current request is an exact idempotent replay already finalized in `idempotency_requests`;
- the run still has its participating squad and authoritative run-unit participation state;
- participating Warband/content state is coherent enough to build a complete Package 2 `CombatInput`.

A different idempotency key against an already-resolved node must not execute or create another battle. Return a narrow already-resolved/conflict response.

Locked/unavailable nodes must not resolve merely because the client knows their IDs.

#### Transaction and lock order
This command owns one database transaction for the whole mutation.

Follow the established user-scoped mutation ordering to avoid races with other vNext commands. At minimum:
1. parse/validate path and idempotency key before mutation;
2. begin transaction;
3. lock the user's `user_state` first;
4. inspect the user-scoped idempotency receipt;
5. lock/load the owned run and target node;
6. validate eligibility and existing-battle state;
7. lock/load the participating squad, units, run HP, loadouts, bindings, and dice required to assemble one coherent combat snapshot;
8. resolve all authored facts through the already-validated `ContentRegistry` outside the kernel;
9. invoke the deterministic combat kernel once;
10. construct/validate the Package 3 participant manifest and finalized battle value;
11. insert the battle;
12. persist terminal player run HP;
13. resolve node/run lifecycle state;
14. increment `player_revision` exactly once;
15. finalize the idempotency receipt with the exact response;
16. commit.

Any failure rolls back all battle/node/run-unit/run/revision/receipt mutations together.

Do not make `BattlePersistenceRepository` own the transaction.

#### Idempotency
Use existing `idempotency_requests`; do not add another idempotency table.

Use a dedicated operation identity such as `resolve_run_node` and a normalized request hash derived from the canonical run/node path identity. The no-body request means headers, CSRF token, timestamps, or presentation data must not affect the request hash.

Required semantics:
- exact same user/key/operation/request after successful commit returns the stored finalized response;
- replay does not rerun combat, create another battle, rewrite HP/node timestamps, unlock nodes again, or increment revision;
- same user/key with another operation or run/node identity conflicts;
- another key after the node has already resolved does not reroll it;
- transaction failure leaves no finalized receipt, so a legitimate retry can attempt the command again from unchanged authoritative state.

Serializing user mutations through the locked `user_state` is the primary application race boundary; the Package 3 unique battle invariant remains the database backstop.

#### Player combat snapshot assembly
Create a focused server-side assembly boundary outside `CombatEngine`. Do not put PDO or `ContentRegistry` into the kernel.

The assembled player side must come only from the active run's persisted/locked state and current validated authored definitions.

For every participating `run_unit_state` row:
- require the unit to be an owned active unit and member of the run's participating squad;
- require the participating squad formation and run-unit set to agree exactly; do not silently add/drop units;
- resolve current `unit_type_id` and level through Package 1's canonical base-level stat resolver;
- use resolved max HP and the persisted run `current_hp`; reject current HP outside `0..resolved max HP` as integrity corruption;
- preserve all five stats only: HP, Attack, Defense, Precision, Resolve; no Speed;
- load the complete ordered equipped active-ability loadout with contiguous order;
- require each equipped ability to be unlocked, authored, active, supported by the current Package 2 combat kernel, and fully bound to its authored die-slot count;
- load exact owned active dice, sizes and profile IDs; normalize their current authored aspect effects through the existing combat content boundary;
- require profile/size eligibility;
- preserve the durable global invariant that one physical die is bound to only one slot across the Warband; additionally reject any duplicate die identity encountered in the assembled combat snapshot rather than relying on normalization to hide corruption;
- include applicable unlocked current-unit passive abilities supported by the Package 2 slice; do not silently ignore an applicable unsupported combat passive/configuration.

Do not persist derived stats/loadouts separately merely for this command: the exact normalized input will be persisted in `battles.input_snapshot`.

#### Formation mapping
Make the current nine-position squad mapping explicit and canonical for combat assembly.

Positions are row-major `0..8` across the 3x3 formation:
- `x = position % 3`;
- `y = intdiv(position, 3)`;
- x=2 is the front column and x=0 is the back column, matching the current combat/target rules.

Use a small shared server/domain helper/value rather than scattering this math through SQL/application code. Update the canonical Warband/formation documentation so this mapping is no longer implicit.

Player kernel combatant keys must be deterministic battle-local keys based on stable formation identity/order, not display names and not parsed database IDs. A form such as `player_p0` ... `player_p8` is appropriate. The Package 3 participant manifest is where each key maps to durable owned `unit_id`, current stable `unit_type_id`, historical display name, and historical art key.

#### Enemy snapshot and manifest
Use the persisted node's stable `encounter_id` and Package 2 `CombatSnapshotNormalizer`/ContentRegistry boundary to resolve the enemy side.

Do not trust or reconstruct hidden encounter facts from the client or generated metadata.

Enemy combatant keys/formation/stats/abilities/plain virtual d6s come from canonical server-only encounter/enemy content. Build manifest entries with the authored enemy type identity, display name, and art key.

The full encounter roster/mechanics remain server-private until observable through finalized battle playback/presentation contracts. The resolve response itself must not dump the hidden normalized input merely because it has been persisted.

#### Combat seed
Use one deterministic server-owned seed derived from durable battle identity facts, not wall-clock time, random UUIDs, or client data. The goal is that the same unchanged run/node attempt cannot produce a different combat merely because an application retry reached the engine before a previous attempt rolled back.

Use an explicit versioned derivation based on stable facts such as run ID, node ID, and encounter ID. Keep the derivation outside the combat kernel and document/test it. Persist the resulting exact seed only inside the normalized battle input.

Do not expose the seed in the node-resolution response.

#### Participant manifest
Construct the Package 3 manifest from the same locked/validated facts used for the snapshot.

For players capture:
- kernel combatant key;
- `side = player`;
- durable owned unit ID;
- current stable player `unit_type_id`;
- historical current display name;
- historical current unit-type art key.

For enemies capture:
- authored encounter combatant key;
- `side = enemy`;
- null owned unit ID/player unit type;
- stable `enemy_unit_type_id`;
- authored display name;
- authored art key.

Let `BattleParticipantManifest` revalidate one-to-one correspondence before persistence.

#### Running the kernel
Pass only the fully normalized `CombatInput` into the Package 2 engine.

Do not let the kernel:
- query SQL/content;
- mutate run state;
- issue rewards;
- decide authorization/idempotency;
- know HTTP identities.

The engine must be invoked once on the non-replay success path. Persist its exact `CombatResult::toArray()` result through Package 3.

If a tiny resolver interface/port around the existing Package 2 engine is useful for application-layer outcome/rollback tests, it may be introduced without changing combat semantics. Do not create a second combat implementation.

#### Persist player run HP
After the finalized result returns, update `run_unit_state.current_hp` for every and only player participant by using the Package 3 manifest key -> owned unit ID mapping and terminal combatant state.

Requirements:
- every run participant receives exactly one terminal player HP value;
- no enemy HP is written to `run_unit_state`;
- no unit outside the run is touched;
- values remain within the already-resolved max HP bounds;
- victory may leave some player units defeated at 0 HP; preserve that exact terminal HP;
- no healing/reset-to-max occurs after combat.

Do not write statuses to run storage in this package. Package 2 statuses are battle-scoped for the current slice; only HP currently survives between nodes.

#### Node and run lifecycle
A combat node is finalized/resolved once regardless of battle outcome.

On **victory**:
- set the combat node status to `completed`;
- set `completed_at` once;
- leave the run `active`;
- inspect persisted `run_edges` from the completed node and transition only directly outgoing nodes currently `locked` to `available`;
- do not unlock grandchildren or reconstruct graph topology from authored Farm knowledge;
- for the current Farm graph this makes Loot available while Rest/Boss/Exit remain locked.

On **defeat** or **stalemate**:
- set the combat node status to `completed` and `completed_at` once because its battle is finalized and cannot be rerolled;
- do not unlock outgoing nodes;
- transition the run from `active` to terminal `failed` and set `ended_at` once;
- preserve the complete graph, battle and run-unit HP history;
- Energy is unchanged and never refunded.

Do not mark a run successful/complete in this package. Farm boss/exit success remains Milestone 5.

The `failed` status is the concrete terminal loss state already anticipated by the accepted Energy/Warband models. Update current run/storage docs as needed so this lifecycle is explicit.

#### Player revision
A newly committed combat resolution changes durable player/run state and increments `player_revision` exactly once in the same transaction.

Exact idempotent replay does not increment it again. Rejections and rolled-back attempts do not increment it.

Do not materialize/regenerate Energy during combat resolution. Energy state and regeneration anchor remain untouched.

#### Response contract
Return a narrow authoritative mutation response suitable for Packages 5-7 without embedding private input or a giant playback payload.

At minimum return:
- battle reference/summary: battle ID, outcome, engine version, playback version, ending round/tick;
- resolved node ID/status/completed timestamp;
- direct node IDs newly made available, if victory;
- terminal player run-unit HP values keyed by owned unit ID;
- resulting run lifecycle status (`active` or `failed`) and terminal timestamp when applicable;
- resulting `player_revision`.

Do not return:
- normalized input snapshot or seed;
- hidden encounter roster/config as a pre-battle catalog;
- rewards/XP/currency;
- full bootstrap/profile/Warband state;
- regenerated run topology;
- full playback merely to avoid Package 5's read contract.

Store this exact response in the idempotency receipt so retry returns the same committed facts.

#### Errors
Use narrow stable error codes/messages consistent with current vNext conventions. Cover at least:
- malformed path/idempotency/body;
- unauthenticated/CSRF failure through existing middleware;
- non-disclosing run/node not found;
- inactive/terminal run;
- node locked/unavailable;
- unsupported node type / missing invalid encounter;
- already-resolved node under another attempt;
- invalid/incomplete participating combat configuration;
- persisted ownership/content/configuration integrity failure;
- idempotency conflict.

Do not leak whether a foreign unit/die/run/node exists through different error shapes.

#### Tests / verification
Add focused application/controller/integration coverage proving at minimum:
- authentication, CSRF and required idempotency key;
- strict no-body request contract and canonical positive run/node IDs;
- foreign/missing run/node non-disclosure;
- locked/unavailable and non-combat nodes cannot resolve;
- the first available Farm combat node resolves against its persisted encounter reference;
- player formation positions map exactly from squad positions `0..8` to `{x,y}` with x=2 front;
- player combatant keys are stable battle-local keys and the manifest maps them to the exact owned unit IDs/type/display/art identity;
- current persisted run HP becomes battle input `current_hp`; max HP/stats use the canonical level resolver;
- active loadout order, full exact dice slots/profile/aspects, supported passives and global die uniqueness are validated;
- incomplete/foreign/inactive/duplicate/configuration-corrupt ability/die/unit state rejects before combat and persists nothing;
- hidden enemy encounter data is assembled server-side and is not accepted from/returned to the client;
- deterministic seed derivation is stable for the same run/node/encounter and differs when durable identity differs;
- a successful command persists one exact finalized battle whose input/manifest/result agree with the command assembly;
- terminal player HP from the battle is written to the exact `run_unit_state` rows;
- victory completes the node and unlocks only direct outgoing locked nodes, preserving later locked Farm nodes;
- defeat completes the node, unlocks nothing, persists terminal HP/battle history, transitions run to `failed`, sets `ended_at`, and releases active-run configuration locks through the existing active-run semantics;
- stalemate follows the same terminal `failed` lifecycle without pretending victory;
- Energy value and regeneration anchor remain unchanged for victory/defeat/stalemate;
- one successful resolution increments `player_revision` exactly once;
- exact same-key replay returns the same battle ID/response without another engine execution, HP write, timestamp change, unlock, battle row, or revision increment;
- same key with another run/node conflicts;
- another key after finalized resolution cannot reroll/create another battle;
- forced failure after engine execution but before commit rolls back battle, HP, node/run, revision and idempotency receipt together;
- current-run read after victory reflects completed Combat, available Loot, unchanged later locked nodes, and authoritative post-battle HP without regeneration;
- current-run read after terminal failure returns no active run under the existing query semantics while the persisted failed run/battle/history remains queryable internally for Package 5;
- no rewards/XP/objectives/Teeth/Raw Chaos are granted or changed;
- no new battle/read/Phaser implementation leaks into this package;
- existing M1-M3 and Packages 1-3 backend/content/combat/persistence tests remain green.

Use actual MySQL 8 for the real transaction/idempotency/run-state tests. Include deterministic application tests for victory, defeat, and stalemate; a small injected combat-resolver port is acceptable for transaction outcome tests if necessary, while at least one real Farm integration path must invoke the actual Package 2 `CombatEngine`.

Run the repository's supported backend/content/MySQL gates. Do not claim absent GitHub CI or an unavailable host-only aggregate passed.

#### Documentation
Update current canonical docs only where this package establishes new concrete behavior:
- `documentation/02-systems/warband-and-formation.md`: explicit row-major 0..8 -> x/y combat mapping and front/back column meaning;
- `documentation/02-systems/run-node-generation.md` or a more appropriate current run lifecycle doc: victory direct-child unlock and finalized combat-node behavior;
- `documentation/07-development-path/vnext-storage-model.md`: concrete terminal `failed` run lifecycle if needed;
- `documentation/07-development-path/vnext-endpoint-inventory.md`: narrow Package 4 combat-node request/response/idempotency behavior if the accepted inventory needs clarification.

Do not restore legacy/prototype docs.

#### Explicitly out of scope
Do not implement or scaffold:
- `GET /api/v1/battles/:battleId/playback` or other battle read/reconnect endpoints;
- bootstrap/current-run battle playback expansion beyond existing run-state consequences;
- Phaser `BattleScene` or client battle DTO/playback;
- client node-resolution button/navigation;
- rewards, Teeth, Raw Chaos, XP, objectives, progression, unit/dice grants, loot, or reward claims;
- Mudking/boss combat;
- Farm successful completion/Exit or Mountains unlock;
- Rest/Loot/Chaos/hazard node resolution;
- persisted statuses/run modifiers;
- battle update/pending/claim/progress lifecycle;
- additional battle/event/playback/reward tables;
- cleanup/rewrite of retained prototype combat source.

#### Completion/reporting
Leave this issue **In Progress** when implementation is ready for architectural review; do not promote Package 5 yourself.

Report:
- exact implementation commit SHA;
- HTTP request/response/error contract;
- application command and transaction/lock order;
- idempotency operation/hash/replay semantics;
- player formation mapping and combatant key scheme;
- exact authoritative player snapshot assembly rules;
- enemy/encounter assembly and exposure boundary;
- deterministic seed derivation;
- participant manifest construction;
- victory/defeat/stalemate node/run lifecycle;
- HP/revision/Energy behavior;
- persisted battle evidence;
- rollback/concurrency/retry evidence;
- exact MySQL/application/controller regression commands and results;
- any environment-limited gates or unresolved concern.
