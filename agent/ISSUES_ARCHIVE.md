# ISSUES ARCHIVE
----
Completed issue entries retained only when they provide 7/25 roadmap execution context.

## Progression Rewards and Unlock Clarity

---
id: PRU-001
title: Correct story-gated feature unlock timing
status: complete
priority: high
milestone: Progression Rewards and Unlock Clarity
description: Shop/Tooth Merchant unlock now grants on victorious Farm boss claim, while Wrong Machine remains gated behind Swamps completion and both paths are backend-authoritative and idempotent.
acceptance_criteria:
  - Wrong Machine unlocks only after the intended first Swamps completion or boss-clear gate.
  - Tooth Merchant unlocks immediately when Mudking is beaten for the first time.
  - Unlock checks are backend-authoritative and idempotent across replayed requests.
  - Automated coverage proves the features are unavailable before their gates and available immediately after.
current_code_references:
  - backend/src/Services/RunLifecycleService.php
  - backend/tests/Integration/RunLifecycleServiceIntegrationTest.php

---
id: PRU-003
title: Fix lineage item drops and reward presentation
status: complete
priority: high
milestone: Progression Rewards and Unlock Clarity
description: Farm pig-family victories now grant generic progression items, Mudking boss victories grant Pig Ears and Mudking Crown Fragment, and reward previews expose generic item details.
acceptance_criteria:
  - Pig Ear and Mudking Crown Fragment have verified drop conditions.
  - Earned special items persist to the player inventory or profile as intended.
  - Reward claim responses include earned special items.
  - Frontend reward presentation includes those items when present.
current_code_references:
  - backend/src/Combat/Engine/DeterministicRunNodeResolver.php
  - backend/src/Controllers/RunNodeController.php
  - backend/src/Support/RunSummaryBuilder.php
  - backend/tests/Integration/BattleNodeResolutionIntegrationTest.php

---
id: PRU-004
title: Surface unlocks, stolen pages, and complete reward totals
status: complete
priority: high
milestone: Progression Rewards and Unlock Clarity
description: Reward summaries now surface newly unlocked systems, first-clear stolen codex pages, teeth totals, and generic item drops, with Raw Chaos earnings gated behind Wrong Machine recovery.
acceptance_criteria:
  - Reward screens show game unlocks as they become available, including Wrong Machine-style unlocks.
  - First-clear codex additions are represented as stolen pages and surfaced in rewards.
  - Reward summary totals include teeth gained alongside units and dice.
  - The rewards screen displays special item drops when earned.
current_code_references:
  - backend/src/Support/RunSummaryBuilder.php
  - backend/src/Services/RunGraphGenerator.php
  - backend/tests/Integration/RunLifecycleServiceIntegrationTest.php
  - frontend/src/app/pages/run-summary-page
  - frontend/src/app/pages/run-node-page

---
id: PIR-001
title: Audit and protect progression item rewards
status: complete
priority: high
milestone: Progression Item Reward Coverage
description: Verified the current first-reconstruction progression item path, fixed Mudking enemy slug detection for item rewards, protected duplicate node resolution from re-granting items, and documented the Farm/Mountains/Swamps reward audit.
acceptance_criteria:
  - Farm pig-family victories and Mudking boss victories continue to grant Pig Ear and Mudking Crown Fragment through the generic item system.
  - Mountains and Swamps reward families are audited for intended lineage material or catalyst drops.
  - Required Wrong Machine or lineage materials are protected by deterministic first-clear, guaranteed boss, or documented pity-style rules.
  - Reward claim responses and run summaries expose earned generic items consistently.
  - Backend integration coverage proves duplicate reward claims do not duplicate progression items.
current_code_references:
  - backend/src/Combat/Engine/DeterministicRunNodeResolver.php
  - backend/tests/Integration/BattleNodeResolutionIntegrationTest.php
  - documentation/02-systems-mvp/13-wrong-machine-and-kin.md

## Wrong Machine and Kin Foundation

