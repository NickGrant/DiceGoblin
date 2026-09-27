# Active Execution Issue

## Milestone 7 - Economy and Inventory

### Milestone 7 Package 4 - Unlock-aware base-unit purchase + shared unit creation

**Status:** In Progress
**Priority:** High

#### Accepted baseline

Milestone 7 Package 3 - Idempotent Teeth purchase + item/basic-die acquisition is approved at `21825dde6bccfb0d925252eea4e76716aec0f3fd`.

Package 3 closure evidence:
- GitHub Full Verification: backend 845 tests / 1,681 assertions; frontend 494 tests; all standard gates PASS;
- focused frontend purchase contracts/API: 19 tests PASS;
- focused MySQL Shop purchase: 11 tests / 92 assertions;
- focused inventory persistence: 6 tests / 20 assertions;
- DB provision/reset: PASS;
- full Docker backend: **845 tests / 3,467 assertions / 268 skipped**;
- production frontend build, bundle, docs/context, and diff check: PASS.

Package 3 established:
- authenticated/CSRF/idempotent `POST /api/v1/shop/purchase`;
- authoritative stale-price precondition;
- atomic Teeth debit + item/die output + one revision + finalized receipt;
- exact retry before current content resolution;
- normal `user_items` and `dice_instances` output;
- client receipt parsing bound to the exact submitted offer and expected price.

Production item and Shop-offer catalogs remain intentionally empty. Do not invent balance content simply to exercise this package.

#### Package 4 purpose

Extend the existing Shop transaction to repeatable **base-unit** acquisition without inventing a Shop-specific roster model.

This package proves:

> an authored tier-1 Basic Goblin unit offer is visible through the normal Shop contract, is available only when the player owns an authored unit-type entitlement, and creates exactly one normal level-1 unit through a shared unit-creation boundary.

Do not implement Academy purchase/unlock actions; Milestone 8 owns how permanent unit-type entitlements are acquired.

#### Unlock model extension

The persistence model is already generic: `user_unlocks` stores authored `unlock.*` IDs.

Extend authored `unlock` validation from region-only to support:
- `target_type: region` -> `target_id: region.*`;
- `target_type: unit_type` -> `target_id: unit_type.*`.

Reference validation must enforce the target type.

Do not add a new entitlement table or duplicate `user_unit_type_unlocks`.

Existing region behavior must remain unchanged. `RegionAvailabilityPolicy` continues to ignore non-region unlocks.

Add a small unit-type availability policy that:
- accepts owned authored unlock IDs;
- ignores stale/unknown unlock IDs rather than granting anything;
- considers only `target_type === unit_type`;
- returns exact authored unit-type IDs;
- uses stable ordinal ordering;
- never infers availability from naming, tier, current roster, or client state.

Milestone 8 will later create/grant these authored unit-type unlocks through Academy progression. Package 4 only consumes the entitlement.

#### Authored Shop unit offers

Extend the `shop_offer` grant union with:

```text
type = unit
unit_type_id
kin_id
```

For Milestone 7, a Shop unit grant is intentionally narrow:
- referenced unit type must exist;
- referenced unit type must be **tier 1**;
- `kin_id` must be exactly `kin.goblin`;
- `kin.goblin` must exist;
- at least one authored `unlock` definition must target that exact `unit_type_id`, so the offer is reachable through the accepted entitlement model.

Do not allow Shop offers for tier-2/tier-3 promoted types.
Do not allow Pig/Lizard/Frog/etc. kin acquisition here; Kin restoration/reconstruction belongs to later milestones.
Do not put unlock IDs, player availability, or price into the safe static grant projection.

The production Shop catalog may remain empty in Package 4. Fixture content should define the needed unit offers + unit-type unlocks.

#### Safe client projection

Extend `ClientShopOfferDefinition` with the static unit grant identity:

```text
type = unit
unit_type_id
kin_id
```

Validate both references against projected authored content.

Do not project:
- price;
- owned unlock IDs;
- the specific unlock source;
- availability rules;
- Academy metadata.

#### Authoritative Shop read availability

Extend `GET /api/v1/shop` without replacing its response shape.

For item and die offers:
- `available = true` as today.

For unit offers:
- `available = true` only when the authenticated player owns at least one valid authored `unit_type` unlock targeting the offer's exact `unit_type_id`;
- otherwise `available = false`.

`can_afford` remains a **Teeth-only** statement:
- `can_afford = teeth >= price`;
- do not fold availability into affordability.

The Shop query remains read-only and must not increment revision.

Unknown/stale owned unlock IDs do not unlock unit offers.
An unlock for another unit type does not unlock the offer.
No ownership or roster-count inference.

#### Purchase availability

Extend the existing `PurchaseShopOfferCommand` for unit grants.

For a new request:
1. lock `user_state` as today;
2. check finalized idempotency receipt before current content/availability;
3. resolve current authored offer/price;
4. for a unit offer, read/lock the caller's owned unlock IDs and apply the shared unit-type availability policy;
5. reject an unavailable unit offer **before** spend/output mutation;
6. then perform the existing price, balance, spend, output, revision, and receipt transaction.

Use:
- `shop_offer_unavailable` (403) for a valid unit offer whose unit type is not unlocked.

Exact finalized retry semantics remain stronger than current content/unlock state:
- an exact matching retry returns the original receipt unchanged even if the unit-type unlock was later removed or Shop content changed.

Item/die purchase behavior must not regress.

#### Shared unit creation boundary

Do not put raw unit-creation SQL in the Shop command.

