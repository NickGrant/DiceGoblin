# Active Execution Issue

## Milestone 7 - Economy and Inventory

### Milestone 7 Package 3 - Idempotent Teeth purchase + item/basic-die acquisition

**Status:** In Progress
**Priority:** High

#### Accepted baseline

Milestone 7 Package 2 - Shop authored-offer model + authoritative Shop read contract is approved at `a1cebe4527c0ea3fb5e9d92ce5314760a887c0b5`.

Package 2 closure evidence:
- GitHub Full Verification: backend 834 tests / 1,681 assertions; frontend 490 tests; all standard gates PASS;
- DB provision/reset: PASS;
- full Docker backend from current Package 2 branch state: **834 tests / 3,374 assertions / 268 skipped**;
- the earlier 242-test result is superseded and must not be cited as full-suite proof.

Package 2 established:
- canonical authored `shop_offer` definitions;
- item + fixed d4/d6/d8 die offer shapes;
- safe client Shop projection without price;
- authenticated read-only `GET /api/v1/shop`;
- authoritative Teeth/revision/price/affordability read model;
- one shared `ClientSafeInteger` PHP->browser numeric boundary.

The production item and Shop-offer catalogs remain intentionally empty. Do not invent product balance/prices merely to populate them.

#### Problem

Prove the first repeatable ordinary Teeth transaction:

> purchase one authored Shop offer exactly once, debit the authoritative Teeth wallet exactly once, create exactly the authored item stack or basic die, and return an immutable retry-safe receipt.

This package supports only the Package 2 offer types:
- stackable item;
- fixed-profile fixed-size die (d4/d6/d8 only).

Do not add unit offers; Package 4 owns repeatable unit acquisition.

#### Endpoint

Implement and register:

`POST /api/v1/shop/purchase`

Requirements:
- authenticated;
- CSRF-protected;
- requires `Idempotency-Key`;
- exact JSON body:

```json
{
  "offer_id": "shop_offer.example",
  "expected_price": {
    "currency_id": "teeth",
    "amount": 7
  }
}
```

`expected_price` is an optimistic transaction precondition representing the price shown to the player. It is **not** a second price authority.

The server:
1. validates/canonicalizes the request and idempotency key;
2. begins the transaction and locks `user_state`;
3. checks an existing idempotency receipt **before resolving current Shop content**;
4. if the same key + same normalized request already committed, returns that exact stored result unchanged;
5. same key + different request conflicts;
6. for a new request, resolves the current authored `shop_offer`;
7. compares the current authoritative Teeth price to `expected_price`;
8. if the offer/price changed, rejects without spending or granting;
9. revalidates current Teeth balance under lock;
10. applies spend + grant + revision + finalized idempotency receipt in one transaction.

This ordering is important: an exact retry must still replay its original receipt after a content deployment changes or removes the offer.

#### Request/error semantics

Use narrow non-disclosing errors:
- malformed body / invalid offer identity: `invalid_shop_purchase` (422);
- invalid idempotency key: existing `idempotency_key_invalid` (400);
- reused key with different normalized request: existing `idempotency_conflict` (409);
- authored offer unavailable for a new request: `shop_offer_not_found` (404);
- expected price no longer matches authoritative price: `shop_offer_changed` (409);
- insufficient Teeth: `insufficient_teeth` (409);
- incoherent wallet/content/persistence state: `shop_data_integrity_error` (500).

Do not reveal private content or foreign state in errors.

#### Transaction ownership and wallet spend

The purchase command owns the complete transaction.

Reuse `PlayerStateRepository::getPlayerStateForUpdate()` for the wallet lock.

Add an explicit Teeth/currency **debit** persistence primitive rather than weakening the existing reward-grant invariant:
- current `applyCurrencyTransition()` is grant-oriented and requires `after >= before`;
- preserve that behavior for reward application;
- add a transaction-neutral spend/debit transition requiring exact `before`, non-negative `after`, and `after <= before`.

The purchase command computes:
- `balance_before`;
- exact price;
- `balance_after = balance_before - price`.

Insufficient balance rejects before durable output creation.

Increment `player_revision` exactly once for a newly committed purchase.

Because the result crosses the browser boundary, a new purchase must also reject integrity state where the next revision cannot remain within `ClientSafeInteger::MAXIMUM`.

#### Item acquisition

For an item offer:
- resolve the authored item and require stackable consistency;
- grant exactly the authored quantity through `UserItemRepository`;
- no separate Shop inventory table;
- return the resulting owned quantity.

Prevent creation of a client-unrepresentable stack:
- the resulting owned quantity must remain <= `ClientSafeInteger::MAXIMUM`;
- enforce this at the shared mutable inventory boundary where practical, not only in controller presentation.

The entire item grant rolls back if wallet spend, revision, receipt finalization, or any later purchase step fails.

#### Die acquisition

For a die offer:
- resolve the authored `dice_profile`;
- revalidate size/profile compatibility;
- size must be exactly d4/d6/d8;
- create one ordinary active row in `dice_instances`;
- the die starts unbound/un-equipped;
- do not use the retained prototype `OwnedDiceGrantService`, affix tables, dice definitions, Codex side effects, or randomization.

