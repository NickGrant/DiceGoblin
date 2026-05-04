Screenshot: `raw-assets/design-review/screenshots/node-resolution.png`

## Purpose of the Screen
Resolve non-rest nodes through a shared encounter-result surface that can present combat playback, loot receipts, terminal outcomes, and onward routing.

## Needed Player Interactions
- Wait for the node to resolve, then use the primary continue action.
- For combat-style nodes, play, pause, step, speed up, or skip the event timeline.
- Retry from an error state when the resolve call fails.
- In dev-enabled flows, optionally copy the current battle log.

## Information Need to Be Conveyed to Player
- What node type is being resolved and whether it is combat, loot, boss, or exit flavored.
- The encounter outcome, key battle or reward details, and any unlocked follow-up nodes.
- Unit impact, claim summary, and whether the run continues or becomes terminal.
- When combat controls are relevant versus when they should disappear for non-combat variants.

## Current Visual Challenges
- Combat readability is the biggest pressure point because status, detail text, timeline controls, log content, and formation visuals all coexist in one scene.
- The shared scene has to support very different variants, so some states can feel visually overloaded while simpler loot states can feel comparatively empty.
- Error and retry messaging sits in the same visual system as success messaging, which can blur urgency if contrast is weak.
- The action column must stay obvious without distracting from the actual encounter report.

## In-Screen Changes That Can Occur
- The initial placeholder transitions into success, error, or terminal state after resolve finishes.
- Combat variants reveal timeline playback controls and tick-by-tick battle detail.
- Loot and other non-combat variants collapse down to a simpler reward-focused presentation.
- The primary action can return to the map or advance into `RunEndSummaryScene` depending on outcome.
