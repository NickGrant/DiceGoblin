# MILESTONES ARCHIVE
----

## Purpose
- This file is intentionally kept lean.
- Historical milestone records can be retrieved from git when needed.

---
name: Developer Support: Seed Catalog Browser
status: complete
issues:
  - DSB-001
description: Add a read-only developer-panel view for seeded catalog tables so content and balance data can be inspected without direct SQL access.
goals:
  - Expose selected seeded tables through a read-only debug API.
  - Present table selection, row counts, and seeded values in the Angular debug panel.
  - Keep unknown tables and all mutation attempts out of scope.
  - Document the supported debug contract.
current_code_context: Implementation touched DebugController, DevToolsService, debug-page Angular components, debug service models, API documentation, and roadmap files.
exit_criteria:
  - Developers can inspect supported seeded tables from `/debug`.
  - The backend allowlists table names and returns rows without write behavior.
  - Frontend and backend coverage protect the read-only browser flow.

---
name: Local Account Authentication
status: complete
issues:
  - LA-001
  - LA-002
  - LA-003
description: Remove Discord as the only sign-in path so players on restricted networks can create accounts and enter the game with local credentials while keeping the existing session model intact.
goals:
  - Add backend-supported local registration and login using email plus password.
  - Keep Discord OAuth available as an alternate provider.
  - Reuse the existing PHP session, CSRF, bootstrap, and profile flow after either login path.
  - Update player-facing login UX and canonical auth documentation.
current_code_context: Implementation touched AuthController, UserRepository, user schema migrations, the Angular landing/login page, SessionService, and authentication/session documentation.
exit_criteria:
  - New players can register with local credentials and receive the same starter account bootstrap as Discord users.
  - Existing local users can sign in without Discord access.
  - Duplicate emails, invalid credentials, and weak local registration payloads return stable API errors.
  - Login documentation and active roadmap files identify backend structural cleanup as the next implementation target after local auth.

---
name: Unit Progression Rework
status: complete
issues:
  - UPR-003
  - UPR-004
  - UPR-005
description: Implement the revised level 10 mastery, level 6 promotion eligibility, passive capstone inheritance, targeting weights, and specialized Tier 2/Tier 3 unit progression model.
goals:
  - Make every unit type max at level 10 while allowing promotion from level 6 onward.
  - Add passive level 10 capstone choices that inherit through promotion.
  - Make Tier 2 and Tier 3 promotions grant one active and one passive ability immediately.
  - Add deterministic targeting weights for marked, wounded, debuffed, backline, and preferred-target behaviors.
  - Implement the first specialized branch set for Bruiser, Marksman, Guardian, Bannerbearer, and Saboteur.
  - Add the Mascot support branch as the alternate Bannerbearer Tier 2 path.
current_code_context: The implementation should use documentation/02-systems-mvp/11-unit-progression-rework.md as the authoritative design reference. Likely affected areas include backend unit type seed data, ability registration and handlers, combat targeting, promotion logic, unit detail API responses, promotion UI, and tests around ability registry coverage and battle resolution.
exit_criteria:
  - Unit progression supports level 10 max and level 6 promotion eligibility independently.
  - Passive capstone choice is persisted and inherited through promotion.
  - Active abilities consume at least one die and expose a die-scaled variable component.
  - Defensive stack effects support half-die scaling where appropriate.
  - Targeting weights make marked, wounded, debuffed, backline, and preferred targets behave predictably.
  - Tier 2 promotion choices grant an active and passive immediately and expose level 10 capstones.
  - Mascot is available as a Bannerbearer Tier 2 branch.
  - Unit detail and promotion UX clearly communicate promotion eligibility, skipped capstones, mastered capstones, and inherited abilities.
  - Backend and frontend tests cover progression, inheritance, ability handlers, targeting behavior, capstone selection, and run-map passive behavior.

---
name: Authenticated Shell Fullscreen UX Pass
status: complete
issues:
  - UX-001
  - UX-002
  - UX-003
  - UX-004
description: Shift the authenticated game experience away from page-like layouts toward a responsive full-screen shell that feels like a game client across mobile, tablet, and desktop. The pass should establish a mobile-first layout contract, unify breakpoint behavior at 0-440px, 441-760px, and 761px+, and introduce screen-to-screen transitions that reduce the feel of traditional web page swaps.
goals:
  - Make authenticated screens fill the viewport and feel like a persistent game shell rather than isolated web pages.
  - Define and implement a mobile-first responsive layout system for 0-440px, 441-760px, and 761px+.
  - Introduce reusable route and screen transitions that make navigation feel intentional and game-like.
  - Validate the shell, HUD, navigation, spacing, and content behavior across core screens before broad rollout.
current_code_context: Primary implementation touched frontend shell/layout components, route containers, shared page-frame components, top HUD and navigation behavior, viewport spacing rules, and screen-level SCSS. UX reference docs under documentation/03-ux/ were updated to reflect the new shell and motion rules.
exit_criteria:
  - Authenticated pages use a consistent full-screen shell instead of page-like centered layouts where not intentionally required.
  - The three responsive breakpoints are documented and consistently implemented.
  - Mobile navigation, HUD density, and page framing remain usable without wasting excessive vertical space.
  - Core screens share a reusable transition pattern for route changes and stateful screen reveals.
  - The revised shell is verified across home, warband, inventory, shop, academy, and run-related flows.

