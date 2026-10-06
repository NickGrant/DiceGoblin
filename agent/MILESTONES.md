# Active Milestone

Read this for sequencing/planning or when closing/promoting an execution package. Normal implementation should use `agent/ISSUES.md` instead.

## Milestone 8 - Permanent Progression

**Status:** Active

### Related Issues
- Milestone 8 Package 7 - Focused manual UAT before Milestone 9 promotion

Milestone 1 - Walking Skeleton is complete and passed manual user UAT.

Milestone 2 - Warband is complete and passed manual user UAT on 2026-09-13.

Milestone 3 - Enter Farm is complete and passed manual user UAT on 2026-09-15.

Milestone 4 - Combat is complete and passed manual user UAT on 2026-09-17.

Milestone 5 - Complete Farm is complete and passed manual user UAT on 2026-09-19.

Milestone 6 - Prove Region Generalization is complete and passed manual user UAT on 2026-09-25. Integrated technical closure was approved at `5c8548d8b70f10d16470a564c53d13d48d10b3e2`; UAT playback corrections through `bb49a28b41381b8ececb7fb3cf74b3a346d2116a` passed final review and Full Verification (backend 792 / 1,626 assertions; frontend 481).

Milestone 7 - Economy and Inventory is complete and passed focused manual user UAT on 2026-09-28. Technical closure was approved at `e59b58e709892cc0a72e609576800dc45115813e`; UAT corrections through `ba21115247ee862a739f07906c5e1df0f304e2b7` fixed economy-screen navigation, Warband dice-confirmation layering, and shared Teeth/Shop state.

The major game-wide visual/UI overhaul remains intentionally deferred.

### Outcome

Implement permanent progression through the accepted vNext boundaries:
- Raw Chaos as the scarce permanent-progression currency;
- authored Academy upgrades backed by permanent unlock ownership;
- unit-type research and repeatable Teeth acquisition after unlock;
- derived permanent capabilities such as Energy normal maximum;
- one authoritative die-size acquisition eligibility policy for progression beyond d8;
- unit promotion with surviving unit identity, durable promotion history, and permanent ability ownership;
- Phaser Academy and unit-progression interaction.

Wrong Machine/kin reconstruction remains Milestone 9.

### Architectural Direction

- Academy definitions and progression tuning live in canonical JSON; MySQL stores only owned unlocks, unit progression state/history, and wallet state.
- Academy upgrade ownership is represented by the permanent unlock it grants; do not add a parallel Academy-ownership table.
- Raw Chaos spends use the same wallet/transaction/idempotency semantics already proven for Teeth.
- Derived capability values are calculated from authored content plus owned unlocks. Do not persist `energy_max` or a duplicate max-die-size field.
- Unit promotion changes the surviving unit instance and records promotion history; it does not replace the unit with a new identity.
- Permanently unlocked abilities remain in `unit_abilities`; capstones are ordinary ability ownership, not separate capstone state.
- Active-run participating unit progression/configuration remains locked.
- Prototype Academy/promotion code is behavioral evidence only; do not revive SQL-authored unit catalogs, feature-upgrade rows, three-unit sacrifice semantics, old team tables, or catch-all profile refreshes without explicit re-approval.
- Permanent access and repeatable acquisition remain separate: Raw Chaos unlocks capability/type; Teeth acquires ordinary individual assets after unlock.

### Package Queue

1. ~~Authored Academy upgrades + permanent capability foundation + read contract.~~ Complete and approved at `0f3069f8aa0d81ff96d7450a006c51230be465a9`; full frontend 522 passed, focused backend 117/716, focused frontend 34; complete supported backend suite confirmed passed.
2. ~~Idempotent Raw Chaos Academy upgrade transaction + first derived-capability/Shop consequences.~~ Complete and approved at `a22617e4e63898932c913e6c1290ed346fca9b55`; required verification confirmed passed by the user after architectural review.
3. ~~Authored unit-promotion graph + promotion-options/unit-progression read contracts.~~ Complete and approved at `58cf57732790d91784ecfba080bd136006586f44`; required verification confirmed passed by the user.
4. ~~Promotion transaction + durable ability/history updates + active-run safety.~~ Complete and approved at `a1067fbdf1fc3fe101198f17dbbd21e5ebd0a93d`; required verification confirmed passed by the user.
5. ~~Phaser Academy + unit-promotion surfaces and Camp/Warband integration.~~ Complete and approved at `f9d077b840376a9c80a7011cc1a4a5e2a7ae195c`; required verification confirmed passed by the user.
6. ~~Permanent-progression integrated verification/closure.~~ Complete and approved at `6314877766187931124c4d8fa5013da02354adfc`; closure evidence was recorded at `1d3a49b190fda6d29466eb89187bc33e63f42293` after architectural review.
7. **Focused manual UAT before Milestone 9 promotion.** Current.

### Sequencing Notes

- Package 1 established authored Academy/capability vocabulary, canonical progression content, shared Energy/die-size capability policy, and the read-only Academy API/client contract and was approved at `0f3069f8aa0d81ff96d7450a006c51230be465a9` after full package verification; an initially reported reduced backend count was confirmed to be a reporting mistake rather than reduced suite execution.
- Package 2 spends Raw Chaos idempotently through the accepted reward/unlock boundary, proves immediate unit-type/Energy/>d8 consequences, closes the canonical basic-die purchase/sell arbitrage, and was approved at `a22617e4e63898932c913e6c1290ed346fca9b55` after required verification was confirmed passed.
- Package 3 authored the 20-edge single-unit promotion graph, read contracts, history integrity, level/XP semantics, and shared active-run lock and was approved at `58cf57732790d91784ecfba080bd136006586f44` after verification passed.
- Package 4 performs promotion atomically with Raw Chaos idempotency while preserving unit identity, level/XP, promotion history, permanent branch abilities, loadout/dice bindings, and active-run safety; approved at `a1067fbdf1fc3fe101198f17dbbd21e5ebd0a93d` after verification passed.
- Package 5 made Academy and promotion player-usable inside the persistent Phaser runtime with lazy authoritative caches, retained mutation attempts, shared Raw Chaos ownership, responsive capture proof, and retired the superseded Angular Academy UI; approved at `f9d077b840376a9c80a7011cc1a4a5e2a7ae195c` after verification passed.
- Package 6 closed the complete permanent-progression slice technically through production-content lifecycle, persistence/reload, cross-domain reconciliation, run-progression cache recovery, and deterministic capture proof; approved at `6314877766187931124c4d8fa5013da02354adfc` after architectural review.
- Package 7 is manual UAT. Do not begin Milestone 9 Wrong Machine/kin work until it passes and the user explicitly confirms promotion.
