# ISSUES ARCHIVE
----

## Purpose
- Historical record for completed or otherwise inactive issue entries moved from `ISSUES.md`.
- Preserve prior context and resolution notes without bloating active execution context.

<!-- Archive history prior to purge can be recovered from git at commit fb22ebc and earlier. -->

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
