# MILESTONES FILE
----
Active milestones only. Move completed entries to `agent/MILESTONES_ARCHIVE.md`.

## Combat Resolution Correctness

**Status:** Planned

Remove hidden fallback behavior from combat resolution so battle outcomes, action timing, and dice effects are driven by authored combat data and simulated events.

Success criteria:

- Combat outcomes are always determined by resolved combat events, not score-based fallback estimates.
- Combat is not cut off by the current 3-5 round planning window.
- Player units and enemies require explicit schedulable ability sets.
- Ability schedules do not auto-fill unused ticks with hidden repeat actions.
- Dice rolls apply the full intended value, and combat logs explain their contribution clearly.
- Active system documentation is updated after implementation to describe the corrected rules.

### Related Issues

- Remove score-based combat outcome fallback
- Remove arbitrary combat round cutoff
- Require explicit ability sets for every combatant
- Remove automatic tick autofill behavior
- Apply full dice roll values in combat math

## UAT Feedback Fix Round 2

**Status:** Planned

Address run-event blockers found during UAT across rest recovery, shrine/hazard resolution clarity, chaos reel application, post-Wrong-Machine dialogue, and voluntary run-return framing.

Success criteria:

- Rest, shrine, and hazard nodes have clear immediate outcomes and visible player-facing results.
- Chaos reels apply the families, shapes, rules, and rewards that the UI presents.
- Persistent run effects have a clear backend contract and a visible frontend home.
- The Whim and mountain kobolds react after Wrong Machine recovery.
- Returning home from a run is framed as a voluntary return rather than a failure.

### Related Issues

- Repair run event node resolution and visibility
- Fix chaos reel encounter application
- Surface active run effects
- Add post-Wrong-Machine mountain dialogue
- Reflavor voluntary run return

## UAT Feedback Fix Round 1

**Status:** Active

Address the first concrete UAT feedback batch across landing/home navigation, warband management, commerce/academy presentation, and guide usability.

Success criteria:

- Landing, home, and menu affordances are clearer and less cluttered.
- Warband cards, filters, unit stat explanations, and squad drag/drop interactions are easier to use.
- Shop and academy remove redundant indicators and use available iconography.
- Guide navigation works and core map/class/combat references are accurate.

### Related Issues

- Polish home navigation and command controls
- Refine warband, unit, and squad-edit UX
- Clean up shop and academy presentation
- Repair guide navigation and combat reference content

## Critical Path UAT

**Status:** Planned

Continue validating the completed July 25 roadmap through the player-facing critical path and turn observed failures into severity-ranked issues.

Success criteria:

- Fresh-account UAT covers Farm, Mountains, Swamps, Wrong Machine recovery, Mystic Cave return, and first Pig Kin reconstruction.
- Repeat-run behavior is checked for story, stolen pages, and unlock messaging.
- Encounter variety and consumable pacing are sampled across multiple regions and seeds.
- Any UAT failures are logged with reproduction notes and player-facing severity.

### Related Issues

- Continue fresh-account July roadmap UAT
- Validate encounter and consumable feel

## UAT Polish Backlog

**Status:** Planned

Confirm release hygiene and hold lower-priority UAT polish that should not block the first fix round.

Success criteria:

- Low-severity improvements are deferred without blocking release hardening.
- `main`, roadmap analysis, active tracker, and generated-artifact policy are reconciled.
- Final validation expectations are documented before release hardening completes.

### Related Issues

- Confirm release merge and generated-artifact hygiene
