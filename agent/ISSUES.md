# Active Execution Issue

`ISSUES.md` contains only the current execution-ready package. Future packages stay summarized in `agent/MILESTONES.md` and the roadmap until promoted. Do not implement them early.

## Milestone 2 - Warband

### Establish unit rename and atomic loadout/dice-binding commands

**Status:** In Progress
**Priority:** High

#### Problem
Owned-unit reads, canonical abilities/dice, saved squads, squad commands, and active-squad bootstrap are now approved. The remaining server-side Warband configuration boundary is the individual unit: players need an authoritative rename command and one atomic replacement command for ordered active abilities plus every exact die binding. Implement those commands without reviving prototype per-slot mutations, profile refreshes, speed-budget rules, promotion logic, run persistence, or Phaser UI.

#### Required Context
- `documentation/07-development-path/vnext-api-contract-model.md` — command authority, transaction/revision rules, authoritative mutation responses
- `documentation/07-development-path/vnext-endpoint-inventory.md` — `PATCH /units/:unitId/name` and complete `PUT /units/:unitId/loadout`
- `documentation/07-development-path/vnext-backend-internal-architecture.md` — thin controllers, application transaction ownership, persistence-only repositories
- `documentation/07-development-path/vnext-storage-model.md` — `unit_instances`, `unit_abilities`, `unit_ability_loadout`, `unit_ability_dice`, `dice_instances`
- `documentation/07-development-path/vnext-authored-content-model.md` — ContentRegistry/stable-ID authority
- `documentation/02-systems/ability-loadouts-and-dice-binding.md` — ordered active loadout and exact physical die-slot binding
- `documentation/02-systems/dice-profiles-and-aspects.md` — profile/size eligibility
- `documentation/07-development-path/vnext-prototype-code-disposition.md` — mine prototype behavior deliberately; do not restore prototype architecture
- Approved Package 3 unit/dice queries and controlled fixture; approved Package 4 transaction/revision patterns

Prototype `UnitLoadoutService` is evidence that one exact owned die cannot be assigned to multiple ability slots. Preserve that durable behavior through the vNext application contract, but do not recover its obsolete speed-budget, SQL catalog, read-side initialization/backfill, or per-slot mutation architecture.

#### Accepted Command Surface
- `PATCH /api/v1/units/:unitId/name` renames one owned active unit.
- `PUT /api/v1/units/:unitId/loadout` atomically replaces the complete player-controlled active ability order and every exact die-slot binding for one owned active unit.
- Both require authenticated session + normal CSRF protection.
- Missing and non-owned unit IDs share a non-disclosing `unit_not_found` behavior.
- Successful mutations return authoritative current unit detail plus the resulting `player_revision`; no global profile refresh is performed.

#### Rename Contract
- Request body contains exactly `{ "name": <string> }`.
- Trim surrounding whitespace before persistence.
- Name must contain 1-128 Unicode characters after trimming, matching the current persistence limit.
- Reject malformed body, extra fields, blank names, and overlong names before mutation.
- Renaming to the already-persisted normalized name is a successful no-op: return authoritative detail/current revision and do not increment `player_revision`.
- A real rename increments `player_revision` exactly once in the same transaction.

#### Complete Loadout Request
Use one explicit whole-aggregate request shape:

```text
{
  "abilities": [
    {
      "ability_id": "ability.some_active",
      "dice_instance_ids": ["123", "456"]
    }
  ]
}
```

Rules:
- Request contains exactly `abilities`.
- `abilities` is an ordered non-empty list. Array order is the committed `equip_order`, starting at 0 with no gaps.
- Each entry contains exactly `ability_id` and `dice_instance_ids`.
- `ability_id` uses the canonical stable-ID string contract and may appear at most once.
- Only abilities durably owned by this unit in `unit_abilities` may be equipped.
- Equipped abilities must resolve through ContentRegistry and be authored `active` abilities. Passive abilities remain durable ownership but are not scheduled loadout actions.
- `dice_instance_ids` is an ordered list whose length must equal the authored `dice_slot_count` for that ability. Array index is `slot_index`.
- Every die ID is a canonical positive instance-ID string.
- Every requested die must be an active die owned by the authenticated player.
- Every requested die's persisted size/profile combination must be valid against current canonical authored content.
- The same exact die instance may appear at most once across the complete submitted loadout.
- One exact die instance is globally bound to at most one ability slot across the player's Warband. A die currently bound to another unit cannot be taken implicitly by configuring this unit; reject the request rather than mutating another unit behind the player's back.
- A die already bound to this unit may move freely to another slot as part of the same complete replacement.
- Do not add the prototype 20-point speed budget or any `Speed` stat/rule. Current vNext scheduling/timing is authored elsewhere and is not an equip-budget constraint.
- Do not infer/grant `unit_abilities` from the current unit type on mutation. Durable per-instance ability ownership is authoritative.
- Do not create missing ability ownership or missing bindings as read-side/backfill behavior.

#### Persistence Invariant for Exact Dice
Package 1 deliberately deferred duplicate-use restrictions until the complete validation package. This package resolves that rule: one `dice_instance_id` may be durably bound to only one `unit_ability_dice` row globally.

Add the appropriate fresh-baseline database uniqueness constraint/index for `unit_ability_dice.dice_instance_id` in addition to application validation. Keep the application validation so ownership/domain errors remain controlled and understandable before SQL constraint failure.

