# Active Execution Issue

## Milestone 9 - Kin and Wrong Machine

### Milestone 9 Package 5 - Focused manual UAT

**Status:** In Progress - User UAT
**Priority:** High

#### Accepted technical baseline

Milestone 9 technical closure is approved at `8e8701c7fb071b7513fbcac4ac3da62e266f6f8e`.

The accepted implementation includes:
- canonical Pig and Lizard Kin and reconstruction recipes;
- Farm/Mountains reconstruction-material acquisition through authored rewards;
- permanent Wrong Machine access and Kin ownership through the accepted unlock model;
- authoritative Wrong Machine read state;
- atomic/idempotent first restoration and deterministic repeat reconstruction;
- shared normal unit creation and persisted MySQL ownership;
- persistent Phaser Wrong Machine screen under `GameScene`;
- unlock-gated Camp navigation;
- authoritative wallet/inventory/Kin/revision/Warband reconciliation;
- production-composed lifecycle verification and deterministic responsive captures.

Package 4 full verification reported backend 375 tests / 1,921 assertions and frontend 539 tests, with content validation, docs lint, context/backlog validation, production frontend build, bundle budget, and diff-whitespace gates passing.

#### Purpose

Perform focused player-facing acceptance of Milestone 9 before promoting to Milestone 10. This is manual UAT, not another implementation package. Do not begin Milestone 10 work while this issue is active.

#### UAT setup

Use the normal development/test environment and a player state where Wrong Machine access can be exercised. A fresh or reset player is preferred where practical so first-restoration behavior can be observed.

Do not require exhaustive replay of automated edge cases such as transaction rollback, cross-player isolation, or idempotency conflict; those are covered by the approved technical closure. Manual UAT should concentrate on the actual player experience and integration seams.

#### Focused UAT checklist

1. **Camp access**
   - Confirm the Wrong Machine entry is absent/inaccessible before its permanent access unlock where practical to test.
   - With access unlocked, confirm the Camp entry is visible and opens the Wrong Machine without leaving `/game` or recreating the game runtime.
   - Confirm Back/Escape returns naturally to Camp.

2. **First-restoration presentation**
   - Open both Pig and Lizard recipes before restoration.
   - Confirm costs, owned ingredient counts, Raw Chaos balance, Kin identity, first-restoration mode, and reconstructability are understandable.
   - Confirm first restoration does not ask the player to select a unit type and communicates that the server chooses from eligible unlocked types.
   - Confirm unavailable reconstruction is visibly disabled when resources are insufficient.

3. **Material acquisition and refresh**
   - Acquire the required reconstruction materials through the normal Farm/Mountains gameplay paths where practical.
   - Return to/reopen Wrong Machine and confirm authoritative owned counts/reconstructability reflect the earned materials without stale or contradictory state.

4. **First restoration**
   - Perform at least one first restoration, and preferably both Pig and Lizard if the test state permits.
   - Confirm the action has a clear confirmation step and cannot visibly double-submit.
   - Confirm Raw Chaos and ingredients decrease correctly after success.
   - Confirm the restored Kin immediately changes to repeat-reconstruction mode.
   - Open Warband without reloading the browser and confirm the newly created unit is visible with the expected Kin.

5. **Persistence/reload**
   - Reload the browser after a successful restoration.
   - Confirm the Kin remains restored, the unit remains owned, spent resources remain spent, and Wrong Machine remains in repeat mode.

6. **Repeat reconstruction**
   - Confirm repeat reconstruction requires an explicit unlocked unit-type choice before spending resources.
   - Select a type and reconstruct when resources permit.
   - Confirm exactly one new unit appears in Warband with the selected type and Kin, resources update, and the Kin remains restored.

7. **Navigation/regression smoke**
   - Move among Camp, Wrong Machine, Warband, Inventory/Supplies, Shop, and Academy after reconstruction.
   - Confirm wallet/inventory/unit state does not visibly disagree between screens and no full browser reload is required for normal navigation.

8. **Responsive smoke**
   - At minimum inspect normal desktop and one compact landscape/mobile-sized presentation if convenient.
   - Confirm recipe details, costs, ingredients, unit-type choices, action controls, errors/status, and Back remain usable.
   - On portrait touch/mobile, confirm the shared rotate-device gate still blocks gameplay rather than presenting a broken Wrong Machine layout.

#### Acceptance

If the focused UAT passes without a blocking finding, report that Milestone 9 UAT passed. The orchestration owner will close Milestone 9 in the canonical repository status and activate Milestone 10 planning/execution.

If UAT exposes a correctness, persistence, navigation, or materially unusable presentation defect, record the exact reproduction and expected behavior. A narrow Milestone 9 UAT correction package should be created and reviewed before UAT is repeated.

Do not self-promote Milestone 10 from the coding agent. Milestone 9 remains active until the user confirms UAT acceptance.
