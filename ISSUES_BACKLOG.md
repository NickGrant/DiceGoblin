# ISSUES BACKLOG
----

## Purpose
- `ISSUES_BACKLOG.md` tracks deferred planning issues that are not part of the active execution lane.
- Keep `ISSUES.md` focused on active/current milestone execution context.
- Move items from this file into `ISSUES.md` when they become execution-ready.

## Issue Template
Use the same issue schema as `ISSUES.md`.

## Backlog Issues

---

title: Continue run panel sizing mismatch with start run
status: unstarted
priority: medium
execution: deferred
ready: no
milestone: Milestone 18 - Run UI Layout Consistency Pass
description: Continue run panel should match the start run panel dimensions and layout; start run is currently the correct reference.

---

title: Rest screen layout pass for squad and controls
status: unstarted
priority: high
execution: deferred
ready: no
milestone: Milestone 18 - Run UI Layout Consistency Pass
description: Apply the same layout improvements used on squad details to rest screen, including a smaller unit list, better grid positioning, improved button spacing/positioning, and removal of helper text in the bottom column and bottom-left area.

---

title: Auto-return to map after finalize rest
status: unstarted
priority: high
execution: deferred
ready: no
milestone: Milestone 19 - Run Flow and Recovery UX

No deferred issues currently.
description: Expand endpoint envelope assertions to verify response shape and required keys consistently instead of relying mainly on HTTP status and ok flags.

---

title: Introduce shared Phaser scene mock fixtures
status: unstarted
priority: high
execution: deferred
ready: no
milestone: Milestone 26 - Frontend Scene Test Harness Consolidation
description: Build reusable test fixtures for FakeScene/FakeContainer/input hooks used by scene tests and remove duplicated inline mock blocks across test files.

---

title: Parameterize repetitive frontend scene error-path tests
status: unstarted
priority: medium
execution: deferred
ready: no
milestone: Milestone 26 - Frontend Scene Test Harness Consolidation
description: Convert repetitive fallback/error-case tests into table-driven variants to reduce boilerplate and preserve coverage of each distinct error source.

---

title: Consolidate frontend mutation CSRF assertion patterns
status: unstarted
priority: medium
execution: deferred
ready: no
milestone: Milestone 26 - Frontend Scene Test Harness Consolidation
description: Reduce duplicated mutation CSRF/header assertions via shared helpers or parameterized test utilities while preserving endpoint-specific behavior checks.

---

title: Enforce richer contract-format assertions in frontend validators
status: unstarted
priority: medium
execution: deferred
ready: no
milestone: Milestone 27 - Assertion Depth and Contract Fidelity
description: Expand validator tests to assert format-level constraints such as timestamp shape, token-like fields, and numeric domain boundaries.

---

title: Add payload-shape assertions to scene routing tests
status: unstarted
priority: medium
execution: deferred
ready: no
milestone: Milestone 27 - Assertion Depth and Contract Fidelity
description: Ensure scene.start assertions validate payload structure and keys, not only destination scene names.

---

title: Expand adapter and component edge-case coverage
status: unstarted
priority: low
execution: deferred
ready: no
milestone: Milestone 27 - Assertion Depth and Contract Fidelity
description: Add targeted edge-case tests for adapter normalization and stateful component behavior to increase confidence in malformed-input handling.

---
