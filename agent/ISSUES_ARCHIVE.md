# ISSUES ARCHIVE
----

## Purpose
- This file is intentionally kept lean.
- Historical issue records can be retrieved from git when needed.

---
id: HDC-002
title: Coalesce unit type ability package fields
status: complete
priority: high
milestone: Hybrid Seed Catalog Ownership
description: Unit type seed data duplicated authored ability packages between `ability_set_json` and `promotion_grants_json`, and still carried obsolete unit-level dice capacity through `max_equipped_dice`.
acceptance_criteria:
  - Use `ability_set_json` as the single database source for unit type authored abilities.
  - Derive promotion preview grant data from destination `ability_set_json`.
  - Add a forward migration that preserves residual grant data before dropping `promotion_grants_json`.
  - Drop `max_equipped_dice` now that dice capacity is derived from equipped abilities and ability slot costs.
  - Regenerate schema snapshots and keep docs aligned.
current_code_references:
  - backend/migrations/71_coalesce_unit_type_ability_sets.sql
  - backend/migrations/72_drop_unit_type_max_equipped_dice.sql
  - backend/src/Services/UnitLoadoutService.php
  - backend/src/Services/PromotionService.php
  - backend/src/Repositories/UnitRepository.php

---
id: HDC-001
title: Classify database tables by data ownership model
status: complete
priority: high
milestone: Hybrid Seed Catalog Ownership
description: Seeded and runtime tables needed a shared classification for database-owned, code/config-owned, and hybrid-owned data.
acceptance_criteria:
  - Add a canonical architecture document that defines database, code/config, and hybrid ownership criteria.
  - Classify every current table in the project by ownership model.
  - Identify near-term candidates for codification or hybrid contract enforcement.
  - Update the active roadmap so the seed browser is marked complete and hybrid catalog cleanup is the current planning lane.
  - Preserve runtime/player-state tables as database-owned unless a concrete reason says otherwise.
current_code_references:
  - documentation/01-architecture/08-seed-catalog-ownership.md
  - documentation/07-roadmap/00-gameplay-systems-roadmap.md
  - agent/ISSUES.md
  - agent/MILESTONES.md

---
id: DSB-001
title: Add read-only seeded table browser to debug panel
status: complete
priority: medium
milestone: Developer Support: Seed Catalog Browser
description: Seeded catalog data was difficult to review without direct SQL access or source spelunking.
acceptance_criteria:
  - Add a read-only backend debug endpoint for allowlisted seeded tables.
  - Include table metadata such as supported table names, labels, row counts, and columns.
  - Add a debug-panel UI for choosing a table and inspecting seeded rows.
  - Refuse unknown table names and avoid any edit/delete/write behavior.
  - Document the seeded table browser contract and add backend/frontend coverage.
current_code_references:
  - backend/src/Controllers/DebugController.php
  - backend/src/Services/DevToolsService.php
  - frontend/src/app/pages/debug-page/debug-page.component.ts
  - frontend/src/app/pages/debug-page/debug-page.component.html
  - frontend/src/app/core/services/debug/debug.service.ts

---
id: LA-001
title: Document local-auth roadmap and auth contract
status: complete
priority: high
milestone: Local Account Authentication
description: The active roadmap needed to reflect the product access blocker caused by Discord-only authentication on restricted networks.
acceptance_criteria:
  - Update active milestones and issues so local auth is visible as the implementation lane.
  - Update authentication, API, UX, and data-model docs to describe local credentials beside Discord OAuth.
  - Keep backend structural cleanup visible as the next follow-up work.
current_code_references:
  - agent/MILESTONES.md
  - agent/ISSUES.md
  - documentation/01-architecture/01-authentication-and-sessions.md
  - documentation/01-architecture/03-backend-api-contracts.md
  - documentation/03-ux/09-first-session-player-journey.md

