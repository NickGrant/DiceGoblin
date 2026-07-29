# Warband Page Analysis
----

Status: active  
Last Updated: 2026-07-29  
Owner: UX + Engineering  
Depends On: `documentation/06-page-analysis/00-index.md`, `documentation/03-ux/08-page-layout-zones.md`  


Route: `/warband`  
Auth: authenticated  
Component: `WarbandPageComponent`

## UX Pieces

- Shared authenticated HUD.
- PageFrame header for warband management.
- Alert stack for errors, success messages, and active-run lock messaging.
- `Squads` section with create-squad action and squad cards.
- `Units` section with filters, sort controls, result count, unit tile rail, and inspect panel.

## Data Displayed

### Squads Section

- Saved squads sorted for presentation.
- Per squad card:
  - squad name
  - unit count
  - active status
  - locked state when tied to the active run

### Units Section

- Filter options:
  - unit type
  - sort order
  - tier
  - level range
- Result count as `filteredUnits / totalUnits`.
- Unit tile rail showing:
  - unit portrait art when available
  - unit display name
- Inspect side panel using `dg-unit-grid-object` for the selected unit.

## Notes

- This page is a roster hub, not a detailed editor.
- Squad edits and membership changes are pushed into the squad details page.
