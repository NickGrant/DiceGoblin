# ISSUES FILE
----
Active issues only. Move completed entries to `ISSUES_ARCHIVE.md`.

---
title: Add unit naming and ability-loadout persistence schema
status: unstarted
priority: high
execution: active
ready: yes
milestone: Milestone 37 - Ability Loadout Rework Foundations
description: Add the first-pass schema and persistence support for player-facing unit names, unlocked abilities, equipped ability order, and ability-slot dice binding. This issue establishes the authoritative persistence layer the rework needs before combat and UX can be updated safely.

---
title: Seed starter units with default abilities and common d4 slot assignments
status: unstarted
priority: high
execution: active
ready: yes
milestone: Milestone 37 - Ability Loadout Rework Foundations
description: Update account bootstrap and starter-grant flows so initial player units receive generated names, default equipped abilities, and common d4 dice assigned into their starter ability slots. The goal is to make the first playable state valid under the new system.

---
title: Author enemy equipped-loadout definitions for cumulative scheduling
status: unstarted
priority: high
execution: active
ready: yes
milestone: Milestone 37 - Ability Loadout Rework Foundations
description: Extend enemy template definitions so each enemy type owns a shared equipped ability order for combat scheduling. This must land before the combat engine can switch enemies onto the new timing model.

---
title: Add backend validators for ability equip budget and slot legality
status: unstarted
priority: medium
execution: active
ready: yes
milestone: Milestone 37 - Ability Loadout Rework Foundations
description: Add server-side validation for the 20-point ability budget, duplicate ability equips, and legality of ability-slot dice assignments. This creates the contract enforcement needed for later UI and API work.

---
title: Rewrite combat scheduler to use cumulative equipped ability timing
status: unstarted
priority: high
execution: active
ready: no
milestone: Milestone 38 - Combat Scheduler and Resolution Rewrite
description: Replace modulo-based combat timing with cumulative once-per-round equipped ability scheduling for both player units and enemies. This includes preserving deterministic same-tick ordering and updating battle resolution to use equipped instances rather than implicit type defaults.

---
title: Replace pooled combat dice resolution with ability-slot reads
status: unstarted
priority: high
execution: active
ready: no
milestone: Milestone 38 - Combat Scheduler and Resolution Rewrite
description: Remove shared unit-dice-pool combat behavior and resolve all combat rolls from authored ability slots. Empty slots must deterministically contribute 1 and repeated equips of the same base ability must reuse the same slot configuration.

---
title: Expand battle logs for equipped ability instances and slot traces
status: unstarted
priority: medium
execution: active
ready: no
milestone: Milestone 38 - Combat Scheduler and Resolution Rewrite
description: Update battle logs and combat payloads so testers can understand equipped ability instance timing, slot values, and empty-slot contributions under the new model.

---
title: Add unit details support for renaming, loadout order, and ability-slot dice editing
status: unstarted
priority: high
execution: active
ready: no
milestone: Milestone 39 - Unit Details and Promotion UX
description: Rework UnitDetailsScene and supporting contracts so players can rename units, edit equipped ability order, manage ability-slot dice, and see their current 20-point budget usage.

---
title: Update inventory flows to target ability-slot equips instead of unit pools
status: unstarted
priority: medium
execution: active
ready: no
milestone: Milestone 39 - Unit Details and Promotion UX
description: Change dice inventory interactions so equips and unequips target a specific unit ability slot rather than a generic per-unit dice pool. This includes showing where dice are currently bound.

---
title: Rework promotion flow for cumulative abilities and sideways destinations
status: unstarted
priority: high
execution: active
ready: no
milestone: Milestone 39 - Unit Details and Promotion UX
description: Update promotion backend and UI flows so units retain cumulative ability catalogs and can choose either the next chain destination or an eligible sideways destination at the tier being exited.

---
title: Normalize rework migrations after schema stabilizes
status: unstarted
priority: medium
execution: deferred
ready: no
milestone: Milestone 40 - Rework Normalization Pass
description: Compact transitional migration history once the rework schema is stable and remove no-longer-needed compatibility structures related to pooled dice and interim loadout persistence.

---
title: Consolidate legacy combat and loadout test fixtures
status: unstarted
priority: medium
execution: deferred
ready: no
milestone: Milestone 40 - Rework Normalization Pass
description: Refactor or remove tests and fixtures that preserve old modulo-scheduler or pooled-dice assumptions, replacing them with shared builders for the new unit and enemy loadout model.

---
title: Refactor repeated scene layout and styling after rework UI lands
status: unstarted
priority: low
execution: deferred
ready: no
milestone: Milestone 40 - Rework Normalization Pass
description: Review repeated scene panel layout, typography, and style logic after the ability-loadout UI is implemented and consolidate the patterns into clearer shared helpers or components.
