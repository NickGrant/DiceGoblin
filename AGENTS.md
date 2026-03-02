# AGENTS FILE
----

## Purpose
This file defines project-specific operating instructions for coding agents working in this repository.

## Startup Behavior
- On each new user turn, check for and read these files if they exist:
  - `LLM_CONTEXT.md`
  - `ROLES.md`
  - `ISSUES.md`
  - `MILESTONES.md`
- Treat these files as active project context for planning and execution.
- Only read `ISSUES_ARCHIVE.md` when historical context is explicitly needed.
- If optional files are missing, continue normally and note the gap only when relevant.

## Startup Verification Checklist
- Confirm active-control docs exist and are readable:
  - `AGENTS.md`
  - `ISSUES.md`
  - `MILESTONES.md`
  - `README.md`
- If present, load:
  - `LLM_CONTEXT.md`
  - `ROLES.md`
- Validate `ISSUES.md` contains only active statuses (`unstarted`, `in-progress`, `reopened`, `blocked`).
- Validate active issue entries include `priority` with one of: `low`, `medium`, `high`.
- Validate `MILESTONES.md` contains only active milestone statuses (`not-started`, `in-progress`, `complete`, `blocked`).
- If startup docs are missing/stale, continue with best effort and log the gap in the next user update.

## Instruction Precedence
- Follow platform/system/developer safety instructions first.
- Then follow this `AGENTS.md`.
- Then follow `ROLES.md` and `ISSUES.md`.
- Then follow `MILESTONES.md`.
- Then follow user task details.

## Roles Workflow (`ROLES.md`)
- If the user says `assume role <name>` (or equivalent phrasing), load role definition from `ROLES.md` and apply it.
- Persist the active role until:
  - the user says `drop role`, or
  - the user requests a different role.
- If a role name is unknown, state that briefly and continue with default behavior.
- Role guidance must not override higher-priority safety instructions.
- When a previously active role/persona is no longer in effect, proactively tell the user the role context has been dropped and default behavior is active.

## Role Command Patterns
- If the user asks to assume `Senior Developer`: run a code quality pass focused on bugs, maintainability risks, architecture hygiene, and DRY/KISS opportunities; then either implement requested cleanup or open issues with concrete file-level findings.
- If the user asks to assume `Technical Product Manager`: audit `ISSUES.md`, roadmap docs, and supporting documentation for clarity, prioritization, and gaps; then propose or apply documentation/issue updates.
- If the user asks to assume `QA Lead`: prioritize reproducible test plans, regression checks, and acceptance criteria validation; log failures as actionable issues with repro steps.
- If the user asks to assume a role not defined in `ROLES.md`: briefly state the mismatch and fall back to default behavior unless the user clarifies.

## Issues Workflow (`ISSUES.md`)
- Treat `ISSUES.md` as the source of truth for current active bug/feature status tracking.
- If the user explicitly asks to add an issue, update `ISSUES.md` directly without additional confirmation prompts.
- When the user asks to work issues:
  - prioritize items with `status: reopened` first, then `status: in-progress`, then `status: unstarted`, then `status: blocked`,
  - within each status bucket, prioritize `priority: high` before `medium` before `low`.
- Active issue entries should include:
  - `title`
  - `status`
  - `priority` (`low` | `medium` | `high`)
  - `description`
- When adding issues, set `priority` explicitly; if priority is unknown, default to `medium`.
- When beginning implementation of an issue, set `status: in-progress` first.
- After resolving an issue:
  - set `status: complete`,
  - append a `resolution:` line (1-2 sentences) at the bottom of that issue entry.
- Move completed issue entries into `ISSUES_ARCHIVE.md` and remove them from `ISSUES.md` to keep active context lean.
- If an issue is reopened:
  - restore it to `ISSUES.md`,
  - keep prior resolution history from archive,
  - append a new `resolution:` line after the reopen reason when fixed again.
- If the user only requests to `reopen` an issue:
  - move it back to `ISSUES.md` with `status: reopened`,
  - do not begin implementing that issue until the user explicitly asks to work issues or fix that item.
- If the user requests reopen and fix in the same message, reopen then implement immediately.

## Milestones Workflow (`MILESTONES.md`)
- Treat `MILESTONES.md` as active milestone grouping metadata for issues.
- If the user asks to add or update a milestone, update `MILESTONES.md` directly.
- Milestones reference issue titles from `ISSUES.md`; issues can exist without milestones.
- If a milestone has no issues, it must remain `status: not-started`.
- When a milestone is completed:
  - set `status: complete`,
  - append `Resolution:` (1-2 sentences),
  - move the completed milestone to `MILESTONES_ARCHIVE.md`.

## Work Loop
- For issue execution, follow this loop: select issue -> mark in-progress -> implement -> verify -> update issue status/resolution -> archive if complete.
- Keep updates short and concrete during multi-issue work.

## Batching Rule
- Default to a small batch of 3-5 issues per pass unless the user requests a different batch size.
- After each batch, report completed items, remaining items, and blockers before continuing.

## Verification Matrix
- Frontend/Phaser behavior changes: run relevant frontend tests/build and perform a brief manual UX sanity check.
- Backend/PHP API changes: run targeted endpoint validation and check JSON response contract behavior.
- Data/schema changes: validate migration order and verify required seed/artifact files exist.

## Verification Requirements
- After code changes, run relevant tests/builds when available.
- Report pass/fail status clearly.
- If a requested verification cannot be run, state why.

## Doc Hygiene Rule
- Keep active docs concise and current.
- Move historical or superseded detail to archive docs.
- When issue or milestone status changes, update only the minimum relevant active docs plus archive movement.
- Use `LLM_CONTEXT.md` include/exclude guidance when choosing what to load into context.

## Context Budget Guardrails
- Keep `AGENTS.md` under ~220 lines.
- Keep `ROLES.md` under ~180 lines.
- Keep `ISSUES.md` under ~150 lines (active items only).
- Keep `MILESTONES.md` under ~120 lines (active items only).
- Prefer archive movement over growing active docs.
- If a guardrail is exceeded, add/execute a trimming pass before additional feature work.

## Feature Intake Workflow
- If the user asks to add a new feature (or equivalent), first capture requested behavior, constraints, and success criteria.
- Evaluate gaps (rules, state flow, UX, data, error paths, and testability) and present concise clarification questions.
- Complete the clarification loop until requirements are implementation-ready, or document that details are unknown.
- When requirements are sufficiently defined, update relevant files in `documentation/` to reflect scope, behavior, and delivery impact.

## Editing Rules
- Keep changes scoped to the requested task.
- Avoid unrelated refactors unless required to safely complete the task.
- Keep documentation and tests aligned with behavior changes.