---
id: PIF-001
title: Add generic progression item foundation
status: complete
priority: high
milestone: Wrong Machine and Kin Foundation
description: Added generic item catalog and user item ownership tables, seeded Pig Ear and Mudking Crown Fragment for the Pig Kin path, exposed owned items through profile/debug payloads, added debug granting and reward materialization support, and documented region items as legacy compatibility.
acceptance_criteria:
  - Add a generic item catalog and per-user item ownership table through new migrations only.
  - Seed at least one Pig Kin lineage material and one Farm boss catalyst.
  - Route new progression reward/profile/debug work through the generic item foundation.
  - Do not add new dependencies on region_items or user_region_items.
  - Keep reward claims and item grants idempotent, with non-negative quantities.
  - Add focused backend coverage for duplicate grants and profile serialization.
current_code_references:
  - backend/migrations/73_generic_progression_items.sql
  - backend/src/Services/ItemInventoryService.php
  - backend/src/Services/ProfileService.php
  - backend/src/Services/UserAssetGrantService.php
  - backend/src/Services/DevToolsService.php
  - backend/src/Controllers/RunNodeController.php
  - backend/tests/Integration/UserAssetGrantServiceIntegrationTest.php
  - backend/tests/Integration/ApiControllerEnvelopeContractTest.php
  - documentation/01-architecture/03-backend-api-contracts.md
  - documentation/01-architecture/04-data-model.md

---
id: KRB-001
title: Canonicalize kin and lineage terminology
status: complete
priority: high
milestone: Wrong Machine and Kin Foundation
description: Canonicalized new documentation, UI copy, and service/API concepts around goblins, goblin-kin, kin, and lineages while keeping legacy `splice_variant` storage as compatibility-only.
acceptance_criteria:
  - Use kin/lineage language in new documentation, UI copy, and service/API additions.
  - Keep old migrations untouched.
  - Plan any `splice_variant` storage/API rename as a forward migration with compatibility handling.
  - Ensure visible unit, reward, shop, and roster copy renders legacy `*-Spliced` values as `* Kin`.
current_code_references:
  - backend/src/Services/KinLabelService.php
  - frontend/src/app/shared/utils/kin-label.ts
  - documentation/01-architecture/03-backend-api-contracts.md

---
id: KRB-002
title: Add account-level lineage unlock state
status: complete
priority: high
milestone: Wrong Machine and Kin Foundation
description: Added account-level lineage unlock state through the existing `user_unlocks` table, treating Basic Goblin as implicit and Pig Kin as the first explicit lineage.
acceptance_criteria:
  - Store explicit lineage unlocks in the existing `user_unlocks` table under the `lineage` namespace.
  - Treat Basic Goblin as the implicit default lineage for every account.
  - Expose owned lineages in the profile payload.
  - Expose the lineage catalog and owned lineages in debug/dev catalog surfaces.
  - Keep old migrations untouched.
  - Do not add new `region_items` dependencies.
current_code_references:
  - backend/src/Services/LineageUnlockService.php
  - backend/src/Services/ProfileService.php
  - backend/src/Services/DevToolsService.php
  - frontend/src/app/core/models/api.models.ts

---
id: KRB-003
title: Add Wrong Machine reconstruction transaction
status: complete
priority: high
milestone: Wrong Machine and Kin Foundation
description: Added backend-authoritative Pig Kin reconstruction with cost preview, Wrong Machine feature gating, transactional Raw Chaos and item spending, lineage unlock grant, tutorial unit grant, and duplicate-request idempotency.
acceptance_criteria:
  - Add a backend service/API surface for reconstructing the first explicit lineage.
  - Require the Wrong Machine feature before reconstruction actions succeed.
  - Preview Pig Kin costs from backend-owned item and Raw Chaos requirements.
  - Spend required materials and boss catalysts only when reconstruction succeeds.
  - Grant the Pig Kin lineage and a Pig Kin unit atomically with the spend.
  - Make duplicate reconstruction idempotent and never double-spend resources.
  - Keep old migrations untouched.
  - Do not add new `region_items` dependencies.
current_code_references:
  - backend/src/Services/WrongMachineReconstructionService.php
  - backend/src/Controllers/WrongMachineController.php
  - backend/tests/Integration/WrongMachineReconstructionControllerTest.php
  - documentation/02-systems-mvp/13-wrong-machine-and-kin.md

