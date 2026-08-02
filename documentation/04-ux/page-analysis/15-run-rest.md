---
Title: "Run Rest Page Analysis"
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

# Run Rest Page Analysis

Route: `/run/rest/:nodeId`  
Auth: authenticated  
Component: `RunRestPageComponent`

## UX Pieces

- Shared authenticated HUD.
- PageFrame header for the rest node.
- Loading, error, and success alert stack.
- Recovery card with a primary `Rest` action.
- Formation grid for the current run squad when rest data is present.

## Data Displayed

- Rest-state availability from `restData()`.
- Recovery explanation copy that resting restores run units to full health.
- Run-unit formation grid rendered through `dg-run-unit-formation-grid`.

## Notes

- This is a compact transactional page.
- It does not show per-unit delta numbers today, only the formation state and the rest action.