---
id: LA-002
title: Add local registration and login API
status: complete
priority: high
milestone: Local Account Authentication
description: Discord OAuth was the only account path, preventing players on networks that block Discord from registering or signing in.
acceptance_criteria:
  - Add schema support for local email/password credentials without breaking existing Discord users.
  - Add registration and login endpoints that establish the existing PHP session and CSRF flow.
  - Store password hashes only; never store raw passwords.
  - Return stable validation, duplicate-account, and invalid-credential errors.
  - Preserve Discord OAuth behavior.
current_code_references:
  - backend/migrations
  - backend/src/Controllers/AuthController.php
  - backend/src/Repositories/UserRepository.php
  - backend/public/index.php
  - backend/src/Services/SessionService.php

---
id: LA-003
title: Add local account controls to login page
status: complete
priority: high
milestone: Local Account Authentication
description: The login page only offered Discord, so players needed visible local registration and sign-in controls that enter the same authenticated shell.
acceptance_criteria:
  - Add local sign-in and registration controls on /login.
  - Keep Discord sign-in available.
  - Refresh the existing session/profile state after successful local auth and route into the app.
  - Show concise validation/error feedback for failed local auth attempts.
  - Cover the new login controls with focused frontend tests.
current_code_references:
  - frontend/src/app/pages/landing-page/landing-page.component.ts
  - frontend/src/app/pages/landing-page/landing-page.component.html
  - frontend/src/app/pages/landing-page/landing-page.component.scss
  - frontend/src/app/core/services/session/session.service.ts

---
id: BSC-001
title: Extract shared roster grant services
status: complete
priority: high
milestone: Backend Structural Cleanup Pass
description: Unit and dice creation lived in battle rewards, shop purchases, debug tools, and starter grants. Shared grant services now own unit naming, loadout initialization, dice affix assignment, and fixed-affix daily deal minting so those flows stop drifting.
acceptance_criteria:
  - Create shared backend services for granting owned units and owned dice.
  - Move at least the battle reward and shop flows onto the shared grant path.
  - Keep starter pack and debug flows aligned or explicitly staged for the next issue.
  - Retain current gameplay behavior and response payload shapes.
  - Add or update targeted backend coverage for the shared grant path where practical.
current_code_references:
  - backend/src/Controllers/RunNodeController.php
  - backend/src/Controllers/ShopController.php
  - backend/src/Services/GrantService.php
  - backend/src/Services/DevToolsService.php

---
id: UPR-003
title: Implement Tier 1 and Tier 2 unit ability packages
status: complete
priority: high
milestone: Unit Progression Rework
description: The unit roster needs updated ability packages so Tier 2 branches feel specialized and capstone choices create long-term lineage decisions. Tier 1 units need passive capstone choices, while Tier 2 branches need immediate active/passive unlocks plus level 10 passive capstones.
acceptance_criteria:
  - Implement Bruiser capstones: Brawl Hardened and Finisher.
  - Implement Enforcer promotion package: Skullcrack and Menacing Follow-Through.
  - Implement Enforcer capstones: No Mercy and Brutal Suppression.
  - Implement Pit Fighter promotion package: Desperate Swing and Counterpunch.
  - Implement Pit Fighter capstones: Last Goblin Standing and Crowd Favorite, with Crowd Favorite capped at 5 stacks.
  - Implement Marksman capstones: Patient Aim and Pick Your Mark, with targeting support.
  - Implement Deadeye promotion package: Piercing Shot and Vantage Point.
  - Implement Deadeye capstones: Kill Lane and Armor Gap.
  - Implement Trapper promotion package: Mark Target and Treasure Sense.
  - Implement Trapper capstones: Exposed Weaknesses and Barbed Mark.
  - Implement Guardian capstones: Bodyguard and Hold the Line.
  - Implement Bulwark promotion package: Taunting Guard and Shield Set.
  - Implement Bulwark capstones: Unmoving and Wall of Scrap.
  - Implement Shieldbreaker promotion package: Crack Armor and Find the Gap.
  - Implement Shieldbreaker capstones: Shatter Plate and Break Open.
  - Implement Bannerbearer capstones: Rally Rhythm and Patch Job.
  - Implement Warcaller promotion package: Warcry and Battle Tempo.
  - Implement Warcaller capstones: Chant of Violence and Mob Mentality.
  - Implement Mascot promotion package: Lucky Chant and Attention Hog.
  - Implement Mascot capstones: Dumb Luck and Morale Goblin.
  - Implement Saboteur capstones: Toxic Tools and Spiteful Reflex.
  - Implement Trickshot promotion package: Disarming Shot and Opportunist.
  - Implement Trickshot capstones: Disabling Hit and Clean Shot.
  - Implement Plaguehand promotion package: Poison Cloud and Nerve Toxin.
  - Implement Plaguehand capstones: Lingering Cloud and Sickly Weakness.
  - Do not introduce Quartermaster as part of this rework.
  - Keep all tuning conservative and centralized enough for later balancing.
