# ISSUES ARCHIVE
----
Completed issue entries retained only when they provide 7/25 roadmap execution context.

## First Pig Kin Demo Release

---
id: FPK-001
title: Align Wrong Machine kin creation and random drop eligibility
status: complete
priority: high
milestone: First Pig Kin Demo Release
description: Wrong Machine reconstruction now creates the requested kin/lineage while selecting the unit type from valid player-unlocked unit types, with a safe starter fallback for accounts that have no eligible unit-type unlocks.
acceptance_criteria:
  - Wrong Machine reconstruction always grants a unit with the reconstructed kin/lineage slug.
  - The granted unit type is randomly selected from the player's unlocked unit types instead of a fixed tutorial unit type.
  - If the player has no eligible unlocked unit types, reconstruction uses a deterministic safe starter fallback.
  - The first successful reconstruction unlocks that kin/lineage before or with the granted unit.
  - Random unit grants include the new kin only after the lineage unlock exists.
  - Locked kin never appear in ordinary random unit drops.
  - Tests cover first reconstruction, duplicate reconstruction, unlocked-unit-type selection, fallback behavior, and post-unlock random drop eligibility.
current_code_references:
  - backend/src/Services/WrongMachineReconstructionService.php
  - backend/src/Services/SpliceVariantService.php
  - backend/tests/Integration/WrongMachineReconstructionControllerTest.php
  - backend/tests/Integration/UserAssetGrantServiceIntegrationTest.php

## Pattern-V2 Run Map Generation

---
id: PV2-001
title: Seed database-owned Pattern-V2 tile catalog
status: complete
priority: high
milestone: Pattern-V2 Run Map Generation
description: Pattern-V2 starter, middle, connector/reward, and terminal tile definitions are seeded through forward-only migrations, with no JSON source-of-truth copy for V2 content.
acceptance_criteria:
  - Pattern-V2 definitions, region rules, and profiles load from database tables.
  - Connector cells remain edge/waypoint authoring metadata rather than runtime run nodes.
  - Request building for Pattern-V2 returns compiled grid tiles.
current_code_references:
  - backend/migrations
  - backend/src/Repositories/RunPatternCatalogRepository.php
  - backend/src/Services/RunPatternGenerationRequestBuilder.php

---
id: PV2-002
title: Validate Pattern-V2 grid catalog contracts
status: complete
priority: high
milestone: Pattern-V2 Run Map Generation
description: Grid catalogue validation now rejects malformed V2 definitions before composer work depends on them.
acceptance_criteria:
  - Invalid dimensions, grids, exits, connections, duplicate node keys, and connector runtime-node misuse are rejected.
  - DB-loaded V2 definitions are covered by validation tests.
current_code_references:
  - backend/src/Services/RunPatternV2GridCatalogValidator.php
  - backend/tests/Unit/RunPatternV2GridCatalogValidatorTest.php
  - backend/tests/Integration/RunPatternV2GridCatalogValidatorIntegrationTest.php

---
id: PV2-003
title: Implement Pattern-V2 tile composer
status: complete
priority: high
milestone: Pattern-V2 Run Map Generation
description: Pattern-V2 composes DB-loaded grid tiles into validated global run maps with connector waypoints, branch keys, generation coordinates, and runtime graph normalization.
acceptance_criteria:
  - Tiles compose within profile budgets and preserve forward progression.
  - Required boss, exit, reachability, and no-crossing constraints are validated.
  - Composer output is deterministic by seed and available through explicit Pattern-V2 requests.
current_code_references:
  - backend/src/Services/RunPatternV2TileComposerService.php
  - backend/src/Services/RunGraphValidationService.php
  - backend/src/Services/RunGraphGenerator.php
  - backend/tests/Unit/RunPatternV2TileComposerServiceTest.php

