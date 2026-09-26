# Active Milestone

Read this for sequencing/planning or when closing/promoting an execution package. Normal implementation should use `agent/ISSUES.md` instead.

## Milestone 7 - Economy and Inventory

**Status:** Active

### Related Issues
- Milestone 7 Package 1 - Authored item + inventory foundation

Milestone 1 - Walking Skeleton is complete and passed manual user UAT.

Milestone 2 - Warband is complete and passed manual user UAT on 2026-09-13.

Milestone 3 - Enter Farm is complete and passed manual user UAT on 2026-09-15.

Milestone 4 - Combat is complete and passed manual user UAT on 2026-09-17.

Milestone 5 - Complete Farm is complete and passed manual user UAT on 2026-09-19.

Milestone 6 - Prove Region Generalization is complete and passed manual user UAT on 2026-09-25. Integrated technical closure was approved at `5c8548d8b70f10d16470a564c53d13d48d10b3e2`; UAT playback corrections through `bb49a28b41381b8ececb7fb3cf74b3a346d2116a` passed final review and Full Verification (backend 792 / 1,626 assertions; frontend 481).

The major game-wide visual/UI overhaul remains intentionally deferred.

### Outcome

Implement the ordinary repeatable economy and owned inventory through accepted vNext boundaries:
- Teeth as the common spend currency;
- authored Shop goods/offers;
- stackable item inventory;
- contextual consumables;
- ordinary/basic dice and unit acquisition;
- dice sale/salvage lifecycle;
- Phaser Shop/Inventory interaction.

Raw Chaos permanent progression remains Milestone 8+, except that salvage may award Raw Chaos if retained by accepted product design.

Until Milestone 8 introduces authoritative die-size eligibility, no Milestone 7 acquisition path may create dice larger than d8.

### Architectural Direction

- JSON in Git defines items, Shop offers, prices/rules, and presentation; MySQL stores mutable ownership and transaction state only.
- Teeth and Raw Chaos remain explicit wallet fields on `user_state` with common transaction semantics; Energy remains separate.
- Purchases/sales/salvage/consumption are explicit player intentions with one transaction owner.
- Spending/random/durable-asset creation uses idempotency where retries could duplicate cost or output.
- Player inventory uses `user_items`; do not recreate prototype item/catalog tables.
- Shop output should create normal unit/dice/item instances through shared asset/inventory boundaries, not Shop-specific ownership tables.
- Equipped or active-run-locked dice cannot be sold/salvaged illegally.
- Consumables use contextual commands rather than a generic arbitrary `use item` endpoint.
- Do not import prototype daily-deal/feature-unlock breadth by default; mine individual behavior only when the package explicitly accepts it.
- No >d8 dice acquisition until Milestone 8.

### Package Queue

1. **Authored item + inventory foundation.** Current.
2. Shop authored-offer model + authoritative Shop read contract.
3. Idempotent Teeth purchase transaction + ordinary item/basic-die acquisition (d8 maximum).
4. Base-unit purchase through unlocked authored unit types + shared unit creation.
5. Contextual consumables: Energy restore + active-run unit healing.
6. Dice sell/salvage lifecycle + Teeth/Raw Chaos outputs and active-run/equipment safety.
7. Phaser Shop + Inventory surfaces and Camp integration.
8. Economy/inventory integrated verification/closure.
9. Focused manual UAT; Milestone 8 is not promoted until it passes.

### Sequencing Notes

- Package 1 establishes only the authored-item, `user_items`, inventory query, and strict content/runtime boundary.
- Package 2 defines Shop offers as authored content and the player-specific read model before any spending mutation exists.
- Package 3 proves the common repeatable Teeth transaction with items/basic dice and explicitly caps all dice acquisition at d8 pending Milestone 8.
- Package 4 adds repeatable base-unit acquisition only after unit-type availability can be enforced through existing authoritative unlock state.
- Package 5 implements contextual item consumption without a generic scriptable item-use endpoint.
- Package 6 handles terminal dice lifecycle transitions and prevents equipped/active-run-locked asset mutation.
- Package 7 adds the Phaser interaction surfaces after authoritative contracts are proven.
- Package 8 closes the complete repeatable economy technically.
- Package 9 is manual UAT. Do not begin Milestone 8 progression work until it passes.
