Screenshot: `raw-assets/design-review/screenshots/region-select.png`

## Purpose of the Screen
Let the player choose a region, understand its cost and risk, and begin a run from `RegionSelectScene`.

## Needed Player Interactions
- Click a region tile to select it.
- Double click an available region tile, or press `Start Run`, to begin.
- Read locked or insufficient-energy feedback when a route is unavailable.
- Use the bottom command strip to leave for other global destinations if needed.

## Information Need to Be Conveyed to Player
- Regions differ by energy cost, route length, and intended difficulty.
- `The Farm` is the tutorial route and default safe entry point.
- Locked regions and low-energy states are expected blockers, not bugs.
- Formation framing matters later in the run, so the scene should support the onboarding objective of choosing a sensible first route.

## Current Visual Challenges
- Startability is communicated in several places at once: lock overlay, energy label, tile overlay, intel copy, and button state.
- Double-click-to-activate is not obvious from the screen alone.
- Feedback hooks exist for locked and energy-blocked selections, but the current scene does not visibly render those messages yet.
- The intel panel communicates region flavor well, but next-step clarity depends on the button state being noticed.

## In-Screen Changes That Can Occur
- Region tiles can switch between locked, selectable, and not-enough-energy states after profile data loads.
- The selected tile outline moves as the player changes selection.
- The `Start Run` button relabels to `Locked` or `Need X Energy` and can disable itself.
- Successful run creation transitions to `MapExplorationScene`.