---
id: WMU-001
title: Add player-facing Wrong Machine reconstruction UI
status: complete
priority: high
milestone: Wrong Machine and Kin Foundation
description: Added an authenticated Wrong Machine page that previews Pig Kin reconstruction costs, shows missing materials, submits the backend reconstruction action, refreshes profile state, and links to the granted unit.
acceptance_criteria:
  - Add a feature-gated player-facing Wrong Machine route.
  - Show backend-owned Pig Kin reconstruction cost preview.
  - Disable reconstruction until the backend marks requirements as satisfied.
  - Submit reconstruction through the existing CSRF-protected backend endpoint.
  - Refresh profile state after reconstruction so currency, items, lineages, and units update.
  - Expose the Wrong Machine in primary navigation once the `wrong_machine` feature is unlocked.
current_code_references:
  - frontend/src/app/pages/wrong-machine-page/wrong-machine-page.component.ts
  - frontend/src/app/core/services/wrong-machine/wrong-machine.service.ts
  - frontend/src/app/layout/command-controls/command-controls.component.ts
  - frontend/src/app/app.routes.ts

---
id: WM-001
title: Add Wrong Machine and Raw Chaos foundation
status: complete
priority: medium
milestone: Wrong Machine and Raw Chaos
description: Raw Chaos account balance storage and backend-authored dice salvage provide the first Wrong Machine currency foundation.
acceptance_criteria:
  - Add Raw Chaos account balance storage.
  - Add backend-authored dice salvage rules.
  - Prevent equipped dice from being salvaged without explicit player action.
  - Document fabrication and catalyst work as follow-up scope.
current_code_references:
  - backend/migrations/64_add_raw_chaos_currency.sql
  - backend/src/Services/DiceSalvageService.php
  - frontend/src/app/pages/dice-page
  - documentation/02-systems-mvp/01-dice-system.md

## Encounter Foundations

---
id: REE-002
title: Add expanded run encounter families
status: complete
priority: medium
milestone: Expanded Run Encounters
description: Added shrine encounters as a backend-authored non-combat run encounter family with deterministic persisted favor results, run graph placement, frontend node presentation, and documentation coverage.
acceptance_criteria:
  - Implement at least one meaningful non-combat encounter family beyond the current dialogue/rest/loot/hazard baseline.
  - Persist any generated encounter result before player resolution.
  - Add player-facing copy and tests for the new encounter flow.
current_code_references:
  - backend/migrations/65_run_nodes_shrine_type.sql
  - backend/src/Combat/Engine/DeterministicRunNodeResolver.php
  - backend/src/Services/RunGraphGenerator.php
  - frontend/src/app/pages/run-node-page
  - documentation/02-systems-mvp/03-encounter-scope.md

---
id: SME-001
title: Design and implement slot-machine-style chaos encounter foundation
status: complete
priority: medium
milestone: Slot-Machine-Style Random Encounters
description: Added the persisted chaos encounter foundation, including generated three-reel results, a single-use reroll mechanic, backend endpoints, API/data-model documentation, and integration coverage.
acceptance_criteria:
  - Document reel responsibilities, result persistence, and reward-scaling constraints.
  - Add backend persistence for generated chaos encounter results.
  - Implement one limited player agency mechanic such as locking or rerolling one reel.
current_code_references:
  - backend/migrations/66_chaos_encounter_results.sql
  - backend/src/Services/ChaosEncounterService.php
  - backend/src/Controllers/ChaosEncounterController.php
  - backend/tests/Integration/ChaosEncounterControllerIntegrationTest.php
  - documentation/01-architecture/03-backend-api-contracts.md
  - documentation/01-architecture/04-data-model.md

---
id: SME-002
title: Place and present chaos encounter nodes
status: complete
priority: medium
milestone: Slot-Machine-Style Random Encounters
description: Added chaos as a reachable procedural run node type, rendered persisted reel results with a one-reroll flow on the run-node page, and kept combat/reward finalization as explicit follow-up scope.
acceptance_criteria:
  - Add chaos run-node placement to eligible procedural run graphs without disrupting existing combat, shrine, rest, loot, boss, exit, dialogue, or hazard flows.
  - Present chaos nodes in the run-node UI with generated reel results, readable risk/reward copy, and the one-reroll agency mechanic.
  - Keep generation idempotent: refreshing or revisiting a chaos node must not regenerate an existing result.
  - Add backend and frontend coverage for chaos node placement and the visible generate/reroll flow.
