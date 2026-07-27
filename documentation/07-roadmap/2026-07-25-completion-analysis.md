# July 25, 2026 Roadmap Completion Analysis
----

Status: active  
Last Updated: 2026-07-27  
Owner: Product + Engineering  
Depends On: `documentation/07-roadmap/2026-07-25.md`

## Summary

The July 25 gameplay progression roadmap is substantially implemented at the systems-foundation level, but it is not fully complete as a content tranche. The strongest completed areas are backend-owned feature unlock timing, generic progression items, Raw Chaos gating, Pig Kin reconstruction, reward presentation, inventory scale fixes, and the first shrine/chaos encounter foundations.

The remaining work is mostly content breadth and balance polish: authored Swamps/Mystic Cave story coverage should be audited against the exact roadmap beats, random recruitment pools still need a hard verification pass, hazard/shrine catalogs are not yet at the requested ten-entry depth, expanded chaos reels are documented but not fully authored at the target content-pack scale, and healing/energy consumables remain deferred.

## Milestone Status

| Roadmap Milestone | Status | Notes |
| --- | --- | --- |
| Milestone 1: Opening Arc Completion | Partial | Wrong Machine and Tooth Merchant unlock timing is backend-owned and idempotent. Reward screens surface unlocks and first-clear stolen pages. The remaining risk is exact narrative coverage: the Mountains, Swamps, and Mystic Cave beats should be audited in-game against the roadmap's required dialogue list. |
| Milestone 2: Progression Item Foundation | Mostly complete | Generic `items` and `user_items` foundation exists, profile/debug/reward paths use it, and Pig Ear plus Mudking Crown Fragment drops are covered. Boss and lineage item reward presentation is complete for the first Pig Kin path. |
| Milestone 3: Kin Reconstruction and Balance | Partial | Account-level lineage unlock state, Pig Kin reconstruction transaction, and player-facing Wrong Machine UI are complete. Full recruitment-pool restriction, broader kin balance review, and compatibility-aware retirement of legacy `splice_variant` storage remain follow-up work. |
| Milestone 4: Encounter Content Framework | Partial | Shrine and chaos node foundations are backend-authoritative and persisted. Current open work adds loot/shrine quality art metadata. The reusable hazard/shrine primitive vocabulary and authored region weighting still need a focused implementation pass. |
| Milestone 5: Encounter Content Pack | Mostly incomplete | The chaos authoring contract exists and chaos can resolve into combat, but the roadmap's full ten hazards, ten shrines, and ten entries per chaos reel are not yet delivered as a content pack. |
| Milestone 6: General Inventory and Consumables | Not started | The generic item foundation can support this, but unit healing consumables and player energy recovery items have not been implemented. |

## Completed Roadmap Slices

- `PRU-001`: Correct story-gated feature unlock timing.
- `PRU-003`: Fix Pig Ear and Mudking Crown Fragment drops plus reward presentation.
- `PRU-004`: Surface unlocks, stolen codex pages, teeth totals, generic items, and Raw Chaos gating in rewards.
- `PIF-001`: Add the generic progression item catalog and user ownership foundation.
- `KRB-001`: Canonicalize new UI/docs/API language around kin and lineages.
- `KRB-002`: Add account-level lineage unlock state.
- `KRB-003`: Add backend-authoritative Pig Kin reconstruction.
- `WMU-001`: Add the player-facing Wrong Machine reconstruction UI.
- `WM-001`: Add Raw Chaos storage and dice salvage foundation.
- `REE-002`: Add persisted shrine encounters as an expanded non-combat encounter family.
- `SME-001`, `SME-002`, `SME-003`: Add, place, present, and finalize slot-machine-style chaos encounters.
- `CEC-001`, `CEC-002`, `CEC-003`: Move chaos finalization into battle-backed encounters and document the expanded authoring contract.
- `ISA-001`, `ISA-002`: Add inventory pagination and Wrong Machine-gated dice salvage affordances.

## Open Or Unverified Roadmap Slices

- `OAR-001` / `OAR-002` / `OAR-004`: Audit exact Mountains, Swamps, and post-recovery Mystic Cave dialogue beats against the roadmap. Unlock timing is fixed, but narrative completeness should be verified separately.
- `PIF-002`: Confirm progression item rewards across every intended Farm, Mountains, and Swamps family, including first-clear protection where required items are campaign-critical.
- `KRB-002`: Verify random recruitment and unit reward pools only select from owned lineages unless a reward explicitly grants a specific kin.
- `KRB-004`: Run a dedicated kin balance pass using the simulation tooling.
- `KRB-005`: Plan the compatibility-aware rename of legacy `splice_variant` storage/API fields. Visible copy has been improved, but storage is intentionally still legacy.
- `ECF-001` / `ECF-002`: Implement a reusable hazard and shrine primitive vocabulary plus authored hazard population rules.
- `ECP-001` / `ECP-002` / `ECP-003`: Seed the full ten-entry hazard, shrine, and three-reel chaos catalogs.
- `INV-002` / `INV-003`: Add between-encounter unit healing and player energy consumables.

## Definition Of Done Progress

Completed:

- Backend-owned feature unlocks are idempotent for Tooth Merchant and Wrong Machine timing.
- Raw Chaos earning and salvage are gated behind Wrong Machine recovery.
- Generic item inventory exists and supports progression materials and catalysts.
- Pig Kin can be reconstructed through the Wrong Machine with backend-owned costs and idempotent spending.
- Reward summaries include unlocks, stolen pages, teeth, units, dice, and generic item drops.
- Shrine and chaos nodes have persisted backend-owned results.

Partial:

- Story progression is represented in code and unlocks, but exact roadmap dialogue beat coverage needs a content audit.
- Kin terminology is player-facing in new work, while legacy storage/API compatibility remains.
- Encounter quality art is implemented in the current open stack for loot and shrine nodes, but broader node content authoring is still pending.
- Chaos encounters are technically mature, but the full roadmap catalog breadth is not present.

Not complete:

- Ten authored hazards.
- Ten authored shrines.
- Ten entries for each chaos reel.
- General healing consumables.
- Player energy recovery consumables.
- Dedicated kin balance simulation review.

## Recommended Next Work

1. Merge the current UX/content cleanup and node-quality PR stack.
2. Run a focused story audit for `OAR-001`, `OAR-002`, and `OAR-004`, then create only the missing dialogue branches.
3. Verify recruitment-pool lineage gating with backend tests before expanding additional kin.
4. Implement `ECF-001` and `ECF-002` as a small data-driven vocabulary rather than one-off node handlers.
5. Seed the `ECP-*` content pack after the primitive vocabulary is stable.
6. Treat consumables as the final stretch milestone once inventory and encounter attrition are better tuned.