---
id: PV2-004
title: Add Pattern-V2 preview and simulation gates
status: complete
priority: medium
milestone: Pattern-V2 Run Map Generation
description: Preview, inspection, comparison, and simulation tooling expose Pattern-V2 validity, branch, shape, cost, pattern-frequency, and boss-path metrics for rollout review.
acceptance_criteria:
  - Simulation reports occupied rows/columns, branch count, fallback rate, validation failures, and boss path metrics.
  - Gates fail on invalid graphs, excessive width, insufficient rows, weak branch counts, or long straight routes.
  - Docker shortcuts run Mountains and Swamps V2 gates and V1/V2 comparisons.
current_code_references:
  - backend/bin/simulate-run-patterns.php
  - backend/bin/inspect-run-patterns.php
  - backend/bin/compare-run-generators.php
  - backend/src/Services/RunPatternSimulationService.php
  - package.json

---
id: PV2-005
title: Opt Mountains into Pattern-V2 maps
status: complete
priority: medium
milestone: Pattern-V2 Run Map Generation
description: Mountains can run through Pattern-V2 via generator selection and local UAT opt-in, with API contract tests proving persisted generation metadata reaches the shared run-map renderer.
acceptance_criteria:
  - Runtime selection supports Mountains Pattern-V2 opt-in.
  - Mountains Pattern-V2 gate and comparison evidence pass deterministic seed suites.
  - Current-run API exposes Pattern-V2 node coordinates and connector waypoint edge metadata.
current_code_references:
  - backend/src/Services/RunGeneratorVersionSelector.php
  - backend/tests/Integration/PatternV2RuntimeApiContractIntegrationTest.php
  - docker-compose.yml
  - backend/.env.example

---
id: PV2-006
title: Opt Swamps into Pattern-V2 maps
status: complete
priority: high
milestone: Pattern-V2 Run Map Generation
description: Swamps now has migration-seeded Pattern-V2 content and local UAT opt-in, preserving required boss, exit, rest, chaos, hazard, shrine, and reward contracts.
acceptance_criteria:
  - Swamps Pattern-V2 definitions, rules, and profile are migration-seeded.
  - Swamps deterministic gates pass with no fallback, no backward traversal, no crossing edges, and required row/branch shape.
  - Current-run API contract covers Swamps Pattern-V2 rendering metadata.
current_code_references:
  - backend/migrations
  - backend/tests/Integration/RunPatternGenerationRequestBuilderIntegrationTest.php
  - backend/tests/Integration/PatternV2RuntimeApiContractIntegrationTest.php
  - package.json

---
id: PV2-007
title: Move remaining biomes to the consistent run rendering path
status: complete
priority: medium
milestone: Pattern-V2 Run Map Generation
description: Farm and Mystic Cave remain authored linear graphs while exposing `fixed-v1` provenance and node coordinates for the same frontend renderer contract used by Pattern-V2 maps.
acceptance_criteria:
  - Farm and Mystic Cave expose generation metadata and coordinates.
  - Frontend map rendering consumes generation coordinates when present.
  - Regression tests cover Farm, Mystic Cave, Mountains Pattern-V2, and Swamps Pattern-V2 renderer-facing graph contracts.
current_code_references:
  - backend/src/Services/RunGraphGenerator.php
  - backend/tests/Integration/RunGraphGeneratorIntegrationTest.php
  - frontend/src/app/pages/run-map-page/run-map-page.component.ts

## Pattern-Based Run Map Generation

---
id: PBM-001
title: Implement Pattern-V1 assembler behind generator version
status: complete
priority: medium
milestone: Pattern-Based Run Map Generation
description: Pattern-V1 exists as a separate versioned generator path with topology assembly, encounter binding, validation, provenance, and deterministic test coverage.
acceptance_criteria:
  - Pattern-V1 builds start, spine, boss/exit, branch, cap, and encounter-bound topology.
  - Invalid graphs fail before persistence.
  - Generation provenance records version, profile, catalogue, attempt, and summary metadata.
