# Context Router
----

## Purpose
- Keep default context small.
- Route targeted questions to the narrowest relevant source.

## Default Load
- `AGENTS.md`
- `agent/LLM_CONTEXT.md`
- `agent/ISSUES.md`
- `agent/MILESTONES.md`
- this file

## Retrieval Routes
- Game overview or glossary:
  - `documentation/00-overview/00-project-overview.md`
  - `documentation/00-overview/01-core-gameplay-loop.md`
  - `documentation/00-overview/03-world-and-lore.md`
  - `documentation/00-overview/02-glossary.md`
- Frontend architecture, routes, state, or API contracts:
  - `documentation/01-architecture/02-frontend-state-and-scene-contracts.md`
  - `documentation/01-architecture/03-backend-api-contracts.md`
  - `documentation/01-architecture/04-data-model.md`
- Gameplay systems, combat, units, progression, loot, or runs:
  - `documentation/02-systems-mvp/`
- UX, navigation, layouts, and player-facing behavior:
  - `documentation/03-ux/`
- Release validation, testing, and quality gates:
  - `documentation/TESTING_STRATEGY.md`
  - `documentation/05-playability-stability/`
  - `agent/QUALITY_GATES.md`
- Backlog sequencing or issue-selection policy:
  - `agent/BACKLOG_OPERATIONS.md`
- Role-driven work:
  - `agent/ROLES.md`
  - `agent/ROLE_CATALOG.md`
- Historical or planning-only context:
  - `agent/ISSUES_BACKLOG.md`
  - `agent/MILESTONES_BACKLOG.md`
  - `agent/ISSUES_ARCHIVE.md`
  - `agent/MILESTONES_ARCHIVE.md`

## Retrieval Rules
- Prefer the smallest doc set that answers the current question.
- Do not load `README.md` or `documentation/README.md` by default.
- Load archives only for reopened work, audits, or historical comparison.
