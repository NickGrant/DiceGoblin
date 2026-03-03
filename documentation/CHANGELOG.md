# Documentation Changelog

Status: active  
Last Updated: 2026-03-02  
Owner: Product + Engineering  
Depends On: `documentation/README.md`, `ISSUES.md`

----

## 2026-03-02
- Added `documentation/README.md` as docs index and task-based entrypoint map.
- Added metadata headers to key docs for status/owner/dependency visibility.
- Deprecated `documentation/worklist.md` as execution tracker in favor of `ISSUES.md`.
- Added `documentation/ACTIVE_CONTEXT.md` for fast session bootstrap context.
- Added `documentation/STYLE_GUIDE.md` and performed initial encoding/style cleanup.
- Added `documentation/archive/README.md` and updated context exclusions for archive paths.
- Added `documentation/03-ux/02-warband-management.md` for detailed warband interaction contract.
- Updated backend API contract doc to distinguish implemented vs planned endpoints and align current route names.
- Introduced `MILESTONES.md` and `MILESTONES_ARCHIVE.md` for milestone-to-issue tracking.
- Rewrote `documentation/01-architecture/02-frontend-state-and-scene-contracts.md` to match implemented Phaser scene configuration and transition flow.
- Added `documentation/QA_CHECKLIST.md` for repeatable docs drift verification.
- Normalized legacy overview docs (`01-core-gameplay-loop.md`, `02-glossary.md`) to style-guide compliant text.
- Reconciled progression wording across combat/encounter/loot/run-resolution docs.
- Added `documentation/TESTING_STRATEGY.md` to define repository-wide test tiers, verification matrix, and release gates.
- Updated `documentation/README.md` to include testing-strategy references in documentation quality workflows.
- Added `documentation/ROADMAP_EXECUTION_POLICY.md` to define milestone ordering and current/open milestone selection rules.
- Added `documentation/BACKLOG_DEPENDENCIES.md` for cross-milestone dependency mapping and Milestones 4-6 sequencing policy.
- Added `documentation/BACKLOG_TRIAGE_POLICY.md` to define triage cadence and status-transition policy.
- Added optional issue dependency fields (`blocked_by`, `enables`) to the `ISSUES.md` template.
- Added `scripts/validate-backlog.mjs` for schema and link validation across `ISSUES.md` and `MILESTONES.md`.
- Reconciled MVP XP wording to Combat/Boss-only in encounter and loot scope docs.
- Added squads-vs-teams compatibility notes in frontend/backend architecture contract docs.
- Converted milestone template example to fenced YAML to avoid active-entry parser ambiguity.
- Removed deprecated `documentation/worklist.md` from active documentation.
- Added metadata headers to previously missing high-impact docs across overview, architecture, systems, UX, and multiplayer sections.
- Added root workflow command `npm run backlog:validate` via `package.json`.
- Added CI workflow `.github/workflows/backlog-validation.yml` to validate backlog schema/links on relevant changes.
- Split deferred planning inventory into `ISSUES_BACKLOG.md` and `MILESTONES_BACKLOG.md` to keep active tracking docs within context guardrails.
- Consolidated roadmap/dependency/triage governance into `documentation/BACKLOG_OPERATIONS.md` and marked legacy policy docs as compatibility pointers.
- Removed superseded policy files (`ROADMAP_EXECUTION_POLICY.md`, `BACKLOG_DEPENDENCIES.md`, `BACKLOG_TRIAGE_POLICY.md`) after consolidation into `BACKLOG_OPERATIONS.md`.
- Added `scripts/startup-check.mjs` and npm scripts (`startup:check`, `llm:check`) for repeatable LLM startup verification.
- Added local reusable skills docs under `skills/` and linked command/skill references from root and documentation indexes.
