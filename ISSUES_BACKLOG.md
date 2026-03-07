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
title: Add create-run domain error branch integration coverage
status: unstarted
priority: high
execution: deferred
ready: no
owner: unassigned
milestone: unassigned
created: 2026-03-04
updated: 2026-03-04
description: |
  Add backend integration tests for `POST /api/v1/runs` domain-error branches: `run_already_active`, `region_not_found`, `region_disabled`, `region_locked`, missing active squad, and `insufficient_energy` to prevent gameplay regression in run-start gating.
---
title: Add resolve-node negative-state branch coverage for ownership and availability rules
status: unstarted
priority: high
execution: deferred
ready: no
owner: unassigned
milestone: unassigned
created: 2026-03-04
updated: 2026-03-04
description: |
  Add integration coverage for `POST /api/v1/runs/:runId/nodes/:nodeId/resolve` error branches including run-not-active, node locked/unavailable, run ownership mismatch, invalid team ownership, and malformed body handling.
---
title: Add claim-battle non-happy-path coverage for claimability and ownership guards
status: unstarted
priority: medium
execution: deferred
ready: no
owner: unassigned
milestone: unassigned
created: 2026-03-04
updated: 2026-03-04
description: |
  Add integration tests for `POST /api/v1/battles/:battleId/claim` non-happy-path branches (`battle_not_completed`, forbidden ownership access, and invalid outcome-state guard) to protect claim-state invariants.
---
title: Add starter-pack provisioning invariants tests for baseline bootstrap flow
status: unstarted
priority: medium
execution: deferred
ready: no
owner: unassigned
milestone: unassigned
created: 2026-03-04
updated: 2026-03-04
description: |
  Add tests for `GrantService` baseline provisioning to verify idempotent starter grants and expected seed-data dependencies (starter region/unit/dice definitions) across clean and pre-seeded test DB states.
---
title: Add gameplay-effect tests for canonical active and passive ability handlers
status: unstarted
priority: medium
execution: deferred
ready: no
owner: unassigned
milestone: unassigned
created: 2026-03-04
updated: 2026-03-04
description: |
  Extend combat unit tests beyond handler registry coverage to assert per-ability gameplay effects (damage/buffs/debuffs/targeting semantics) for canonical active and passive handlers.
