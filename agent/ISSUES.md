# Active Execution Issue

## Milestone 7 - Economy and Inventory

### Milestone 7 Package 7 - Phaser Shop + Inventory surfaces and Camp integration

**Status:** In Progress
**Priority:** High

#### Accepted baseline

Milestone 7 Package 6 - Dice sell/salvage lifecycle is approved at `5601ba03cfb7072c016cd3788107855119cb745f`.

Package 6 closure evidence:
- dice valuation: 5 tests / 11 assertions;
- dice lifecycle/MySQL/security: 14 tests / 44 assertions;
- reward application regression: 8 tests / 33 assertions;
- focused frontend lifecycle/API: 19 tests PASS;
- full frontend: **506 tests PASS**;
- full Docker backend: **890 tests / 3,631 assertions / 268 skipped**;
- DB provision/reset, Docker content validation, PHP syntax, production build, bundle check, docs lint, and `git diff --check`: PASS.

Package 6 established retained `sold`/`salvaged` die lifecycle transitions, deterministic Teeth/Raw Chaos valuation, idempotent replay, and equipment/active-run safety.

#### Package 7 purpose

Make the Milestone 7 economy usable through the persistent Phaser client without changing the accepted server economy.

Implement the player-facing interaction surfaces for:
- Shop browsing and purchase;
- owned item/supply inventory;
- Energy-recharge consumable use from between-run inventory;
- dice sell/salvage from the existing Warband dice inventory;
- active-run unit-healing consumables through a lightweight RunScene supplies interaction;
- Camp navigation into Shop and Supplies/Inventory.

Do not revive Angular gameplay pages or create new Phaser scenes for these destinations. They are screens/views inside the existing persistent `GameScene` or a lightweight overlay inside `RunScene`.

#### GameScene navigation and Camp integration

Extend the existing GameScene screen/navigation model with:
- `shop`;
- `inventory` / Supplies.

Camp must expose clear interactive entry points for Shop and Supplies alongside the existing Warband/run interactions.

Back/Escape/controller-back behavior must follow the existing GameScreenNavigator history and return naturally to the prior GameScene screen. Do not use Angular routing as gameplay navigation.

Use the accepted responsive/safe-area model at Compact, Standard, and Wide sizes. Follow the current visual guide and neighboring Phaser screens; final cross-cutting visual polish remains deferred.

#### Shop screen

Use `GameStore.loadShop` and the strict Shop/client-content contracts already established.

Present, at minimum:
- current Teeth;
- each authored Shop offer;
- grant presentation resolved from the projected authored item/die/unit content;
- authoritative price;
- authoritative availability;
- current affordability;
- clear unavailable/insufficient-funds states.

Purchase uses the exact authoritative offer identity and price currently shown:
```text
offer_id
expected_price.currency_id = teeth
expected_price.amount = authoritative current price
```

A logical purchase attempt owns one idempotency key. While submitting, duplicate purchase input is disabled. Ambiguous network/server outcomes retain the same request + key for retry. Definitive client/business rejection may clear the attempt and allow a new action.

On successful purchase, reconcile only authoritative affected state:
- bootstrap Teeth -> returned `balance_after`;
- bootstrap `player_revision` -> returned revision;
- item output -> update an already-loaded item cache to returned `owned_quantity_after`, otherwise mark/not-load it for later authoritative fetch;
- die output -> add the returned active die to an already-loaded dice cache using projected profile/material/aspect content and no invented bindings, otherwise mark/not-load dice for later fetch;
- unit output -> add the returned active unit summary to an already-loaded unit cache using projected authored content, otherwise mark/not-load units for later fetch.

The Shop read model must no longer display stale affordability after a successful Teeth change. Reconcile from the new balance when safe or mark/reload the Shop authoritatively. Do not require a full bootstrap refresh for a normal successful purchase.

If reconciliation detects a contradiction, preserve the committed server result, mark affected cache state stale/error, and present recovery/reload guidance rather than fabricating local state.

#### Supplies / item inventory screen

Use `GameStore.loadItems` and projected item definitions.

Present owned stacks with:
- display name;
- quantity;
- category/rarity;
- description/effect summary where applicable.

Materials have no Use action.

For `energy_restore` consumables, expose a between-run Use action that calls the established contextual Energy endpoint. Eligibility remains server-authoritative; local state may only be used to disable obviously impossible actions for UX.

Use one retained idempotency key per logical Energy-use attempt across ambiguous retries. On success:
- adopt the authoritative Energy view;
- adopt the returned `player_revision`;
- update/remove the exact item stack from already-loaded inventory using `owned_quantity_after`.

