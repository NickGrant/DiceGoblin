# ISSUES FILE
----
Active issues only. Move completed entries to `agent/ISSUES_ARCHIVE.md`.

## Authenticated Shell Fullscreen UX Pass

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
