# ISSUES FILE
----
Active issues only. Move completed entries to `agent/ISSUES_ARCHIVE.md`.

## Authenticated Shell Fullscreen UX Pass

### UX-003: Create reusable game-like screen transition system

**Milestone:** Authenticated Shell Fullscreen UX Pass  
**Status:** Open  
**Priority:** Medium

#### Problem

Route changes and screen reveals currently feel like standard website navigation. We need a lightweight but reusable transition system that adds game-like continuity without slowing interaction or obscuring critical state changes.

#### Acceptance Criteria

- Define a transition vocabulary for route changes, page entry, and key panel reveals.
- Implement reusable transition hooks or classes that can be applied across authenticated screens.
- Ensure transitions are fast, readable, and can be reduced or disabled when accessibility or clarity requires it.
- Apply the system to a representative set of core screens so the shell feels cohesive rather than one-off animated.

#### Current Code References

- `frontend/src/app/layout/game-shell/*`
- `frontend/src/app/app.html`
- `frontend/src/app/app.scss`
- `documentation/03-ux/03-encounter-flow-transition-matrix.md`
- `documentation/03-ux/00-ux-and-debug-scope.md`

### UX-004: Run full responsive UX pass on core authenticated screens

**Milestone:** Authenticated Shell Fullscreen UX Pass  
**Status:** Open  
**Priority:** High

#### Problem

Once the shell, breakpoints, and transition system exist, the core authenticated screens need a coordinated pass so the experience feels like one game product instead of a mix of upgraded and legacy pages.

#### Acceptance Criteria

- Audit and update home, warband, inventory, shop, academy, guide, and key run screens against the new shell and breakpoint rules.
- Resolve the most obvious density, spacing, and hierarchy mismatches between screens.
- Verify the top HUD, navigation drawer, and content framing remain stable across representative flows.
- Update UX docs or validation checklists to reflect the new responsive shell behavior.

#### Current Code References

- `frontend/src/app/pages/home-page/*`
- `frontend/src/app/pages/warband-page/*`
- `frontend/src/app/pages/dice-page/*`
- `frontend/src/app/pages/shop-page/*`
- `frontend/src/app/pages/academy-page/*`
- `frontend/src/app/pages/run-*/*`
- `documentation/03-ux/*`
