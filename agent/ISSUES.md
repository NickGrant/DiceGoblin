# Active Execution Issue

## Milestone 9 - Kin and Wrong Machine

### Milestone 9 Package 2 - Idempotent reconstruction transaction + first-restoration/repeat semantics

**Status:** In Progress
**Priority:** High

#### Accepted baseline

Milestone 9 Package 1 is approved through implementation `2126bb76f65b753bef227f68c048de1738808bf7` plus focused production-drop correction `79e38a5a41d25fd36a97ea630ee2379d0e2f6049`.

Package 1 established:
- canonical Pig and Lizard Kin definitions;
- canonical reconstruction recipes for both families;
- permanent Kin ownership through the existing unlock model;
- permanent Wrong Machine access as a recipe prerequisite;
- authoritative `GET /api/v1/wrong-machine` read semantics;
- generic authored Mountains/Farm reconstruction-material victory drops;
- shared first-restoration `random_unlocked` and repeat `chosen_unlocked` selection semantics.

Preserve all accepted Milestone 7/8 inventory, economy, permanent progression, unit ownership, idempotency, transaction, and `player_revision` behavior.

#### Purpose

Add the one authoritative Wrong Machine mutation that consumes the authored recipe requirements and creates exactly one Kin unit, while making first restoration and repeat reconstruction atomic, retry-safe, ownership-safe, and server authoritative.

Do not begin the Phaser Wrong Machine surface in this package.

#### Mutation contract

Add a dedicated authenticated Wrong Machine reconstruction mutation under the existing `/api/v1/wrong-machine` domain.

The request must identify:
- the reconstruction recipe;
- the expected authoritative mode/cost state needed to reject stale client intent safely;
- a chosen unit type only when the current authoritative mode is repeat reconstruction;
- the shared idempotency key using the existing mutation convention.

The server, not the client, determines the current mode from Kin unlock ownership inside the transaction.

A stale request must not silently reinterpret first-restoration intent as repeat reconstruction, or vice versa. Return a conflict that tells the caller to refresh rather than spending resources under different semantics.

Do not accept arbitrary Kin IDs, prices, ingredient lists, or output stats from the client. Those come from canonical authored content.

#### First restoration

When the Kin unlock is not yet owned and all prerequisites/resources are satisfied:
- resolve the recipe's `first_restoration` mode;
- require permanent Wrong Machine access and all authored prerequisites;
- debit exactly the authored Raw Chaos amount;
- consume exactly the authored item quantities;
- choose one unit type from the player's currently unlocked/eligible unit types using a server-owned deterministic/retry-safe random decision;
- create exactly one active level-1 unit with the recipe's Kin through the existing shared `NormalUnitCreationService`/normal unit-ownership boundary;
- grant the recipe's Kin unlock exactly once;
- increment `player_revision` exactly once for the complete committed intention;
- return a finalized authoritative receipt containing the created unit, spend/consumption results, Kin restoration result, and resulting revision.

The first-restoration random choice must be frozen by the command's idempotency boundary. Retrying the same finalized request must return the same unit/output without another roll, spend, unlock grant, or unit creation.

Do not create a separate first-restoration history/boolean table. Kin unlock ownership remains the durable restoration truth.

#### Repeat reconstruction

When the Kin unlock is already owned:
- resolve the recipe's `repeat_reconstruction` mode;
- require the caller to provide one chosen unit type;
- reject missing, malformed, locked, nonexistent, or otherwise ineligible unit types;
- debit/consume the authored repeat requirements exactly once;
- create exactly one unit with the selected unit type and recipe Kin through the same shared unit-creation boundary;
- do not replay or duplicate the Kin unlock grant;
- increment `player_revision` exactly once;
- finalize and replay the same authoritative receipt through idempotency.

Repeat reconstruction contains no gameplay randomness after the player selects the unit type.

#### Transaction and concurrency invariants

The reconstruction command owns one database transaction for the full player intention.

Inside that transaction, lock/re-read the authoritative state needed to prevent races across:
- Raw Chaos balance;
- ingredient quantities;
- permanent prerequisite/Kin unlock ownership;
- player revision;
- idempotency identity.

