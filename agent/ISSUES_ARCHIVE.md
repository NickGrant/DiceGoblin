# ISSUES ARCHIVE
----

## Purpose
- Historical record for completed or otherwise inactive issue entries moved from `agent/ISSUES.md`.
- Preserve prior context and resolution notes without bloating active execution context.

<!-- Archive history prior to purge can be recovered from git at commit fb22ebc and earlier. -->

---
title: Add mobile viewport regression checks for key play flows
status: complete
priority: medium
execution: active
ready: yes
milestone: Mobile Improvements
description: Responsive behavior is spread across shared layout components and page-level SCSS files. A repeatable mobile regression pass would make future UI changes safer.
resolution: Added `documentation/05-playability-stability/04-mobile-viewport-regression-checklist.md` as the canonical mobile QA pass covering login, guide, home, warband, squad details, unit details, dice, shop, regions, run map, run node, and run summary across portrait and landscape phone viewports. The checklist logs the required HUD overlap, horizontal overflow, readability, and primary-action reachability defects, and the testing/docs indexes now point future mobile layout changes at this regression pass. `npm run startup:check`, `npm run backlog:validate`, and `npm run llm:check` passed while closing the issue.

---
title: Make unit loadout editing touch-friendly
status: complete
priority: high
execution: active
ready: yes
milestone: Mobile Improvements
description: Unit detail loadout editing appeared optimized for pointer-based drag/drop interactions. Ability cards, inline dice slots, loadout bars, and small remove buttons were difficult to use on phone screens.
resolution: Added a tap-first unit-details loadout flow with explicit `Add to Loadout`, `Up`, `Down`, and `Remove` controls so mobile users can edit ability order and membership without drag/drop. Tightened the unit-details responsive layout so inline dice slots wrap cleanly, equipped bars show readable speed/dice metadata at phone widths, and loadout action targets meet touch sizing expectations. `npm run llm:check` passed, the focused `unit-details-page` ChromeHeadless spec passed, and the frontend production build passed after keeping the component SCSS within its local budget. Full backend PHPUnit remained externally blocked because the configured MySQL test server refused connections, and the full frontend ChromeHeadless suite still has unrelated pre-existing failures in `run-rest-page.component.spec.ts`, `dice-grid-object.component.spec.ts`, `shop-dice-grid-object.component.spec.ts`, and `home-page.component.spec.ts`. Manual phone-width touch emulation against a live authenticated unit-details route was not completed in this environment.

---
title: Make formation management usable on touch devices
status: complete
priority: high
execution: active
ready: yes
milestone: Mobile Improvements
description: Squad formation management used drag/drop interactions that became unreliable once the formation grid collapsed to a single mobile column, leaving touch users without a dependable way to move units between the pool and formation slots.
resolution: Added a tap-first selection and placement flow on squad details so touch users can select a pool or formation unit, tap any slot to place or swap it, and return the selected unit to the pool without dragging. Mobile styling now makes selected, occupied, empty, and locked/disabled states clearer with larger touch targets and a readable single-column layout. `npm run llm:check` passed, the focused squad-details frontend spec passed, and the frontend production build passed. Full frontend ChromeHeadless remains blocked by unrelated pre-existing failures in `shop-dice-grid-object.component.spec.ts`, `run-rest-page.component.spec.ts`, `home-page.component.spec.ts`, and `dice-grid-object.component.spec.ts`; backend PHPUnit remained externally blocked because the local MySQL test server refused connections.

---
title: Make the game shell and bottom HUD responsive on phones
status: complete
priority: high
execution: active
ready: yes
milestone: Mobile Improvements
description: The authenticated Angular shell and persistent bottom HUD need to fit phone portrait and landscape viewports without hiding primary actions or reserving an oversized fixed bottom gap.
resolution: Measured the live bottom HUD height into a shared CSS variable, switched the game shell bottom padding to that runtime value, and tightened the command strip layout for stacked portrait phones plus short landscape phones while preserving safe-area padding. `npm run llm:check` passed, targeted shell/HUD frontend specs passed, the frontend production build passed, portrait and landscape phone screenshots confirmed the CTA stayed visible above the HUD, backend PHPUnit was externally blocked because the configured MySQL test server on `127.0.0.1:3307` refused connections, and the full frontend suite still has unrelated pre-existing failures in run-rest, home-page, dice-grid, and shop-dice-grid specs.