Update fresh-baseline tests accordingly.

#### Atomic Replacement
- The loadout command owns one transaction.
- Lock player state for revision serialization, the target owned active unit, its current configuration, and the relevant owned dice/binding state needed to prevent races.
- Validate the complete proposed configuration before destructive replacement.
- Once accepted, replace both `unit_ability_loadout` and `unit_ability_dice` as one atomic unit.
- Never expose a transient half-valid state.
- A validation failure or persistence failure rolls back everything and does not increment `player_revision`.
- A request identical to the committed ordered abilities and exact bindings is a successful no-op and does not increment `player_revision`.
- A real committed configuration change increments `player_revision` exactly once.

#### Existing Corruption
Reads already treat invalid authored IDs, foreign dice, terminal assets, and invalid slot/binding relationships as integrity failures. Mutations must not silently repair unrelated corruption.

Before rename/loadout mutation, validate enough current authoritative unit state to ensure the command is not operating on corrupt persisted configuration. If current state is corrupt, return the narrow Warband integrity failure and perform no write/revision change.

Do not make GETs repair state.

#### Future Run Lock Boundary
Do not implement active-run locking yet because run persistence does not exist.

Structure rename/loadout application commands so Milestone 3 can insert an authoritative configuration-lock policy before mutation without changing the endpoint contracts or moving domain rules into controllers/repositories.

Do not add fake run tables or placeholder `hasActiveRun=false` services.

#### Dice Read Consistency
`GET /api/v1/dice` exposes binding summaries. After an accepted loadout replacement, its authoritative read result must reflect the new exact bindings. Do not add an independent `equipped` flag.

The loadout mutation response need not duplicate the entire dice collection; full returned unit detail plus `player_revision` is the accepted command result. Later Phaser cache code can reconcile/refresh the lazy dice domain without a global profile/bootstrap refresh.

#### Response Shape
Successful rename and loadout responses should use the same full mutable unit-detail semantics already established by `GET /api/v1/units/:unitId`, plus current `player_revision`.

Prefer:

```text
{
  "unit": <authoritative unit detail>,
  "player_revision": <integer>
}
```

Reuse the existing unit-detail assembler/query behavior rather than creating a second incompatible response model.

#### Error/Ownership Behavior
- Unauthenticated -> existing unauthorized behavior.
- Invalid CSRF -> existing mutation CSRF behavior.
- Missing/non-owned/terminal unit -> non-disclosing `unit_not_found`.
- Structurally or semantically invalid rename/loadout -> narrow unit-configuration validation error.
- Persisted corruption -> `warband_data_integrity_error`.
- Do not expose another player's unit/die IDs, names, SQL, or exception text.
- A foreign/missing/terminal requested die should be described generically as unavailable configuration, not distinguished in a way that leaks ownership/existence.

#### Tests
Add focused real-MySQL integration coverage proving at minimum:
- authentication and CSRF for both mutations;
- rename validation: exact body, trimming, Unicode length, blank/overlong/extra fields;
- successful rename and exactly-one revision increment;
- same-name rename is a no-op with no revision increment;
- missing/foreign/terminal unit is non-disclosing;
- valid complete loadout replacement preserves ordered active abilities and exact slots;
- loadout order is persisted contiguously from 0;
- owned passive ability cannot be equipped as an active action;
- unowned ability cannot be equipped;
- missing/wrong-type authored ability fails safely;
- slot list length must exactly match `dice_slot_count`;
- duplicate ability IDs rejected;
- duplicate die ID anywhere in submitted loadout rejected;
- missing/foreign/terminal die rejected without disclosure;
- die size/profile authored incompatibility rejected;
- die already bound to another unit cannot be stolen implicitly;
- a die may move between slots within the configured unit in one atomic replacement;
- database uniqueness prevents one die from occupying multiple persisted binding rows;
- failed replacement preserves prior loadout/bindings and revision;
- identical complete loadout is a no-op for revision;
- successful changed loadout increments revision exactly once;
- `GET /units/:unitId` reflects the accepted result;
- `GET /dice` binding summaries reflect the accepted result;
- current persisted corruption blocks mutation without repair;
- Package 3 fixture remains valid under the new global die uniqueness invariant;
- Package 4 squad/bootstrap tests remain green.

Use the repository-supported MySQL/Docker path; do not satisfy mutation behavior only with mocks.

#### Explicitly Out of Scope
- Promotion options or promotion transactions — Milestone 8.
- Ability granting/unlocking mutations.
- Phaser Warband navigation/read UI — Package 6.
- Phaser squad editor — Package 7.
- Phaser unit detail/loadout UI — Package 8.
- Production starter onboarding, Shop, Academy, Wrong Machine, run persistence/locks, combat, rewards, or Milestone 3.
- Final visual/UI overhaul.

#### Completion
Run applicable context/docs checks, focused unit-configuration integration tests, fresh-baseline/MySQL verification, Docker backend suite, Package 3 read/fixture regressions, Package 4 squad/bootstrap regressions, and content validation because loadout validation depends on canonical ability/profile metadata. Exercise real HTTP mutations against fixture-populated MySQL state where practical.

Leave this package **In Progress** for architectural review. Do not promote or begin Package 6 in the same coding-agent change.
