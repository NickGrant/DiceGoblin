Screenshot: `raw-assets/design-review/screenshots/rest-management.png`

## Purpose of the Screen
Handle the limited active-run rest phase where the player repositions the current squad, makes a few controlled upgrades, and finalizes the stop.

## Needed Player Interactions
- Reposition units on the formation grid.
- Select squad members from the unit panel.
- Open inventory or store-style actions tied to the rest stop.
- Apply intermediate edits and finalize the rest when satisfied.

## Information Need to Be Conveyed to Player
- This is a run-scoped management surface, not the full between-run warband editor.
- Current unit HP, squad membership, and formation all matter before leaving the rest node.
- Store or upgrade actions are limited and contextual to the rest phase.
- Finalizing rest commits the state and returns the player to the run map.

## Current Visual Challenges
- The screen mixes formation editing, run-state summaries, and rest actions, so priorities can feel split between tactical and transactional tasks.
- The content area reuses patterns from squad editing, which is helpful, but it can reduce the sense that this screen has special run-phase stakes.
- Summary and status text need to stay readable while action buttons stack densely on the right.
- Players need a strong visual distinction between planning changes and having finalized them.

## In-Screen Changes That Can Occur
- The scene begins in a loading or open-rest state and then fills with run-specific squad data.
- Formation edits and selected units change grid occupancy, card states, and action availability.
- Inventory or purchase actions can branch into related management flows and then return here.
- Finalize locks the rest state and routes back to `MapExplorationScene`.
