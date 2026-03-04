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
title: Remove unsafe any-casts from API client team mutation flow
status: unstarted
priority: medium
execution: deferred
ready: no
owner: unassigned
milestone: Milestone 10 - Engineering Maintainability and Contracts
created: 2026-03-02
updated: 2026-03-02
description: |
  [Role: Senior Developer] `frontend/src/services/apiClient.ts` currently relies on repeated `as any` coercions for session CSRF access and team response typing. Introduce explicit response interfaces and typed helpers to prevent runtime-shape drift and improve compile-time guarantees.
---
title: Add end-to-end player journey document for first-session flow
status: unstarted
priority: medium
execution: deferred
ready: no
owner: unassigned
milestone: Milestone 13 - Player Experience and UX Flow
created: 2026-03-02
updated: 2026-03-02
description: |
  [Role: Technical Product Manager] Document the canonical player journey from session bootstrap to first run completion/claim, including failure states and expected UX touchpoints, to align sequencing decisions across roles.
---
title: Refactor WarbandManagementScene logic into testable state module
status: unstarted
priority: medium
execution: deferred
ready: no
owner: unassigned
milestone: Milestone 10 - Engineering Maintainability and Contracts
created: 2026-03-02
updated: 2026-03-02
description: |
  [Role: Senior Developer] `frontend/src/scenes/WarbandManagementScene.ts` contains significant state/interaction logic mixed with rendering orchestration. Extract placement/save state transitions into a pure module to reduce scene complexity and improve test coverage quality.
---
title: Consolidate repeated controller auth/csrf/service bootstrap patterns
status: unstarted
priority: medium
execution: deferred
ready: no
owner: unassigned
milestone: Milestone 10 - Engineering Maintainability and Contracts
created: 2026-03-02
updated: 2026-03-02
description: |
  [Role: Senior Developer] API controllers repeatedly construct services and duplicate auth/CSRF handling patterns. Introduce shared helpers/base patterns to reduce drift and improve maintainability.
---
title: Remove nested transaction ownership between controllers and repositories
status: unstarted
priority: medium
execution: deferred
ready: no
owner: unassigned
milestone: Milestone 10 - Engineering Maintainability and Contracts
created: 2026-03-02
updated: 2026-03-02
description: |
  [Role: Senior Developer] Several flows nest controller-level and repository-level transactions. Define clear transaction ownership boundaries to avoid hidden rollback/commit edge cases and simplify reasoning about persistence behavior.
---
title: Introduce stricter typed DTO mapping for profile squad payload assembly
status: unstarted
priority: low
execution: deferred
ready: no
owner: unassigned
milestone: Milestone 10 - Engineering Maintainability and Contracts
created: 2026-03-02
updated: 2026-03-02
description: |
  [Role: Senior Developer] `ProfileService` and repository payload composition rely on broad array shapes. Add explicit DTO mapping/types for response assembly to reduce runtime shape drift.
---
title: Add first-session onboarding and objective framing UX spec
status: unstarted
priority: medium
execution: deferred
ready: no
owner: unassigned
milestone: Milestone 13 - Player Experience and UX Flow
created: 2026-03-02
updated: 2026-03-02
description: |
  [Role: Game Designer] Define onboarding messaging and immediate goals from first login through first run start to reduce confusion and increase early-session engagement.
---
title: Add encounter preview UX for node risk and reward expectations
status: unstarted
priority: medium
execution: deferred
ready: no
owner: unassigned
milestone: Milestone 13 - Player Experience and UX Flow
created: 2026-03-02
updated: 2026-03-02
description: |
  [Role: Game Designer] Add UX support for encounter preview information (node intent, risk/reward hints) before commitment to improve perceived agency and flow clarity.
---
title: Add post-battle progression summary UX contract
status: unstarted
priority: medium
execution: deferred
ready: no
owner: unassigned
milestone: Milestone 13 - Player Experience and UX Flow
created: 2026-03-02
updated: 2026-03-02
description: |
  [Role: Game Designer] Define post-battle summary UX (XP gains, rewards, squad changes, next-step prompt) to strengthen player feedback loops.
---
title: Define run failure and recovery UX states for partial and total defeat
status: unstarted
priority: medium
execution: deferred
ready: no
owner: unassigned
milestone: Milestone 13 - Player Experience and UX Flow
created: 2026-03-02
updated: 2026-03-02
description: |
  [Role: Game Designer] Specify UX flows for partial defeat retry and total failure outcomes so recovery behavior feels intentional and understandable.
---
title: Create player-value feature ordering model for upcoming milestones
status: unstarted
priority: low
execution: deferred
ready: no
owner: unassigned
milestone: Milestone 13 - Player Experience and UX Flow
created: 2026-03-02
updated: 2026-03-02
description: |
  [Role: Game Designer] Define a player-value prioritization rubric (clarity, engagement, retention impact) to guide sequencing decisions across non-combat and combat-adjacent features.
