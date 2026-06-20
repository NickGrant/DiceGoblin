# AGENTS FILE
----

## Purpose
This file defines the always-loaded project contract for coding agents in this repository.

## Startup Behavior
- On each new user turn, check for and read these files if they exist:
  - `agent/LLM_CONTEXT.md`
  - `agent/ISSUES.md`
  - `agent/MILESTONES.md`
- Use `agent/CONTEXT_ROUTER.md` as the default retrieval map for any additional project or documentation context.
- Read `agent/ROLES.md` only when:
  - the user asks to assume a role
  - the task is explicitly role-based
  - role guidance is needed to resolve an execution decision
- Treat `agent/ISSUES.md` and `agent/MILESTONES.md` as the active execution source of truth.
- Treat `agent/ISSUES_BACKLOG.md` and `agent/MILESTONES_BACKLOG.md` as planning-only context loaded on demand.
- Treat `agent/ISSUES_ARCHIVE.md` and `agent/MILESTONES_ARCHIVE.md` as historical context loaded on demand.
- Prefer running:
  - `npm.cmd run startup:check`
  - `npm.cmd run backlog:validate`
  - `npm.cmd run llm:check`

## Canonical References
- Workspace index: `agent/README.md`
- Context-loading policy: `agent/LLM_CONTEXT.md`
- Context retrieval map: `agent/CONTEXT_ROUTER.md`
- Role activation and summaries: `agent/ROLES.md`
- Full role definitions and clarification logging: `agent/ROLE_CATALOG.md`
- Backlog sequencing, issue/milestone workflow, and batching: `agent/BACKLOG_OPERATIONS.md`
- Verification, doc hygiene, feature intake, and spec activation: `agent/QUALITY_GATES.md`
- Current status evaluation workflow: `agent/CURRENT_STATUS_EVALUATION.md`

## Instruction Precedence
- Follow platform/system/developer safety instructions first.
- Then follow this `AGENTS.md`.
- Then follow `agent/ROLES.md`, `agent/ISSUES.md`, and `agent/MILESTONES.md`.
- Then follow other referenced agent docs.
- Then follow user task details.

## Execution Defaults
- Work from `agent/ISSUES.md` and `agent/MILESTONES.md` unless the user explicitly asks for planning/backlog work.
- Keep changes scoped to the requested task.
- Avoid unrelated refactors unless required to safely complete the task.
- Keep documentation and tests aligned with behavior changes.
- In PowerShell, do not chain commands with `&&`; run sequential commands separately.
- Treat generated artifacts like `frontend/dist` as policy-controlled output:
  - include them only when the user explicitly asks to commit everything
  - otherwise prefer source-only commits and call out generated changes separately

## Special Triggers
- If the user asks to assume a role, follow `agent/ROLES.md`.
- If detailed role definitions are needed after role activation, load `agent/ROLE_CATALOG.md`.
- If the user asks for `current status evaluation`, execute `agent/CURRENT_STATUS_EVALUATION.md`.