---
title: Highlight acquired unlocks in the guide for logged-in users
status: complete
priority: high
execution: active
ready: yes
milestone: Watcher Testing
description: Logged-in players need the guide to reflect their progression by clearly showing which unlocks they have already acquired.
resolution: Added acquired-state guide styling for authenticated players, extended the profile payload with `unit_type_unlocks` so feature and academy unlocks can both be derived from session/profile state, and kept the public guide neutral for anonymous visitors. `npm run llm:check` and the frontend production build passed, targeted guide/session frontend specs passed, backend PHPUnit was blocked by the local MySQL test database refusing connections, and the full frontend suite still has unrelated pre-existing failures in run-rest, dice-grid, shop-dice-grid, and home-page specs.

---
title: Make guide available to logged-in users
status: complete
priority: high
execution: active
ready: yes
milestone: Watcher Testing
description: The guide is currently available as a public route, but watcher testing needs it to be explicitly available to logged-in users from the authenticated game experience as well.
resolution: Kept the public `/guide` route for anonymous visitors, added an authenticated shell route that reuses the same guide component, and made the guide page session-aware so logged-in players get return-to-game actions without clearing their session or active run state.

---
title: Add navigation from inside the game to the guide
status: complete
priority: high
execution: active
ready: yes
milestone: Watcher Testing
description: Players need a discoverable route from inside the authenticated game shell to the guide.
resolution: Added a persistent `Guide` entry in the authenticated bottom command strip, passed a safe `returnUrl` into the authenticated guide route so players can return to their exact in-game location, and kept the guide route session-safe during active runs. `npm run llm:check` passed, focused frontend guide/navigation specs passed, and backend PHPUnit remained blocked by the local MySQL test database refusing connections (`SQLSTATE[HY000] [2002]`).

---
title: Add unit naming and ability-loadout persistence schema
status: complete
priority: high
execution: active
ready: yes
milestone: Milestone 37 - Ability Loadout Rework Foundations
description: Add the first-pass schema and persistence support for player-facing unit names, unlocked abilities, equipped ability order, and ability-slot dice binding. This issue establishes the authoritative persistence layer the rework needs before combat and UX can be updated safely.
resolution: Added the first rework schema layer for unit display names, unlocked/equipped ability persistence, ability-slot dice bindings, and enemy equipped loadouts, then surfaced the new fields through profile reads with legacy-user backfill. Frontend verification passed, while backend integration tests were blocked by the local MySQL test database refusing connections.

---
title: Seed starter units with default abilities and common d4 slot assignments
status: complete
priority: high
execution: active
ready: yes
milestone: Milestone 37 - Ability Loadout Rework Foundations
description: Update account bootstrap and starter-grant flows so initial player units receive generated names, default equipped abilities, and common d4 dice assigned into their starter ability slots. The goal is to make the first playable state valid under the new system.
resolution: Starter grants now create named units, seed default equipped abilities, bind common d4s into starter ability slots, and preserve temporary legacy unit-dice compatibility for the current combat stack. Additional unit-creation paths for shop, rest rewards, gameplay store buys, and debug grants were also aligned to the new initialization flow.

---
title: Author enemy equipped-loadout definitions for cumulative scheduling
status: complete
priority: high
execution: active
ready: yes
milestone: Milestone 37 - Ability Loadout Rework Foundations
description: Extend enemy template definitions so each enemy type owns a shared equipped ability order for combat scheduling. This must land before the combat engine can switch enemies onto the new timing model.
resolution: Added enemy equipped-loadout persistence and a seed migration that mirrors current authored active abilities into ordered enemy loadouts. This gives the combat rewrite a stable enemy contract without requiring per-enemy instance customization.

---
title: Add backend validators for ability equip budget and slot legality
status: complete
priority: medium
execution: active
ready: yes
milestone: Milestone 37 - Ability Loadout Rework Foundations
description: Add server-side validation for the 20-point ability budget, duplicate ability equips, and legality of ability-slot dice assignments. This creates the contract enforcement needed for later UI and API work.
resolution: Added shared loadout-service validation for equip budget enforcement, duplicate ability equips within budget, unlocked-ability checks, slot-index legality, and dice ownership/binding conflicts. Added integration coverage for the new rules, with syntax checks passing and DB-backed PHPUnit still blocked by the local MySQL test database refusing connections.

