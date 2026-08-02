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
  - `documentation/00-overview/02-glossary.md`
- Lore, story, and biome progression:
  - `documentation/01-lore/README.md`
  - `documentation/01-lore/00-world-and-lore.md`
  - `documentation/01-lore/01-story-and-biome-progression.md`
- Frontend architecture, routes, state, or API contracts:
  - `documentation/05-technical/02-frontend-state-and-scene-contracts.md`
  - `documentation/05-technical/03-backend-api-contracts.md`
  - `documentation/05-technical/04-data-model.md`
- Seed catalog ownership and DB-vs-code decisions:
  - `documentation/05-technical/09-seed-catalog-ownership.md`
- Gameplay systems, combat, units, progression, loot, or runs:
  - `documentation/02-systems/README.md`
  - `documentation/02-systems/`
  - `documentation/02-systems/mvp-reference/`
- Content catalogs, units, kin, enemies, biomes, items, affixes, encounters, or codex entries:
  - `documentation/03-content/README.md`
- UX, navigation, layouts, and player-facing behavior:
  - `documentation/04-ux/README.md`
  - `documentation/04-ux/`
  - `documentation/04-ux/page-analysis/`
- Release validation, testing, and quality gates:
  - `documentation/06-testing-release/00-testing-strategy.md`
  - `documentation/06-testing-release/`
  - `agent/QUALITY_GATES.md`
- Development path, roadmap, demo planning, or changelog:
  - `documentation/07-development-path/README.md`
  - `documentation/07-development-path/`
- Documentation maintenance, engineering standards, or LLM context architecture:
  - `documentation/08-operations/README.md`
  - `documentation/08-operations/`
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