current_code_references:
  - backend/src/Services/RunGraphGenerator.php
  - backend/src/Services/RunPatternPreviewAssemblerService.php
  - backend/tests/Integration/RunGraphGeneratorIntegrationTest.php

---
id: PBM-002
title: Opt Mountains into Pattern-V1 maps
status: complete
priority: medium
milestone: Pattern-Based Run Map Generation
description: Mountains received Pattern-V1 catalogue/profile content and deterministic comparison/gate evidence before the later Pattern-V2 opt-in superseded it for local UAT.
acceptance_criteria:
  - Mountains Pattern-V1 preserves required node, boss, exit, story, and reward contracts.
  - Simulation reports node count, branch count, spine depth, fallback rate, and validation failures.
current_code_references:
  - backend/data/run-patterns
  - backend/bin/simulate-run-patterns.php
  - documentation/06-testing-release/evidence/01-mountains-pattern-v1-gate-evidence.md

---
id: PBM-003
title: Opt Swamps into Pattern-V1 maps
status: complete
priority: medium
milestone: Pattern-Based Run Map Generation
description: Swamps received wider Pattern-V1 catalogue/profile content and deterministic gate evidence before the later Pattern-V2 opt-in superseded it for local UAT.
acceptance_criteria:
  - Swamps Pattern-V1 preserves required rest, chaos, boss, exit, story, reward, and path-length contracts.
  - Simulation covers Swamps-specific branch, recovery, and reward distribution.
current_code_references:
  - backend/data/run-patterns
  - backend/bin/simulate-run-patterns.php
  - documentation/06-testing-release/evidence/02-swamps-pattern-v1-gate-evidence.md

---
id: PBM-004
title: Migrate story placement into generation requests
status: complete
priority: medium
milestone: Pattern-Based Run Map Generation
description: Pattern paths resolve start, before-boss, and before-exit story placement requests before graph validation and persistence.
acceptance_criteria:
  - User-specific story requirements are resolved before topology assembly.
  - Pattern story placements do not bypass required boss or exit routes.
current_code_references:
  - backend/src/Services/RunGraphGenerator.php
  - backend/tests/Integration/RunGraphGeneratorIntegrationTest.php
  - documentation/02-systems/05-run-node-generation.md

---
id: PBM-005
title: Add pattern generation debug and simulation gates
status: complete
priority: medium
milestone: Pattern-Based Run Map Generation
description: Pattern simulation and inspection tooling expose validation, fallback, branch, backtrack, duration, frequency, occupied-coordinate, and boss-path metrics.
acceptance_criteria:
  - Debug tooling reports generation provenance and shape metrics.
  - Simulation gates fail on invalid graphs, fallback, excessive backtracks, weak branches, or long straight routes.
current_code_references:
  - backend/src/Services/RunPatternSimulationService.php
  - backend/bin/inspect-run-patterns.php
  - backend/bin/compare-run-generators.php
  - package.json

## UAT Feedback Fix Round 2

---
id: UAT2-001
title: Repair run event node resolution and visibility
status: complete
priority: high
milestone: UAT Feedback Fix Round 2
description: Rest, shrine, and hazard nodes now resolve with refreshed unit state and player-readable non-combat result copy, without the old extra approach step for shrine and hazard nodes.
acceptance_criteria:
  - Rest finalize returns refreshed run unit HP state and the frontend consumes it.
  - Shrine and hazard nodes resolve directly from the map node screen.
  - Shrine and hazard result screens show labels, details, and encounter result payloads.
  - Run-node and rest-page tests cover the behavior.
current_code_references:
  - backend/src/Combat/Engine/DeterministicRunNodeResolver.php
  - backend/src/Repositories/RunRepository.php
  - frontend/src/app/pages/run-node-page/run-node-page.component.ts
  - frontend/src/app/pages/run-rest-page/run-rest-page.component.ts