---
title: Rewrite combat scheduler to use cumulative equipped ability timing
status: complete
priority: high
execution: active
ready: yes
milestone: Milestone 38 - Combat Scheduler and Resolution Rewrite
description: Replace modulo-based combat timing with cumulative once-per-round equipped ability scheduling for both player units and enemies. This includes preserving deterministic same-tick ordering and updating battle resolution to use equipped instances rather than implicit type defaults.
resolution: Updated the deterministic combat engine to read ordered equipped abilities for players and authored enemy equipped loadouts, then schedule each active once per round at its cumulative trigger tick. Added coverage for cumulative tick behavior and kept legacy ability-set fallback behavior in place for transitional data.

---
title: Replace pooled combat dice resolution with ability-slot reads
status: complete
priority: high
execution: active
ready: yes
milestone: Milestone 38 - Combat Scheduler and Resolution Rewrite
description: Remove shared unit-dice-pool combat behavior and resolve all combat rolls from authored ability slots. Empty slots must deterministically contribute 1 and repeated equips of the same base ability must reuse the same slot configuration.
resolution: Combat rolls now read ordered ability-slot bindings for player units, use deterministic empty-slot d1 fallback per authored slot, and ignore legacy pooled unit dice during action resolution. Backend integration coverage was updated for slot-driven timing, bound-die precedence, starter-baseline expectations, and blank-password local test environments.

---
title: Expand battle logs for equipped ability instances and slot traces
status: complete
priority: medium
execution: active
ready: yes
milestone: Milestone 38 - Combat Scheduler and Resolution Rewrite
description: Update battle logs and combat payloads so testers can understand equipped ability instance timing, slot values, and empty-slot contributions under the new model.
resolution: Action events now include equipped ability instance order, loadout source, per-slot trace objects, and readable slot-trace summaries while preserving the older dice summary fields for compatibility. The node-resolution frontend summary now surfaces the new instance and slot trace details so testers can read cumulative timing and empty-slot behavior directly from the battle log.

---
title: Add unit details rename and loadout mutation endpoints
status: complete
priority: high
execution: active
ready: yes
milestone: Milestone 39 - Unit Details and Promotion UX
description: Add backend endpoints and frontend client contracts for renaming units and replacing equipped ability order from Unit Details. This creates the mutation layer the scene rewrite will need before it can stop relying on legacy unit-wide dice APIs.
resolution: Added authenticated gameplay endpoints for renaming units and replacing equipped ability order, surfaced the response contracts through the frontend API client/types, and added backend plus frontend mutation coverage for the new unit-details contract layer.

---
title: Add unit details support for renaming, loadout order, and ability-slot dice editing
status: complete
priority: high
execution: active
ready: yes
milestone: Milestone 39 - Unit Details and Promotion UX
description: Rework UnitDetailsScene and supporting contracts so players can rename units, edit equipped ability order, manage ability-slot dice, and see their current 20-point budget usage.
resolution: Rebuilt UnitDetailsScene around the new unit-details view models so players can rename units, reorder equipped abilities, assign or clear ability-slot dice, and read their current 20-point loadout budget in one screen. Added debug catalog/profile coverage for the new scene data shape and verified the updated frontend build and test suite.

---
title: Update inventory flows to target ability-slot equips instead of unit pools
status: complete
priority: medium
execution: active
ready: yes
milestone: Milestone 39 - Unit Details and Promotion UX
description: Change dice inventory interactions so equips and unequips target a specific unit ability slot rather than a generic per-unit dice pool. This includes showing where dice are currently bound.
resolution: Removed the unused pooled-dice mutation client surface from the frontend and updated inventory dice presentation so equipped dice now show their unit, bound ability, and slot context. Frontend tests, build, and backlog validation all passed after the cleanup.

---
title: Rework promotion flow for cumulative abilities and sideways destinations
status: complete
priority: high
execution: active
ready: yes
milestone: Milestone 39 - Unit Details and Promotion UX
description: Update promotion backend and UI flows so units retain cumulative ability catalogs and can choose either the next chain destination or an eligible sideways destination at the tier being exited.
resolution: Added promotion-option reads and destination-aware promotion execution to UnitDetailsScene, including a lightweight destination selector wired to the new backend contract. Frontend tests/build, backend PHPUnit, and backlog validation all passed after the promotion UX was connected end to end.