---
title: Reduce frontend production bundle size via scene-level code splitting
status: unstarted
priority: medium
execution: deferred
ready: no
owner: unassigned
milestone: Milestone 10 - Engineering Maintainability and Contracts
created: 2026-03-03
updated: 2026-03-03
description: |
  [Role: Senior Developer] Frontend build currently emits a large primary bundle (~1.5 MB minified warning). Introduce scene-level lazy loading and/or manual chunking strategy to lower initial payload size and keep build warnings actionable.
---
title: Implement exit-node completion flow and completed run transition
status: unstarted
priority: high
execution: deferred
ready: no
owner: unassigned
milestone: Milestone 15 - Backend Gameplay Completion
created: 2026-03-04
updated: 2026-03-04
description: |
  Add run-map support for an always-visible exit node that is only reachable through the boss path and implement backend run transition to `completed` when exit is successfully taken.
---
title: Implement run-end cleanup for completed status while preserving earned XP
status: unstarted
priority: high
execution: deferred
ready: no
owner: unassigned
milestone: Milestone 15 - Backend Gameplay Completion
created: 2026-03-04
updated: 2026-03-04
description: |
  Extend terminal cleanup orchestration so `completed`, `failed`, and `abandoned` all run cleanup, with completed runs preserving earned XP while clearing active run locks and run-scoped transient state.
---
title: Implement rest workflow endpoints with transactional snapshot and squad sync
status: unstarted
priority: high
execution: deferred
ready: no
owner: unassigned
milestone: Milestone 15 - Backend Gameplay Completion
created: 2026-03-04
updated: 2026-03-04
description: |
  Implement `rest/open`, `rest/state`, and `rest/finalize` APIs so rest nodes support non-consuming edit sessions and atomic dual-write updates to run snapshot + saved squad state.
---
title: Implement backend auto-level application at rest finalize and run cleanup
status: unstarted
priority: high
execution: deferred
ready: no
owner: unassigned
milestone: Milestone 15 - Backend Gameplay Completion
created: 2026-03-04
updated: 2026-03-04
description: |
  Implement backend-authoritative level-up math execution as an automatic pass on rest finalization and run cleanup, rather than per-claim leveling.
---
title: Implement manual promotion endpoint with primary-secondary consume semantics
status: unstarted
priority: medium
execution: deferred
ready: no
owner: unassigned
milestone: Milestone 15 - Backend Gameplay Completion
created: 2026-03-04
updated: 2026-03-04
description: |
  Add a promotion API that takes one primary unit id and two distinct secondary unit ids; primary persists/upgrades while secondaries are consumed. Promotion is allowed between runs or during open rest workflow, and must reject ineligible active-run snapshot participants.
---
title: Expose dice equip and unequip gameplay endpoints with rest-only run constraints
status: unstarted
priority: medium
execution: deferred
ready: no
owner: unassigned
milestone: Milestone 15 - Backend Gameplay Completion
created: 2026-03-04
updated: 2026-03-04
description: |
  Expose backend equip/unequip endpoints that enforce per-unit slot caps and block active-run modifications outside rest workflow.
---
title: Implement Rest Management scene with open-edit-finalize workflow
status: unstarted
priority: high
execution: deferred
ready: no
owner: unassigned
milestone: Milestone 16 - Frontend Gameplay Completion
created: 2026-03-04
updated: 2026-03-04
description: |
  Add a dedicated rest management scene that supports rest open/edit/finalize flow, allows squad edits and dice changes, and returns a per-unit summary (healing and progression deltas) before returning to run map.
---
title: Implement embedded promotion flow in Unit Details for between-run and rest contexts
status: unstarted
priority: high
execution: deferred
ready: no
owner: unassigned
milestone: Milestone 16 - Frontend Gameplay Completion
created: 2026-03-04
updated: 2026-03-04
description: |
  Implement promotion controls directly in Unit Details with correct context gating: enabled between runs and during open rest workflow, disabled in active-run non-rest contexts.
---
title: Implement end-of-run summary scene shell for completed failed and abandoned outcomes
status: unstarted
priority: high
execution: deferred
ready: no
owner: unassigned
milestone: Milestone 16 - Frontend Gameplay Completion
created: 2026-03-04
updated: 2026-03-04
description: |
  Add a shared RunEnd summary shell that renders outcome-specific messaging while always showing rewards, XP/level progression, and surviving/defeated unit breakdown.
---
title: Implement distinct exit-node visuals and locked-path affordance on run map
status: unstarted
priority: medium
execution: deferred
ready: no
owner: unassigned
milestone: Milestone 16 - Frontend Gameplay Completion
created: 2026-03-04
updated: 2026-03-04
description: |
  Add a visually distinct exit-node treatment (portal/door style) and clear path-lock signaling so the node is visible throughout a run but manually unreachable until boss path unlock.
---
title: Wire Dice Inventory screen into active-rest management flow
status: unstarted
priority: medium
execution: deferred
ready: no
owner: unassigned
milestone: Milestone 16 - Frontend Gameplay Completion
created: 2026-03-04
updated: 2026-03-04
description: |
  Keep Dice Inventory as a dedicated screen and integrate it with rest-management context so allowed dice actions are available during rest and blocked in active-run non-rest contexts.
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
