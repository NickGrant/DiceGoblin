# Documentation Index
----

Status: active  
Last Updated: 2026-03-05  
Owner: Product + Engineering  
Depends On: `README.md`, `ISSUES.md`, `AGENTS.md`

## Purpose
- Provide a single entrypoint for project documentation.
- Clarify reading order and which docs are authoritative for implementation.
- Reduce context bloat by making document intent explicit.

## Authoritative Execution Sources
- `ISSUES.md` (active implementation backlog and status)
- `MILESTONES.md` (active milestone groupings)
- `AGENTS.md` (agent workflow rules and execution policy)
- `documentation/01-architecture/03-backend-api-contracts.md` (API contract intent)
- `documentation/01-architecture/04-data-model.md` (schema and entity relationships)

## Recommended Reading Order
1. `documentation/00-overview/00-project-overview.md`
2. `documentation/00-overview/01-core-gameplay-loop.md`
3. `documentation/01-architecture/00-tech-stack.md`
4. `documentation/01-architecture/01-authentication-and-sessions.md`
5. `documentation/01-architecture/02-frontend-state-and-scene-contracts.md`
6. `documentation/01-architecture/03-backend-api-contracts.md`
7. `documentation/01-architecture/04-data-model.md`
8. `documentation/02-systems-mvp/` (combat, progression, encounters)
9. `documentation/03-ux/` (UX behavior and visual direction)
10. `documentation/04-multiplayer/` (future-facing systems, non-MVP)
11. `documentation/05-playability-stability/` (release-readiness and stability gates)
12. `documentation/06-character-profiles/00-overview.md` (creative reference only)
13. `documentation/03-ux/03-encounter-flow-transition-matrix.md`
14. `documentation/03-ux/04-combat-viewer-readability.md`
15. `documentation/02-systems-mvp/08-encounter-reward-surface-rules.md`
16. `documentation/03-ux/05-unit-dice-details-acceptance.md`
17. `documentation/03-ux/06-promotion-and-dice-management-sequencing.md`
18. `documentation/03-ux/07-dice-pool-consumption-and-refresh-cues.md`
19. `documentation/03-ux/08-page-layout-zones.md`

## Task-Based Entry Points
- If changing API routes, payloads, auth, or error contracts:
  - `documentation/01-architecture/03-backend-api-contracts.md`
  - `documentation/01-architecture/01-authentication-and-sessions.md`
  - `documentation/01-architecture/04-data-model.md`
- If changing schema, repositories, or migration behavior:
  - `documentation/01-architecture/04-data-model.md`
  - `backend/migrations/schema_all.sql`
- If changing combat resolution or ability behavior:
  - `documentation/02-systems-mvp/00-combat-system.md`
  - `documentation/02-systems-mvp/07-combat-math-and-modifiers.md`
- If changing run flow, encounters, or reward progression:
  - `documentation/02-systems-mvp/03-encounter-scope.md`
  - `documentation/02-systems-mvp/04-loot-and-drop-scope.md`
  - `documentation/02-systems-mvp/06-run-resolution-scope.md`
  - `documentation/02-systems-mvp/08-encounter-reward-surface-rules.md`
- If changing frontend scene state or UX behavior:
  - `documentation/01-architecture/02-frontend-state-and-scene-contracts.md`
  - `documentation/03-ux/00-ux-and-debug-scope.md`
  - `documentation/03-ux/03-encounter-flow-transition-matrix.md`
  - `documentation/03-ux/04-combat-viewer-readability.md`
  - `documentation/03-ux/05-unit-dice-details-acceptance.md`
  - `documentation/03-ux/06-promotion-and-dice-management-sequencing.md`
  - `documentation/03-ux/07-dice-pool-consumption-and-refresh-cues.md`
  - `documentation/03-ux/08-page-layout-zones.md`
  - `documentation/03-ux/02-warband-management.md`
  - `documentation/03-ux/01-visual-design-guide.md`
- If planning/triaging work:
  - `ISSUES.md` (active)
  - `MILESTONES.md` (active milestone grouping)
  - `ISSUES_BACKLOG.md` (deferred planning inventory)
  - `MILESTONES_BACKLOG.md` (deferred milestone inventory)
  - `ISSUES_ARCHIVE.md` (historical only, on-demand)
  - `documentation/ACTIVE_CONTEXT.md` (current high-signal snapshot)
  - `documentation/BACKLOG_OPERATIONS.md`
- If validating documentation quality and drift:
  - `documentation/QA_CHECKLIST.md`
  - `documentation/CURRENT_STATUS_EVALUATION.md`
  - `documentation/TESTING_STRATEGY.md`
  - `documentation/05-playability-stability/00-release-gate-criteria.md`
  - `documentation/05-playability-stability/01-critical-path-playtest-script.md`
  - `documentation/05-playability-stability/03-stale-state-recovery-checklist.md`
  - `documentation/STYLE_GUIDE.md`
  - `documentation/CHANGELOG.md`
  - `documentation/BACKLOG_OPERATIONS.md`
- If creating/reviewing visual character direction for art generation:
  - `documentation/06-character-profiles/00-overview.md`
  - `documentation/06-character-profiles/PROFILE_TEMPLATE.md`
  - `documentation/06-character-profiles/*.md`

## Reference Data Docs
- `documentation/JSON Schema/unit_types.base_stats_json.json`
- `documentation/JSON Schema/unit_types.ability_set_json.json`
- `documentation/unit_list.json`
- `documentation/enemy_list.json`
- `documentation/06-character-profiles/00-overview.md` (creative reference lane)

## Deprecations
- `documentation/worklist.md` was removed on 2026-03-02 after migration to issue/milestone tracking.
- Active planning and execution tracking now lives in `ISSUES.md` and `MILESTONES.md`.
- Historical roadmap snapshots remain in git history until archive lanes are introduced.

## LLM Ops References
- Startup and backlog checks:
  - `npm run startup:check`
  - `npm run backlog:validate`
  - `npm run llm:check`
  - `npm run docs:lint`
  - `npm run bundle:check`
  - `npm run verify:full`
- Local reusable skills:
  - `skills/backlog-ops/SKILL.md`
  - `skills/startup-verification/SKILL.md`

