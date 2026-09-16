# Active Execution Issue

## Milestone 4 - Combat

### Milestone 4 Package 3 - Battle persistence + playback boundary

**Status:** In Progress
**Priority:** High

#### Problem
Package 2 established the canonical first-Farm combat slice and an infrastructure-free deterministic combat kernel. The kernel consumes a strict normalized `CombatInput` and returns a versioned `CombatResult` containing terminal state plus ordered semantic playback events.

Before Package 4 can resolve a real run node transactionally, vNext needs a durable battle boundary that stores the exact finalized battle facts. A historical battle must remain reconnectable/explainable even if owned unit configuration or authored content changes later.

This package is persistence only. Do not resolve run nodes, run the engine from an application command, update run HP/node state, add HTTP routes, or implement Phaser playback.

#### Persistence decision
Extend the single fresh baseline `backend/migrations/vnext_baseline.sql`; do not create a migration chain or data backfill.

Add the smallest concrete battle persistence needed by the Package 2 model. Prefer a single `battles` table rather than introducing a speculative `battle_playback` table: Package 2 playback is currently a one-to-one immutable part of the versioned result payload, so splitting or duplicating it provides no current benefit.

A finalized battle record must durably retain:
- battle identity;
- owning run and exact run node relationship;
- the exact normalized kernel input snapshot used for the battle;
- a compact persistence/presentation participant manifest kept outside the kernel;
- the exact versioned result/playback payload returned by the kernel;
- ordinary persistence timestamp(s) outside the deterministic payload.

Do not add reward, XP, objective, progression, claim, event-sourcing, turn, tick, or per-playback-event tables.

#### Required relational invariants
The battle belongs to a concrete existing run node in the same run.

Use a same-run relational constraint, not two unrelated foreign keys that would permit a battle row to pair run A with a node from run B. `run_nodes` already exposes unique `(run_id,id)` for this purpose.

Enforce **at most one finalized battle per run node** with a real database uniqueness invariant. A completed combat node must never accumulate a second battle because of retries/reloads/concurrent requests. Package 4 will add application idempotency on top of this database safety net.

Deleting a run may cascade its battle history with the rest of run-owned state. Do not make battle deletion mutate or terminate a run.

A committed Package 3 battle is finalized; do not invent `pending`, `running`, `claimed`, playback-progress, or similar battle lifecycle states. Package 4 will execute combat synchronously inside the owning application transaction.

Do not duplicate `user_id` onto battles merely for ownership lookup; ownership is already authoritative through battle -> run -> user. Future read queries can join the run.

#### Suggested concrete battle shape
Exact column names may follow repository conventions, but the storage should stay close to:
- `id` BIGINT UNSIGNED primary key;
- `run_id` BIGINT UNSIGNED not null;
- `run_node_id` BIGINT UNSIGNED not null;
- `engine_version` positive small integer;
- `playback_version` positive small integer;
- `input_snapshot` JSON not null;
- `participant_manifest` JSON not null;
- `result_json` JSON not null;
- `created_at` timestamp.

The version columns deliberately duplicate the top-level result versions as small query/debug compatibility facts. Repository validation must reject a mismatch between the stored columns and the result payload rather than allowing them to drift.

Do not add a duplicated outcome column, encounter catalog FK, user FK, content catalog FK, reward state, or other convenience columns unless an actual current Package 3 test/use case demonstrates the need.

MySQL JSON validity is already enforced by the JSON type. Do not attempt to encode the complete Package 2 event schema as brittle SQL JSON path CHECK constraints. Structural validation belongs at the persistence boundary before insert/read.

#### Exact normalized input snapshot
Persist the exact deterministic kernel snapshot that Package 2 receives, including its seed and normalized combatants/abilities/dice/status facts. This is an immutable historical input, not a pointer to mutable current Warband/content configuration.

Do not reconstruct a stored battle input later from:
- current unit level/type;
- current ability loadout/order;
- current dice bindings/profile content;
- current authored enemy/encounter definitions;
- current `run_unit_state` HP after later nodes.

JSON storage may normalize textual formatting/key order. Tests should compare decoded structures/deep equality rather than raw serialized bytes.

Do not add database IDs to `CombatInput` merely for persistence. The combat kernel remains unaware of repositories/database identity.

#### Participant manifest
Package 2 intentionally uses stable combatant keys inside the pure kernel rather than database/display identity. Persistence therefore needs a small manifest that lets later packages relate those keys back to authoritative run participants and present the historical combatants without parsing meaning out of key strings.

Define a strict value/validation boundary for a participant manifest. It must contain exactly one entry for every combatant key in the stored input and no extras.

For this Milestone 4 slice, each manifest entry should contain only durable identity/presentation facts that are actually needed later:
- `combatant_key`;
- `side` (`player` or `enemy`);
- player owned `unit_id` for player entries and null for enemies;
- stable unit type identity (`unit_type_id` for players or `enemy_unit_type_id` for enemies), using a deliberately named field/model rather than an ambiguous numeric ID;
- display name captured for the historical battle;
- art key captured for the historical battle.

Do not duplicate position, stats, abilities, dice, HP, or statuses into the manifest; those are already in the immutable input/result snapshots. Do not include mutable rewards/progression.

Validation must prove:
- manifest keys are unique and exactly match input combatant keys;
- manifest side matches the corresponding input side;
- player entries have a positive owned unit ID and player type ID while enemy entries do not masquerade as owned units;
- type IDs use the expected stable namespaces;
- display/art identity is bounded/non-empty;
- duplicate player `unit_id` entries are rejected.

Package 4 will own loading/ownership verification when constructing this manifest from the real run. Package 3 validates and persists the manifest but does not query player/content state to build it.

