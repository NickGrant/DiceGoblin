# Active Execution Issue

## Milestone 7 - Economy and Inventory

### Milestone 7 Package 2 - Shop authored-offer model + authoritative Shop read contract

**Status:** In Progress
**Priority:** High

#### Problem

The vNext runtime has no canonical authored Shop-offer model or authoritative read contract, so later purchase work has no safe source for offer identity, current price, availability, or affordability.

#### Accepted baseline

Milestone 7 Package 1 - Authored item + inventory foundation is architecturally approved at `3b15fc3024ef12499624e71df8740f3d5912fadb`.

Package 1 closure evidence:
- GitHub Full Verification: backend 812 tests / 1,648 assertions; frontend 486 tests; all standard gates PASS;
- focused item content: 14 tests / 22 assertions;
- MySQL inventory repository/query/controller: 6 / 20;
- MySQL baseline/composition: 8 / 66;
- full Docker backend: 613 / 2,554 / 150 skipped;
- DB provision/reset, Docker content validation, production frontend build, bundle, docs/context, and `git diff --check`: PASS.

Package 1 established:
- canonical authored `item` definitions;
- fresh-baseline `user_items`;
- transaction-neutral inventory repository mutations;
- authenticated `GET /api/v1/items`;
- strict client inventory/content contracts.

The production item catalog remains intentionally empty pending approved product content.

#### Package 2 purpose

Establish one authored Shop-offer model and one authoritative read endpoint before any purchase mutation exists.

This package must answer:

> What can this player currently see in the ordinary Shop, what does each offer cost right now, and can the player currently afford it?

It must **not** spend Teeth or create items/dice/units yet.

Do not port the prototype `ShopService`, daily-deal tables, feature unlock catalog, database-authored prices, or affix-based dice model.

#### Authored Shop offer model

Add a canonical `shop_offer` definition family in Git-tracked JSON.

Package 2 supports only the goods needed by Package 3:

1. **stackable item offer**
   - references an authored `item.*`;
   - fixed positive quantity;
   - referenced item must be stackable.

2. **die offer**
   - references an authored `dice_profile.*`;
   - fixed die size;
   - size must be allowed by the profile;
   - size must be **d4, d6, or d8 only** for Milestone 7.

Do **not** support unit offers in this package; Package 4 owns them.
Do **not** support random/daily/limited offers in this package.
Do **not** support Academy/permanent unlock offers.

Each offer has exactly one Teeth price:
- currency ID: `teeth`;
- positive integer amount.

Recommended semantic shape:

- stable `shop_offer.*` ID;
- `type: shop_offer`;
- one exact grant union:
  - item: item ID + quantity;
  - die: profile ID + size;
- one exact price object: Teeth + amount.

Do not add mutable availability, purchase history, price, or catalog rows to MySQL.

The production Shop-offer catalog may remain empty at the end of Package 2 if concrete offers have not yet been product-approved. Use fixture content to prove the model. Package 3 may introduce the first production offers when it implements acquisition.

#### Validation

Extend `ContentRegistry` / `ContentValidator` so:
- IDs are canonical/unique;
- exact field sets are enforced;
- unsupported grant types reject;
- item references exist and are stackable;
- die profile references exist;
- offered die size is profile-compatible;
- offered die size is never > d8 during Milestone 7;
- price currency is exactly Teeth;
- price amount is positive;
- unknown/private/randomization/limit fields reject.

Do not create a generic arbitrary Shop rule language.

#### Safe client projection

Add a safe `shop_offers` catalog to generated client content so Phaser can resolve static offer identity and the authored target locally.

Project only the stable static grant identity needed for presentation:
- offer ID;
- grant type;
- item ID + quantity **or** dice profile ID + size.

Do **not** project the offer price as a second price authority. Price comes from the authenticated Shop query.

Do not expose future randomization, purchase limits, server-only availability rules, or transaction configuration.

The client registry must strictly validate projected offer identity/reference coherence.

#### Authoritative Shop query