current_code_references:
  - backend/migrations/68_run_nodes_chaos_type.sql
  - backend/src/Services/RunGraphGenerator.php
  - backend/src/Services/ChaosEncounterService.php
  - frontend/src/app/pages/run-node-page
  - documentation/02-systems-mvp/03-encounter-scope.md

---
id: SME-003
title: Finalize chaos encounter rewards
status: complete
priority: medium
milestone: Slot-Machine-Style Random Encounters
description: Added backend-authoritative chaos encounter finalization that applies bounded persisted-result rewards once, clears the chaos run node, unlocks downstream progression, and presents completion rewards in the run-node UI.
acceptance_criteria:
  - Add a backend-authoritative finalize path for generated chaos results that completes the run node.
  - Apply a bounded reward based on the persisted reel result, including the advertised reward multiplier.
  - Keep finalize idempotent so retries return the same completed result without duplicating rewards.
  - Add player-facing completion copy and backend/frontend coverage for the finalize flow.
current_code_references:
  - backend/migrations/69_chaos_encounter_finalized_rewards.sql
  - backend/src/Services/ChaosEncounterService.php
  - backend/src/Controllers/ChaosEncounterController.php
  - backend/tests/Integration/ChaosEncounterControllerIntegrationTest.php
  - frontend/src/app/pages/run-node-page
  - documentation/01-architecture/03-backend-api-contracts.md
  - documentation/01-architecture/04-data-model.md

---
id: CEC-001
title: Finalize chaos reels into battle-backed encounters
status: complete
priority: high
milestone: Chaos Encounters as Combat
description: Chaos reel finalization now locks the persisted reel result and binds the chaos node to a deterministic combat template so resolving the node produces a persisted battle instead of direct payout.
acceptance_criteria:
  - Finalizing a generated chaos result creates or returns one persisted battle for that run node.
  - The battle log meta records the chaos reel summary and selected symbols.
  - Existing finalized chaos results remain idempotent and do not duplicate rewards or battles.
  - Backend integration coverage proves chaos finalization returns a battle payload with playback events.
current_code_references:
  - backend/src/Services/ChaosEncounterService.php
  - backend/src/Controllers/ChaosEncounterController.php
  - backend/src/Controllers/RunNodeController.php
  - backend/tests/Integration/ChaosEncounterControllerIntegrationTest.php

---
id: CEC-002
title: Transition chaos frontend into combat playback
status: complete
priority: high
milestone: Chaos Encounters as Combat
description: The chaos run-node UI now treats finalization as encounter locking, then resolves and renders the normal battle playback surface for the resulting fight.
acceptance_criteria:
  - The finalize button copy reflects starting/locking the encounter, not direct payout.
  - A successful chaos finalize response sets the node result battle payload.
  - Confirmed chaos nodes with an existing battle reopen into the battle playback surface.
  - Frontend tests cover the chaos-to-playback transition.
current_code_references:
  - frontend/src/app/pages/run-node-page/run-node-page.component.ts
  - frontend/src/app/pages/run-node-page/run-node-page.component.html
  - frontend/src/app/pages/run-node-page/run-node-page.component.spec.ts

---
id: CEC-003
title: Expand chaos reel combat authoring
status: complete
priority: medium
milestone: Chaos Encounters as Combat
description: Added the authoring contract for chaos enemy family, encounter shape, and rule/reward reels, including deterministic backend authority and follow-up modifier hooks.
acceptance_criteria:
  - Document the authoring contract for chaos enemy family, encounter shape, and rule/reward effects.
  - Add backlog-ready work for richer combat modifiers such as bolstered enemies, ambush opening state, guaranteed loot, and Raw Chaos reward hooks.
  - Keep the current implementation deterministic and backend-authoritative while leaving room for catalog growth.
current_code_references:
  - documentation/02-systems-mvp/15-chaos-reel-combat-authoring.md

## Inventory And Reward UX Supporting The Roadmap

---
id: ISA-001
title: Add pagination to inventory collections
status: complete
priority: high
milestone: Inventory Scale and Actions
description: Dice and unit inventories now chunk large collections with stable page controls that preserve filtering, sorting, and clear empty/final-page states.
acceptance_criteria:
  - Dice inventory supports pagination or an equivalent chunking control.
  - Unit inventory supports pagination or an equivalent chunking control.
  - Empty, filtered, and final-page states are clear and stable.
  - Existing sort/filter behavior continues to work with pagination.
