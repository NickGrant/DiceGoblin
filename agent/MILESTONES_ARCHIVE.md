# MILESTONES ARCHIVE
----
Completed milestone entries retained only when they provide 7/25 roadmap execution context.

---
name: Progression Rewards and Unlock Clarity
status: complete
issues:
  - PRU-001
  - PRU-003
  - PRU-004
description: Clarified feature unlock timing and reward presentation for the first Wrong Machine progression loop.
goals:
  - Keep Wrong Machine, Tooth Merchant, and Raw Chaos timing backend-authoritative.
  - Surface unlocks, stolen codex pages, teeth, and special item drops in reward summaries.
  - Ensure Pig Ear and Mudking Crown Fragment drops persist through generic items.
current_code_context: RunLifecycleService, RunSummaryBuilder, RunGraphGenerator, deterministic node resolution, and run reward UI carry the implemented behavior.
exit_criteria:
  - Wrong Machine unlocks after the intended Swamps gate.
  - Tooth Merchant unlocks from first Mudking defeat.
  - Reward screens show unlocks, stolen pages, teeth, and generic item drops.
  - Raw Chaos rewards remain gated behind Wrong Machine recovery.

---
name: Progression Item Reward Coverage
status: complete
issues:
  - PIR-001
description: Verified and protected the first-reconstruction generic progression item reward path across the current Farm, Mountains, and Swamps scope.
goals:
  - Keep Pig Ear and Mudking Crown Fragment rewards deterministic for the first Pig Kin reconstruction path.
  - Audit Mountains and Swamps for currently required progression material drops.
  - Prove repeated node resolution does not duplicate progression items.
  - Document future protection requirements before new region materials become campaign-critical.
current_code_context: DeterministicRunNodeResolver now checks canonical enemy template slugs for progression item rewards, BattleNodeResolutionIntegrationTest covers Mudking grants and duplicate resolution, and the Wrong Machine/kin doc records the current regional audit.
exit_criteria:
  - Pig Ear and Mudking Crown Fragment drops are covered by backend tests.
  - Farm, Mountains, and Swamps progression rewards are audited against current first-reconstruction needs.
  - Required materials have deterministic or boss-guaranteed protection.
  - Reward payloads, run summaries, and profile inventory stay aligned.

---
name: Wrong Machine and Kin Foundation
status: complete
issues:
  - PIF-001
  - KRB-001
  - KRB-002
  - KRB-003
  - WMU-001
description: Built the foundation for generic progression items, lineage unlock state, Pig Kin reconstruction, and the player-facing Wrong Machine route.
goals:
  - Add a generic item foundation for lineage materials, boss catalysts, machine catalysts, and unlock keys.
  - Retire `region_items` as the path for new progression rewards and profile work.
  - Canonicalize player-facing terminology around goblins, goblin-kin, kin, and lineages.
  - Preserve Basic Goblins as the implicit default while preparing account-level lineage unlocks.
  - Make Pig Kin the guaranteed first reconstruction path.
  - Provide a player-facing Wrong Machine route for the first reconstruction.
current_code_context: Generic `items` and `user_items` provide progression inventory, lineages are stored through `user_unlocks`, Pig Kin reconstruction is backend-authoritative and idempotent, and the frontend exposes a feature-gated Wrong Machine page after recovery.
exit_criteria:
  - Account-level lineage unlock state exists.
  - Profile/debug surfaces expose owned lineages.
  - Player-facing UI renders kin language instead of splice language.
  - Pig Kin reconstruction has backend-owned cost preview, spending, lineage unlock, and tutorial unit grant behavior.
  - Players can open the Wrong Machine after recovery and reconstruct Pig Kin from the normal UI.

---
name: Wrong Machine and Raw Chaos
status: complete
issues:
  - WM-001
description: Added Raw Chaos currency storage and dice salvage as the first Wrong Machine currency foundation.
goals:
  - Add Raw Chaos account balance storage.
  - Add backend-authored dice salvage rules.
  - Keep fabrication and catalyst work out of the first foundation slice.
current_code_context: Migration 64, DiceSalvageService, dice inventory UI, and dice-system documentation define the completed foundation.
exit_criteria:
  - Raw Chaos is stored on the account.
  - Dice salvage is backend-authored.
  - Equipped dice are protected from accidental salvage.

---
name: Expanded Run Encounters
status: complete
issues:
  - REE-002
description: Added shrine encounters as the first expanded non-combat encounter family beyond dialogue, rest, loot, and hazard nodes.
goals:
  - Persist generated shrine outcomes.
  - Resolve shrine nodes through the normal run-node lifecycle.
  - Present shrine copy and rewards in the frontend.
current_code_context: RunGraphGenerator, DeterministicRunNodeResolver, run-node UI, and encounter documentation cover the shrine foundation.
exit_criteria:
  - Shrine results are deterministic and persisted.
  - Resolving shrine encounters fits the existing run-node lifecycle.
  - The frontend presents shrine encounters and result copy clearly.
  - Backend and frontend coverage protect the core shrine flow.

---
name: Slot-Machine-Style Random Encounters
status: complete
issues:
  - SME-001
  - SME-002
  - SME-003
description: Added persisted chaos results, one reroll, reachable chaos run nodes, and backend-authoritative finalization.
goals:
  - Persist three-reel chaos results.
  - Add bounded player agency without regenerating the result.
  - Place and present chaos nodes in eligible procedural runs.
  - Finalize generated chaos encounters through a backend-authoritative path.
current_code_context: Migrations 66, 68, and 69, ChaosEncounterService, ChaosEncounterController, RunGraphGenerator, the Angular run-node page, API/data-model documentation, and backend/frontend coverage define the completed foundation.
exit_criteria:
  - Generated chaos results are durable and idempotent.
  - Chaos nodes are reachable and visually distinct on run maps.
  - One reroll may be used before finalization.
  - Finalize applies rewards once, clears the node, and unlocks downstream progression.

