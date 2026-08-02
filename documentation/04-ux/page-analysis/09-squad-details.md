---
Title: "Squad Details Page Analysis"
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

# Squad Details Page Analysis

Route: `/warband/squads/:squadId`  
Auth: authenticated  
Component: `SquadDetailsPageComponent`

## UX Pieces

- Shared authenticated HUD.
- PageFrame header using the squad name.
- Alert stack for errors, success messages, run lock warning, and squad-over-cap warning.
- Two-column editing layout:
  - `Squad Setup`
  - `Formation`
- Drag/drop and tap-first squad assignment controls.

## Data Displayed

### Squad Setup Column

- Squad name input.
- Save button state.
- Available unit pool with, per unit:
  - display name
  - unit type name or slug
  - level
  - selected state
  - locked-in-run note when applicable

### Formation Column

- Current selected unit count versus `squadUnitCap()`.
- Selection helper copy explaining whether a unit is coming from the pool or a formation slot.
- 3x3 formation cells.
- Per occupied cell:
  - unit name
  - unit type
  - level
  - selected state
- Per empty cell:
  - empty `Drop unit` state

## Notes

- The page supports both pointer drag/drop and tap-first placement.
- Active-run squads are locked from mutation.
