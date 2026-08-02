---
Title: "Debug Page Analysis"
Status: Needs Review
Last Updated: 2026-08-01
Owner: Product + UX
Depends On:
  - documentation/04-ux/page-analysis/00-index.md
  - documentation/04-ux/08-page-layout-zones.md
Category: 04-ux
Tags:
  - ux
  - page-analysis
---

# Debug Page Analysis

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