---
id: UAT2-002
title: Fix chaos reel encounter application
status: complete
priority: high
milestone: UAT Feedback Fix Round 2
description: Chaos reels now apply finalized enemy-family, encounter-shape, rule, and reward effects through backend-owned chaos encounter results.
acceptance_criteria:
  - Enemy-family reels can select matching encounter families across regions.
  - Encounter-shape and rule/reward reels are applied to finalized battle and reward payloads.
  - Chaos battle previews and logs expose applied reel effects for UAT.
  - Backend integration tests cover cross-biome reel behavior.
current_code_references:
  - backend/src/Services/ChaosEncounterService.php
  - backend/src/Controllers/RunNodeController.php
  - backend/tests/Integration/ChaosEncounterControllerIntegrationTest.php
  - frontend/src/app/pages/run-node-page/run-node-page.component.ts

---
id: UAT2-003
title: Surface active run effects
status: complete
priority: high
milestone: UAT Feedback Fix Round 2
description: Current-run payloads expose active shrine, hazard, and chaos effects, and the run map presents those effects near the map controls.
acceptance_criteria:
  - Backend exposes current active run effects.
  - Run map shows active effect names and concise descriptions.
  - Run node result screens identify visible immediate or persistent effect details.
  - Tests cover at least one active run effect.
current_code_references:
  - backend/src/Controllers/ApiController.php
  - backend/src/Repositories/RunRepository.php
  - frontend/src/app/pages/run-map-page/run-map-page.component.ts
  - frontend/src/app/pages/run-map-page/run-map-page.component.html

---
id: UAT2-004
title: Add post-Wrong-Machine mountain dialogue
status: complete
priority: medium
milestone: UAT Feedback Fix Round 2
description: The Whim, Mystic Cave, and mountain kobold dialogue now branch after Wrong Machine recovery through feature-gated dialogue placement.
acceptance_criteria:
  - The Whim and mountain kobolds have post-Wrong-Machine branches.
  - Dialogue unlock requirements prevent recovery branches from appearing early.
  - Run graph and dialogue tests cover branch availability.
current_code_references:
  - backend/src/Services/RunGraphGenerator.php
  - backend/tests/Integration/RunGraphGeneratorIntegrationTest.php
  - frontend/public/assets/data/dialogue/dialogue-scripts.json
  - documentation/02-systems/04-dialogue-flow-determination.md

---
id: UAT2-005
title: Reflavor voluntary run return
status: complete
priority: medium
milestone: UAT Feedback Fix Round 2
description: Voluntary run exit is now framed as Return Home / Returned Home instead of abandon/failure language.
acceptance_criteria:
  - Run map action is labeled `Return Home`.
  - Run summary title for abandoned status is player-facing as `Returned Home`.
  - Service summary state uses the returned-home copy.
  - Tests cover the updated title.
current_code_references:
  - frontend/src/app/pages/run-map-page/run-map-page.component.html
  - frontend/src/app/pages/run-summary-page/run-summary-page.component.ts
  - frontend/src/app/core/services/run/run.service.ts
  - frontend/src/app/pages/run-summary-page/run-summary-page.component.spec.ts

## UAT Feedback Fix Round 1

---
id: UAT1-001
title: Polish home navigation and command controls
status: complete
priority: medium
milestone: UAT Feedback Fix Round 1
description: Home navigation and command controls were cleaned up with corrected breadcrumbs, less utility clutter, a Raw Chaos tracker icon, and animated dropdown behavior.
acceptance_criteria:
  - Home breadcrumbs omit the extra HQ link.
  - Removed formation, map, and unlock summary cards from home utilities.
  - Raw Chaos tracker uses an icon after Wrong Machine unlock.
  - Dropdown menu opens with slide-down/fade-in animation.
current_code_references:
  - frontend/src/app/pages/home-page/home-page.component.html
  - frontend/src/app/layout/page-frame/page-frame.component.ts
  - frontend/src/app/layout/command-controls/command-controls.component.ts
  - frontend/src/app/layout/command-controls/command-controls.component.scss

