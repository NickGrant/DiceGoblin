# Active Execution Issue

## Milestone 8 - Permanent Progression

### Milestone 8 Package 4 - Promotion transaction + durable ability/history updates + active-run safety

**Status:** In Progress
**Priority:** High

#### Problem

Commit authored single-unit promotions as one retry-safe Raw Chaos transaction while preserving the unit's durable progression and configuration.

#### Accepted baseline

Milestone 8 Package 3 - Authored unit-promotion graph + progression read contracts is approved at `58cf57732790d91784ecfba080bd136006586f44`.

Package 3 established:
- the canonical 20-edge Goblin promotion graph;
- single-unit promotion with no sacrifices;
- tier-1 -> tier-2 level 3 / 5 Raw Chaos eligibility;
- tier-2 -> tier-3 level 6 / 10 Raw Chaos eligibility;
- persistent unit level/XP semantics;
- promotion-history validation;
- shared active-run participation locking;
- `xp_to_next_level`;
- `GET /api/v1/units/:unitId/promotion-options`;
- browser-safe projected promotion identities and strict frontend reconciliation.

The user confirmed Package 3 verification passed.

Package 4 performs the mutation only. Do not build Phaser Academy/promotion UI yet.

#### Purpose

Implement one authoritative, idempotent unit-promotion transaction that:

- spends Raw Chaos exactly once;
- preserves the same unit identity, display name, kin, level, and normalized XP;
- changes the current unit type to the authored target;
- appends exactly one durable promotion-history row;
- permanently grants only target abilities the unit does not already own;
- preserves all previously owned abilities;
- preserves the existing valid loadout and dice bindings;
- rejects promotion of a unit participating in an active run;
- increments `player_revision` exactly once;
- returns enough authoritative state for Package 5 reconciliation without a global profile refresh.

Do not retire or consume any other unit.

#### Endpoint and request

Implement:

```text
POST /api/v1/units/:unitId/promote
```

Requirements:
- authenticated;
- CSRF;
- valid `Idempotency-Key`;
- canonical positive owned unit path ID;
- exact JSON body:

