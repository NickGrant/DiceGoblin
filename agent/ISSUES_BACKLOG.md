# ISSUES BACKLOG
----

## Purpose
- `agent/ISSUES_BACKLOG.md` tracks deferred planning issues that are not part of the active execution lane.
- Keep `agent/ISSUES.md` focused on active/current milestone execution context.
- Move items from this file into `agent/ISSUES.md` when they become execution-ready.

## Issue Template
Use the same issue schema as `agent/ISSUES.md`.

## Backlog Issues

---
id: UX-001
title: Define full-screen shell and viewport layout contract
status: unstarted
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
status: unstarted
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
status: unstarted
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
status: unstarted
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