`unit_heal` consumables are visible in inventory but are explicitly run-use supplies; do not invent permanent/Camp healing.

#### Dice lifecycle interaction

Do not duplicate the owned dice collection into a competing persistence model. Extend the existing Warband Dice Inventory presentation with player actions for the Package 6 commands.

For a selected die:
- show authored profile/material/rarity/aspect presentation;
- show its current equipment binding when present;
- offer **Sell for Teeth** and **Salvage for Raw Chaos** only through the authoritative endpoints;
- require an explicit destructive-action confirmation before submission;
- visibly disable/guard lifecycle actions for equipped dice and preserve the active-run lock presentation.

Client-side disabled state is UX only; the server remains authoritative.

Each sell/salvage logical action retains one idempotency key across ambiguous retry. On successful transition:
- remove the exact terminal die from an already-loaded active dice cache;
- adopt returned Teeth or Raw Chaos and `player_revision` in bootstrap;
- if Teeth changed and Shop is already loaded, reconcile or stale/reload Shop affordability;
- do not modify unit/loadout state because only unbound dice may succeed.

#### Active-run healing interaction

Make the established `unit_heal` command minimally player-usable during an active run.

Add a lightweight Supplies interaction within `RunScene` rather than a new scene. It may be an overlay/panel and should:
- load/reuse owned item inventory;
- show owned `unit_heal` consumables;
- show the exact participating owned units/current run HP needed to choose a target;
- use projected unit/content data only for presentation; server response remains authoritative for max HP and final HP;
- support healing a 0-HP participant when the run is active;
- not advance a node, rewrite playback, or mutate permanent unit state.

Use one retained idempotency key per logical heal attempt across ambiguous retry.

On successful heal:
- update the exact participant's cached `current_hp` from the authoritative receipt;
- adopt returned `player_revision`;
- update/remove the exact healing-item stack from inventory;
- leave run topology/node state unchanged.

If current inventory was never loaded, load it only when the Supplies interaction is opened; do not eagerly fetch all inventory at startup.

#### GameStore/cache requirements

Extend GameStore with explicit reconciliation/subscription behavior required by these screens rather than having screens mutate cache internals directly.

Preserve the accepted cache semantics:
- not-loaded / loading / fresh / stale / error;
- backend remains authoritative;
- mutation results update only affected cache slices;
- a mutation against a never-loaded domain does not force an unrelated eager fetch;
- unexpected revision regression is an integrity failure;
- stale/error recovery remains retryable.

Shop and item-inventory screens must re-render when their cache state changes; use a store-owned subscription boundary or an equivalent coherent mechanism.

#### Error and retry UX

Distinguish:
- loading;
- empty;
- retryable read/network failure;
- definitive business rejection;
- ambiguous idempotent mutation outcome;
- local integrity/reconciliation failure.

Do not silently issue a new idempotency key after an ambiguous purchase/use/sell/salvage/heal result.

Session expiration may direct the player to reload/sign in; integrity mismatches should prefer safe recovery over speculative state.

#### Verification and tests

Add focused tests for:
- GameScene navigation/back history for Shop and Inventory;
- Camp Shop/Supplies entry points;
- Shop loading/error/empty/availability/affordability presentation;
- exact purchase payload + retained-key ambiguous retry;
- successful item/die/unit purchase reconciliation;
- item inventory loading/error/empty states;
- Energy-restoration use/retry/reconciliation;
- dice sell/salvage confirmation, equipped/active-run lock presentation, retry, and reconciliation;
- RunScene healing supplies selection/use/retry and exact participant HP reconciliation;
- no permanent-unit/node mutation from healing;
- cache revision regression/integrity recovery;
- Compact `844x390`, Standard `1600x900`, Wide `2560x1080`, safe-inset, and portrait-gate behavior for new surfaces;
- existing Camp/Warband/Run/Battle navigation and economy/runtime contract regressions.

Run:
- `npm run verify:package`;
- focused frontend Shop/Inventory/GameStore/screen tests;
- production frontend build and bundle check;
- DB provision/reset;
- full Docker backend;
- content/docs/diff checks.

Report exact test/assertion/skipped counts where available.

#### Out of scope

- new backend economy endpoints or changed economy semantics;
- generic `use item` behavior;
- permanent out-of-run unit healing;
- Academy, Wrong Machine, Codex, objectives, or Milestone 8+ progression;
- >d8 acquisition;
- final Shop catalog/balance tuning;
- scheduled lifecycle cleanup;
- final game-wide visual/UI overhaul.

#### Completion

Implement only Milestone 7 Package 7. Leave it **In Progress** for architectural review. Do not promote Package 8 yourself.
