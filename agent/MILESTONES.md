# Active Milestone

Read this for sequencing/planning or when closing/promoting an execution package. Normal implementation should use `agent/ISSUES.md` instead.

## Milestone 10 - Run Encounter Depth

**Status:** Active

### Related Issues
- Milestone 10 Package 1 - Branch-capable run topology and route-choice foundation

Milestone 1 - Walking Skeleton is complete and passed manual user UAT.

Milestone 2 - Warband is complete and passed manual user UAT on 2026-09-13.

Milestone 3 - Enter Farm is complete and passed manual user UAT on 2026-09-15.

Milestone 4 - Combat is complete and passed manual user UAT on 2026-09-17.

Milestone 5 - Complete Farm is complete and passed manual user UAT on 2026-09-19.

Milestone 6 - Prove Region Generalization is complete and passed manual user UAT on 2026-09-25. Integrated technical closure was approved at `5c8548d8b70f10d16470a564c53d13d48d10b3e2`; UAT playback corrections through `bb49a28b41381b8ececb7fb3cf74b3a346d2116a` passed final review and Full Verification (backend 792 / 1,626 assertions; frontend 481).

Milestone 7 - Economy and Inventory is complete and passed focused manual user UAT on 2026-09-28. Technical closure was approved at `e59b58e709892cc0a72e609576800dc45115813e`; UAT corrections through `ba21115247ee862a739f07906c5e1df0f304e2b7` fixed economy-screen navigation, Warband dice-confirmation layering, and shared Teeth/Shop state.

Milestone 8 - Permanent Progression is complete and passed focused manual user UAT on 2026-10-05. Technical closure was approved at `6314877766187931124c4d8fa5013da02354adfc`; Package 7 UAT passed with no blocking findings requiring a correction package.

Milestone 9 - Kin and Wrong Machine is complete and passed focused manual user UAT on 2026-10-10. Technical closure was approved at `8e8701c7fb071b7513fbcac4ac3da62e266f6f8e`; Package 5 basic focused UAT passed with no blocking findings requiring a correction package.

The major game-wide visual/UI overhaul remains intentionally deferred.

### Outcome

Deepen authored runs beyond linear combat/reward sequences while preserving the accepted authoritative run architecture:
- branch-capable run topology and player route choice;
- Rest encounters through the generalized encounter model;
- hazards and shrines;
- Chaos encounters;
- run-scoped modifiers;
- contextual consumable interaction where encounters require it;
- multi-step node interactions where needed;
- persistent/reconnect-safe authoritative state and Phaser presentation.

Knowledge/objectives remain Milestone 11. Mystic Cave/onboarding remains Milestone 12. Swamp/Frog Kin remains Milestone 13.

### Architectural Direction

- Extend the existing authored region/run/node architecture rather than creating a second run engine.
- Server/MySQL state remains authoritative for generated topology, current position, choices, encounter progress, modifiers, rewards, and terminal state where persistence is required.
- Authored encounter definitions belong in canonical content JSON; do not move gameplay catalogs into SQL or client constants.
- Phaser `RunScene` presents authoritative available routes/actions and reconciles mutation receipts; it does not predict hidden outcomes or fabricate route availability.
- Route choice must be explicit and reconnect-safe. A player may not resolve an arbitrary generated node merely because its identifier is known.
- Reuse the existing idempotency/retry, reward, inventory, energy, unit-state, and run persistence boundaries where semantically valid.
- Multi-step encounters should persist only the minimal durable state needed to resume safely; avoid encounter-specific persistence tables unless the shared run/node model cannot represent the accepted semantics.
- Farm and Mountains must remain valid through the generalized architecture; avoid region-specific branches in application/runtime code.
- Final visual polish remains deferred; responsive interaction and deterministic capture remain required.

### Package Queue

1. **Branch-capable run topology + route-choice foundation.** Current.
2. **Generalized non-combat encounter contract + Rest migration.** Planned.
3. **Hazard encounter foundation.** Planned.
4. **Shrine encounter foundation.** Planned.
5. **Chaos encounter foundation.** Planned.
6. **Run-scoped modifier model + authoritative application.** Planned.
7. **Contextual consumables + multi-step encounter interaction.** Planned.
8. **Phaser RunScene branching/encounter interaction integration.** Planned.
9. **Run-encounter-depth integrated verification/technical closure.** Planned.
10. **Focused manual UAT before Milestone 11.** Planned.

### Sequencing Notes

- Package 1 changes the run topology/position boundary first so later encounter types can rely on explicit reachable-node semantics instead of the current effectively linear traversal.
- Package 2 establishes the generic non-combat encounter interaction contract and migrates existing Rest behavior onto it before adding new encounter families.
- Packages 3-5 add hazards, shrines, and Chaos through that shared encounter boundary rather than bespoke controller/runtime paths.
- Package 6 owns run-scoped modifiers and their persistence/application semantics.
- Package 7 adds contextual consumable and multi-step interaction only after encounter and modifier boundaries are stable.
- Package 8 completes player-facing Phaser integration across branching and the new encounter types.
- Package 9 proves the complete production-composed slice, persistence/reload/retry behavior, regression safety, and responsive capture coverage.
- Package 10 is manual UAT. Do not begin Milestone 11 until it passes and the user explicitly confirms promotion.