current_code_references:
  - frontend/src/app/pages/dice-page
  - frontend/src/app/pages/warband-page

---
id: ISA-002
title: Complete unlocked dice action affordances
status: complete
priority: medium
milestone: Inventory Scale and Actions
description: Dice inventory hides duplicate badge clutter, exposes salvage only after Wrong Machine recovery, and keeps Raw Chaos earning and controls behind the unlock.
acceptance_criteria:
  - Dice inspect modal includes salvage only after Wrong Machine unlock.
  - Players cannot earn or salvage Raw Chaos until Wrong Machine is unlocked.
  - Raw Chaos tracker appears in the controls area after Wrong Machine unlock.
  - Rarity and "Raw Chaos ready" badges are removed from dice inventory tiles.
  - Duplicate `.dg-proto-chip` information is removed where it does not add unique value.
current_code_references:
  - backend/src/Controllers/GameplayController.php
  - backend/src/Services/DiceSalvageService.php
  - backend/src/Services/RunLifecycleService.php
  - frontend/src/app/layout/command-controls
  - frontend/src/app/pages/dice-page

## Late July Roadmap Completion

---
id: OAR-001
title: Complete Wrong Machine opening arc dialogue coverage
status: complete
priority: high
milestone: Opening Arc Story Audit
description: Completed the Mountains, Swamps, and post-recovery Mystic Cave story coverage needed for the Wrong Machine opening arc, with repeat-run behavior remaining backend-owned and idempotent.
acceptance_criteria:
  - Mountains discovery and completion dialogue points players toward the Swamps.
  - Swamps includes opening, investigation, boss confrontation, and machine recovery coverage.
  - Post-recovery Mystic Cave dialogue explains Raw Chaos, lineage materials, and the first reconstruction path.
  - Repeat runs avoid replaying all one-time exposition beats.
current_code_references:
  - backend/src/Services/RunLifecycleService.php
  - backend/src/Services/RunGraphGenerator.php
  - documentation/07-roadmap/2026-07-25-completion-analysis.md

---
id: KPB-001
title: Verify owned-lineage random reward pools
status: complete
priority: high
milestone: Kin Pool and Balance Completion
description: Verified that random kin rewards use Basic Goblin plus account-owned lineages by default while allowing explicitly authored lineage grants.
acceptance_criteria:
  - Default accounts roll Basic Goblin as the eligible baseline.
  - Pig Kin unlocked accounts can roll Pig Kin through owned-lineage eligibility.
  - Explicit authored lineage grants remain allowed.
  - Backend coverage protects default, unlocked, and explicit-grant cases.
current_code_references:
  - backend/src/Services/LineageUnlockService.php
  - backend/tests/Integration
  - documentation/07-roadmap/2026-07-25-completion-analysis.md

---
id: KPB-002
title: Run representative Pig Kin balance simulation review
status: complete
priority: medium
milestone: Kin Pool and Balance Completion
description: Ran representative Basic Goblin and Pig Kin balance simulations across early-region contexts and documented the resulting no-blocker balance posture.
acceptance_criteria:
  - Representative simulations compare Basic Goblin and Pig Kin units in Farm, Mountains, and Swamps contexts.
  - Results identify stat or passive tuning risks before more kin are added.
  - Findings are documented with recommended balance changes or a clear no-change decision.
current_code_references:
  - documentation/07-roadmap/2026-07-25-completion-analysis.md

---
id: KPB-003
title: Plan legacy splice storage retirement
status: complete
priority: medium
milestone: Kin Pool and Balance Completion
description: Planned the compatibility-aware retirement of legacy `splice_variant` storage/API naming while keeping current durable fields intact.
acceptance_criteria:
  - Current `splice_variant` storage/API usage is inventoried.
  - A forward migration and response compatibility plan is documented.
  - No player-facing UI introduces new "splice" terminology.
  - Required tests before a future storage rename are named.
current_code_references:
  - documentation/07-roadmap/2026-07-25-completion-analysis.md

---
id: EPF-001
title: Define hazard and shrine effect primitives
status: complete
priority: high
milestone: Encounter Primitive Framework
description: Defined reusable hazard and shrine primitive vocabularies so encounter content can grow through authored rules instead of one-off handlers.
acceptance_criteria:
  - Hazard primitives cover HP attrition, temporary modifiers, currency/item pressure, route pressure, and kin-flavored mitigations.
  - Shrine primitives cover small rewards, cleansing, bargains, reroutes, and controlled risk.
  - Primitive definitions follow seed/catalog ownership rules.
  - Representative primitive resolution and idempotency are covered.
