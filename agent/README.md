# Agent Workspace
----

## Purpose
- Keep agent-operation files separate from game/project documentation.
- Centralize backlog control, role definitions, context manifests, and agent workflow references.

## Core Files
- `ISSUES.md`
- `ISSUES_BACKLOG.md`
- `ISSUES_ARCHIVE.md`
- `MILESTONES.md`
- `MILESTONES_BACKLOG.md`
- `MILESTONES_ARCHIVE.md`
- `CONTEXT_ROUTER.md`
- `ROLES.md`
- `ROLE_CATALOG.md`
- `LLM_CONTEXT.md`
- `ROLE_CLARIFICATION.md`

## Workflow Docs
- `BACKLOG_OPERATIONS.md`
- `QUALITY_GATES.md`
- `ACTIVE_CONTEXT.md`
- `CURRENT_STATUS_EVALUATION.md`
- `07-llm-ops/`

## Automation Helpers
- `npm.cmd run backlog -- ...` manages issue and milestone records without manual file editing.
- `npm.cmd run agent:docs -- role list|show ...` retrieves role details without loading the full role catalog into context.
- `npm.cmd run agent:docs -- role-clarification add|list ...` appends and inspects clarification log entries without loading the entire log file.

## Root Exceptions
- `AGENTS.md` stays at repo root as the primary entrypoint for coding agents.
- `README.md` stays at repo root as the primary repository overview.
