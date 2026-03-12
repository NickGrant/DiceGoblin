# Documentation Index
----

Status: active  
Last Updated: 2026-03-09  
Owner: Product + Engineering  
Depends On: `README.md`, `ISSUES.md`, `AGENTS.md`

## Purpose
- Single entrypoint for project docs.
- Fast mapping from task type to canonical documents.

## Canonical Execution Sources
- `AGENTS.md`
- `ISSUES.md`
- `MILESTONES.md`
- `documentation/01-architecture/03-backend-api-contracts.md`
- `documentation/01-architecture/04-data-model.md`

## Suggested Read Order
1. `documentation/00-overview/00-project-overview.md`
2. `documentation/01-architecture/` (all)
3. `documentation/02-systems-mvp/` (all)
4. `documentation/03-ux/` (all)
5. `documentation/05-playability-stability/` (release validation)
6. `documentation/07-ux-rebuild/` (current visual migration lane)

## Task Entry Points
- Backend/API changes:
  - `documentation/01-architecture/03-backend-api-contracts.md`
  - `documentation/01-architecture/01-authentication-and-sessions.md`
  - `documentation/01-architecture/04-data-model.md`
- Frontend scene/state changes:
  - `documentation/01-architecture/02-frontend-state-and-scene-contracts.md`
  - `documentation/03-ux/`
  - `documentation/07-ux-rebuild/`
- Combat/reward/progression changes:
  - `documentation/02-systems-mvp/00-combat-system.md`
  - `documentation/02-systems-mvp/07-combat-math-and-modifiers.md`
  - `documentation/02-systems-mvp/08-encounter-reward-surface-rules.md`
- Planning/triage:
  - `ISSUES.md`, `MILESTONES.md`
  - `ISSUES_BACKLOG.md`, `MILESTONES_BACKLOG.md`
  - `documentation/BACKLOG_OPERATIONS.md`
  - `documentation/ACTIVE_CONTEXT.md`
- Verification/release:
  - `documentation/TESTING_STRATEGY.md`
  - `documentation/05-playability-stability/00-release-gate-criteria.md`
  - `documentation/05-playability-stability/01-critical-path-playtest-script.md`
  - `documentation/CHANGELOG.md`

## Reference Data
- `documentation/08-json-schema/unit_types.base_stats_json.json`
- `documentation/08-json-schema/unit_types.ability_set_json.json`
- `documentation/unit_list.json`
- `documentation/enemy_list.json`

## LLM Ops
- `npm run startup:check`
- `npm run backlog:validate`
- `npm run llm:check`

## Local Automation
- Scene screenshot capture:
  - `skills/scene-screenshot/SKILL.md`
  - `npm run capture:scene -- --scene <scene>`
- UX scene review loops:
  - `skills/ux-scene-review/SKILL.md`
