# Active Execution Issue

## Milestone 7 - Economy and Inventory

### Milestone 7 Package 9 - Focused manual UAT

**Status:** In Progress
**Priority:** High

#### Accepted technical baseline

Milestone 7 Package 8 - Economy/inventory integrated verification and closure is approved at `e59b58e709892cc0a72e609576800dc45115813e`.

Closure evidence:
- focused backend content/economy: **102 tests / 408 assertions**;
- focused frontend: **127/127 PASS**;
- full frontend: **515/515 PASS**;
- full Docker backend: **891 tests / 3,676 assertions / 268 skipped**;
- DB provision/reset: PASS;
- production frontend build: PASS;
- bundle check: PASS, largest main bundle **339.09 KiB**;
- content validation/client projection: PASS at revision `6d5e576f7bd2453f3f35d09a3abd6c0ac414843655486e48ceb906378abbe367`;
- deterministic Shop, Supplies, and Run Supplies captures: PASS;
- `npm run llm:check`, docs lint, and `git diff --check`: PASS.

The canonical production economy now includes:
- Spark Tonic — 4 Teeth, restores 12 Energy;
- Field Poultice — 4 Teeth, restores 9 active-run HP;
- Plain Cardboard d4 — 4 Teeth;
- Plain Cardboard d6 — 6 Teeth;
- Plain Cardboard d8 — 8 Teeth;
- Goblin Bruiser — 8 Teeth, available only when the player owns `unlock.unit_type.bruiser`.

Package 9 is manual user UAT. Do not implement Milestone 8 during this package.

#### UAT objective

Verify that the ordinary player-facing economy is understandable and functional through the real Phaser UI, survives navigation/reload, and does not expose an obvious state/reconciliation defect.

This is not balance approval and not final visual/UI approval. Record balance/presentation observations separately unless they prevent meaningful use.

#### 1. Camp entry points

From Camp:

- confirm **Shop**, **Supplies**, and **Warband** are reachable;
- confirm Teeth, Energy, and other displayed resources remain coherent;
- enter Shop and return to Camp;
- enter Supplies and return to Camp;
- verify Back/Escape behaves naturally and does not leave a broken/blank screen.

**Pass:** all destinations are reachable and return cleanly without losing the current session/game state.

#### 2. Shop catalog and affordability

Open Shop with the canonical production content.

Verify:
- Spark Tonic and Field Poultice are present;
- Cardboard d4, d6, and d8 are present;
- no die larger than d8 is offered;
- prices shown are 4 / 4 / 4 / 6 / 8 Teeth for the two consumables and d4/d6/d8 respectively;
- affordability changes correctly relative to the current Teeth balance;
- the Bruiser offer is visibly unavailable if this account does not own its unit-type entitlement.

If the account already owns `unlock.unit_type.bruiser`, verify the Bruiser is available for 8 Teeth and may be purchased. Otherwise, the unavailable state is the expected UAT result; technical integration coverage already proves the entitled purchase path.

#### 3. Purchase and persistence

Purchase at least:
- one Spark Tonic;
- one Field Poultice;
- two unbound dice suitable for the sell/salvage checks below.

After each purchase:
- Teeth should decrease once by the displayed price;
- the Shop should immediately reflect the new Teeth balance/affordability;
- Supplies or Warband should show the acquired asset when opened;
- ordinary navigation should remain available after a successful purchase.

Reload the game after at least one purchase.

**Pass:** purchased assets and the reduced Teeth balance survive reload without duplicate grants or another charge.

#### 4. Supplies and Energy restore

Open Supplies.

Verify:
- Spark Tonic is shown with its owned quantity and Energy-restoration purpose;
- Field Poultice is visible but clearly presented as a run-use healing item rather than a Camp healing action;
- materials, if present, do not expose a Use action.

Use Spark Tonic while current Energy is below normal maximum.

Verify:
- exactly one tonic is consumed;
- Energy increases by the authoritative amount;
- the displayed quantity and Energy update without a full game restart;
- returning to Camp preserves the new Energy state.

If practical, reload and confirm the resulting Energy/item quantity persists.

Do not block UAT on waiting for natural Energy regeneration merely to test overcharge; automated coverage owns the detailed regeneration/overcharge edge cases.

#### 5. Dice sell and salvage

Open Warband -> Dice.

For an existing equipped die:
- verify Sell/Salvage is visibly unavailable/guarded.

For an unbound purchased die:
- request Sell;
- verify a destructive confirmation appears;
- confirm it;
- verify the die disappears from the active owned-dice list and Teeth increases once.

For another unbound purchased die:
- request Salvage;
- confirm it;
- verify the die disappears from the active owned-dice list and Raw Chaos increases once.

Navigate away and back, then reload.

**Pass:** terminal dice do not reappear as active, wallet changes persist, and equipped dice remain protected.

#### 6. Active-run healing

Start or resume a real run with at least one participating goblin below maximum HP.

Open **Supplies** from RunScene.

Verify:
- Field Poultice appears;
- participating goblins are selectable with current HP presentation;
- selecting the poultice and an injured participant enables the heal action;
- one use consumes exactly one poultice;
- the chosen run participant's HP increases;
- the run remains active;
- current node/map state does not advance or otherwise change because of healing.

Close Supplies and continue normal RunScene interaction.

**Pass:** healing changes only the intended active-run HP/item quantity and does not disrupt the run lifecycle.

If producing an injured participant naturally is inconvenient, this is the one UAT step that may be deferred to the already-passing integration/capture proof rather than introducing test-only gameplay behavior.

#### 7. Retry/navigation sanity

During ordinary UAT, pay attention to mutation transitions.

Verify:
- buttons do not remain permanently disabled after a successful or definitively rejected action;
- Back/Escape works after settled actions;
- no double-click produces obvious duplicate assets or double wallet changes;
- no stale Shop/Supplies/Warband presentation persists after navigating away and returning.

Do not intentionally simulate packet loss unless convenient. Automated retained-idempotency tests own ambiguous-network retry semantics.

#### 8. Final reload check

After completing the economy interactions, reload from the browser and revisit:
- Camp;
- Shop;
- Supplies;
- Warband Dice;
- active RunScene if one remains active.

Verify the authoritative state matches the actions performed:
- Teeth;
- Raw Chaos;
- Energy;
- item quantities;
- active dice;
- run HP/state.

#### UAT reporting

Report each section as:
- **PASS**
- **FAIL** — include the exact step and observed behavior
- **NOT EXERCISED** — only for the entitlement-dependent Bruiser purchase or naturally injured-run healing case

Also report any non-blocking UX/balance observations separately from functional failures.

#### Completion

Package 9 passes when the focused manual checks expose no blocking Milestone 7 defect.

Do not mark Milestone 7 complete or begin Milestone 8 implementation automatically. After the user reports UAT results, architectural review will either:
- close Milestone 7 and promote Milestone 8; or
- issue a focused Milestone 7 UAT correction package.