All of the following must commit together or none of them may persist:
- Raw Chaos debit;
- ingredient decrements;
- Kin unlock grant when applicable;
- unit creation;
- player revision increment;
- finalized idempotency receipt.

Use the existing idempotency infrastructure and conventions rather than introducing a Wrong-Machine-specific retry table.

A reused idempotency key with a different canonical request must conflict without mutation.

Failures before commit—including injected failure after resource debit, after unlock work, or after unit creation—must roll back all reconstruction effects.

#### Resource and ownership rules

Reject without mutation when:
- recipe does not exist;
- Wrong Machine access or another authored prerequisite is missing;
- authoritative mode differs from the client's expected mode;
- Raw Chaos is insufficient;
- any ingredient is insufficient;
- repeat mode has no chosen unit type;
- selected unit type is not currently eligible;
- player state/revision/resource data is incoherent;
- referenced authored output data is invalid.

Do not mirror wallet or inventory state in Wrong Machine persistence.

Do not allow resources or unlocks belonging to another player to satisfy reconstruction.

If reconstruction while an active run exists would violate an existing unit-ownership/run invariant, preserve that invariant explicitly and test the accepted behavior rather than bypassing the shared safety model.

#### API/read reconciliation

After a successful mutation, the receipt must provide enough authoritative state for Package 3 to reconcile without guessing. At minimum include:
- recipe id;
- resolved mode;
- created unit identity including unit type and Kin;
- Raw Chaos before/after spend;
- consumed item quantities and authoritative owned-after amounts;
- Kin unlock/restoration outcome;
- resulting `player_revision`.

The existing Wrong Machine read query must reflect the new state immediately after reconstruction:
- first restoration becomes repeat mode;
- owned balances/ingredients are reduced;
- `kin_restored` becomes true after first restoration;
- reconstructability is re-derived from current authoritative resources.

Do not add a client-side or persisted `reconstructable` authority.

#### Pig/Lizard genericity

Both `reconstruction_recipe.reconstruct_pig_kin` and `reconstruction_recipe.reconstruct_lizard_kin` must execute through the same command/domain path.

No Pig-specific or Lizard-specific controller/service branch is acceptable for spending, unit creation, restoration, or repeat behavior.

Do not add Frog Kin or Milestone 10 work.

#### Verification

Run focused tests and the applicable gates from `agent/QUALITY_GATES.md`.

At minimum prove:
- successful Pig first restoration;
- successful Lizard first restoration;
- first restoration chooses only currently eligible unit types and creates exactly one unit of the requested recipe Kin;
- Kin unlock ownership is granted once and changes the read model to repeat mode;
- successful Pig and Lizard repeat reconstruction with explicit eligible unit type;
- repeat output is deterministic from the request and does not replay first-restoration effects;
- exact Raw Chaos and ingredient debit/owned-after values;
- insufficient currency and each insufficient ingredient path are non-mutating;
- missing Wrong Machine prerequisite is non-mutating;
- stale expected mode is non-mutating;
- invalid/locked repeat unit type is non-mutating;
- cross-player isolation;
- same-key replay returns the exact finalized result without duplicate spend/unit/unlock;
- same key with different canonical request conflicts;
- forced failures at meaningful points roll back wallet, inventory, unlock, unit creation, revision, and idempotency result;
- `player_revision` advances exactly once on success and not on rejected/rolled-back requests;
- Package 1 Wrong Machine reads and authored drop behavior remain green;
- clean MySQL provision/reset and full supported backend/content/docs gates pass.

#### Completion evidence

Report:
- implementation SHA;
- mutation endpoint/request/response shape;
- transaction and idempotency strategy;
- how first-restoration random unit-type choice is made retry-safe;
- proof that shared `NormalUnitCreationService`/unit ownership is reused;
- focused and full verification counts;
- confirmation Pig and Lizard use one generic mutation path;
- confirmation no Phaser Wrong Machine surface or Milestone 10 work was introduced.

Leave Package 2 **In Progress** for architectural review. Do not promote Package 3 yourself.
