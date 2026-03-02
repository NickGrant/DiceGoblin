# LLM Context Manifest
----

## Purpose
- Define a portable, low-noise context loading pattern for this project.
- Minimize token waste while preserving high-value decision context.

## Always Include (First Pass)
- `AGENTS.md`
- `ROLES.md` (if present)
- `ISSUES.md`
- `MILESTONES.md` (if present)
- `README.md`
- `documentation/README.md`
- `documentation/ACTIVE_CONTEXT.md` (if present)

## Include On Demand
- `documentation/01-architecture/` docs for API and system-contract decisions
- `documentation/02-systems-mvp/` docs for gameplay rules and scope
- `documentation/03-ux/` docs for UX and visual behavior
- `documentation/JSON Schema/` only when editing schema contracts
- `ISSUES_ARCHIVE.md` only for historical context and reopened items
- `MILESTONES_ARCHIVE.md` only for historical context

## Prefer Excluding From LLM Context
- `frontend/dist/`
- `frontend/node_modules/`
- `raw-assets/`
- `documentation/archive/`
- `documentation/worklist.md` (deprecated)
- binary assets (`*.jpg`, `*.png`, audio/video files)
- generated bundles, maps, and lock output not relevant to the task
- historical/archive docs unless explicitly needed

## Context Budget Guardrails
- Keep `AGENTS.md` under ~220 lines.
- Keep `ROLES.md` under ~180 lines.
- Keep `ISSUES.md` under ~150 lines (active items only).
- Keep `MILESTONES.md` under ~120 lines (active items only).
- Move resolved/historical content to archives immediately.

## Portability Rules
- Keep this file repo-agnostic where possible.
- Prefer references to role/workflow patterns over app-specific implementation details.
- Reuse this file as a template when bootstrapping other projects.
