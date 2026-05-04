Screenshot: `raw-assets/design-review/screenshots/squad-details.png`

## Purpose of the Screen
Own squad membership, formation editing, activation, rename, save, and deletion for a selected squad without becoming the unit ability editor.

## Needed Player Interactions
- Click a unit, then click a formation cell to place it.
- Click a formation cell directly to target placement.
- Double click a filled cell to remove the placed unit.
- Use `Back`, `Rename`, `Clear Cell`, `Save Squad`, `Set Active`, and `Delete Squad` as allowed.

## Information Need to Be Conveyed to Player
- Left is `Back` and right is `Front` for formation logic.
- The squad can include bench members through `unit_ids`, not only currently placed bodies.
- Active and reserve squad status matters, especially because active squads cannot be deleted here.
- If a run is already active, the screen should signal that changes are still possible but should be made intentionally.

## Current Visual Challenges
- The placement model is powerful but not fully self-explanatory because it mixes unit selection, cell targeting, and double-click removal.
- Formation rules for larger footprints are enforced, but the reason for a failed placement is only surfaced as a brief toast.
- The action column is button-heavy, so save, activate, and destructive actions sit close together.
- The screen has strong editor utility, but limited visual reinforcement for which unit is currently "picked up" versus merely already placed.

## In-Screen Changes That Can Occur
- The scene loads squad data, then fills the unit grid and 3x3 formation grid.
- Selecting or placing units changes card badges, outlines, and the enabled state of `Clear Cell`.
- Save, activate, rename, and delete actions can each trigger API updates and toast feedback.
- After save or activate, the scene reloads profile-backed squad state; after delete, it returns to `WarbandManagementScene`.
