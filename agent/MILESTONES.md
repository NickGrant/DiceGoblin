# Active Milestone

Read this for sequencing/planning or when closing/promoting an execution package. Normal implementation should use `agent/ISSUES.md` instead.

## Milestone 10 - Run Encounter Depth

**Status:** Active

### Related Issues
- Milestone 10 Package 9 - Run-encounter-depth integrated verification/technical closure

Milestones 1-9 are complete and passed their required manual UAT.

Milestone 10 Package 1 - Branch-capable run topology and route-choice foundation is approved at `86c88987aaf14391660cb3b868cd10e537dd19d8` after the exploration-frontier correction.

Milestone 10 Package 2 - Generalized non-combat encounter contract and Rest migration is approved through correction `f782b368e3bc1e4672bcec886bf3a29263e5baf8`.

Milestone 10 Package 3 - Hazard encounter foundation is approved through correction `31b53b106d84edf2db846ec971877080e08aac9f`.

Milestone 10 Package 4 - Shrine encounter foundation is approved through correction `c121a67a4903637424c68fb9347f9f9192f4c2e5`.

Milestone 10 Package 5 - Chaos encounter foundation is approved at `32caee03565d2c86c0f54e50f076975f13ea806b`.

Milestone 10 Package 6 - Run-scoped modifier model + authoritative application is approved through correction `736f5211a6e2fff1838fa5c6ec779e229f7368d6`.

Milestone 10 Package 7 - Contextual consumables + multi-step encounter interaction is approved through verification correction `bad02a8b80b18d7689faaa6b42db92ffc45395e6`. The correction also repaired canonical backend PHPUnit discovery so the complete suite is enumerated reliably.

Milestone 10 Package 8 - Phaser RunScene branching/encounter interaction integration is approved at `a91fbece6e989c2a468fbafe88b1eb02553a3dfc`. RunScene now presents the authoritative exploration graph/frontier, generic multi-action and persisted-step encounters, narrow modifier/result facts, authoritative rejection/reload recovery, and responsive branching layouts without family-specific Shrine/Chaos dispatch or production content expansion.

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
- The authoritative exploration frontier is explicit and reconnect-safe. Resolving one available node does not consume other available nodes; a player may not resolve an arbitrary generated node merely because its identifier is known.
- Reuse the existing idempotency/retry, reward, inventory, energy, unit-state, and run persistence boundaries where semantically valid.
- Multi-step encounters persist only the minimal durable state needed to resume safely.
- Farm remains the fixed linear tutorial graph: Combat -> Loot -> Rest -> Boss -> Exit.
- Mountains is the first production region to introduce exploration branching and Hazards while retaining the Farm vocabulary; Shrine and Chaos encounters are not Mountains content.
- Swamp is the first planned production region to add Shrine and Chaos to the encounter vocabulary. Later regions may use the full encounter array.
- Avoid region-specific branches in application/runtime code; regional vocabulary/topology belongs in authored content.
- Final visual polish remains deferred; responsive interaction and deterministic capture remain required.

### Package Queue

1. **Branch-capable run topology + route-choice foundation.** Approved at `86c88987aaf14391660cb3b868cd10e537dd19d8`.
2. **Generalized non-combat encounter contract + Rest migration.** Approved through `f782b368e3bc1e4672bcec886bf3a29263e5baf8`.
3. **Hazard encounter foundation.** Approved through `31b53b106d84edf2db846ec971877080e08aac9f`.
4. **Shrine encounter foundation.** Approved through `c121a67a4903637424c68fb9347f9f9192f4c2e5`.
5. **Chaos encounter foundation.** Approved at `32caee03565d2c86c0f54e50f076975f13ea806b`.
6. **Run-scoped modifier model + authoritative application.** Approved through `736f5211a6e2fff1838fa5c6ec779e229f7368d6`.
7. **Contextual consumables + multi-step encounter interaction.** Approved through `bad02a8b80b18d7689faaa6b42db92ffc45395e6`.
8. **Phaser RunScene branching/encounter interaction integration.** Approved at `a91fbece6e989c2a468fbafe88b1eb02553a3dfc`.
9. **Run-encounter-depth integrated verification/technical closure.** Current.
10. **Focused manual UAT before Milestone 11.** Planned.

### Sequencing Notes

- Packages 1-7 established the authoritative exploration, encounter, randomness, modifier, contextual-consumable, and resumable interaction contracts.
- Package 8 completed the player-facing generic RunScene integration across those contracts while preserving server authority and responsive presentation.
- Package 9 proves the complete production-composed slice, persistence/reload/retry behavior, regression safety, responsive capture coverage, and performs the approved test-suite rationalization: remove obsolete permanently skipped prototype coverage whose behavior has accepted vNext replacements, replace brittle whole-catalog/count assertions with durable invariants, and retain exact authored-content assertions only where the content shape itself is a canonical product contract.
- Package 10 is manual UAT. Do not begin Milestone 11 until it passes and the user explicitly confirms promotion.
