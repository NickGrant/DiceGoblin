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