---
id: UAT1-002
title: Refine warband, unit, and squad-edit UX
status: complete
priority: high
milestone: UAT Feedback Fix Round 1
description: Warband filtering, unit card density, unit stat explanations, and squad drag/drop feedback were improved for UAT.
acceptance_criteria:
  - Warband filters units by squad assignment.
  - Warband cards no longer show full stat blocks.
  - Slot marker appears inline with level.
  - Unit stat hover tooltips explain each stat.
  - Squad edit supports long available-unit lists and obvious drop-target feedback.
current_code_references:
  - frontend/src/app/pages/warband-page/warband-page.component.ts
  - frontend/src/app/pages/unit-details-page/unit-details-page.component.scss
  - frontend/src/app/pages/squad-details-page/squad-details-page.component.html
  - frontend/src/app/pages/squad-details-page/squad-details-page.component.scss

---
id: UAT1-003
title: Clean up shop and academy presentation
status: complete
priority: medium
milestone: UAT Feedback Fix Round 1
description: Shop and academy presentation now remove duplicate currency copy and use the available iconography and tier presentation.
acceptance_criteria:
  - Shop second daily deal title omits `Deal 2:`.
  - Shop and academy remove redundant tooth indicators.
  - Academy uses available icons and tier icons.
  - Placeholder role/future-recruit copy is removed.
current_code_references:
  - frontend/src/app/pages/shop-page/shop-page.component.html
  - frontend/src/app/pages/shop-page/shop-page.component.spec.ts
  - frontend/src/app/pages/academy-page/academy-page.component.html
  - frontend/src/app/pages/academy-page/academy-page.component.ts

---
id: UAT1-004
title: Repair guide navigation and combat reference content
status: complete
priority: high
milestone: UAT Feedback Fix Round 1
description: Guide navigation, map glossary, starter class list, and player-readable combat/action explanation content were repaired.
acceptance_criteria:
  - Guide sidenav links scroll to intended sections.
  - Map glossary includes current run-map icon types.
  - Starter classes only show Bruiser and Marksman.
  - Guide explains unit action determination and combat calculation.
current_code_references:
  - frontend/src/app/pages/guide-page/guide-page.component.ts
  - frontend/src/app/pages/guide-page/guide-page.component.html
  - frontend/src/app/pages/guide-page/guide-page.component.spec.ts

## Combat Resolution Correctness

---
id: CRC-001
title: Remove score-based combat outcome fallback
status: complete
priority: high
milestone: Combat Resolution Correctness
description: Combat outcomes now come from simulated battle events instead of player/enemy score estimates.
acceptance_criteria:
  - Combat outcome is determined by simulated events, not by a score fallback.
  - Score fallback metadata and result initialization are removed.
  - Integration coverage proves long combat resolves through events.
current_code_references:
  - backend/src/Combat/Engine/DeterministicRunNodeResolver.php
  - backend/tests/Integration/DeterministicRunNodeResolverFormationIntegrationTest.php
  - documentation/02-systems/03-combat-resolution.md

---
id: CRC-002
title: Remove arbitrary combat round cutoff
status: complete
priority: high
milestone: Combat Resolution Correctness
description: Combat now continues until a terminal event state or explicit engine safety cap instead of the old 3-5 round planning window.
acceptance_criteria:
  - The ordinary 3-5 round cutoff is removed.
  - The safety cap is exceptional engine protection.
  - Battle logs still report actual round and tick timing.
current_code_references:
  - backend/src/Combat/Engine/DeterministicRunNodeResolver.php
  - backend/tests/Integration/DeterministicRunNodeResolverFormationIntegrationTest.php
  - documentation/02-systems/03-combat-resolution.md

