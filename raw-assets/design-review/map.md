Screenshot: `raw-assets/design-review/screenshots/map.png`

## Purpose of the Screen
Act as the active-run map where the player reads node progression, chooses available routes, and manages abandon or refresh actions.

## Needed Player Interactions
- Click an available node to move into node resolution or rest management.
- Read node states without triggering blocked actions on locked or cleared nodes.
- Use `Refresh Map` or `Abandon Run` from the action column.
- Read temporary toast feedback after returning from another run screen.

## Information Need to Be Conveyed to Player
- Which nodes are `available`, `cleared`, or `locked`.
- The path forward through combat, loot, rest, boss, and exit nodes.
- The current run's sense of progress and risk without exposing deterministic outcomes.
- That rest is the only route back into active-run management changes.

## Current Visual Challenges
- The map needs to communicate route progression very quickly, but the node graph and overview chips compete for the same first-read attention.
- Locked versus available states must stay unmistakable, especially once more node art or metadata is added.
- Preview value is intentionally limited, so the scene can feel sparse if node identity is not visually strong enough.
- The action column is simple, which helps readability, but it leaves the map area carrying almost all strategic context.

## In-Screen Changes That Can Occur
- Refresh reloads the current run payload and redraws the graph.
- Returning from node resolution can inject a colored resolution message toast.
- Choosing a rest node routes into `RestManagementScene`; choosing a non-rest node routes into `NodeResolutionScene`.
- Abandoning the run can branch directly into `RunEndSummaryScene`.
