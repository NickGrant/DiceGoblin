# LLM Context Manifest
----

## Purpose
- Define a portable, low-noise context loading pattern for this project.
- Minimize token waste while preserving high-value decision context.

## Always Include (First Pass)
- `AGENTS.md`
- `agent/ISSUES.md`
- `agent/MILESTONES.md` (if present)
- `agent/CONTEXT_ROUTER.md`

## Include Conditionally
- `agent/ROLES.md` only for role activation or role-driven work
- `agent/ROLE_CATALOG.md` only when detailed role authority or constraints are required
- `agent/ACTIVE_CONTEXT.md` only when active issues or milestones need a quick project-focus snapshot

## Include On Demand
- `agent/ISSUES_BACKLOG.md` and `agent/MILESTONES_BACKLOG.md` for deferred roadmap/planning context
- `agent/BACKLOG_OPERATIONS.md` for roadmap/triage/dependency policy decisions
- `README.md` for repository-level setup or command questions
- `documentation/README.md` only when a broader documentation index is needed
- `documentation/05-technical/` docs for API, architecture, data model, and schema decisions
- `documentation/02-systems/` docs for gameplay rules, current system behavior, and legacy MVP reference
- `documentation/04-ux/` docs for UX, visual behavior, and route analysis
- `documentation/01-lore/` docs for story, setting, and biome progression direction
- `documentation/06-testing-release/` docs for release validation, UAT, and QA checklists
- `agent/ISSUES_ARCHIVE.md` only for historical context and reopened items
- `agent/MILESTONES_ARCHIVE.md` only for historical context

## Prefer Excluding From LLM Context
- `frontend/dist/`
- `frontend/node_modules/`
- `raw-assets/`
- `documentation/archive/`
- `agent/ROLE_CLARIFICATION.md` (log file; load only when explicitly requested)
- binary assets (`*.jpg`, `*.png`, audio/video files)
- generated bundles, maps, and lock output not relevant to the task
- historical/archive docs unless explicitly needed

## Context Budget Guardrails
- Keep `AGENTS.md` under ~220 lines.
- Keep `agent/ROLES.md` as a short activation/index file.
- Keep `agent/ROLE_CATALOG.md` out of default context.
- Keep `agent/ISSUES.md` under ~250 lines (active items only).
- Keep `agent/MILESTONES.md` under ~120 lines (active items only).
- Keep `agent/ISSUES_BACKLOG.md` and `agent/MILESTONES_BACKLOG.md` out of default context unless planning requires them.
- Move resolved/historical content to archives immediately.

## Portability Rules
- Keep this file repo-agnostic where possible.
- Prefer references to role/workflow patterns over app-specific implementation details.
- Reuse this file as a template when bootstrapping other projects.
