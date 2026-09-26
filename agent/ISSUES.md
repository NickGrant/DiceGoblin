# Active Execution Issue

## Milestone 7 - Economy and Inventory

### Milestone 7 Package 1 - Authored item + inventory foundation

**Status:** In Progress
**Priority:** High

#### Accepted baseline

Milestone 6 - Prove Region Generalization is complete. Manual UAT passed the full Farm -> Mountains player path, and the final focused playback-contract correction is approved at `bb49a28b41381b8ececb7fb3cf74b3a346d2116a`.

Final Milestone 6 verification at that SHA:
- backend: 792 tests / 1,626 assertions;
- frontend: 481 tests;
- content, docs/context, production build, bundle, and diff checks PASS.

Branching/route choice is deferred to Milestone 10. Die-size acquisition eligibility beyond d8 is deferred to Milestone 8.

#### Milestone 7 outcome

Build the repeatable ordinary economy around:
- Teeth spending;
- stackable item inventory;
- Shop acquisition;
- contextual consumables;
- dice sale/salvage;
- repeatable basic goods.

Raw Chaos remains the scarce progression currency, but Milestone 7 does **not** implement Academy/permanent progression or the >d8 acquisition capability.

Until Milestone 8:
> no Milestone 7 Shop, loot, reward, or other acquisition path may create a die larger than d8.

#### Problem

The accepted economy needs one canonical authored-item family and one mutable stackable-inventory read boundary before later packages can add acquisition or use commands safely.

#### Package 1 purpose

Establish the vNext authored-item and mutable stackable-inventory boundary without importing prototype catalog/database architecture.

This package is intentionally foundational. It should make item definitions safe/authored and player inventory queryable so later Shop/consumable packages have one accepted storage/content contract.

Do not implement Shop purchase, consumable use, dice sale/salvage, or final UI in this package.

#### Authored item model

Add a canonical authored `item` definition family to Git-tracked content.

The production schema should support the information later economy packages actually need, without copying the broad prototype item table.

Minimum safe authored concerns:
- stable `item.*` ID;
- display name;
- description;
- category;
- rarity/presentation classification where useful;
- art/icon key;
- stackability;
- effect metadata only when the item is a concrete consumable and the effect can be strictly validated.

Do not add MySQL item catalogs.

Do not invent Shop prices or permanent-progression semantics in item definitions. Shop offer/cost data belongs to the later Shop package.

For consumable effects, support only concrete accepted effect shapes when/if authored definitions are added. Do not create an arbitrary scriptable effect blob.

It is acceptable for the production item catalog to be empty at the end of Package 1 if no specific item has been product-approved. Tests may use focused fixture definitions rather than promoting prototype consumable names by accident.

#### Content registry / validation

Extend the existing content loading/validation boundary so:
- item IDs are stable/canonical;
- duplicate IDs are rejected;
- required presentation fields are validated;
- category/effect combinations are coherent;
- unknown effect types/fields are rejected;
- any referenced authored IDs are validated;
- private economy configuration is not accidentally exposed through the client projection.

If items are included in the safe client projection, expose presentation/mechanics fields needed for local rendering only. Do not expose future Shop pricing/randomization/private offer configuration through item definitions.

#### Mutable inventory storage

Update `backend/migrations/vnext_baseline.sql` using the fresh-baseline policy.

Add `user_items` consistent with the accepted storage decision:
- `user_id`;
- stable authored `item_id`;
- non-negative quantity;
- unique (`user_id`, `item_id`);
- FK to `users` only; authored item IDs are validated by PHP/content, not MySQL catalog FKs.

Do not create prototype-style `items` or inventory catalog tables.

Add a persistence-only repository for:
- deterministic list-by-user;
- lock/read one owned stack;
- increment/grant within an existing caller-owned transaction;
- decrement/spend with exact quantity validation and insufficient-inventory rejection;
- removing/normalizing zero-quantity rows according to one documented repository rule.

