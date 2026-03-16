# ISSUES FILE
----
Active issues only. Move completed entries to `ISSUES_ARCHIVE.md`.

---
title: Align encounter map node-state contract across backend and frontend
status: unstarted
priority: high
execution: active
ready: yes
milestone: Encounter Map Resolution and UX Hardening
description: |
	Reconcile node status and type handling between backend run-map payloads and frontend
	map rendering logic so locked/available/cleared states and node-type routing remain
	consistent for combat, rest, boss, and exit nodes.

---
title: Stabilize encounter map node placement and edge readability
status: unstarted
priority: high
execution: active
ready: yes
milestone: Encounter Map Resolution and UX Hardening
description: |
	Improve NodeList placement and graph readability so nodes do not overlap, remain within
	visible bounds, and preserve clear path/edge legibility under varying map shapes.

---
title: Finalize encounter map interaction flow for combat, rest, and exit nodes
status: unstarted
priority: high
execution: active
ready: yes
milestone: Encounter Map Resolution and UX Hardening
description: |
	Ensure node click behavior consistently routes to the expected scene flows (combat/node
	resolution, rest management, and exit completion) and returns to map with correct
	post-resolution context messaging.

---
title: Harden map fallback and stale-run recovery messaging
status: unstarted
priority: medium
execution: active
ready: yes
milestone: Encounter Map Resolution and UX Hardening
description: |
	Improve user-facing fallback behavior when current-run payloads are missing, stale,
	or unavailable so map recovery paths are explicit and do not dead-end scene flow.

---
title: Add encounter map frontend regression tests for NodeList construction and transitions
status: unstarted
priority: high
execution: active
ready: yes
milestone: Encounter Map Resolution and UX Hardening
description: |
	Repair and expand map-scene regression coverage for NodeList instantiation, fallback
	rendering, and node click transition guards to prevent map-flow regressions.

---
title: Add node-resolution scene regression tests for action-state transitions
status: unstarted
priority: high
execution: active
ready: yes
milestone: Encounter Map Resolution and UX Hardening
description: |
	Add or update NodeResolutionScene tests to verify action-button state transitions
	(Resolving -> Back to Map/Continue) and terminal/non-terminal navigation behaviors.