---
id: CRC-003
title: Require explicit ability sets for every combatant
status: complete
priority: high
milestone: Combat Resolution Correctness
description: Combatants with missing, empty, unknown, or passive-only active schedules now fail validation instead of receiving a hidden basic attack.
acceptance_criteria:
  - The automatic basic attack schedule fallback is removed.
  - Missing or unschedulable ability sets fail before combat proceeds.
  - Player loadout mutation rejects empty active schedules before deleting existing rows.
current_code_references:
  - backend/src/Combat/Engine/DeterministicRunNodeResolver.php
  - backend/src/Services/UnitLoadoutService.php
  - backend/tests/Unit/Combat/DeterministicRunNodeResolverPrimitivesTest.php
  - backend/tests/Integration/UnitLoadoutServiceIntegrationTest.php

---
id: CRC-004
title: Remove automatic tick autofill behavior
status: complete
priority: high
milestone: Combat Resolution Correctness
description: Ability scheduling now preserves authored gaps and no longer auto-fills unused ticks with repeatable filler actions.
acceptance_criteria:
  - Only explicit equipped/authored abilities are scheduled.
  - Sparse schedules retain unused ticks.
  - Tests assert no hidden filler actions are inserted.
current_code_references:
  - backend/src/Combat/Engine/DeterministicRunNodeResolver.php
  - backend/tests/Integration/BattleNodeResolutionIntegrationTest.php
  - documentation/02-systems/03-combat-resolution.md

---
id: CRC-005
title: Apply full dice roll values in combat math
status: complete
priority: high
milestone: Combat Resolution Correctness
description: Combat dice now contribute the full rolled value, with trace copy describing contribution instead of centered modifiers.
acceptance_criteria:
  - Combat dice math applies full roll totals.
  - Battle traces expose player-readable full-roll contribution.
  - Tests cover exploding dice and slot contribution trace behavior.
current_code_references:
  - backend/src/Combat/Engine/DeterministicRunNodeResolver.php
  - backend/tests/Unit/Combat/DeterministicRunNodeResolverPrimitivesTest.php
  - backend/tests/Integration/BattleNodeResolutionIntegrationTest.php
  - documentation/02-systems/03-combat-resolution.md

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
  - documentation/02-systems/mvp-reference/13-wrong-machine-and-kin.md

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
  - documentation/05-technical/03-backend-api-contracts.md
  - documentation/05-technical/04-data-model.md

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
  - documentation/05-technical/03-backend-api-contracts.md

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
  - documentation/02-systems/mvp-reference/13-wrong-machine-and-kin.md

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
  - documentation/02-systems/mvp-reference/01-dice-system.md

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
  - documentation/02-systems/mvp-reference/03-encounter-scope.md

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
  - documentation/05-technical/03-backend-api-contracts.md
  - documentation/05-technical/04-data-model.md

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
  - documentation/02-systems/mvp-reference/03-encounter-scope.md

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
  - documentation/05-technical/03-backend-api-contracts.md
  - documentation/05-technical/04-data-model.md

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
  - documentation/02-systems/mvp-reference/15-chaos-reel-combat-authoring.md

## Inventory And Reward UX Supporting The Roadmap

---
id: GIC-001
title: Add between-encounter unit healing consumables
status: complete
priority: low
milestone: General Inventory and Consumables
description: Added spendable healing consumables to the generic item catalog, a transactional backend run-unit healing endpoint, and a run-map supplies panel for wounded active-run units.
acceptance_criteria:
  - Healing consumable definitions exist in the generic item catalog.
  - Players can use healing consumables only outside active combat resolution.
  - Item spending is backend-authoritative, transactional, and idempotent where retries are possible.
  - Frontend inventory or run surfaces expose the action where it naturally belongs.
current_code_references:
  - backend/migrations/75_seed_healing_consumables.sql
  - backend/src/Services/ConsumableItemService.php
  - backend/src/Controllers/GameplayController.php
  - backend/tests/Integration/ConsumableItemServiceIntegrationTest.php
  - frontend/src/app/pages/run-map-page
  - frontend/src/app/core/services/run/run.service.ts

