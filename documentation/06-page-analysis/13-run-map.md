# Run Map Page Analysis
----

Status: active  
Last Updated: 2026-07-29  
Owner: UX + Engineering  
Depends On: `documentation/06-page-analysis/00-index.md`, `documentation/03-ux/08-page-layout-zones.md`  


Route: `/run/map`  
Auth: authenticated  
Component: `RunMapPageComponent`

## UX Pieces

- Shared authenticated HUD.
- PageFrame header for the current run.
- Loading and error alerts.
- Four-part run layout:
  - node legend
  - abandon-run action block
  - map viewport
  - run-unit formation panel

## Data Displayed

### Legend

- Node legend entries with icon and label for each node type currently represented by `legendEntries()`.

### Map

- Background art from `mapBackgroundUrl()`.
- SVG edge network with available, cleared, and locked path states.
- SVG node markers with:
  - node icon
  - node type label
  - node status

### Formation Panel

- `dg-run-unit-formation-grid` showing current run squad condition inside the active formation.

## Notes

- The page is traversal-focused and intentionally lightweight on dense text.
- Node resolution is delegated to `/run/node/:nodeId` or `/run/rest/:nodeId`.
