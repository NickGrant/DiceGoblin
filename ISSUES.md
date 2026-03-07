# ISSUES FILE
----
Active issues only. Move completed entries to `ISSUES_ARCHIVE.md`.

---
title: Add regression coverage for consolidated non-rest node resolution flow
status: blocked
priority: medium
execution: active
ready: yes
owner: unassigned
milestone: Milestone 14 - Run Node Resolution Consolidation
created: 2026-03-07
updated: 2026-03-07
description: |
  Add focused tests validating scene routing, payload handling, supported node-type behavior, and run-end branching after node resolution consolidation.
  Blocked note: `npm run test` now executes without `spawn EPERM`, but current suite has baseline frontend failures (`window is not defined` in Phaser-dependent suites and failing Node-map mocks) that need stabilization before this regression coverage can be added cleanly.

---
title: Migrate HomeScene to standardized UX rebuild components
status: unstarted
priority: high
execution: deferred
ready: no
owner: unassigned
milestone: Milestone 16 - Scene Migration to UX Rebuild Components
created: 2026-03-07
updated: 2026-03-07
description: |
  Refactor `HomeScene` to use the new shared component library for navigation areas, headers, and interaction surfaces while preserving start/continue run behavior.

---
title: Migrate RegionSelectScene and MapExplorationScene to shared component library
status: unstarted
priority: high
execution: deferred
ready: no
owner: unassigned
milestone: Milestone 16 - Scene Migration to UX Rebuild Components
created: 2026-03-07
updated: 2026-03-07
description: |
  Replace scene-specific UI implementations in `RegionSelectScene` and `MapExplorationScene` with standardized components and shared interaction patterns.

---
title: Migrate warband management scenes to shared list/action components
status: unstarted
priority: high
execution: deferred
ready: no
owner: unassigned
milestone: Milestone 16 - Scene Migration to UX Rebuild Components
created: 2026-03-07
updated: 2026-03-07
description: |
  Refactor `WarbandManagementScene`, `SquadDetailsScene`, and `UnitDetailsScene` to use unified list, card-grid, and action control components from the UX rebuild library.

---
title: Migrate inventory, rest, and run summary scenes to shared component library
status: unstarted
priority: medium
execution: deferred
ready: no
owner: unassigned
milestone: Milestone 16 - Scene Migration to UX Rebuild Components
created: 2026-03-07
updated: 2026-03-07
description: |
  Refactor `DiceInventoryScene`, `RestManagementScene`, and `RunEndSummaryScene` to standardized layout, list, and feedback components to reduce UI drift.

---
title: Remove superseded scene-local UI implementations after migration
status: unstarted
priority: medium
execution: deferred
ready: no
owner: unassigned
milestone: Milestone 16 - Scene Migration to UX Rebuild Components
created: 2026-03-07
updated: 2026-03-07
description: |
  Delete or deprecate duplicated scene-local UI code that is replaced by shared components, ensuring imports and references are cleaned up.

---
title: Update UX rebuild docs to reflect final component adoption per scene
status: unstarted
priority: medium
execution: deferred
ready: no
owner: unassigned
milestone: Milestone 16 - Scene Migration to UX Rebuild Components
created: 2026-03-07
updated: 2026-03-07
description: |
  Update scene/component mapping docs to show final adopted components and note any intentionally deferred scene-specific exceptions.
