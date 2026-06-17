# ISSUES FILE
----
Active issues only. Move completed entries to `agent/ISSUES_ARCHIVE.md`.

## Watcher Testing

### WT-003: Add navigation from inside the game to the guide

**Milestone:** Watcher Testing  
**Status:** Open  
**Priority:** High

#### Problem

Players need a discoverable route from inside the authenticated game shell to the guide.

#### Acceptance Criteria

- A logged-in player can navigate from the game UI to the guide.
- The guide entry point is visible from a sensible persistent or high-traffic location, such as the bottom command strip, home page, or both.
- Returning from the guide to the game is supported.
- Navigation does not break the authenticated session.
- Opening the guide during an active run does not discard run state.

#### Current Code References

- `frontend/src/app/app.routes.ts`
- `frontend/src/app/layout/bottom-command-strip/bottom-command-strip.component.*`
- `frontend/src/app/layout/game-shell/game-shell.component.*`
- `frontend/src/app/pages/home-page/home-page.component.*`

## Mobile Improvements

### MI-001: Make the game shell and bottom HUD responsive on phones

**Milestone:** Mobile Improvements  
**Status:** Open  
**Priority:** High

#### Problem

The current Angular game shell reserves fixed bottom space for the bottom HUD, and the HUD uses a large fixed-position base bar image with large internal padding. Below small breakpoints, only limited adjustments are applied. On phone screens this may crowd navigation, hide content behind the HUD, reserve too much bottom space, or create awkward vertical scrolling.

#### Acceptance Criteria

- The bottom HUD fits common phone portrait widths without overlapping controls.
- Main content bottom spacing responds to the actual mobile HUD footprint rather than relying on a fixed large padding value.
- Mobile layout accounts for safe-area insets where practical.
- Home, Warband, Dice, Shop, Regions, Run Map, and Run Node remain reachable with the HUD visible.
- Verify behavior on at least one portrait phone viewport and one landscape phone viewport.

#### Current Code References

- `frontend/src/app/layout/game-shell/game-shell.component.html`
- `frontend/src/app/layout/game-shell/game-shell.component.scss`
- `frontend/src/app/layout/bottom-command-strip/bottom-command-strip.component.html`
- `frontend/src/app/layout/bottom-command-strip/bottom-command-strip.component.scss`

### MI-002: Make formation management usable on touch devices

**Milestone:** Mobile Improvements  
**Status:** Open  
**Priority:** High

#### Problem

Squad formation management uses drag/drop-style interactions. The formation grid becomes single-column below the mobile breakpoint, but touch users still need a reliable way to move units between the pool and formation cells.

#### Acceptance Criteria

- Formation assignment can be completed reliably on touch devices.
- Provide a tap/select fallback or equivalent non-drag interaction for moving units into formation cells.
- The single-column mobile formation layout remains readable.
- Empty, occupied, locked, and disabled states are clear on mobile.
- Verify on a phone-width viewport using touch emulation.

#### Current Code References

- `frontend/src/app/pages/squad-details-page/squad-details-page.component.*`
- `frontend/src/app/pages/squad-details-page/squad-details-page.component.scss`
- `frontend/src/app/shared/ui/run-unit-formation-grid/run-unit-formation-grid.component.*`

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