---
id: GIC-002
title: Add player energy recovery consumables
status: complete
priority: low
milestone: General Inventory and Consumables
description: Added spendable energy consumables to the generic item catalog, a transactional backend restore endpoint, and a compact energy-slot action that uses owned energy items below cap.
acceptance_criteria:
  - Energy consumable definitions exist in the generic item catalog.
  - Use rules respect energy caps and do not bypass intended pacing.
  - Item spending and energy restoration are backend-authoritative.
  - Tests cover cap behavior, insufficient item cases, and duplicate requests.
current_code_references:
  - backend/migrations/76_seed_energy_consumables.sql
  - backend/src/Services/ConsumableItemService.php
  - backend/src/Controllers/GameplayController.php
  - backend/tests/Integration/ConsumableItemServiceIntegrationTest.php
  - frontend/src/app/layout/command-controls

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
  - documentation/07-development-path/2026-07-25-completion-analysis.md

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
  - documentation/07-development-path/2026-07-25-completion-analysis.md

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
  - documentation/07-development-path/2026-07-25-completion-analysis.md

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
  - documentation/07-development-path/2026-07-25-completion-analysis.md

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
  - documentation/07-development-path/2026-07-25-completion-analysis.md

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
  - documentation/07-development-path/2026-07-25-completion-analysis.md

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
  - documentation/07-development-path/2026-07-25-completion-analysis.md

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
  - documentation/07-development-path/2026-07-25-completion-analysis.md

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
  - documentation/02-systems/mvp-reference/15-chaos-reel-combat-authoring.md
  - documentation/07-development-path/2026-07-25-completion-analysis.md

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
  - documentation/07-development-path/2026-07-25-completion-analysis.md

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
  - documentation/07-development-path/2026-07-25-completion-analysis.md

## UAT Feedback Fix Round 1

---
id: UAT-001
title: Polish landing, home, and menu affordances
status: complete
priority: high
milestone: UAT Feedback Fix Round 1
description: Addressed landing/home/menu UAT findings by making the landing logo the visible brand mark, clarifying the guide action, removing redundant home utility chips, suppressing the extra home breadcrumb, adding a Raw Chaos HUD icon, and animating dropdown menu entry.
acceptance_criteria:
  - Landing page replaces the visible H1 text treatment with the existing logo treatment.
  - The "How to play" action is visually distinguishable as a link/button.
  - Home breadcrumbs do not include an `HQ` link.
  - Home utilities remove the formation, map, and unlocks section.
  - Raw Chaos has a proper icon wherever it is tracked in the command controls.
  - Dropdown/menu opening uses a small slide-down plus fade-in animation without flicker.
current_code_references:
  - frontend/src/app/pages/landing-page
  - frontend/src/app/pages/home-page
  - frontend/src/app/layout/page-frame
  - frontend/src/app/layout/command-controls

## Pattern-Based Run Map Generation

---
id: PRG-001
title: Add pattern catalog schema and validation
status: complete
priority: medium
milestone: Pattern-Based Run Map Generation
description: Added authored run-pattern source files, runtime catalog storage, sync tooling, validator coverage, variant compilation, and repository access without changing live region generation.
acceptance_criteria:
  - Structured pattern/profile source files are introduced under the documented `backend/data/run-patterns/` ownership model.
  - Runtime catalog storage or sync behavior is defined without making raw SQL the primary authoring surface.
  - Pattern, socket, transform, region-rule, fallback-set, and profile validation are implemented.
  - Validation proves enabled patterns have legal node keys, sockets, internal edges, transforms, and phase rules.
  - No live region generation behavior changes in this foundation slice.
