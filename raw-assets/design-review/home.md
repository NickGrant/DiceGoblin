Screenshot: `raw-assets/design-review/screenshots/home.png`

## Purpose of the Screen
Serve as the authenticated hub that orients the player, exposes the main between-run destinations, and routes either into a new run or an active run.

## Needed Player Interactions
- Choose one large destination panel: start or continue run, warband, shop, inventory, or dev panel when enabled.
- Use the persistent bottom command strip for global navigation and account actions.
- Hover panels to reveal their labels before committing.

## Information Need to Be Conveyed to Player
- The immediate objective is to prep the squad and start or resume a run.
- `Start Run` changes to `Continue Run` when `active_run` exists.
- Warband, inventory, and shop are between-run management spaces.
- The bottom strip is persistent across authenticated scenes.

## Current Visual Challenges
- The home screen now exposes five destination panels, so the original three-choice onboarding simplicity is diluted.
- Panel meaning depends heavily on hover labels when art is present, which can slow first-read comprehension.
- The welcome band and panel grid both carry orientation copy, creating some duplication.
- Disabled dev panel state can look similar to a normal destination card because it still occupies equal visual weight.

## In-Screen Changes That Can Occur
- The start panel swaps between `Start Run` and `Continue Run` after a fresh profile fetch.
- Hover and press states darken panels and reveal overlay text.
- The dev panel can be disabled by environment configuration.
- Logout from the bottom strip leaves the authenticated flow.