Repository methods must not start/commit their own transaction.

#### Inventory query

Implement and register:

`GET /api/v1/items`

Contract:
- authenticated read;
- no mutation or revision increment;
- returns the player's owned positive-quantity stacks only;
- stable deterministic ordering by `item_id`;
- each entry contains only mutable ownership state, minimally `item_id` and `quantity`;
- authored display/effect information resolves from the safe local content projection rather than being duplicated in the response;
- unknown/stale authored item IDs in mutable storage are treated as an integrity failure, not silently rendered.

Follow existing vNext response-envelope and non-disclosing auth patterns.

Add the corresponding runtime API/parser/store boundary only as needed to make the inventory query usable later. Do not build the final Inventory screen in Package 1.

#### Currency boundary

Do not redesign currency storage. `user_state.teeth` and `raw_chaos` remain the accepted explicit wallet fields.

Package 1 may add/refine a shared currency transition primitive only if required by the inventory foundation, but:
- reward grants must keep working exactly as today;
- a later Shop package will own Teeth debit semantics and idempotent purchase transactions;
- Energy remains outside generic currency handling.

#### Tests

Content:
- valid item definition loads;
- malformed ID/presentation/category/effect shape rejects;
- duplicate/unknown fields reject;
- safe projection does not leak future private Shop configuration;
- empty production item catalog remains valid if no item content is yet approved.

MySQL/repository:
- fresh baseline contains `user_items`;
- list returns only the caller's stacks in stable order;
- grant/increment is exact;
- spend/decrement is exact;
- insufficient spend rejects atomically;
- zero-quantity behavior matches the chosen rule;
- cross-user isolation;
- stale/unknown authored item identity is caught at the application/query boundary.

API/client:
- authenticated `GET /api/v1/items` returns deterministic owned stacks;
- empty inventory returns an empty collection;
- query is read-only and does not increment `player_revision`;
- unauthorized request follows existing policy;
- strict frontend parser rejects malformed/duplicate/unknown item identities if client parsing is added.

#### Verification

Run:
- `npm run verify:package`;
- focused item-content tests;
- focused inventory repository/query/controller tests;
- `npm run test:db:provision:docker`;
- `npm run test:db:reset:docker`;
- applicable MySQL focused tests;
- `npm run test:backend:docker`.

Report test/assertion/skipped counts where available.

#### Out of scope

- Shop offers/prices/purchases;
- daily deals;
- basic dice/unit purchase;
- consumable use;
- Energy restore/healing commands;
- dice selling;
- dice salvage;
- Raw Chaos progression spending;
- Academy;
- >d8 acquisition;
- final Inventory/Shop Phaser screens;
- prototype DB catalogs.

#### Current architectural review finding

The Package 1 implementation at `0534ac99ca43fe7a0ac08bc71b4d2efd2d054c7f` is otherwise aligned with the package architecture, but one client/server ordering defect remains:

- `UserItemRepository::listPositiveForUser()` returns `item_id` in MySQL `ascii_bin` order, while `parseItemCollectionEnvelope()` validates ascending order with JavaScript `localeCompare()`. Those orderings differ for legal stable IDs containing punctuation such as `_` (and can differ around digits), so an authoritative correctly sorted response can be rejected as non-deterministic. Validate inventory ordering using the same ordinal/code-unit ordering represented by the server contract rather than locale-sensitive collation. Add a focused regression test with legal item IDs whose ASCII order differs from `localeCompare`.

Do not change the server persistence collation or broaden the item model to fix this. Preserve strict duplicate/order validation.

After correction, run the Package 1 verification already specified, including focused MySQL inventory tests and full Docker backend proof. Leave Package 1 **In Progress** and do not promote Package 2.

#### Completion

Implement only Milestone 7 Package 1. Leave it **In Progress** for architectural review. Do not promote Package 2 yourself.
