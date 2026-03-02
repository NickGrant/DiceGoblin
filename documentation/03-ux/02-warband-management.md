# Warband Management UX and System Contract
----

Status: active  
Last Updated: 2026-03-02  
Owner: UX + Frontend  
Depends On: `frontend/src/scenes/WarbandManagementScene.ts`, `frontend/src/components/FormationGrid3x3.ts`, `frontend/src/components/UnitListPanel.ts`

## Purpose
- Define expected UX behavior and system-side assumptions for warband management.
- Keep frontend interactions and backend squad payload contracts aligned.

## Scope
- Squad creation from warband scene when no active squad exists.
- Unit list interactions and formation placement on a 3x3 grid.
- Save behavior for `unit_ids` membership and `formation` placement payload.

## Interaction Model
- The formation grid is a fixed 3x3 layout with cells `A1..C3`.
- User can place units by:
  - selecting a grid cell then clicking a unit, or
  - selecting a unit and then clicking a grid cell.
- Double-click on an occupied cell clears that cell.
- `Clear Cell` action clears only the currently selected occupied cell.

## Bench Membership (Intentional Current Behavior)
- A unit may exist in squad `unit_ids` without being placed in formation.
- This "bench membership" behavior is intentional for current iteration and may be tightened later.
- Save payload always includes:
  - full `unit_ids` membership set
  - full 3x3 `formation` with nulls for empty cells

## Visual State Rules
- Unit row `highlighted`: unit is in current squad membership.
- Unit row `outlined`: unit is currently placed in formation.
- Unit row `badge`: shows `SELECTED` or `PLACED` based on interaction state.
- `Clear Cell` button is enabled only when selected cell is occupied.

## Error and Success Feedback
- Load failure: centered error text on scene.
- Create/save failure: toast message with error text.
- Save success: short-lived success toast (`Saved!`).

## Data Contract Notes
- Profile hydration currently reads squads from `profile.data.squads`.
- Squad update payload shape:
```json
{
  "unit_ids": ["2001", "2002"],
  "formation": [
    { "cell": "A1", "unit_instance_id": "2001" },
    { "cell": "B1", "unit_instance_id": null }
  ]
}
```