current_code_references:
  - backend/data/run-patterns/
  - backend/migrations/78_run_pattern_catalog_storage.sql
  - backend/src/Services/RunPatternCatalogValidator.php
  - backend/src/Services/RunPatternCatalogSyncService.php
  - backend/src/Services/RunPatternVariantCompiler.php
  - backend/src/Repositories/RunPatternCatalogRepository.php

## Shrine Expansion

---
id: SHR-001
title: Add generated quality-weighted shrine effects
status: complete
priority: high
milestone: Shrine Expansion
description: Added backend-generated shrine outcomes selected from region and quality weighted pools, with persisted reward/log results and claim-time effects for teeth, healing, route clearing, next-combat modifiers, double teeth, and unit upgrades.
acceptance_criteria:
  - Shrine nodes generate an effect at encounter time from region, run seed, node id, and quality.
  - Shrine node metadata stores quality/rendering context only, not preselected effect slugs.
  - Poor, good, and great shrine qualities have different weighted effect pools.
  - Generated shrine results persist through battle logs/rewards so repeated claims are idempotent.
  - Backend tests cover effect generation and claim-time application.
current_code_references:
  - backend/src/Services/EncounterPrimitiveCatalog.php
  - backend/src/Combat/Engine/DeterministicRunNodeResolver.php
  - backend/src/Services/RunLifecycleService.php
  - backend/tests/Integration/RunLifecycleServiceIntegrationTest.php

---
id: SHR-002
title: Add declineable shrine offer flow
status: complete
priority: high
milestone: Shrine Expansion
description: Added accept and decline claim decisions for costly shrine bargains, including frontend actions, backend guards, and idempotent declined claim snapshots.
acceptance_criteria:
  - Shrine nodes with costs generate a persisted offer before applying effects.
  - The frontend presents accept and decline actions for costly shrines.
  - Declining clears or exits the shrine according to the chosen design without applying the positive or negative effect.
  - Accepting applies both cost and reward exactly once.
  - API and frontend tests cover accept, decline, refresh, and idempotent repeat calls.
current_code_references:
  - backend/src/Controllers/BattleController.php
  - backend/src/Services/RunLifecycleService.php
  - frontend/src/app/pages/run-node-page/run-node-page.component.ts
  - frontend/src/app/core/services/run/run.service.ts

---
id: SHR-003
title: Consume shrine combat modifiers in battle resolution
status: complete
priority: medium
milestone: Shrine Expansion
description: Added generic next-combat run modifiers for damage, attack, defense, precision, and resolve, with combat log metadata and one-combat consumption.
acceptance_criteria:
  - `squad_damage_next_combat` increases squad damage by the authored multiplier for the next combat-like node.
  - The modifier is consumed after one eligible combat.
  - Combat logs identify the shrine modifier contribution.
  - Tests prove the modifier applies once and then expires.
current_code_references:
  - backend/src/Services/RunCombatModifierService.php
  - backend/src/Combat/Engine/DeterministicRunNodeResolver.php
  - backend/src/Controllers/RunNodeController.php
  - backend/tests/Unit/RunCombatModifierServiceTest.php

---
id: SHR-004
title: Add shrine unit-upgrade reward effect
status: complete
priority: medium
milestone: Shrine Expansion
description: Added the Borrowed Future shrine effect that upgrades one eligible unit gained earlier in the run to the next authored unit-type tier and reports the result in claim snapshots and run summaries.
acceptance_criteria:
  - The shrine can select one unit gained during the current run.
  - The selected unit is upgraded or rerolled to a higher tier according to authored tier rules.
  - Reward preview, claim response, and run summary show the upgraded unit clearly.
  - Tests cover no eligible unit, one eligible unit, and multiple eligible units.
current_code_references:
  - backend/src/Services/EncounterPrimitiveCatalog.php
  - backend/src/Services/RunLifecycleService.php
  - backend/src/Support/RunSummaryBuilder.php
  - backend/tests/Integration/RunLifecycleServiceIntegrationTest.php