---
name: Chaos Encounters as Combat
status: complete
issues:
  - CEC-001
  - CEC-002
  - CEC-003
description: Converted chaos finalization into battle-backed encounters and documented the expanded reel authoring contract.
goals:
  - Finalize chaos reels into deterministic combat templates.
  - Render finalized chaos nodes through normal combat playback.
  - Document the enemy-family, encounter-shape, and rule/reward reel contract.
current_code_context: ChaosEncounterService, RunNodeController, battle reward/log persistence, the Angular run-node page, and chaos reel combat authoring documentation define the current state.
exit_criteria:
  - Chaos reel finalization creates or returns a persisted battle contract.
  - Finalized chaos rewards are claimed through the same battle reward path as combat nodes.
  - The frontend transitions from settled reels into combat playback instead of ending at a direct payout panel.
  - Docs and tests describe the expectation clearly.

---
name: Inventory Scale and Actions
status: complete
issues:
  - ISA-001
  - ISA-002
description: Kept growing inventory screens usable and aligned dice actions with Wrong Machine unlock progression.
goals:
  - Paginate dice, unit, and related collection screens.
  - Expose salvage only after Wrong Machine recovery.
  - Remove redundant dice badges and duplicate prototype chips from inventory views.
current_code_context: Angular dice and warband pages, command controls, dice salvage UI, backend Raw Chaos award gating, and focused frontend/backend coverage define the completed behavior.
exit_criteria:
  - Dice and unit inventories remain stable across filters, sorts, and page changes.
  - Dice inspect modals expose salvage only after Wrong Machine unlock.
  - Raw Chaos cannot be earned through run or salvage flows before Wrong Machine recovery.

---
name: Opening Arc Story Audit
status: complete
issues:
  - OAR-001
description: Completed and audited the Wrong Machine opening arc from Mountains discovery through Swamps recovery and post-recovery Mystic Cave reconstruction guidance.
goals:
  - Make the Wrong Machine story understandable before and after recovery.
  - Keep story, feature, and dialogue state backend-owned and idempotent.
  - Avoid confusing repeat-run exposition.
current_code_context: Run lifecycle, run graph generation, and authored dialogue state now support the implemented Wrong Machine opening arc. The July 25 completion analysis records UAT as the remaining validation step.
exit_criteria:
  - Mountains and Swamps story beats have implemented or documented equivalents.
  - Wrong Machine recovery is tied to backend-owned state.
  - Mystic Cave reconstruction guidance appears only after recovery.
  - Repeat runs do not replay the full first-clear exposition chain.

---
name: Kin Pool and Balance Completion
status: complete
issues:
  - KPB-001
  - KPB-002
  - KPB-003
description: Finished the first kin reconstruction loop by verifying random reward pools, reviewing Pig Kin balance, and planning legacy storage retirement.
goals:
  - Limit random kin rewards to Basic Goblin plus owned lineages unless explicitly authored.
  - Compare Basic Goblin and Pig Kin through representative simulations.
  - Plan the compatibility-aware retirement of legacy `splice_variant` storage.
current_code_context: Lineage unlock services, reward-pool tests, balance simulation notes, and the July 25 completion analysis define the completed first-lineage posture.
exit_criteria:
  - Random kin rewards respect owned lineages.
  - Pig Kin balance has no known release-blocking simulation concern.
  - Legacy storage rename is documented as deferred-by-design future work.

---
name: Encounter Primitive Framework
status: complete
issues:
  - EPF-001
  - EPF-002
description: Defined reusable hazard and shrine primitives and connected procedural hazard population to authored region/depth rules.
goals:
  - Replace one-off hazard and shrine behavior with reusable primitive metadata.
  - Keep primitive resolution backend-authoritative and idempotent.
  - Select hazards by authored eligibility and weighting without breaking run graph guarantees.
current_code_context: Encounter primitive catalogs, deterministic node resolution, run graph generation, and backend coverage define the completed framework.
exit_criteria:
  - Hazard and shrine primitive vocabularies exist.
  - Hazard population respects region, depth, and graph guarantees.
  - Representative primitive resolution is covered.

---
name: Encounter Content Pack
status: complete
issues:
  - ECP-001
  - ECP-002
  - ECP-003
description: Seeded the initial launch-breadth content pack for hazards, shrines, and chaos reels.
goals:
  - Author at least ten hazards through approved primitives.
  - Author at least ten shrines through approved primitives.
  - Expand chaos enemy-family, encounter-shape, and rule/reward reels to the launch breadth target.
current_code_context: Seeded encounter catalogs, chaos reel authoring documentation, and validation coverage define the implemented content pack. UAT remains responsible for variety feel.
exit_criteria:
  - Ten hazards are enabled.
  - Ten shrines are enabled.
  - Chaos reels meet the ten-entry launch breadth target or documented equivalent.
  - Enabled entries resolve safely.

---
name: General Inventory and Consumables
status: complete
issues:
  - GIC-001
  - GIC-002
description: Added healing and energy consumables through the generic item foundation.
goals:
  - Let players use unit healing consumables outside active combat.
  - Let players use energy recovery consumables without exceeding caps.
  - Keep consumable spend and restoration backend-authoritative.
current_code_context: Generic item catalog entries, item inventory services, backend consumable APIs, and frontend run/control surfaces define the completed consumable implementation.
exit_criteria:
  - Healing consumables are implemented.
  - Energy consumables are implemented.
  - Spending and restoration are transactional and cap-aware.
  - Balance feel is deferred to UAT evidence.
