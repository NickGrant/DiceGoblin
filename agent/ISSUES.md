# Active Execution Issue

## Milestone 7 - Economy and Inventory

### Milestone 7 Package 8 - Economy/inventory integrated verification and closure

**Status:** In Progress
**Priority:** High

#### Accepted baseline

Milestone 7 Package 7 - Phaser Shop + Inventory surfaces and Camp integration is approved at `6aeb397050fa8695eeaab2b4d1460de3f82c254c`.

Package 7 closure evidence:
- focused correction frontend: **35/35 PASS**;
- full frontend: **522/522 PASS**;
- full Docker backend: **890 tests / 3,631 assertions / 268 skipped**;
- DB provision/reset: PASS;
- Docker content validation: PASS;
- production build: PASS;
- bundle check: PASS, largest main bundle **339.09 KiB**;
- `npm run llm:check`: PASS;
- `npm run docs:lint`: PASS;
- `git diff --check`: PASS.

Package 7 established persistent Phaser Shop/Supplies surfaces, Camp navigation, Energy consumable use, dice sell/salvage controls, active-run healing UI, authoritative cache reconciliation, and retained-idempotency interaction safety.

#### Problem

Close Milestone 7 technically by proving the complete repeatable economy/inventory slice as one integrated system and correcting only defects exposed by that proof.

This is primarily a verification, integration-hardening, and narrow migration-cleanup package. Do not add a new economy feature or rebalance the product.

#### Integrated economy proof

Add/extend integration coverage so a fresh vNext database proves the accepted Milestone 7 lifecycle across authoritative boundaries.

At minimum demonstrate:

1. authenticated player/bootstrap begins from valid `user_state`;
2. Shop catalog is authored from canonical JSON and returns current Teeth/revision/availability/affordability;
3. item purchase spends Teeth once and produces/updates the owned stack;
4. basic-die purchase spends Teeth once and creates an active die no larger than d8;
5. unlocked authored base-unit purchase spends Teeth once and creates the expected unconfigured level-1 unit through the shared unit-creation path;
6. Energy consumable use decrements exactly one owned stack and applies authoritative regeneration/overcharge semantics;
7. an active-run healing consumable decrements exactly one stack and changes only the participating run unit HP;
8. an eligible unbound die can be sold for Teeth and transitions to retained `sold`;
9. an eligible unbound die can be salvaged for Raw Chaos and transitions to retained `salvaged`;
10. terminal dice disappear from the active owned-dice query while their lifecycle records remain persisted;
11. resulting Teeth, Raw Chaos, inventory quantities, Energy, run HP, and `player_revision` survive fresh authoritative reads/reload.

Use current commands, repositories, and content. Do not create a test-only economy path that bypasses the real application boundaries.

#### UAT-readiness correction

The current canonical production catalogs are empty: `backend/content/items/catalog.json` and `backend/content/shop_offers/catalog.json` contain no definitions. Temporary test content and deterministic capture fixtures are not sufficient for Milestone 7 closure because Package 9 must exercise the real application against canonical authored content.

Before Package 8 can close, add the minimum real authored economy content required for manual UAT:

- at least one canonical stackable `energy_restore` consumable;
- at least one canonical stackable `unit_heal` consumable;
- canonical Shop offers for those consumables;
- canonical basic-die Shop offers using existing profiles and only d4/d6/d8 sizes;
- at least one canonical tier-1 Goblin unit offer so the accepted unlock-aware availability path exists in the live catalog.

Do not grant new unit-type unlocks merely to make the offer purchasable. The Shop must truthfully present unit offers as unavailable when the player lacks the existing entitlement.

Prices, quantities, names, descriptions, rarity, and presentation IDs should be simple intentional Milestone 7 values. They are not final economy balancing. Do not import prototype daily deals, feature-unlock Shop upgrades, affix catalogs, or >d8 acquisition.

The generated client projection must contain the canonical items/offers. A normal live Shop read using repository content must be non-empty, and purchased canonical consumables must be usable through the established contextual commands.

Update the integrated closure proof so the production-content portion exercises these canonical item/offer definitions rather than proving the entire economy only with temporary authored test definitions. Temporary content may remain for edge cases that cannot be expressed safely against the production catalog.

#### Cross-command invariants

Prove the Milestone 7 commands compose safely rather than only passing in isolation:

- every new committed durable mutation increments `player_revision` exactly once;
- exact idempotent retries do not double-spend, double-credit, double-consume, duplicate assets, or reroll anything;
- same-key semantic conflicts remain rejected across each applicable command;
- rejected mutations do not alter wallet, inventory, Energy, HP, lifecycle, revision, or finalized receipt;
- injected pre-commit failures roll back all affected state;
- client-safe integer overflow remains atomic;
- foreign/missing resources retain ownership-safe/non-disclosing behavior;
- Shop affordability reflects authoritative Teeth changes after purchase/sale;
- item quantities remain coherent after purchase then consumption;
- active-run/equipment locks remain effective for dice lifecycle operations;
- healing does not modify permanent unit state or run-node topology;
- no Milestone 7 acquisition path creates a die larger than d8.

Do not add cross-command coupling merely to make the tests pass. Commands remain explicit player intentions with one transaction owner each.

#### Frontend integrated proof

Exercise the persistent Phaser runtime across the complete economy UX rather than testing each screen only as an isolated class.

Cover at minimum:
- Camp -> Shop -> purchase -> back to Camp;
- Camp -> Supplies -> Energy use -> back to Camp;
- Camp -> Warband Dice -> sell/salvage confirmation -> authoritative wallet/dice reconciliation;
- active RunScene -> Supplies -> unit heal -> run state remains active and node state unchanged;
- mutation state survives rerender/reflow and ambiguous retry retains exact request/key;
- successful/definitively rejected mutations release navigation;
- reconciliation failures leave the committed server outcome recoverable and mark affected cache state safely rather than inventing data;
- lazy domains are not eagerly fetched merely because another economy mutation succeeds;
- normal reload/bootstrap + lazy reads reproduce the committed server state.

Use the current `GameStore`, runtime API contracts, and screen boundaries. Do not create a second economy cache/store.

#### Responsive and deterministic presentation verification

Run deterministic Phaser verification for the newly completed Milestone 7 surfaces.

At minimum verify representative:
- Camp with Shop/Supplies entry points;
- Shop with available and unavailable/unaffordable offers;
- Supplies with material, Energy restore, and run-use heal presentation;
- Warband Dice with unbound lifecycle actions and equipped/active-run locked state;
- RunScene Supplies/healing interaction.

Verify affected layouts at:
- Compact landscape `844x390`;
- Standard `1600x900`;
- Wide `2560x1080`;
- safe-inset Compact;
- portrait mobile gate where the capture/runtime path can affect it.

Prefer the repository deterministic capture tooling. Fix functional/layout defects found, but do not begin the deferred game-wide visual overhaul.

#### Narrow prototype retirement

The Phaser Shop is now the proven live replacement.

Before deletion, confirm current references. If they remain isolated/unrouted as expected, retire the superseded Angular Shop-only path:
- prototype Angular Shop page;
- its page-only Angular Shop service;
- Shop-only Angular UI components/tests that have no remaining live consumer.

Do not remove public/platform shell code.

Do **not** broadly delete the old backend prototype `ShopService` / progression-era economy implementation in this package. Its routes are already unregistered, and portions remain migration evidence for later permanent-progression decisions. Milestone 8+ or final hardening owns that broader backend cleanup.

Update current migration/disposition documentation only as needed to state that the Angular Shop path has been retired after Phaser replacement proof.

#### Regression expectations

The closure package must keep green:
- auth/session/bootstrap;
- Warband/loadout/squad flows;
- run start/abandon/map;
- combat/playback;
- Loot/Rest/Boss/Exit;
- Mountains generalization;
- Shop/inventory/consumables/dice lifecycle;
- content projection secrecy/validation;
- responsive host/orientation behavior.

Do not weaken an earlier milestone invariant to close Milestone 7.

#### Required verification

Run:
- `npm run verify:package`;
- focused integrated economy/inventory tests;
- focused frontend economy/runtime/navigation/reconciliation tests;
- DB provision + reset from the current vNext baseline;
- full Docker backend;
- full frontend;
- production frontend build;
- bundle check;
- Docker content validation;
- deterministic scene captures/review for the affected economy surfaces;
- `npm run llm:check`;
- `npm run docs:lint`;
- `git diff --check`.

Report exact focused/full test and assertion counts, skipped counts, bundle result, and capture results.

#### Out of scope

- final economy balancing beyond the minimum UAT-ready canonical catalog;
- daily deals;
- feature-unlock Shop upgrades;
- Academy/permanent progression;
- Raw Chaos spending;
- >d8 acquisition;
- Wrong Machine;
- generic item use;
- permanent out-of-run healing;
- new run encounter mechanics;
- final cross-cutting visual/UI overhaul;
- broad prototype-backend deletion.

#### Completion

Implement only Milestone 7 Package 8. Leave it **In Progress** for architectural review.

Do not mark Milestone 7 complete and do not promote Milestone 8. Package 9 is focused manual UAT and must occur first.
