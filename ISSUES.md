# ISSUES FILE
----
Active issues only. Move completed entries to `ISSUES_ARCHIVE.md`.

---
title: Create unified Node Resolution Scene for non-rest run nodes
status: unstarted
priority: high
execution: deferred
ready: no
owner: unassigned
milestone: Milestone 14 - Run Node Resolution Consolidation
created: 2026-03-07
updated: 2026-03-07
description: |
  Create a dedicated `NodeResolutionScene` to handle `combat`, `loot`, `boss`, and `exit` node resolution flows so map navigation and resolution UX are centralized outside `MapExplorationScene`.
---
title: Route map node clicks through centralized node-resolution navigation contract
status: unstarted
priority: high
execution: deferred
ready: no
owner: unassigned
milestone: Milestone 14 - Run Node Resolution Consolidation
created: 2026-03-07
updated: 2026-03-07
description: |
  Refactor `MapExplorationScene` click handlers so non-rest nodes route into the new resolution scene with a consistent payload contract (`runId`, `nodeId`, `nodeType`) and predictable return behavior.
---
title: Move non-rest resolve/exit API orchestration out of MapExplorationScene
status: unstarted
priority: high
execution: deferred
ready: no
owner: unassigned
milestone: Milestone 14 - Run Node Resolution Consolidation
created: 2026-03-07
updated: 2026-03-07
description: |
  Relocate resolve/exit request orchestration and outcome branching from `MapExplorationScene` into a dedicated controller flow used by the new node-resolution scene.
---
title: Define unified node outcome surface for victory, defeat, claim, and errors
status: unstarted
priority: medium
execution: deferred
ready: no
owner: unassigned
milestone: Milestone 14 - Run Node Resolution Consolidation
created: 2026-03-07
updated: 2026-03-07
description: |
  Implement a single UI outcome surface for node resolution states (including clear retry/failure messaging) so non-rest encounters use consistent player feedback and action prompts.
---
title: Add regression coverage for consolidated non-rest node resolution flow
status: unstarted
priority: medium
execution: deferred
ready: no
owner: unassigned
milestone: Milestone 14 - Run Node Resolution Consolidation
created: 2026-03-07
updated: 2026-03-07
description: |
  Add focused tests validating scene routing, payload handling, supported node-type behavior, and run-end branching after node resolution consolidation.

---
title: Implement core layout shell components from 03-component-specifications
status: unstarted
priority: high
execution: deferred
ready: no
owner: unassigned
milestone: Milestone 15 - Build UX Rebuild Component Library
created: 2026-03-07
updated: 2026-03-07
description: |
  Build foundational reusable layout shell components defined in `documentation/07-ux-rebuild/03-component-specifications.md` (page frame, title bands, section containers, shared spacing rules).
---
title: Implement reusable navigation components from 03-component-specifications
status: unstarted
priority: high
execution: deferred
ready: no
owner: unassigned
milestone: Milestone 15 - Build UX Rebuild Component Library
created: 2026-03-07
updated: 2026-03-07
description: |
  Implement standardized navigation components (home affordance, scene navigation panels, and navigation action variants) defined by the UX rebuild component specifications.
---
title: Implement shared list framework with loading, error, and pagination states
status: unstarted
priority: high
execution: deferred
ready: no
owner: unassigned
milestone: Milestone 15 - Build UX Rebuild Component Library
created: 2026-03-07
updated: 2026-03-07
description: |
  Create a reusable list foundation component that supports loading/failure/empty states and optional pagination controls, then expose extension points for name-link lists and grid lists.
---
title: Implement standardized action controls and button variants from component spec
status: unstarted
priority: medium
execution: deferred
ready: no
owner: unassigned
milestone: Milestone 15 - Build UX Rebuild Component Library
created: 2026-03-07
updated: 2026-03-07
description: |
  Implement shared action controls (primary, accept/reject, icon+text variants, grouped button lists) to eliminate divergent button implementations across scenes.
---
title: Implement HUD and feedback utility components from component spec
status: unstarted
priority: medium
execution: deferred
ready: no
owner: unassigned
milestone: Milestone 15 - Build UX Rebuild Component Library
created: 2026-03-07
updated: 2026-03-07
description: |
  Implement reusable HUD and feedback components (energy/status tooltip surfaces, toast/inline feedback wrappers, confirmation/dialog primitives) per component specification.
---
title: Add component-level test coverage and usage examples for UX rebuild library
status: unstarted
priority: medium
execution: deferred
ready: no
owner: unassigned
milestone: Milestone 15 - Build UX Rebuild Component Library
created: 2026-03-07
updated: 2026-03-07
description: |
  Add tests and concise usage examples for new reusable components to ensure expected behavior and reduce integration ambiguity during scene migration.

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