{
  "promotion_id": "unit_promotion....",
  "expected_price": {
    "currency_id": "raw_chaos",
    "amount": <positive client-safe integer>
  }
}
```

No additional fields.

The semantic idempotency identity is:

```text
unit_id + promotion_id + expected Raw Chaos price
```

The same key reused for a different unit, promotion ID, or expected price conflicts.

#### Transaction and lock ordering

Use one caller-owned transaction.

Required sequence:

1. parse path ID/request/idempotency key and canonicalize the semantic request;
2. begin transaction;
3. lock/read `user_state` first;
4. look up the idempotency receipt;
5. if a receipt exists:
   - validate operation type/request hash;
   - validate the stored result structurally against the canonical request;
   - commit the read transaction;
   - return the stored result;
6. validate Raw Chaos/revision client-safe state;
7. lock/read the owned active unit;
8. missing, foreign, or terminal unit -> normal non-disclosing `unit_not_found`;
9. validate current unit type, level/XP normalization, owned abilities, loadout/bindings, and persisted promotion history through the accepted shared boundaries;
10. resolve the current authored promotion definition;
11. require its `from_unit_type_id` to equal the unit's current type;
12. require current level >= authored `required_level`;
13. revalidate active-run participation through `ActiveRunConfigurationPolicy`;
14. require current authored price to equal `expected_price`;
15. require sufficient Raw Chaos;
16. calculate target ability delta using `UnitPromotionPolicy`;
17. debit Raw Chaos;
18. mutate the same unit instance to the target unit type, preserving display name, kin, level, XP, lifecycle, loadout, and dice bindings;
19. append exactly one promotion-history row;
20. insert exactly the missing target abilities into permanent `unit_abilities`;
21. re-read/validate the resulting authoritative Unit Detail inside the transaction;
22. increment `player_revision` exactly once;
23. finalize/read-back the idempotency receipt;
24. optional injected pre-commit test hook;
25. commit.

The `user_state FOR UPDATE` lock must remain first. Run start already takes that same lock before creating an active run, so promotion and run start serialize for one user. Do not introduce a second ad-hoc cross-command lock.

Receipt replay must occur before current unit/content/ownership checks after the player-state lock. An exact retry of a committed promotion must remain replayable even though:
- the unit is now a different type;
- the history row exists;
- target abilities are now owned;
- current authored promotion content/price changes in a later release.

#### Active-run safety

Only the exact participating unit is locked.

- If the unit appears in the current active run's participation set, reject with the existing configuration-locked semantic error and mutate nothing.
- If another unit participates in an active run but this unit does not, promotion is allowed.
- Malformed active-run participation remains an integrity failure.
- Do not inspect only active squad membership; participation is the authority.

Because promotion changes permanent combat stats/type/abilities, it must never mutate a participating unit while the run is active.

#### Promotion mutation semantics

On success:

**Identity**
- unit instance ID unchanged;
- display name unchanged;
- kin unchanged;
- lifecycle remains `active`.

**Progression**
- level unchanged;
- normalized XP unchanged;
- `xp_to_next_level` therefore remains the current level threshold.

**Type/history**
- current `unit_type_id` becomes exactly the selected authored target;
- append one `unit_promotions` row:
  - same `unit_id`;
  - exact prior type;
  - exact target type;
  - authoritative DB timestamp;
- do not rewrite earlier history.

**Permanent abilities**
- all existing `unit_abilities` rows remain;
- insert only `new_ability_ids` calculated from the Package 3 policy;
- zero new abilities is valid;
- duplicate ability ownership must not be created.

**Loadout/dice**
- do not clear, reorder, replace, or auto-equip anything;
- existing loadout remains valid because previously owned abilities remain owned;
- existing physical die bindings remain unchanged;
- newly granted active abilities begin unequipped.

No automatic heal, Energy change, squad change, Shop/Academy change, or run-state change occurs.

#### Repository boundaries

Extend the vNext repository narrowly rather than using prototype `PromotionService`.

Needed atomic operations may include:
- compare-and-update current unit type for one owned active unit;
- append promotion history;
- insert missing permanent abilities.

All promotion writes require a caller-owned transaction.

The type update should guard the expected current type so stale state cannot silently promote from another type.

Do not add:
- promotion ownership tables;
- unit tier columns;
- class-level XP;
- consumed-unit JSON;
- capstone tables/state.

#### Error semantics

Use stable command errors:

- malformed path/body -> existing unit/config validation style, 422 where applicable;
- invalid/missing idempotency key -> existing idempotency error;
- missing/foreign/terminal unit -> `unit_not_found`;
- unknown promotion ID -> `unit_promotion_not_found`;
- promotion not authored from current unit type -> `unit_promotion_unavailable`;
- level below requirement -> `unit_promotion_level_required`;
- participating active-run unit -> existing `unit_configuration_locked`;
- current price differs from expected -> `unit_promotion_changed`;
- insufficient Raw Chaos -> `insufficient_raw_chaos`;
- same key/different semantic request -> existing idempotency conflict;
- corrupted persisted/content state -> integrity/server failure.

Rejected commands change nothing: no Raw Chaos, unit type, history, ability ownership, loadout, dice binding, revision, or receipt.

#### Exact success response

Return exactly:

```text
promotion
  promotion_id
  from_unit_type_id
  to_unit_type_id
  granted_ability_ids[]
spend
  currency_id = raw_chaos
  amount
  balance_before
  balance_after
unit
  <full authoritative Unit Detail shape>