Implement and register:

`GET /api/v1/shop`

This is an authenticated **read-only** query.

Return a strict response containing:
- current authoritative Teeth balance;
- current `player_revision`;
- deterministic ordered Shop offers.

For each offer return only mutable/authoritative transaction-facing facts:
- `offer_id`;
- authoritative Teeth price;
- `available`;
- `can_afford`.

For Package 2, every valid authored item/die offer is ordinarily available, so `available` is true. Keep the explicit field because later Package 4 unit offers may become unlock-aware without replacing the query contract.

`can_afford` is derived from the authoritative current Teeth balance and offer price. It is informative only; Package 3 must still revalidate balance transactionally.

Ordering must be deterministic by `offer_id` using the same ordinal/ASCII semantics already established for stable inventory IDs.

The query:
- does not mutate player state;
- does not increment `player_revision`;
- does not create offers in MySQL;
- does not roll randomness;
- does not reserve stock;
- does not create purchase receipts;
- does not infer availability from client state.

A missing/incoherent player state or authored Shop definition is an integrity/server failure rather than a client-repair path.

#### Frontend runtime boundary

Add only enough framework-neutral runtime support for later Shop UI:
- strict Shop response parser;
- projected offer resolution through `ClientContentRegistry`;
- Runtime API `getShop()`;
- GameStore Shop cache/load/retry state if consistent with existing lazy domain patterns.

The parser must reject:
- unknown/unprojected offer IDs;
- duplicates;
- non-deterministic order;
- price currency other than Teeth;
- non-positive/unsafe amounts;
- malformed booleans;
- grant identity disagreement between API/client content if any grant facts are repeated;
- malformed or expanded envelopes.

Do not build the final Phaser Shop screen yet.

#### Database boundary

Package 2 should require **no new Shop tables**.

Update baseline/composition tests as needed to prove no prototype Shop catalog/daily-deal tables were introduced.

MySQL remains responsible only for the player's existing `user_state.teeth` / revision in this package.

#### Tests

Content/registry:
- empty production Shop catalog is valid;
- valid fixture item and die offers load;
- item target must exist and be stackable;
- die target must exist and support the offered size;
- d10/d12/d20 Shop offers reject even if the profile supports them;
- non-Teeth price rejects;
- zero/negative price rejects;
- duplicate/extra/random/limit fields reject;
- client projection excludes price/private configuration;
- client registry resolves projected item/die targets strictly.

Backend/API:
- unauthenticated GET rejects;
- empty Shop returns current Teeth/revision and no offers;
- deterministic fixture offers return authoritative prices;
- `available` is true for valid Package 2 offers;
- `can_afford` is correct above/below/exact balance;
- another user's Teeth cannot influence the response;
- query does not mutate Teeth or increment revision;
- no Shop/daily-deal MySQL catalog is added.

Frontend:
- strict response parsing and projected offer resolution;
- empty catalog;
- item/die offers;
- duplicate/unsorted/unknown IDs reject;
- invalid Teeth price/booleans/envelope reject;
- lazy cache/retry behavior does not corrupt prior good data.

#### Verification

Run:
- `npm run verify:package`;
- focused Shop content/projection tests;
- focused Shop backend/query/controller tests;
- focused Shop frontend contract/store tests;
- `npm run test:db:provision:docker`;
- `npm run test:db:reset:docker`;
- applicable focused MySQL tests;
- `npm run test:backend:docker`.

Report test/assertion/skipped counts where available.

#### Out of scope

- `POST /api/v1/shop/purchase`;
- Teeth debit;
- item/die creation;
- unit offers/acquisition;
- daily deals or rotations;
- purchase limits/stock;
- randomness;
- discounts/sell bonuses/market mastery;
- feature/Academy unlocks;
- consumable use;
- dice sale/salvage;
- >d8 acquisition;
- final Shop/Inventory screens.

#### Completion

Implement only Milestone 7 Package 2. Leave it **In Progress** for architectural review. Do not promote Package 3 yourself.
