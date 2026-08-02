---
Title: "July 25, 2026 Roadmap Completion Analysis"
Status: Legacy Reference
Last Updated: 2026-08-01
Owner: Product
Depends On:
  - documentation/07-development-path/2026-07-25-roadmap.md
Category: 07-development-path
Tags:
  - development-path
---

# July 25, 2026 Roadmap Completion Analysis

Superseded By: `documentation/07-development-path/2026-07-30-first-pig-kin-demo-roadmap.md`

## Summary

The July 25 gameplay progression roadmap is now implemented at the planned issue-slice level. Since the original analysis, the remaining execution lanes were completed: Wrong Machine story coverage, owned-lineage reward-pool verification, Pig Kin balance review, legacy kin storage retirement planning, hazard/shrine primitive framework, authored hazard population, initial hazard and shrine catalogs, expanded chaos reel catalogs, and healing/energy consumables.

The work is ready to move from implementation into UAT and issue reporting. The remaining risk is no longer large missing systems; it is validation quality: full critical-path playthrough, copy/content clarity, encounter variety feel, attrition/consumable balance, mobile/layout polish, and reconciliation of any merge-state or branch-order artifacts before final release hardening.

## Milestone Status

| Roadmap Milestone | Status | Notes |
| --- | --- | --- |
| Milestone 1: Opening Arc Completion | Complete, UAT needed | Mountains, Swamps, and post-recovery Mystic Cave dialogue coverage was completed and tied to backend-owned Wrong Machine state. UAT should verify that first-time and repeat runs tell the story in the intended order. |
| Milestone 2: Progression Item Foundation | Complete for current Pig Kin path | Generic `items` and `user_items` foundation exists, profile/debug/reward paths use it, Pig Ear and Mudking Crown Fragment drops are protected, and rewards surface item details. Future region material families can build on this foundation. |
| Milestone 3: Kin Reconstruction and Balance | Complete for first lineage | Account-level lineage unlock state, owned-lineage random pools, Pig Kin reconstruction, player-facing Wrong Machine UI, representative balance simulations, and legacy storage retirement planning are complete. Executing the storage rename remains intentionally deferred. |
| Milestone 4: Encounter Content Framework | Complete | Hazard and shrine primitive vocabularies exist, current resolution routes through primitive metadata, and authored hazard population respects region/depth eligibility. |
| Milestone 5: Encounter Content Pack | Complete for launch breadth target | Initial hazard and shrine catalogs meet the ten-entry target, and chaos enemy-family, encounter-shape, and rule/reward reels were expanded to the ten-entry breadth target. |
| Milestone 6: General Inventory and Consumables | Complete, balance UAT needed | Healing consumables and energy consumables are seeded, backend-authoritative, transactional, capped where applicable, and surfaced in the frontend. UAT should verify scarcity and rest-node value. |

## Completed Roadmap Slices

- `PRU-001`: Correct story-gated feature unlock timing.
- `PRU-003`: Fix Pig Ear and Mudking Crown Fragment drops plus reward presentation.
- `PRU-004`: Surface unlocks, stolen codex pages, teeth totals, generic items, and Raw Chaos gating in rewards.
- `OAR-001`: Complete Wrong Machine opening arc dialogue coverage.
- `PIR-001`: Audit and protect progression item rewards.
- `PIF-001`: Add the generic progression item catalog and user ownership foundation.
- `KRB-001`: Canonicalize new UI/docs/API language around kin and lineages.
- `KRB-002`: Add account-level lineage unlock state.
- `KRB-003`: Add backend-authoritative Pig Kin reconstruction.
- `KPB-001`: Verify random kin rewards use Basic Goblin plus owned lineages unless explicitly authored.
- `KPB-002`: Run representative Basic Goblin and Pig Kin balance simulation review.
- `KPB-003`: Plan compatibility-aware retirement of legacy `splice_variant` storage.
- `WMU-001`: Add the player-facing Wrong Machine reconstruction UI.
- `WM-001`: Add Raw Chaos storage and dice salvage foundation.
- `EPF-001`: Define hazard and shrine primitive vocabularies.
- `EPF-002`: Populate hazard nodes from authored region/depth rules.
- `ECP-001`: Seed the initial hazard catalog.
- `ECP-002`: Seed the initial shrine catalog.
- `ECP-003`: Expand chaos reel catalogs.
- `REE-002`: Add persisted shrine encounters as an expanded non-combat encounter family.
- `SME-001`, `SME-002`, `SME-003`: Add, place, present, and finalize slot-machine-style chaos encounters.
- `CEC-001`, `CEC-002`, `CEC-003`: Move chaos finalization into battle-backed encounters and document the expanded authoring contract.
- `ISA-001`, `ISA-002`: Add inventory pagination and Wrong Machine-gated dice salvage affordances.
- `GIC-001`: Add between-encounter unit healing consumables.
- `GIC-002`: Add player energy recovery consumables.

