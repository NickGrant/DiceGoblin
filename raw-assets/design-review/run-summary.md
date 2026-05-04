Screenshot: `raw-assets/design-review/screenshots/run-summary.png`

## Purpose of the Screen
Close out a run with a shared terminal summary for completed, failed, or abandoned outcomes.

## Needed Player Interactions
- Review the outcome header and the two summary panels.
- Read rewards, progression, survivor, and defeated-unit breakdowns.
- Use the single `Continue` action to leave the run and return to the home loop.

## Information Need to Be Conveyed to Player
- The run's terminal status and what that status means.
- What rewards or progression were granted.
- Which units survived and which were defeated.
- That the current run is over and cannot be resumed from this screen.

## Current Visual Challenges
- The scene must communicate three different emotional outcomes while reusing one shell.
- Dense list content can become text-heavy, especially when rewards and progression both grow.
- Empty states need to feel intentional rather than like missing data.
- The action column is intentionally minimal, so the summary content has to carry almost all of the closure value.

## In-Screen Changes That Can Occur
- The hero card and accent color shift based on completed, failed, or abandoned status.
- Rewards, progression, survivors, and defeated lists expand or collapse based on payload content.
- The chip counts update to match the summary payload.
- The continue action exits the run flow and returns to `HomeScene`.
