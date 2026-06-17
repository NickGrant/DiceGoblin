# ISSUES FILE
----
Active issues only. Move completed entries to `agent/ISSUES_ARCHIVE.md`.

## Mobile Improvements

### MI-003: Make unit loadout editing touch-friendly

**Milestone:** Mobile Improvements  
**Status:** Open  
**Priority:** High

#### Problem

Unit detail loadout editing appears optimized for pointer-based drag/drop interactions. Ability cards, inline dice slots, loadout bars, and small remove buttons may be difficult to use on phone screens.

#### Acceptance Criteria

- Ability and dice loadout changes can be completed reliably on touch devices.
- Provide a tap/select fallback or equivalent non-drag interaction for assigning dice or abilities.
- Remove controls meet mobile touch-target expectations.
- Inline slots and loadout bars remain readable at phone widths.
- Verify unit details on a phone-width viewport using touch emulation.

#### Current Code References

- `frontend/src/app/pages/unit-details-page/unit-details-page.component.*`
- `frontend/src/app/pages/unit-details-page/unit-details-page.component.scss`

### MI-004: Add mobile viewport regression checks for key play flows

**Milestone:** Mobile Improvements  
**Status:** Open  
**Priority:** Medium

#### Problem

Responsive behavior is spread across shared layout components and page-level SCSS files. A repeatable mobile regression pass would make future UI changes safer.

#### Acceptance Criteria

- Add a documented mobile QA checklist or automated visual/regression coverage for common phone portrait and landscape viewports.
- Cover at least: login, guide, home, warband, squad details, unit details, dice inventory, shop, regions, run map, run node, and run summary.
- Include checks for fixed bottom HUD overlap, horizontal overflow, unreadable cards, and unreachable primary actions.
- The checklist or tests are easy to run before watcher testing.

#### Current Code References

- `frontend/src/app/layout/game-shell/game-shell.component.scss`
- `frontend/src/app/layout/bottom-command-strip/bottom-command-strip.component.scss`
- `frontend/src/app/pages/*/*.component.scss`
