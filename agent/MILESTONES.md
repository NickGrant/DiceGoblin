# Active Milestone

Read this for sequencing/planning or when closing/promoting an execution package. Normal implementation should use `agent/ISSUES.md` instead.

## Milestone 9 - Kin and Wrong Machine

**Status:** Active

### Related Issues
- Milestone 9 Package 4 - Kin/Wrong Machine integrated verification/technical closure

Milestone 1 - Walking Skeleton is complete and passed manual user UAT.

Milestone 2 - Warband is complete and passed manual user UAT on 2026-09-13.

Milestone 3 - Enter Farm is complete and passed manual user UAT on 2026-09-15.

Milestone 4 - Combat is complete and passed manual user UAT on 2026-09-17.

Milestone 5 - Complete Farm is complete and passed manual user UAT on 2026-09-19.

Milestone 6 - Prove Region Generalization is complete and passed manual user UAT on 2026-09-25. Integrated technical closure was approved at `5c8548d8b70f10d16470a564c53d13d48d10b3e2`; UAT playback corrections through `bb49a28b41381b8ececb7fb3cf74b3a346d2116a` passed final review and Full Verification (backend 792 / 1,626 assertions; frontend 481).

Milestone 7 - Economy and Inventory is complete and passed focused manual user UAT on 2026-09-28. Technical closure was approved at `e59b58e709892cc0a72e609576800dc45115813e`; UAT corrections through `ba21115247ee862a739f07906c5e1df0f304e2b7` fixed economy-screen navigation, Warband dice-confirmation layering, and shared Teeth/Shop state.

Milestone 8 - Permanent Progression is complete and passed focused manual user UAT on 2026-10-05. Technical closure was approved at `6314877766187931124c4d8fa5013da02354adfc`; Package 7 UAT passed with no blocking findings requiring a correction package.

The major game-wide visual/UI overhaul remains intentionally deferred.

### Outcome

Implement Kin restoration and Wrong Machine reconstruction through the accepted vNext boundaries:
- canonical authored Kin and reconstruction definitions;
- durable player-scoped Kin restoration/ownership;
- Pig and Lizard reconstruction through one generic architecture;
- explicit first-restoration versus deterministic repeat behavior;
- authoritative ingredient/currency availability and transactional reconstruction;
- Phaser Wrong Machine interaction integrated into the persistent runtime;
- integrated persistence/reload/reconciliation verification and focused manual UAT.

Frog Kin and Swamp parity remain Milestone 13. Run encounter-depth work remains Milestone 10.

### Architectural Direction

- Kin and reconstruction definitions live in canonical content JSON; do not author gameplay catalogs in SQL.
- MySQL stores only durable player ownership/state and reconstruction results that truly need persistence.
- Reuse the existing permanent-unlock model for Kin restoration where semantically valid; do not create duplicate ownership flags.
- Inventory and wallet remain authoritative sources for ingredient/currency ownership; Wrong Machine state must not mirror them.
- First-restoration and repeat reconstruction are explicit authored/domain semantics, not frontend guesses.
- Repeat reconstruction is deterministic unless a later accepted decision explicitly changes it.
- Reconstruction that creates a unit must use the shared unit-creation/ownership boundaries rather than a Wrong-Machine-only unit model.
- Server/API state is authoritative. Phaser caches may reconcile/invalidate but may not fabricate availability or ownership.
- Prototype Wrong Machine/Kin code is behavioral evidence only; no prototype API/schema/Angular compatibility requirement exists.

### Package Queue

1. **Authored kin/reconstruction foundation + ownership/read contract.** Approved through `2126bb76f65b753bef227f68c048de1738808bf7` with production-drop correction `79e38a5a41d25fd36a97ea630ee2379d0e2f6049`.
2. **Idempotent reconstruction transaction + first-restoration/repeat semantics.** Approved at `722aacbb6cab4f9907cfeeacf86326ceda024eeb`.
3. **Phaser Wrong Machine surface + Camp/runtime integration.** Approved at `3ca7d2aa512d66b94fce37870de5bcc65f8fd189`.
4. **Kin/Wrong Machine integrated verification/technical closure.** Current.
5. Focused manual UAT before Milestone 10.

### Sequencing Notes

- Package 1 established the canonical Pig/Lizard Kin and reconstruction vocabulary, durable ownership boundary, authoritative read model, and generic authored reconstruction-material acquisition path.
- Package 2 owns resource consumption, deterministic/retry-safe output creation, idempotency, first-restoration effects, repeat behavior, rollback, and persistence.
- Package 3 made the accepted backend behavior player-usable in the persistent Phaser runtime through a generic Wrong Machine screen, authoritative runtime/cache reconciliation, and unlock-gated Camp navigation without reviving the prototype Angular architecture.
- Package 4 now closes the complete Milestone 9 technical slice through production-content lifecycle, MySQL persistence/reload, cross-domain reconciliation, regression coverage, and deterministic responsive capture proof.
- Package 5 is manual UAT. Do not begin Milestone 10 encounter-depth work until it passes and the user explicitly confirms promotion.
