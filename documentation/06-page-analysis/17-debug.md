# Debug Page Analysis
----

Status: active  
Last Updated: 2026-07-29  
Owner: UX + Engineering  
Depends On: `documentation/06-page-analysis/00-index.md`, `documentation/03-ux/08-page-layout-zones.md`  


Route: `/debug`  
Auth: authenticated, runtime-gated  
Component: `DebugPageComponent`

## UX Pieces

- Shared authenticated HUD.
- PageFrame header for the debug panel.
- Alert stack for errors, success messages, and loading state.
- Two-column layout:
  - `Grants`
  - `Catalog`

## Data Displayed

### Grants Column

- Numeric input for soft currency grants.
- Unit dropdown sourced from `catalog.unit_types`.
- Die controls:
  - sides dropdown from `catalog.dice_definitions`
  - rarity dropdown
- Region item dropdown from `catalog.region_items`.
- Owned-unit dropdown from `catalog.owned_units`.
- Numeric level input for direct unit-level changes.
- Action buttons for:
  - grant currency
  - grant unit
  - grant die
  - grant item
  - set unit level

### Catalog Column

- Unit type count.
- Dice definition count.
- Region item count.
- `Reset Account` action.

## Notes

- This page is explicitly for testing and balancing support rather than player-facing progression.
