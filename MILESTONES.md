# MILESTONES FILE
----
Active milestones only. Move completed entries to `MILESTONES_ARCHIVE.md`.

---
name: Encounter Map Resolution and UX Hardening
status: not-started
execution_window: open
is_current: yes
issues:
	- Align encounter map node-state contract across backend and frontend
	- Stabilize encounter map node placement and edge readability
	- Finalize encounter map interaction flow for combat, rest, and exit nodes
	- Harden map fallback and stale-run recovery messaging
	- Add encounter map frontend regression tests for NodeList construction and transitions
	- Add node-resolution scene regression tests for action-state transitions
description: |
	Consolidate encounter-map behavior, contracts, and UX reliability so run navigation,
	node resolution entry points, and map readability are stable before the next gameplay
	resolution pass.
