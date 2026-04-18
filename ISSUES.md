# ISSUES FILE
----
Active issues only. Move completed entries to `ISSUES_ARCHIVE.md`.

title: Add unit details support for renaming, loadout order, and ability-slot dice editing
status: unstarted
priority: high
execution: active
ready: yes
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