Create one transaction-neutral application/domain service for normal owned-unit creation that can later be reused by rewards, Wrong Machine, onboarding, etc.

For a Package 4 Shop-created unit:

- owner = authenticated user;
- `unit_type_id` = authored offer target;
- `kin_id = kin.goblin`;
- `level = 1`;
- `xp = 0`;
- `lifecycle_status = active`;
- no promotion history;
- own every authored ability listed by the tier-1 unit type;
- **no ability loadout yet**;
- **no dice bindings**;
- create **no dice** as a side effect.

Use a deterministic temporary display name equal to the authored unit type's `display_name`. Names do not need to be unique; the existing rename command remains the player customization path.

The service:
- requires a caller-owned transaction;
- validates authored unit/kin/ability coherence;
- delegates persistence to `WarbandUnitRepository` methods;
- does not increment revision;
- does not own price/unlock policy;
- returns the normal created unit identity/state needed for the receipt.

Repository additions remain persistence-only and require caller-owned transactions for creation writes.

#### Unconfigured-unit Warband contract

A purchased unit intentionally arrives without equipped actions/dice so Shop purchase does not silently mint free dice.

Backend `UnitDetailQuery` already represents an empty loadout/binding set without treating it as persisted corruption.

Update the strict frontend unit-detail contract to permit this one valid unconfigured state:
- `ability_loadout = []`;
- `dice_bindings = []`;
- owned abilities are still present.

Do **not** permit partial configured state:
- if the loadout is non-empty, all existing strict active-ability and complete-dice-slot invariants remain;
- bindings with an empty loadout reject;
- partial/missing bindings for a non-empty loadout reject.

Run safety remains unchanged:
- `RunParticipationValidator` already rejects an empty loadout as `run_configuration_invalid`;
- do not weaken that check.

A purchased unit may exist in the roster and be edited/renamed before it is combat-ready.

#### Unit purchase result

Extend the purchase output union:

```text
type = unit
unit:
  id
  display_name
  unit_type_id
  kin_id
  level
  xp
  lifecycle_status
```

Required values for Package 4:
- canonical positive string ID;
- exact offered `unit_type_id`;
- `kin_id = kin.goblin`;
- `level = 1`;
- `xp = 0`;
- `lifecycle_status = active`;
- nonblank bounded display name matching the deterministic creation rule.

The idempotency receipt stores this exact unit output. Exact retry must not create another unit.

Extend strict frontend purchase parsing so unit output agrees with the originally submitted offer and projected unit/kin identities.

#### Existing purchase/request semantics

Do not change:
- request body;
- `expected_price` stale-price behavior;
- Teeth-only price;
- idempotency hash/request identity;
- item/die output semantics;
- one revision increment per newly committed purchase;
- receipt-first exact retry;
- client-safe integer boundaries.

#### Tests

Authored content/unlocks:
- existing region unlock remains valid;
- valid unit-type unlock resolves its exact target;
- mismatched target_type/target namespace rejects;
- missing/wrong target type rejects;
- valid tier-1 `kin.goblin` unit offer loads/projects;
- tier-2/tier-3 unit offer rejects;
- non-`kin.goblin` Shop unit offer rejects;
- unit offer with no authored unit-type unlock target rejects;
- safe projection contains unit/kin grant identity only.

Availability/read:
- locked unit offer is returned with `available=false`;
- exact owned unit-type unlock makes only that target available;
- stale/unknown/wrong-target unlock does not grant availability;
- `can_afford` remains independent of availability;
- read does not mutate revision.

Purchase:
- locked unit offer -> 403 `shop_offer_unavailable`, no spend/output/receipt/revision;
- unlocked offer spends exact Teeth and creates exactly one owned unit;
- created unit is level 1 / xp 0 / active / Basic Goblin;
- created unit owns exactly the unit type's authored ability IDs;
- no promotions, loadout, dice bindings, or new dice are created;
- exact retry creates no second unit or second spend;
- exact retry still returns after entitlement removal;
- same-key different request still conflicts;
- item/die purchase regressions remain green;
- user A's unlock cannot authorize user B.

Warband/runtime:
- purchased unconfigured unit appears in unit collection;
- unit detail with owned abilities + empty loadout/bindings parses successfully;
- empty loadout with bindings rejects;
- non-empty loadout still requires all dice slots;
- placing an unconfigured purchased unit in a squad does not make it runnable; run start rejects `run_configuration_invalid` until configured.

Transaction/integrity:
- shared unit creation requires caller transaction;
- failure after unit creation but before receipt commit rolls back unit, spend, revision, and receipt;
- no new unit/Shop entitlement tables.

#### Verification

Run:
- `npm run verify:package`;
- focused unlock/content/unit-offer tests;
- focused Shop query availability tests;
- focused unit-creation/purchase tests;
- focused frontend Shop + unconfigured-unit contracts;
- `npm run test:db:provision:docker`;
- `npm run test:db:reset:docker`;
- focused MySQL unit-purchase/run-safety tests;
- `npm run test:backend:docker`.

Report exact tests/assertions/skipped counts where available.

#### Out of scope

- Academy purchase/grant flows;
- Raw Chaos progression;
- production unit prices/offers;
- tier-2/tier-3 direct purchase;
- Pig/Lizard/Frog/etc. Shop acquisition;
- kin reconstruction;
- random names;
- free/starter dice bundled with units;
- automatic squad insertion;
- automatic loadout configuration;
- consumable use;
- dice sell/salvage;
- final Shop/Inventory UI.

#### Completion

Implement only Milestone 7 Package 4. Leave it **In Progress** for architectural review. Do not promote Package 5 yourself.
