Screenshot: `raw-assets/design-review/screenshots/unit-details.png`

## Purpose of the Screen
Serve as the main unit editor for reviewing a unit's identity, loadout, dice bindings, and promotion path.

## Needed Player Interactions
- Switch between loadout and promotion tabs.
- Select equipped abilities and available dice.
- Equip or unequip dice from ability slots.
- Rename the unit, remove equipped abilities, or promote when requirements are met.

## Information Need to Be Conveyed to Player
- The unit's role, tier, level, XP state, and equipped loadout budget.
- Which abilities are equipped, which are merely unlocked, and how loadout order affects play.
- Which dice are available, already bound, or incompatible with the selected slot.
- Whether promotion is available, blocked, or read-only during a run.

## Current Visual Challenges
- This is one of the densest screens in the game, with summary, tabs, loadout rows, dice slots, and actions competing for attention.
- Shared-slot and duplicate-ability rules can be hard to parse from the current visual treatment alone.
- Promotion requirements and destination choices are powerful but easy to miss beside the loadout-heavy default view.
- The action column carries many stateful buttons, so affordance clarity matters more than decoration here.

## In-Screen Changes That Can Occur
- Tab changes swap the central workflow between loadout editing and promotion.
- Selecting a different ability row changes the active slot controls and dice recommendations.
- Equip, unequip, rename, and promotion actions can trigger API writes and refreshed profile state.
- Debug captures can also open directly to a specific initial tab for review.
