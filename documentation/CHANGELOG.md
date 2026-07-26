# Documentation Changelog

Status: active  
Last Updated: 2026-05-29  
Owner: Product + Engineering  
Depends On: `documentation/README.md`, `agent/ISSUES.md`

----

## 2026-07-25
- Added the player-facing Wrong Machine reconstruction UI and closed the Wrong Machine and Kin Foundation active agent milestone.
- Added `documentation/02-systems-mvp/13-wrong-machine-and-kin.md` as the active contract for approved goblin-kin terminology, lineage ownership, Pig Kin first reconstruction, and deferred legacy splice compatibility work.
- Updated `documentation/README.md` to include the Wrong Machine and kin contract in gameplay and warband task entry points.
- Added `documentation/02-systems-mvp/14-balancing-strategy-and-simulation.md` to define balance goals, simulation metrics, and future simulation-tool expectations.

## 2026-05-29
- Added `documentation/ENGINEERING_STANDARDS.md` as the canonical coding standards reference for test coverage, SCSS, HTML, TypeScript, and architecture expectations.
- Expanded `documentation/TESTING_STRATEGY.md` with explicit coverage expectations and when manual verification is acceptable.
- Updated `documentation/README.md` to include the engineering standards doc in the active documentation path.
- Consolidated the overview, frontend-contract, warband UX, run UX, and first-session docs so each topic has one canonical active document.
- Removed outdated planning and migration leftovers from the active documentation path, including the old master-context overview and split UX contract shards.
- Reframed the architecture docs around the current Angular frontend instead of legacy scene ownership language.
- Removed `documentation/unit_list.json` and `documentation/enemy_list.json` so seeded roster data lives only in backend migrations.

## 2026-04-18
- Split agent workflow and backlog-control docs out of `documentation/` into `agent/` so game documentation and agent instructions are no longer mixed together.
- Slimmed `README.md` to point readers to `documentation/README.md` for game docs and `agent/README.md` for agent workflow, removing duplicated command and skills sections.
- Refined `documentation/README.md` so it indexes only `documentation/` content and points agent-ops readers back to `AGENTS.md` and `agent/README.md`.

## 2026-04-01
- Added `documentation/05-playability-stability/02-first-release-manual-gate-evidence.md` to capture the Milestone 31 manual gate evidence for fresh-account bootstrap, successful run, failed run, resume continuity, and reset-account validation.
- Added `documentation/05-playability-stability/03-first-release-checklist.md` to turn the final release-prep requirements into an explicit closeout checklist, including the required debug-tooling release toggles.
- Closed the Milestone 31 run-flow readability issue after the map, node-resolution, and run-summary polish pass.
- Closed Milestone 31 and opened Milestone 32 as the current execution lane after moving the larger `RestManagementScene` redesign into deferred backlog follow-up.

## 2026-03-08
- Rewrote `documentation/03-ux/01-visual-design-guide.md` as the single canonical visual style source and removed conflicting parallel style direction.
- Updated `documentation/03-ux/08-page-layout-zones.md` to clarify that overlay color keys are layout and debug references, not canonical art palette tokens.
- Updated UX docs to replace top-corner home and energy controls with a global bottom split command strip contract.

## 2026-03-05
- Added `documentation/06-character-profiles/PROFILE_TEMPLATE.md` to standardize creative profile entries.
- Updated `documentation/README.md` to include character-profile and enemy-list documentation entry points.
- Updated `documentation/06-character-profiles/00-overview.md` for encoding cleanup, filename corrections, and explicit creative-reference-only scope.
- Added metadata headers to all character profile docs and template so `docs:lint` remains clean.

## 2026-03-02
- Added `documentation/README.md` as docs index and task-based entrypoint map.
- Added metadata headers to key docs for status, owner, and dependency visibility.
- Deprecated `documentation/worklist.md` as execution tracker in favor of `ISSUES.md`.
- Added `documentation/ACTIVE_CONTEXT.md` for fast session bootstrap context.
- Added `documentation/STYLE_GUIDE.md` and performed initial encoding and style cleanup.
- Added `documentation/03-ux/02-warband-management.md` for detailed warband interaction contract.
- Updated backend API contract doc to distinguish implemented vs planned endpoints and align current route names.
- Introduced `MILESTONES.md` and `MILESTONES_ARCHIVE.md` for milestone-to-issue tracking.
- Added `documentation/TESTING_STRATEGY.md` to define repository-wide test tiers, verification matrix, and release gates.
- Updated `documentation/README.md` to include testing-strategy references in documentation quality workflows.
- Reconciled MVP XP wording to Combat and Boss-only in encounter and loot scope docs.
- Added squads-vs-teams compatibility notes in frontend and backend architecture contract docs.
- Added metadata headers to previously missing high-impact docs across overview, architecture, systems, UX, and multiplayer sections.
