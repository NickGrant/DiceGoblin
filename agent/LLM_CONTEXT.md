# LLM Context Manifest
----

## Purpose
- Define a portable, low-noise context loading pattern for this project.
- Minimize token waste while preserving high-value decision context.

## Always Include (First Pass)
- `AGENTS.md`
- `agent/ROLES.md` (if present)
- `agent/ISSUES.md`
- `agent/MILESTONES.md` (if present)
- `README.md`
- `documentation/README.md`
- `agent/ACTIVE_CONTEXT.md` (if present)

## Include On Demand
- `agent/ISSUES_BACKLOG.md` and `agent/MILESTONES_BACKLOG.md` for deferred roadmap/planning context
- `agent/BACKLOG_OPERATIONS.md` for roadmap/triage/dependency policy decisions
- `documentation/01-architecture/` docs for API and system-contract decisions
- `documentation/02-systems-mvp/` docs for gameplay rules and scope
- `documentation/03-ux/` docs for UX and visual behavior
- `documentation/08-json-schema/` only when editing schema contracts
- `agent/ISSUES_ARCHIVE.md` only for historical context and reopened items
- `agent/MILESTONES_ARCHIVE.md` only for historical context

## Prefer Excluding From LLM Context
- `frontend/dist/`
- `frontend/node_modules/`
- `raw-assets/`
- `agent/ROLE_CLARIFICATION.md` (log file; load only when explicitly requested)
- binary assets (`*.jpg`, `*.png`, audio/video files)
- generated bundles, maps, and lock output not relevant to the task
- historical/archive docs unless explicitly needed

## Context Budget Guardrails
- Keep `AGENTS.md` under ~220 lines.
- Keep `agent/ROLES.md` under ~180 lines.
- Keep `agent/ISSUES.md` under ~250 lines (active items only).
- Keep `agent/MILESTONES.md` under ~120 lines (active items only).
- Keep `agent/ISSUES_BACKLOG.md` and `agent/MILESTONES_BACKLOG.md` out of default context unless planning requires them.
- Move resolved/historical content to archives immediately.

## Portability Rules
- Keep this file repo-agnostic where possible.
- Prefer references to role/workflow patterns over app-specific implementation details.
- Reuse this file as a template when bootstrapping other projects.
