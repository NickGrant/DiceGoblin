# MILESTONES BACKLOG
----

## Purpose
- `MILESTONES_BACKLOG.md` tracks deferred milestone groupings outside the active execution lane.
- Keep `MILESTONES.md` focused on active/current milestone execution context.
- Promote milestones from this file into `MILESTONES.md` when they are opened for execution.

## Backlog Milestones

---
name: Milestone 18 - Run UI Layout Consistency Pass
status: not-started
execution_window: closed
is_current: no
issues:
	- Continue run panel sizing mismatch with start run
	- Rest screen layout pass for squad and controls
description: |
	Normalize run-adjacent panel sizing and rest-scene composition so UI density,
	spacing, and hierarchy are consistent with the current visual baseline.

---
name: Milestone 19 - Run Flow and Recovery UX
status: not-started
execution_window: closed
is_current: no
issues:
	- Auto-return to map after finalize rest
	- Mark no-enemies node resolved and show reason
	- Add back to map action on resolve node
	- Fix abandon run confirmation button overlap
	- Replace create squad native dialog with styled modal
	- Rename abandon dialog options to abandon and stay
	- Refresh home screen state immediately after abandon
description: |
	Improve run-state transitions, error recovery messaging, and dialog consistency
	so player flow remains continuous and clearly recoverable across node resolution
	and abandonment paths.

---
name: Milestone 20 - Region Select UX and Readability
status: not-started
execution_window: closed
is_current: no
issues:
	- Distinguish locked region appearance in choose region
	- Improve region title readability and placement
	- Require double-click to start unlocked region
	- Set choose region default selection and intel CTA
description: |
	Refine region-select readability and interaction model with clearer lock states,
	stronger title contrast, and a deliberate selection/start flow.