#### Result/playback persistence
Persist the exact Package 2 `CombatResult::toArray()`-equivalent payload, including:
- `engine_version`;
- `playback_version`;
- outcome;
- ending round/tick;
- terminal combatants/statuses;
- ordered semantic playback events.

Do not split semantic events into rows or store a second duplicate copy of playback. Do not add timestamps/prose inside the deterministic result.

Add a strict persistence codec/value boundary that validates/hydrates stored battle data rather than handing arbitrary decoded JSON around. It may reuse Package 2 validation/value objects where appropriate, but must not make the combat engine depend on PDO/repositories.

At minimum, validate before insertion and when hydrating a row:
- supported positive engine/playback versions;
- stored version columns exactly match result versions;
- outcome is `victory`, `defeat`, or `stalemate`;
- result combatant keys exactly match input combatant keys and sides;
- terminal HP stays within `0..max_hp` from the normalized input;
- event sequence is contiguous from zero and each event has the expected version-1 event structure/facts;
- result ending round/tick and battle-end event are coherent;
- deterministic payload contains no wall-clock/reward fields prohibited by Package 2.

Do not rerun combat in order to validate persistence. The stored result is finalized evidence; persistence validation checks structure/coherence, not whether a second simulation happens to reproduce it.

#### Repository boundary
Add a focused battle repository for persistence primitives only.

Required capabilities are limited to what Packages 4-5 will concretely need, for example:
- insert one finalized battle for `(run_id, run_node_id)` and return its ID;
- fetch a finalized battle by battle ID;
- fetch the finalized battle for a run node.

The repository must not:
- begin/commit/rollback transactions;
- run combat;
- assemble ContentRegistry/Warband snapshots;
- update run HP or node status;
- decide authorization;
- issue rewards;
- own idempotency behavior.

Package 4's application command will own the transaction around repository insertion plus run/node/HP state mutation.

Treat finalized battle payloads as immutable through the repository API. Do not add update methods for input/result/playback.

#### Existing Warband invariant
The durable Warband already enforces one physical die instance bound to only one ability slot via `unit_ability_dice`. Package 3 does not assemble player snapshots, so do not duplicate that logic here.

However, persistence validation must not normalize away duplicate combat die identity if it appears in an externally supplied snapshot. Preserve exact input. Package 4, which loads the real DB relationships, must continue enforcing the existing ownership/binding invariant before the engine runs.

#### Current-run/bootstrap exposure
Do not expose battles, input snapshots, encounter rosters, seeds, result/playback, or participant manifests through the existing bootstrap/current-run responses in this package.

Package 5 owns battle read/reconnect API contracts and non-disclosure. Existing M1-M3 responses should remain unchanged except for unavoidable internal repository/type additions.

#### Tests / verification
Add focused persistence/unit/integration coverage proving at minimum:
- fresh MySQL 8 baseline provisions the battle table and no speculative battle/event/reward tables;
- registration/fresh player state has zero battles;
- battle row must reference a real run/node pair from the same run;
- one run node cannot have two finalized battles at the database level;
- distinct combat nodes may each have their own battle;
- deleting a run cascades its battles consistently with run-owned state;
- exact decoded normalized input round-trips through MySQL JSON;
- participant manifest round-trips and must exactly cover input combatants with correct sides/identity rules;
- duplicate/missing/extra manifest keys and duplicate player unit IDs are rejected;
- exact decoded Package 2 result/playback round-trips with ordering/sequence intact;
- stored engine/playback columns must match payload versions;
- malformed outcome, ending facts, terminal HP/key mismatch, event sequence/schema mismatch, or prohibited deterministic fields are rejected;
- repository insert/fetch does not mutate run nodes, run HP, player revision, Energy, rewards, or idempotency state;
- repository performs no transaction management of its own;
- no new HTTP routes/controllers or Phaser/client DTOs are added;
- existing M1-M3, Package 1, and Package 2 backend/content/combat tests remain green.

Use actual MySQL 8 for relational and JSON round-trip assertions. Do not represent absent GitHub CI or an unavailable host-only aggregate verification command as passed.

#### Documentation
Update the current vNext storage/development documentation only if needed so battle persistence matches the implementation. Document the deliberate single-row immutable snapshot/result approach and why a separate `battle_playback` table is not currently needed.

Do not restore legacy schema/combat docs. Git history remains recovery evidence.

#### Explicitly out of scope
Do not implement or scaffold:
- combat-node resolution application command/controller/route;
- CSRF/idempotency behavior for node resolution;
- invoking `CombatEngine` from a real run;
- loading real Warband dice/abilities into `CombatInput`;
- updates to `run_unit_state`, `run_nodes`, run lifecycle, or player revision after combat;
- battle read HTTP APIs or authorization contracts;
- Phaser `BattleScene`, playback timing, or client battle DTOs;
- rewards, Teeth, XP, objectives, progression, unit/dice grants, or loot;
- Mudking/boss combat, Farm completion, or Mountains unlock;
- Rest/loot/Chaos node resolution;
- `battle_playback`, per-event, event-sourcing, claim, tick, turn, or reward tables without a concrete current requirement;
- cleanup/rewrite of retained prototype combat source.

#### Completion/reporting
Leave this issue **In Progress** when implementation is ready for architectural review; do not promote Package 4 yourself.

Report:
- exact implementation commit SHA;
- exact battle schema and invariants;
- whether a separate playback table was avoided and why;
- participant-manifest structure and validation;
- input/result persistence codec/value boundaries;
- repository methods and confirmation that they do not own transactions;
- MySQL same-run/one-battle-per-node/cascade/JSON round-trip evidence;
- malformed persistence validation evidence;
- exact verification commands/pass counts and any environment-limited gates.
