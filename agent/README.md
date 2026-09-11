# Agent Workspace

`AGENTS.md` is the coding-agent contract.

## Normal Implementation
Load `agent/ISSUES.md` plus the touched source/tests. It contains one execution-ready package. Use `agent/CONTEXT_ROUTER.md` only to locate additional authority and `agent/QUALITY_GATES.md` at verification time.

## Sequencing
- `MILESTONES.md` — active milestone and short package queue; load for sequencing/package promotion.
- `BACKLOG_OPERATIONS.md` — just-in-time issue workflow; load when updating execution state.
- `ISSUES_BACKLOG.md` / `MILESTONES_BACKLOG.md` — explicitly deferred work only.
- Full roadmap — `documentation/07-development-path/vnext-game-overhaul.md`.

## Optional Context
- `LLM_CONTEXT.md` summarizes context-loading policy; it is not additional required implementation context.
- `ROLES.md` / `ROLE_CATALOG.md` are explicit-user-request review lenses only.
- `ROLE_CLARIFICATION.md` is historical/supporting role-policy context only.

Git history is the archive for completed/superseded execution state. Do not create parallel archive files.
