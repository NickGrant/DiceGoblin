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
- Treat `ISSUES_BACKLOG.md` and `MILESTONES_BACKLOG.md` as planning context loaded on demand.
- Use `documentation/BACKLOG_OPERATIONS.md` as canonical policy for milestone sequencing, issue dependencies, and triage cadence.
- Only read `ISSUES_ARCHIVE.md` when historical context is explicitly needed.
- Only read `MILESTONES_ARCHIVE.md` when historical milestone context is explicitly needed.
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
- If present and needed for planning-only work, load:
  - `ISSUES_BACKLOG.md`
  - `MILESTONES_BACKLOG.md`
- Validate `ISSUES.md` contains only active statuses (`unstarted`, `in-progress`, `reopened`, `blocked`).
- Validate active issue entries include `priority` with one of: `low`, `medium`, `high`.
- Validate active issue entries include `execution` (`active` | `deferred`) and `ready` (`yes` | `no`).
- Validate `MILESTONES.md` contains only active milestone statuses (`not-started`, `in-progress`, `complete`, `blocked`).
- Validate milestone entries use `execution_window` (`open` | `closed`) and `is_current` (`yes` | `no`).
- If startup docs are missing/stale, continue with best effort and log the gap in the next user update.
- Prefer using automation when available:
  - `npm.cmd run startup:check`
  - `npm.cmd run backlog:validate`
  - `npm.cmd run llm:check`

## Instruction Precedence
- Follow platform/system/developer safety instructions first.
- Then follow this `AGENTS.md`.
- Then follow `ROLES.md` and `ISSUES.md`.
- Then follow `MILESTONES.md`.
- Then follow user task details.

## Roles Workflow (`ROLES.md`)
- If the user says `assume role <name>` (or equivalent phrasing), load role definition from `ROLES.md` and apply it.
- Proactively adopt the best-fit role when evaluating, planning, or reviewing work in a role-specific domain, even if the user did not explicitly request a role.
- For mixed-domain tasks, run short role-segmented passes instead of forcing a single role across all concerns.
- Persist the active role until:
  - the user says `drop role`, or
  - the user requests a different role.
- If a role name is unknown, state that briefly and continue with default behavior.
- Role guidance must not override higher-priority safety instructions.
- When a previously active role/persona is no longer in effect, proactively tell the user the role context has been dropped and default behavior is active.
- In role-driven evaluations, explicitly label which role lens is being used in progress updates and findings.

## Role Command Patterns
- If the user asks to assume `Senior Developer`: run a code quality pass focused on bugs, maintainability risks, architecture hygiene, and DRY/KISS opportunities; then either implement requested cleanup or open issues with concrete file-level findings.
- If the user asks to assume `Technical Product Manager`: audit `ISSUES.md`, roadmap docs, and supporting documentation for clarity, prioritization, and gaps; then propose or apply documentation/issue updates.
- If the user asks to assume `QA Lead`: prioritize reproducible test plans, regression checks, and acceptance criteria validation; log failures as actionable issues with repro steps.
- If the user asks to assume `Asset Librarian`: run an asset hygiene pass focused on naming consistency, folder organization, duplicate detection, missing required assets, and unreferenced assets; then either apply safe non-destructive cleanup updates or open issues with concrete file-level findings.
- If the user asks to assume a role not defined in `ROLES.md`: briefly state the mismatch and fall back to default behavior unless the user clarifies.

## Role Clarification Logging
- During role-based evaluation or decision making, append to `ROLE_CLARIFICATION.md` when clearer role definition would improve decision quality.
- Use the required entry format:
  - `name: <role name>`
  - `decision: <brief summary of decision made>`
  - `definition: <aspect of the role to better define>`
- Treat `ROLE_CLARIFICATION.md` as a log file: do not load it into active context unless the user explicitly asks.
- After appending, if `ROLE_CLARIFICATION.md` exceeds 500 lines, notify the user immediately.

## Issues Workflow (`ISSUES.md`)
- Treat `ISSUES.md` as the source of truth for current active bug/feature execution tracking.
- Treat `ISSUES_BACKLOG.md` as deferred planning inventory; promote items into `ISSUES.md` before execution.
- If the user explicitly asks to add an issue, update `ISSUES.md` directly without additional confirmation prompts.
- When the user asks to work issues:
  - prioritize items with `status: reopened` first, then `status: in-progress`, then `status: unstarted`, then `status: blocked`,
  - within each status bucket, prioritize `priority: high` before `medium` before `low`.