current_code_references:
  - backend/src/Combat/Engine
  - documentation/07-roadmap/2026-07-25-completion-analysis.md

---
id: EPF-002
title: Populate hazard nodes from authored rules
status: complete
priority: medium
milestone: Encounter Primitive Framework
description: Connected hazard node population to authored region/depth rules while preserving run graph guarantees.
acceptance_criteria:
  - Hazard selection respects region eligibility and weighting.
  - Generated runs remain connected and preserve boss, rest, loot, shrine, chaos, and exit guarantees.
  - Sparse-catalog fallback behavior is documented or protected by tests.
  - Backend generator coverage protects placement contracts.
current_code_references:
  - backend/src/Services/RunGraphGenerator.php
  - documentation/07-roadmap/2026-07-25-completion-analysis.md

---
id: ECP-001
title: Seed initial hazard catalog
status: complete
priority: medium
milestone: Encounter Content Pack
description: Seeded the initial ten-entry hazard catalog using approved hazard primitives and authored region eligibility.
acceptance_criteria:
  - Ten hazard definitions exist with stable slugs.
  - Each hazard uses approved hazard primitives.
  - Region eligibility, weight, title, and result copy are authored.
  - Enabled hazards resolve through supported primitives.
current_code_references:
  - documentation/07-roadmap/2026-07-25-completion-analysis.md

---
id: ECP-002
title: Seed initial shrine catalog
status: complete
priority: medium
milestone: Encounter Content Pack
description: Seeded the initial ten-entry shrine catalog using approved shrine primitives and authored reward/risk copy.
acceptance_criteria:
  - Ten shrine definitions exist with stable slugs.
  - Each shrine uses approved shrine primitives.
  - Region eligibility, weight, title, and result copy are authored.
  - Enabled shrines resolve through supported primitives.
current_code_references:
  - documentation/07-roadmap/2026-07-25-completion-analysis.md

---
id: ECP-003
title: Expand chaos reel catalogs
status: complete
priority: medium
milestone: Encounter Content Pack
description: Expanded enemy-family, encounter-shape, and rule/reward chaos reels to the launch breadth target while preserving Raw Chaos gating.
acceptance_criteria:
  - Enemy-family, encounter-shape, and rule/reward reels each contain ten enabled entries or documented launch equivalents.
  - Entries are weighted and eligible by region where appropriate.
  - Raw Chaos rewards remain gated behind Wrong Machine recovery.
  - Enabled reel entries can finalize into valid combat encounters.
current_code_references:
  - documentation/02-systems-mvp/15-chaos-reel-combat-authoring.md
  - documentation/07-roadmap/2026-07-25-completion-analysis.md

---
id: GIC-001
title: Add between-encounter unit healing consumables
status: complete
priority: low
milestone: General Inventory and Consumables
description: Added healing consumables through the generic item foundation with backend-authoritative spend and frontend run-surface actions.
acceptance_criteria:
  - Healing consumable definitions exist in the generic item catalog.
  - Players can use healing consumables only outside active combat resolution.
  - Item spending is backend-authoritative and transactional.
  - Frontend run surfaces expose the action where it naturally belongs.
current_code_references:
  - backend/src/Services/ItemInventoryService.php
  - frontend/src/app/pages/run-map-page
  - documentation/07-roadmap/2026-07-25-completion-analysis.md

---
id: GIC-002
title: Add player energy recovery consumables
status: complete
priority: low
milestone: General Inventory and Consumables
description: Added energy recovery consumables through the generic item foundation with backend-authoritative spend and energy-cap handling.
acceptance_criteria:
  - Energy consumable definitions exist in the generic item catalog.
  - Use rules respect energy caps and intended pacing.
  - Item spending and energy restoration are backend-authoritative.
  - Cap behavior, insufficient item cases, and duplicate requests are covered.
current_code_references:
  - backend/src/Services/ItemInventoryService.php
  - frontend/src/app/layout/command-controls
  - documentation/07-roadmap/2026-07-25-completion-analysis.md
