# ISSUES FILE
----
Active issues only. Move completed entries to `agent/ISSUES_ARCHIVE.md`.

## Mobile Improvements

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