Add the minimal persistence-only creation method to the accepted vNext dice repository boundary (prefer the existing `WarbandDiceRepository`) with caller-owned transaction enforcement.

Return the created die instance using string identity:
- `id`;
- `size`;
- `profile_id`;
- `lifecycle_status: active`.

Do not create multiple dice from one die offer in Package 3.

#### Purchase result / idempotency receipt

Persist and return one exact finalized result shape.

Common fields:

```text
offer_id
spend:
  currency_id = teeth
  amount
  balance_before
  balance_after
player_revision
output
```

Item output:

```text
type = item
item_id
quantity_granted
owned_quantity_after
```

Die output:

```text
type = die
die:
  id
  size
  profile_id
  lifecycle_status
```

The idempotency receipt stores this finalized result and exact retries return it byte-semantically unchanged as structured data.

Do not add purchase-history or receipt tables beyond existing `idempotency_requests`.

#### Authoritative price / content behavior

For a **new** idempotency key:
- current authored offer identity and current authored price are authoritative;
- client-projected grant identity may inform presentation but never authorizes output;
- the command grants from server `ContentRegistry`, not request-provided grant data.

If Shop content changes between GET and POST:
- matching current price -> transact normally;
- mismatched current price -> `shop_offer_changed`;
- removed offer -> `shop_offer_not_found`.

For an already finalized matching idempotency receipt:
- replay the receipt without re-reading/revalidating the current offer.

#### Production content

Do **not** invent production Shop prices, consumable names, or balance values in this package.

It is acceptable for production `items/catalog.json` and `shop_offers/catalog.json` to remain empty.

Use fixture authored content in integration tests to prove both item and die purchase paths. Concrete production inventory can be introduced by the package that owns that product behavior before Milestone 7 UAT.

#### Frontend runtime boundary

Add framework-neutral support only; no Shop screen yet.

Add:
- strict purchase-result parser;
- Runtime API purchase method that sends CSRF + `Idempotency-Key` + exact body;
- error classification necessary for later UI retry/refresh behavior.

The parser must strictly validate:
- exact envelope/field sets;
- safe non-negative currency/revision/quantity values;
- spend arithmetic (`before - amount = after`);
- Teeth currency only;
- output discriminated union;
- item output agrees with projected offer grant;
- die output agrees with projected offer profile/size and has canonical positive string ID;
- no >d8 die output;
- malformed/extra fields reject.

Do **not** add final Shop UI or broad GameStore purchase orchestration in this package. Package 7 owns presentation and same-runtime cache choreography.

Ambiguous transport/5xx outcomes must preserve the caller's original idempotency key/request so Package 7 can retry the exact operation rather than silently minting a new key.

#### Tests

Backend/content/application:
- item purchase debits exact Teeth and increments exact stack quantity;
- die purchase debits exact Teeth and creates exactly one unbound active die;
- exact-balance purchase succeeds with zero Teeth remaining;
- insufficient Teeth changes nothing;
- expected-price mismatch changes nothing;
- missing offer changes nothing;
- d4/d6/d8 fixed die offers purchase successfully in fixture coverage;
- no >d8 path is accepted;
- item stack overflow beyond client-safe maximum rejects atomically;
- revision overflow beyond client-safe maximum rejects atomically;
- command requires caller-owned/command-owned transaction boundaries as appropriate;
- no prototype Shop/dice catalog tables are introduced.

Idempotency:
- exact same key + same request returns exact first result;
- replay does not spend twice;
- replay does not increment item twice;
- replay does not create a second die;
- same key + different offer conflicts;
- same key + different expected price conflicts;
- exact replay still succeeds after fixture content price changes/removes the offer;
- rollback before receipt commit leaves no spend/output/receipt.

Ownership/isolation:
- purchase affects only authenticated user wallet/inventory/dice;
- created die belongs only to purchaser.

API/security:
- auth required;
- CSRF required;
- idempotency key required;
- strict request field set;
- documented error/status mapping.

Frontend:
- strict item purchase result;
- strict die purchase result;
- spend arithmetic/offer-output coherence;
- malformed/expanded/unsafe/>d8 outputs reject;
- request sends exact expected-price/idempotency/CSRF contract;
- ambiguous retry preserves caller-provided request identity rather than creating a new purchase.

#### Verification

Run:
- `npm run verify:package`;
- focused Shop-purchase backend tests;
- focused item/dice persistence tests;
- focused purchase frontend contract/API tests;
- `npm run test:db:provision:docker`;
- `npm run test:db:reset:docker`;
- focused MySQL Shop purchase integration;
- `npm run test:backend:docker`.

Report exact test/assertion/skipped counts where available.

#### Out of scope

- unit offers/acquisition;
- production price/balance tuning;
- daily deals/rotations;
- stock/purchase limits;
- random Shop rolls;
- >d8 acquisition or progression capability;
- consumable use;
- dice sell/salvage;
- Raw Chaos spend;
- final Shop/Inventory Phaser screens.

#### Completion

Implement only Milestone 7 Package 3. Leave it **In Progress** for architectural review. Do not promote Package 4 yourself.