---
name: Backend Structural Cleanup Pass
status: complete
issues:
  - BSC-001
  - BSC-002
  - BSC-003
  - BSC-004
  - BSC-005
description: Reduced backend orchestration drift by extracting lifecycle, grant, request-helper, mutation-guard, shop, and academy service boundaries, then documented the narrow event-style coordination strategy.
goals:
  - Create a single canonical service path for run lifecycle transitions and cleanup.
  - Consolidate unit and dice grant creation behind shared services.
  - Reduce controller duplication for auth, JSON validation, transactions, and mutation guards.
  - Prepare the backend for narrow domain events only where they provide clear value after service extraction.
current_code_context: Implementation touched backend controllers, shared controller concerns, lifecycle/grant/objective-oriented services, shop and academy service tests, and domain-event evaluation documentation.
exit_criteria:
  - Run lifecycle transitions no longer require duplicated controller logic to stay consistent.
  - Unit and dice creation paths are consolidated across battle rewards, shop flows, debug tools, and starter grants.
  - Common controller plumbing is meaningfully reduced or centralized.
  - Event-style coordination remains narrow, synchronous, and documented for future objective or bounty progress work.

---
name: Gameplay Systems Roadmap Foundations
status: complete
issues:
  - RGF-001
description: Landed the first dependency-reducing roadmap foundations after backend cleanup: profile objectives, home guidance, expanded stat fields, basic splice lineage, hazard node vocabulary, and bounty-board persistence.
goals:
  - Establish safe schema/API footholds before full feature behavior.
  - Keep roadmap foundations backend-authoritative and testable.
  - Leave full system behavior in explicit follow-up milestones rather than treating foundations as complete features.
current_code_context: Implementation touched profile DTOs, ObjectiveService, home dashboard UI, migrations 59-61, combat/encounter docs, and data-model documentation.
exit_criteria:
  - Fresh schema builds include roadmap migrations 59-61.
  - Objective, expanded stat, splice, hazard, and bounty concepts have durable contracts.
  - Active backlog is advanced to the next implementation-ready milestone.

---
name: Expanded Combat Stats
status: complete
issues:
  - ECS-002
  - ECS-003
  - ECS-004
description: Turned Precision and Resolve from visible stat fields into deterministic combat behavior, authored seed data, and player-facing comparison vocabulary.
goals:
  - Make Precision influence eligible attack reliability and restrained critical-hit outcomes.
  - Make Resolve influence harmful-status and control resistance.
  - Author conservative Precision and Resolve values for current units and enemies.
  - Surface expanded-stat outcomes clearly in battle logs and comparison UI.
current_code_context: Implementation touched deterministic combat resolution, forward migration 62, combat docs, profile/stat payloads, and frontend unit/reward comparison surfaces.
exit_criteria:
  - Neutral Precision and Resolve values preserve the existing baseline feel.
  - Non-neutral values create readable tactical differences in combat.
  - Player and enemy seed data intentionally includes Precision and Resolve.
  - Combat logs and UI make misses, critical hits, and resisted effects understandable.

---
name: Goblin DNA Splice Variants
status: complete
issues:
  - DNA-002
description: Added an authored launch splice catalog, persisted rolled variants through unit acquisition, applied splice stat modifiers, and surfaced splice identity in player-facing roster/reward views.
goals:
  - Define a small enabled splice catalog.
  - Persist rolled variants for recruited, purchased, and rewarded units.
  - Keep `basic_goblin` as the safe default lineage.
  - Make splice identity visible where players compare units.
current_code_context: Implementation touched migration 63, SpliceVariantService, UnitRepository, unit grant paths, run rewards, shop recruitment, and unit/reward frontend surfaces.
exit_criteria:
  - Fresh schema builds include the splice variant catalog.
  - Unit grants can assign enabled splice variants deterministically or randomly as appropriate.
  - Unit payloads expose splice metadata and stat effects.
  - Frontend unit and reward surfaces display splice identity.

---
name: Progression Guidance and Home Dashboard
status: complete
issues:
  - PGH-002
description: Connected objective progress to durable backend gameplay facts and updated the home dashboard to show completed and next objective states.
goals:
  - Derive objective progress from completed runs and claimed victories.
  - Keep progress idempotent across retries and refreshes.
  - Present objective status clearly on the home dashboard.
current_code_context: Implementation touched ObjectiveService, ProfileService, profile contract coverage, and the Angular home page.
exit_criteria:
  - Objective progress reflects durable gameplay rows.
  - Completed objectives and next objectives appear in profile/home state.
  - The home dashboard remains driven by backend-authored objective ordering.

---
name: Bounty Board
status: complete
issues:
  - BB-002
