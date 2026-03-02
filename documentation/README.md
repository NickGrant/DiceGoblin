# Documentation Index
----

Status: active  
Last Updated: 2026-03-02  
Owner: Product + Engineering  
Depends On: `README.md`, `ISSUES.md`, `AGENTS.md`

## Purpose
- Provide a single entrypoint for project documentation.
- Clarify reading order and which docs are authoritative for implementation.
- Reduce context bloat by making document intent explicit.

## Authoritative Execution Sources
- `ISSUES.md` (active implementation backlog and status)
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

## Reference Data Docs
- `documentation/JSON Schema/unit_types.base_stats_json.json`
- `documentation/JSON Schema/unit_types.ability_set_json.json`
- `documentation/unit_list.json`

## Deprecations
- `documentation/worklist.md` is deprecated as of 2026-03-02.
- Active planning and execution tracking now lives in `ISSUES.md`.
- Historical roadmap snapshots remain in git history until archive lanes are introduced.