player_revision
```

Rules:
- promotion identity must match the submitted promotion;
- from/to match the committed durable history row;
- `granted_ability_ids` is exactly the ordered ability delta actually inserted;
- Raw Chaos arithmetic is exact/client-safe;
- `unit.id` equals path unit ID;
- returned Unit Detail has target current type, unchanged level/XP, complete promotion history, permanent abilities, unchanged loadout and bindings;
- returned `player_revision` is the one resulting revision.

Persisted receipt replay validation must be request-bound but must **not** resolve current authored promotion content.

#### Idempotency behavior

Prove:

- exact retry returns byte/structure-equivalent finalized result;
- exact retry does not spend again;
- exact retry does not append history again;
- exact retry does not insert abilities again;
- exact retry does not increment revision again;
- exact retry does not need current unit type to still equal the original `from`;
- exact retry survives current promotion-definition price/content changes;
- same key + different unit/promotion/price conflicts;
- a new idempotency key after the unit already promoted does not perform another copy of the same edge; it rejects based on current type/path.

#### Converging branch behavior

Explicitly prove at least these cases:

**Enforcer -> Juggernaut**
- current Enforcer-owned abilities are retained;
- Juggernaut target currently adds no new ability IDs if all its authored abilities are already owned;
- zero grant delta is valid.

**Pit Fighter -> Juggernaut**
- Desperate Swing and Counterpunch remain permanently owned;
- target grants Skullcrack and Menacing Follow-Through if missing;
- resulting Juggernaut therefore retains branch history mechanically through permanent ability ownership.

Repeat analogous coverage for at least one other converging family.

#### Frontend/runtime mutation contract

Add framework-neutral mutation support only; no Phaser UI yet.

Add:
- exact promotion mutation payload/result types;
- strict parser;
- Runtime API method for `POST /api/v1/units/:unitId/promote` using CSRF + idempotency;
- reuse the existing Unit Detail parser for the returned full unit rather than creating a competing unit-detail shape.

The frontend method/parser may accept current projected content and owned-dice summaries, matching existing Unit Detail/loadout mutation parsing patterns.

Validate:
- canonical requested unit ID;
- exact request fields;
- exact response envelope/field set;
- response promotion ID equals request;
- projected promotion exists;
- projected from/to match response;
- spend currency is exactly Raw Chaos;
- spend amount equals expected price;
- wallet arithmetic exact/client-safe;
- granted ability IDs unique/deterministic/projected;
- each granted ability exists on the target unit type;
- returned unit ID equals path unit ID;
- returned unit current type equals promotion target;
- returned level/XP normalized;
- returned promotion history ends with the exact from -> to transition;
- each granted ability appears in returned permanent ability ownership;
- pre-existing loadout/binding parsing remains strict;
- player revision client-safe.

Do not add a GameStore mutation workflow or screen state until Package 5.

#### Tests

Add focused coverage for at least:

**Successful mutation**
- Bruiser -> Enforcer;
- Bruiser -> Pit Fighter;
- one tier-2 -> shared tier-3 path with zero ability delta;
- one alternate branch -> same tier-3 path with retained branch abilities + nonzero target delta;
- unit ID/name/kin/level/XP unchanged;
- exact history append;
- exact permanent ability delta;
- loadout/bindings unchanged;
- Raw Chaos debit;
- revision +1 only.

**Eligibility/rejection**
- level below requirement;
- insufficient Raw Chaos;
- expected-price mismatch;
- promotion ID unknown;
- authored promotion from another current type;
- foreign/missing/terminal unit non-disclosing;
- participating active-run unit locked;
- non-participating unit while another run is active succeeds;
- malformed current promotion history fails integrity;
- malformed/duplicate ability state fails integrity.

**Atomicity/idempotency**
- exact retry;
- same key/different unit;
- same key/different promotion;
- same key/different expected price;
- replay after current content/price changes;
- injected pre-commit failure rolls back wallet/type/history/ability/revision/receipt;
- stale guarded type update fails atomically;
- client-safe Raw Chaos/revision boundaries.

**Regression**
- Unit Detail after success parses/validates;
- promotion-options after tier-1 success now exposes exactly the authored tier-2 -> tier-3 edge;
- terminal tier-3 promotion-options empty after second promotion;
- existing rename/loadout behavior remains valid with retained branch abilities;
- run-start locking/race serialization assumptions remain covered.

**Frontend**
- request/header/idempotency behavior;
- strict success parser;
- zero/nonzero ability grant lists;
- returned Unit Detail reconciliation;
- malformed spend/history/target/ability identity rejects.

#### Verification

Run:
- `npm run verify:package`;
- focused promotion-command/idempotency/atomicity tests;
- focused promotion history/policy/read regressions;
- focused active-run locking/run-start regressions;
- focused frontend promotion + Unit Detail contracts;
- DB provision/reset;
- full supported Docker backend suite;
- full frontend suite;
- production frontend build;
- bundle/content/docs/diff gates.

Report exact focused/full counts where available.

#### Out of scope

- Phaser Academy screen;
- Phaser promotion selection/confirmation UI;
- GameStore Academy/promotion workflow;
- changing promotion graph/costs unless a defect is found;
- consuming/retiring secondary units;
- level/XP reset;
- capstone-specific state/endpoints;
- automatic loadout changes;
- final progression balance;
- Wrong Machine/kin progression;
- final visual overhaul.

#### Completion

Implement only Milestone 8 Package 4. Leave it **In Progress** for architectural review. Do not promote Package 5 yourself.