description: Built the first authenticated bounty board service and API on top of the existing bounty schema foundation.
goals:
  - List authored bounty contracts.
  - Accept bounties with active-slot and duplicate protection.
  - Sync progress from durable gameplay facts.
  - Claim completed bounty rewards idempotently.
current_code_context: Implementation touched BountyBoardService, BountyBoardController, routes, API docs, and integration coverage.
exit_criteria:
  - Players can list, accept, sync, and claim bounties through backend endpoints.
  - Bounty progress is idempotent and derived from existing gameplay state.
  - Bounty rewards are backend-authored and tested.

---
name: Academy and Feature-Unlock Expansion
status: complete
issues:
  - AFE-001
description: Expanded Academy research with backend-authored availability and gameplay-progress requirements.
goals:
  - Add unlock requirements that depend on gameplay progress.
  - Keep availability and requirements backend-authoritative.
  - Show unmet requirements in the Academy UI.
current_code_context: Implementation touched AcademyService, academy API contracts, Academy page UI/tests, and academy system documentation.
exit_criteria:
  - Academy catalog entries expose availability and requirements.
  - Locked research cannot be purchased before requirements are met.
  - The frontend displays requirement state clearly.

---
name: Wrong Machine and Raw Chaos
status: complete
issues:
  - WM-001
description: Added Raw Chaos currency storage and dice salvage as the first Wrong Machine foundation.
goals:
  - Store Raw Chaos on player state.
  - Salvage unequipped dice into backend-calculated Raw Chaos.
  - Block equipped dice from salvage.
  - Document fabrication and catalyst work as follow-up scope.
current_code_context: Implementation touched migration 64, schema snapshots, DiceSalvageService, dice valuation, profile currency contracts, Dice Inventory UI, and dice system documentation.
exit_criteria:
  - Fresh schema builds include Raw Chaos.
  - Dice salvage is backend-authoritative and transactional.
  - Equipped dice cannot be salvaged.
  - The Dice Inventory exposes Raw Chaos balance and salvage confirmation.

---
name: Expanded Run Encounters
status: complete
issues:
  - REE-002
description: Added shrine encounters as the first expanded non-combat encounter family beyond dialogue, rest, loot, and hazard nodes.
goals:
  - Add at least one meaningful non-combat encounter family.
  - Persist generated encounter state before player resolution.
  - Keep encounter outcomes backend-authoritative and testable.
  - Surface clear player-facing copy for the new encounter flow.
current_code_context: Implementation touched migration 65, run graph generation, deterministic node resolution, run node API/frontend presentation, and encounter documentation.
exit_criteria:
  - Shrine encounters have durable backend-authored favor results.
  - Resolving shrine encounters fits the existing run-node lifecycle.
  - The frontend presents shrine encounters and result copy clearly.
  - Backend and frontend coverage protect the core shrine flow.

---
name: Slot-Machine-Style Random Encounters Foundation
status: complete
issues:
  - SME-001
description: Established persisted chaos reel results and one single-use reroll mechanic as the foundation for later reachable chaos encounter nodes.
goals:
  - Document reel responsibilities, result persistence, and reward-scaling constraints.
  - Persist generated chaos encounter results.
  - Add one bounded player agency mechanic.
current_code_context: Implementation touched migration 66, ChaosEncounterService, ChaosEncounterController, backend routing, API/data-model documentation, and integration tests.
exit_criteria:
  - Generated chaos results are durable per run node.
  - Reroll state is persisted and single-use.
  - Wrong-owner, invalid-node, and idempotency cases are tested.

---
name: Complete Tier III Progression
status: complete
issues:
  - T3P-001
description: Filled the starter-family Tier III class map and aligned mastery capstone and promotion seed data with player-facing references.
goals:
  - Define Tier III destinations for every major Tier I family.
  - Add capstone coverage and inherited-passive review.
  - Add promotion requirements using mastery as the initial advanced gate.
current_code_context: Implementation touched migration 67, schema snapshots, progression integration tests, unit-art mapping, codex/guide pages, and progression documentation.
exit_criteria:
  - Every starter family has a Tier III chain destination.
  - Tier II chain promotions require mastery before Tier III options appear.
  - Terminal Tier III classes expose authored mastery capstone choices.

---
name: Slot-Machine-Style Random Encounters
status: complete
issues:
  - SME-001
  - SME-002
  - SME-003
description: Completed the slot-machine-style chaos encounter loop from persisted reel generation through reachable run-map placement, one-reroll agency, backend-authored finalization rewards, and node progression.
goals:
  - Persist generated chaos encounter results per run node.
  - Add bounded player agency without regenerating the result.
  - Place and present chaos nodes in eligible procedural runs.
  - Finalize generated chaos encounters through a backend-authoritative path.
current_code_context: Implementation touched migrations 66, 68, and 69, ChaosEncounterService, ChaosEncounterController, RunGraphGenerator, the Angular run-node page, API/data-model documentation, and backend/frontend coverage.
exit_criteria:
  - Generated chaos results are durable and idempotent.
  - Chaos nodes are reachable and visually distinct on run maps.
  - One reroll may be used before finalization.
  - Finalize applies rewards once, clears the node, and unlocks downstream progression.
