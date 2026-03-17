# MILESTONES FILE
----
Active milestones only. Move completed entries to `MILESTONES_ARCHIVE.md`.

---
name: Milestone 21 - Reusable Modal Architecture
status: in-progress
execution_window: open
is_current: yes
issues:
	- Define modal abstraction contract and migration plan
	- Implement BaseModal and yes/no confirmation variant
	- Implement InputModal by extending confirmation modal
	- Migrate existing modal call sites to new modal hierarchy
	- Add modal regression tests for lifecycle and input behavior
description: |
	Normalize modal behavior under a composable base class so confirmation and input
	flows share layout, lifecycle, and interaction rules while reducing duplicated
	scene-level dialog wiring.