current_code_references:
  - documentation/02-systems-mvp/11-unit-progression-rework.md
  - backend/migrations/30_seed_unit_types.sql
  - backend/src/Combat/Abilities/AbilityRegistry.php
  - backend/tests/Unit/Combat/AbilityHandlerRegistryCoverageTest.php

---
id: UPR-004
title: Add capstone and promotion UX
status: complete
priority: high
milestone: Unit Progression Rework
description: The player needs to understand the promote-early versus master-first decision. Since normal level-up is not interactive, the UI needs clear capstone selection and promotion-preview behavior without adding friction to every level gain.
acceptance_criteria:
  - Unit detail view shows current level, max level, promotion eligibility, mastery state, selected capstone, and inherited abilities.
  - Level 10 units can choose one passive capstone from two options.
  - If a level 10 unit starts promotion without a selected capstone, the UI requires capstone choice before confirming promotion.
  - Promotion preview warns when promoting before level 10 will skip the current unit type capstone.
  - Promotion preview shows the active and passive abilities granted immediately by the selected Tier 2 or Tier 3 type.
  - Promotion preview shows inherited passives and capstones clearly.
  - Copy uses player-facing language for wounded, capstone, mastered, inherited, and promotion eligibility.
  - The UX remains usable on mobile after the current mobile milestone work.