- Active issue entries should include:
  - `title`
  - `status`
  - `priority` (`low` | `medium` | `high`)
  - `execution` (`active` | `deferred`)
  - `ready` (`yes` | `no`)
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
- Default auto-execution gate:
  - only auto-work issues where `execution: active` and `ready: yes`,
  - and either issue `milestone` is empty/unassigned, or linked milestone is current and open.
- If issue is linked to a milestone that is not current/open, treat it as planning-only unless user explicitly asks to work it now.
- Triage cadence:
  - run a backlog triage pass at least weekly,
  - run triage when opening/closing a milestone,
  - run triage after major roadmap/doc-contract updates that can change issue sequencing.
- Status transition policy:
  - `unstarted -> in-progress` when active implementation begins,
  - `in-progress -> blocked` when blocked by unresolved dependency,
  - `blocked -> in-progress` when blocker clears,
  - archive as complete after verification and resolution logging,
  - use `reopened` for previously complete issues that regress.

## Milestones Workflow (`MILESTONES.md`)
- Treat `MILESTONES.md` as active milestone grouping metadata for issues.
- Treat `MILESTONES_BACKLOG.md` as deferred milestone inventory; move milestones into `MILESTONES.md` when opened for execution.
- If the user asks to add or update a milestone, update `MILESTONES.md` directly.
- Milestones reference issue titles from `ISSUES.md`; issues can exist without milestones.
- If a milestone has no issues, it must remain `status: not-started`.
- Milestones should include:
  - `execution_window` (`open` | `closed`)
  - `is_current` (`yes` | `no`)
- Only one milestone should have `is_current: yes` at a time.
- Milestone execution gate:
  - issues mapped to milestones with `execution_window: closed` are not auto-executed.
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
  - When a scene-specific visual review or reproducible UI artifact would help, use the local scene screenshot workflow (`skills/scene-screenshot/SKILL.md`) and attach/call out the captured scene state.
- Backend/PHP API changes: run targeted endpoint validation and check JSON response contract behavior.
- Data/schema changes: validate migration order and verify required seed/artifact files exist.

## Verification Requirements
- After code changes, run relevant tests/builds when available.
- Report pass/fail status clearly.
- Minimum pre-commit verification for mixed frontend/backend work:
  - `npm.cmd run llm:check`
  - `composer --working-dir=backend test` (or backend equivalent)
  - `npm --prefix frontend run test`
  - `npm --prefix frontend run build`
- If any build/test command fails at any point, immediately notify the user in the next update with:
  - failing command,
  - error summary (top actionable failures),
  - whether the failure appears pre-existing or introduced by current changes.
- Never defer disclosure of known build/test failures until final summary.
- If a requested verification cannot be run, state why.

## Doc Hygiene Rule
- Keep active docs concise and current.
- Move historical or superseded detail to archive docs.
- When issue or milestone status changes, update only the minimum relevant active docs plus archive movement.
- Use `LLM_CONTEXT.md` include/exclude guidance when choosing what to load into context.

## Context Budget Guardrails
- Keep `AGENTS.md` under ~220 lines.
- Keep `ROLES.md` under ~180 lines.
- Keep `ISSUES.md` under ~250 lines (active items only).
- Keep `MILESTONES.md` under ~120 lines (active items only).
- Prefer archive movement over growing active docs.
- If a guardrail is exceeded, add/execute a trimming pass before additional feature work.

## Feature Intake Workflow
- If the user asks to add a new feature (or equivalent), first capture requested behavior, constraints, and success criteria.
- Evaluate gaps (rules, state flow, UX, data, error paths, and testability) and present concise clarification questions.
- Complete the clarification loop until requirements are implementation-ready, or document that details are unknown.
- When requirements are sufficiently defined, update relevant files in `documentation/` to reflect scope, behavior, and delivery impact.

## Current Status Evaluation Workflow
- If the user asks for `current status evaluation` (or equivalent phrasing), execute `documentation/CURRENT_STATUS_EVALUATION.md`.
- Treat that workflow as a two-cycle, all-roles backlog quality pass:
  - cycle 1: top concerns + issue/milestone creation,
  - cycle 2: cross-role reconciliation and issue/milestone revisions.

## Editing Rules
- Keep changes scoped to the requested task.
- Avoid unrelated refactors unless required to safely complete the task.
- Keep documentation and tests aligned with behavior changes.
- Treat generated artifacts (`frontend/dist`) as policy-controlled output:
  - if user requests "commit everything", include generated artifacts;
  - otherwise prefer source-only commits and call out generated changes separately.

## Local Skills
- Repository-local reusable skills live under `skills/`.
- Current local skills:
  - `skills/backlog-ops/SKILL.md`
  - `skills/startup-verification/SKILL.md`
  - `skills/scene-screenshot/SKILL.md`
  - `skills/ux-scene-review/SKILL.md`