## Remaining Work Source

The July 25 roadmap implementation phase is complete enough to keep as historical context. Current planning has moved to the first Pig Kin demo release target, where active work is limited to making the player path through first Pig Kin creation stable and presentable.

Recommended UAT focus areas:

1. **Critical path story flow:** Fresh account through Farm, Mountains, Swamps, Wrong Machine recovery, Mystic Cave return, and first Pig Kin reconstruction.
2. **Repeat-run behavior:** Confirm one-time story nodes, stolen pages, and unlock messaging do not replay confusingly.
3. **Reward clarity:** Verify teeth, units, dice, item drops, stolen pages, unlocks, and special catalysts are visible at the moments players expect.
4. **Wrong Machine loop:** Confirm Raw Chaos gating, dice salvage gating, Pig Kin costs, duplicate reconstruction behavior, and granted unit/profile refresh.
5. **Kin pools and balance feel:** Confirm Basic Goblins remain viable, Pig Kin feels distinct but not mandatory, and random rewards do not roll locked kin.
6. **Encounter variety:** Confirm hazards, shrines, and chaos nodes show enough readable variation across regions and seeds.
7. **Consumable balance:** Confirm healing items do not erase rest-node value and energy items do not bypass intended pacing.
8. **Frontend polish:** Validate mobile and desktop layouts for home, run map, run node, rest, summary, inventory, Wrong Machine, Academy, shop, guide, and login.
9. **Copy terminology:** Watch for remaining player-facing "splice" language except where explicitly documenting legacy storage.
10. **Merge/release hygiene:** Confirm `main` contains the full merged PR stack and generated frontend artifacts are either intentionally included or omitted according to release policy.

## Deferred By Design

These are not blockers for the July 25 roadmap UAT, but they are still real future work:

- Executing the compatibility-aware database/API rename away from legacy `splice_variant` storage.
- Adding additional reconstructed lineages beyond Pig Kin.
- Adding more biome-specific progression materials and boss catalysts beyond the current protected path.
- Adding richer kin traversal traits or run-generation interactions.
- Tuning consumable cadence, drop sources, and per-run/per-node limits after playtest data.
- Expanding hazard, shrine, and chaos catalogs beyond the initial launch-breadth target.

## Definition Of Done Progress

Completed:

- Swamps conclude with recovery of the Wrong Machine.
- Feature and dialogue state are backend-owned and idempotent for the implemented progression path.
- Players can collect and inspect generic progression items.
- Required Pig Kin boss/material rewards are protected from random progression failure.
- Players can reconstruct Pig Kin through the Wrong Machine.
- Future random recruitment respects player-owned lineage unlocks unless explicitly authored.
- Pig Kin balance has been reviewed through representative simulations.
- Basic Goblins remain the baseline comparison and are not intentionally obsolete.
- Procedural regions have real hazard, shrine, and chaos content variety.
- At least ten hazards and ten shrines are enabled.
- Each chaos reel contains at least ten enabled entries.
- Item and encounter catalogs are inspectable through developer/debug tooling.
- Reward, spend, unlock, and consumable-use paths are backend-authoritative.
- Healing and energy consumables are implemented.

Needs UAT evidence:

- Fresh-player story comprehension from first session through Wrong Machine recovery.
- Repeat-run story and reward messaging clarity.
- Encounter-content feel across multiple seeds.
- Consumable rarity and attrition balance.
- Mobile and desktop layout polish on the full critical path.
- Final release hygiene after all PRs are confirmed merged into `main`.

## Recommended Next Work

1. Confirm this analysis file lands on `main` and that `main` includes the full completed PR stack.
2. Run the critical-path UAT script from a fresh account.
3. Log UAT findings as new active issues grouped by player-facing severity.
4. Fix only UAT-confirmed blockers or high-signal polish before broader release hardening.