current_code_references:
  - documentation/02-systems-mvp/11-unit-progression-rework.md
  - frontend/src/app/pages/unit-details-page/*
  - frontend/src/app/pages/squad-details-page/*
  - backend/tests/Integration/GameplayUnitDetailsEndpointTest.php

---
id: UPR-005
title: Add progression rework test coverage and validation
status: complete
priority: high
milestone: Unit Progression Rework
description: The progression rework touches data, combat, promotion, API responses, targeting, run-map behavior, and frontend UX. It needs dedicated regression coverage so later balancing does not break core lineage rules.
acceptance_criteria:
  - Add backend tests for level 10 max level and level 6 promotion eligibility.
  - Add backend tests for capstone selection, persistence, and promotion inheritance.
  - Add backend tests that all new abilities are registered and have handlers.
  - Add combat tests for active abilities consuming dice and scaling at least one variable component.
  - Add combat tests for half-die defensive stack scaling.
  - Add combat tests for one-attack stack consumption and clearing.
  - Add combat tests for once-per-round reaction limits.
  - Add combat tests for target weighting with marked, wounded, debuffed, backline, and preferred targets.
  - Add tests for Treasure Sense revealing at most one hidden treasure node per run.
  - Add frontend tests or documented QA coverage for capstone selection and promotion preview.
  - Run the relevant verification commands from agent/QUALITY_GATES.md and report any failures.
current_code_references:
  - documentation/02-systems-mvp/11-unit-progression-rework.md
  - agent/QUALITY_GATES.md
  - backend/tests/Unit/Combat/AbilityHandlerRegistryCoverageTest.php
  - backend/tests/Integration/BattleNodeResolutionIntegrationTest.php
  - backend/tests/Integration/GameplayUnitDetailsEndpointTest.php

---
id: UX-001
title: Define full-screen shell and viewport layout contract
status: complete
priority: high
milestone: Authenticated Shell Fullscreen UX Pass
description: The authenticated frontend still reads like a set of web pages in a container instead of a continuous game shell. We need a single viewport/layout contract that defines how the HUD, content region, safe areas, vertical spacing, and screen framing behave before we tune individual pages.
acceptance_criteria:
  - Document the target shell behavior for authenticated screens, including viewport usage, safe-area handling, and persistent HUD spacing.
  - Refactor shared shell/page-frame layout so major authenticated screens fill the available viewport intentionally.
  - Remove accidental page-like margins and container behavior where they break the game-like presentation.
  - Keep desktop behavior aligned with the current top-header direction while preparing for mobile-first responsive work.
current_code_references:
  - frontend/src/app/layout/game-shell/*
  - frontend/src/app/layout/bottom-command-strip/*
  - frontend/src/app/shared/ui/dg-page-frame/*
  - frontend/src/app/app.scss
  - documentation/03-ux/08-page-layout-zones.md

---
id: UX-002
title: Implement mobile-first breakpoint system across authenticated UI
status: complete
priority: high
milestone: Authenticated Shell Fullscreen UX Pass
description: The shell and core screens need a deliberate mobile-first responsive system rather than ad hoc screen-size fixes. The UI should explicitly target 0-440px, 441-760px, and 761px+ and use those breakpoints to control layout density, HUD behavior, navigation, spacing, and content hierarchy.
acceptance_criteria:
  - Define the three canonical breakpoints and their intended layout behavior in UX docs and shared styles.
  - Update the authenticated shell and top HUD to follow the mobile-first breakpoint strategy.
  - Apply the breakpoint strategy to core authenticated screens so layout, spacing, and hierarchy remain consistent.
  - Avoid duplicate or conflicting breakpoint logic across related screens where a shared rule can be used instead.
current_code_references:
  - frontend/src/app/layout/bottom-command-strip/*
  - frontend/src/app/layout/game-shell/*
  - frontend/src/app/pages/home-page/*
  - frontend/src/app/pages/warband-page/*
  - frontend/src/app/pages/shop-page/*
  - documentation/03-ux/08-page-layout-zones.md
  - documentation/03-ux/09-first-session-player-journey.md

---
id: UX-003
title: Create reusable game-like screen transition system
status: complete
priority: medium
milestone: Authenticated Shell Fullscreen UX Pass
description: Route changes and screen reveals currently feel like standard website navigation. We need a lightweight but reusable transition system that adds game-like continuity without slowing interaction or obscuring critical state changes.
acceptance_criteria:
  - Define a transition vocabulary for route changes, page entry, and key panel reveals.
  - Implement reusable transition hooks or classes that can be applied across authenticated screens.
  - Ensure transitions are fast, readable, and can be reduced or disabled when accessibility or clarity requires it.
  - Apply the system to a representative set of core screens so the shell feels cohesive rather than one-off animated.
current_code_references:
  - frontend/src/app/layout/game-shell/*
  - frontend/src/app/app.html
  - frontend/src/app/app.scss
  - documentation/03-ux/03-encounter-flow-transition-matrix.md
  - documentation/03-ux/00-ux-and-debug-scope.md

---
id: UX-004
title: Run full responsive UX pass on core authenticated screens
status: complete
priority: high
milestone: Authenticated Shell Fullscreen UX Pass
description: Once the shell, breakpoints, and transition system exist, the core authenticated screens need a coordinated pass so the experience feels like one game product instead of a mix of upgraded and legacy pages.
acceptance_criteria:
  - Audit and update home, warband, inventory, shop, academy, guide, and key run screens against the new shell and breakpoint rules.
  - Resolve the most obvious density, spacing, and hierarchy mismatches between screens.
  - Verify the top HUD, navigation drawer, and content framing remain stable across representative flows.
  - Update UX docs or validation checklists to reflect the new responsive shell behavior.
current_code_references:
  - frontend/src/app/pages/home-page/*
  - frontend/src/app/pages/warband-page/*
  - frontend/src/app/pages/dice-page/*
  - frontend/src/app/pages/shop-page/*
  - frontend/src/app/pages/academy-page/*
  - frontend/src/app/pages/run-*/*
  - documentation/03-ux/*

---
id: BSC-002
title: Centralize run lifecycle transitions
status: complete
priority: high
milestone: Backend Structural Cleanup Pass
description: Run failure, completion, cleanup, and summary timing were split between resolve and claim controllers. RunLifecycleService now owns run-end transitions and battle-claim mutation sequencing so claim transport no longer duplicates reward, XP, attrition, and fail-run orchestration.
acceptance_criteria:
  - Create a backend service that owns failed, abandoned, and completed run transitions.
  - Remove duplicated cleanup and end-run sequencing from multiple controllers.
  - Preserve current summary and XP behavior unless explicitly changed.
  - Add or update targeted regression coverage for the lifecycle service.
current_code_references:
  - backend/src/Services/RunLifecycleService.php
  - backend/src/Controllers/BattleController.php
  - backend/src/Controllers/ApiController.php
  - backend/src/Controllers/RunNodeController.php
  - backend/src/Repositories/RunRepository.php
  - backend/tests/Integration/RunLifecycleServiceIntegrationTest.php

---
id: BSC-003
title: Extract shared mutation guard and controller helpers
status: complete
priority: medium
milestone: Backend Structural Cleanup Pass
description: Shared controller helpers now cover recurring auth/session wrappers, JSON body parsing, request validation, and active-run mutation checks across the most duplicated backend controllers.
acceptance_criteria:
  - Create a shared unit mutation guard or equivalent policy service.
  - Reduce repeated request/response boilerplate across the most duplicated controllers.
  - Keep controller behavior and status codes stable.
current_code_references:
  - backend/src/Controllers/Concerns/HandlesControllerRequests.php
  - backend/src/Controllers/GameplayController.php
  - backend/src/Controllers/TeamController.php
  - backend/src/Controllers/AcademyController.php
  - backend/src/Controllers/ShopController.php
  - backend/tests/Integration/ApiControllerEnvelopeContractTest.php

---
id: BSC-004
title: Separate shop and academy domain services from controllers
status: complete
priority: medium
milestone: Backend Structural Cleanup Pass
description: Shop and academy orchestration has been moved toward dedicated service paths so controllers stay focused on transport behavior while catalog, unlock, and purchase rules remain stable.
acceptance_criteria:
  - Extract dedicated services for catalog and purchase orchestration where it materially reduces controller complexity.
  - Keep unlock rules and response envelopes unchanged.
  - Preserve daily-deal and feature-unlock behavior with regression coverage where practical.
current_code_references:
  - backend/src/Controllers/ShopController.php
  - backend/src/Controllers/AcademyController.php
  - backend/tests/Integration/ShopServiceIntegrationTest.php
  - backend/tests/Integration/AcademyServiceIntegrationTest.php

---
id: BSC-005
title: Evaluate narrow synchronous domain events after service extraction
status: complete
priority: medium
milestone: Backend Structural Cleanup Pass
description: The domain event pass concluded that a broad event bus is not needed yet; future event-like handling should stay narrow and synchronous, with bounty/objective progress as the first likely candidate.
acceptance_criteria:
  - Do not introduce a broad event bus before the core service extractions land.
  - Document or implement only narrowly scoped synchronous events where they clearly reduce coupling.
  - Keep core gameplay mutations understandable and directly traceable.
current_code_references:
  - documentation/01-architecture/05-domain-events-evaluation.md
  - backend/src/Controllers
  - backend/src/Services

---
id: RGF-001
title: Add roadmap foundation batch
status: complete
priority: high
milestone: Gameplay Systems Roadmap Foundations
description: The first roadmap implementation batch landed foundational contracts for progression guidance, objectives, expanded stats, splice lineage, run hazard vocabulary, and bounty persistence without claiming those full systems are complete.
acceptance_criteria:
  - Add home dashboard next-action and progress guidance.
  - Add profile objective structure.
  - Add Precision and Resolve schema/API visibility.
  - Add persistent basic splice lineage.
  - Add hazard run node vocabulary.
  - Add bounty definition and accepted-bounty storage.
current_code_references:
  - frontend/src/app/pages/home-page
  - backend/src/Services/ObjectiveService.php
  - backend/migrations/59_unit_splice_variant_foundation.sql
  - backend/migrations/60_run_nodes_hazard_type.sql
  - backend/migrations/61_bounty_board_foundation.sql
  - documentation/07-roadmap/00-gameplay-systems-roadmap.md

---
id: ECS-002
title: Implement Precision and Resolve combat behavior
status: complete
priority: high
milestone: Expanded Combat Stats
description: Precision and Resolve now affect deterministic hit, crit, and harmful-status resistance behavior while neutral values preserve baseline combat feel.
acceptance_criteria:
  - Add deterministic Precision-based hit and critical behavior for eligible attacks.
  - Add Resolve-based resistance behavior for Poison, Bleeding, Sleep, and other supported harmful statuses.
  - Preserve current combat determinism and replayability.
  - Add battle-log language when Precision or Resolve changes an outcome.
  - Keep neutral `5` values close to existing gameplay behavior.
  - Add targeted backend combat tests for hit, crit, resisted status, and neutral-stat behavior.
current_code_references:
  - backend/src/Combat/Engine/DeterministicRunNodeResolver.php
  - backend/tests/Unit/Combat/DeterministicRunNodeResolverPrimitivesTest.php
  - documentation/02-systems-mvp/00-combat-system.md

---
id: ECS-003
title: Author Precision and Resolve balance data
status: complete
priority: high
milestone: Expanded Combat Stats
description: Seeded player unit and enemy template stats now include conservative authored Precision and Resolve values through a forward migration.
acceptance_criteria:
  - Add authored Precision and Resolve values to player unit type seed data.
  - Add authored Precision and Resolve values to enemy template seed data.
  - Keep existing regions playable with conservative tuning.
  - Document the initial tuning assumptions and any intentionally neutral entries.
  - Add or update regression coverage that verifies seeded stat JSON exposes the expected fields.
current_code_references:
  - backend/migrations/62_seed_precision_resolve_stats.sql
  - backend/tests/Integration/ExpandedCombatStatsSeedIntegrationTest.php
  - documentation/02-systems-mvp/00-combat-system.md

---
id: ECS-004
title: Surface expanded stats in player-facing comparisons
status: complete
priority: medium
milestone: Expanded Combat Stats
description: Precision and Resolve are now displayed consistently across comparison-oriented unit and reward surfaces.
acceptance_criteria:
  - Show Precision and Resolve consistently wherever unit or enemy stat blocks are compared.
  - Keep compact/mobile layouts readable.
  - Explain misses, critical hits, and resisted outcomes through existing battle summary or log surfaces.
  - Add focused frontend coverage for at least one stat-comparison surface.
current_code_references:
  - frontend/src/app/core/models/api.models.ts
  - frontend/src/app/shared/utils/unit-formatters.ts
  - frontend/src/app/pages/unit-details-page
  - frontend/src/app/pages/run-loot-page

---
id: DNA-002
title: Implement splice variant catalog and acquisition
status: complete
priority: high
milestone: Goblin DNA Splice Variants
description: Units now roll and persist authored splice variants when granted through recruitment, shop purchases, and deterministic run rewards, with player-facing splice metadata surfaced in roster and reward views.
acceptance_criteria:
  - Define a small launch set of splice variants with modest stat tendencies or conditional behaviors.
  - Persist rolled variants when units are recruited, purchased, or granted.
  - Display splice identity in unit details, rewards, recruitment, and relevant filters.
  - Preserve `basic_goblin` as the migration/default lineage.
current_code_references:
  - backend/migrations/63_seed_splice_variants.sql
  - backend/src/Services/SpliceVariantService.php
  - backend/src/Repositories/UnitRepository.php
  - frontend/src/app/pages/warband-page

---
id: PGH-002
title: Record objective progress from gameplay events
status: complete
priority: high
milestone: Progression Guidance and Home Dashboard
description: Objective progress is now derived from durable gameplay facts such as completed runs and claimed victorious battles, and the home dashboard presents completed and next objective states.
acceptance_criteria:
  - Record progress for the first objective set from backend-owned gameplay facts.
  - Keep progress idempotent across retries and refreshes.
  - Show completed and next objective states on the home dashboard.
current_code_references:
  - backend/src/Services/ObjectiveService.php
  - backend/src/Services/ProfileService.php
  - frontend/src/app/pages/home-page

---
id: BB-002
title: Build bounty board service and API
status: complete
priority: high
milestone: Bounty Board
description: The bounty board now exposes authenticated backend endpoints for listing, accepting, syncing progress, and claiming authored bounty contracts.
acceptance_criteria:
  - Add backend services and API endpoints for board listing, acceptance, progress, completion, and reward claim.
  - Enforce active-slot limits and duplicate prevention.
  - Keep generated board state stable for its rotation period.
  - Add backend and frontend coverage for the core bounty flow.
current_code_references:
  - backend/src/Services/BountyBoardService.php
  - backend/src/Controllers/BountyBoardController.php
  - backend/tests/Integration/BountyBoardControllerIntegrationTest.php
  - documentation/01-architecture/03-backend-api-contracts.md

---
id: AFE-001
title: Expand academy and feature-unlock tree
status: complete
priority: medium
milestone: Academy and Feature-Unlock Expansion
description: Academy unit research now includes backend-authored gameplay progress requirements and visible availability state.
acceptance_criteria:
  - Add at least one new unlock path that depends on gameplay progress rather than only currency.
  - Keep unlock requirements visible and backend-authoritative.
  - Document how the expanded tree connects to bounties, splice research, or future crafting.
current_code_references:
  - backend/src/Services/AcademyService.php
  - backend/tests/Integration/AcademyServiceIntegrationTest.php
  - frontend/src/app/pages/academy-page
  - documentation/02-systems-mvp/12-academy-and-feature-unlocks.md

---
id: WM-001
title: Add Wrong Machine and Raw Chaos foundation
status: complete
priority: medium
milestone: Wrong Machine and Raw Chaos
description: Raw Chaos account balance storage and backend-authored dice salvage now provide the first Wrong Machine currency foundation.
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
id: T3P-001
title: Complete Tier III progression coverage
status: complete
priority: medium
milestone: Complete Tier III Progression
description: Added Tier III chain destinations for every starter family, terminal mastery capstone choices, Tier II mastery promotion requirements, frontend codex/guide references, and seed integration coverage.
acceptance_criteria:
  - Define Tier III destinations for every major Tier I family.
  - Add capstone coverage and inherited-passive review.
  - Add promotion requirements that use regions, mastery, research, or region items.
current_code_references:
  - backend/migrations/67_tier_three_progression_coverage.sql
  - backend/tests/Integration/TierThreeProgressionSeedIntegrationTest.php
  - frontend/src/app/pages/codex-page
  - frontend/src/app/pages/guide-page
  - documentation/02-systems-mvp/02-units-and-progression.md

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
