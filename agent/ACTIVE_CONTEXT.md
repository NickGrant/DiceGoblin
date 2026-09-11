# Active Context Snapshot
----

Status: active
Last Updated: 2026-09-10
Owner: Product + Engineering
Depends On: `agent/ISSUES.md`, `agent/MILESTONES.md`, `documentation/07-development-path/vnext-game-overhaul.md`, `documentation/07-development-path/vnext-prototype-code-disposition.md`

## Current Focus
**Milestone 1 - Walking Skeleton is active.** The prototype source-disposition audit is complete.

The current execution target is **Establish the fresh vNext database baseline** in `agent/ISSUES.md`.

Before replacing schema/auth persistence, consult `documentation/07-development-path/vnext-prototype-code-disposition.md`. Preserve reusable authentication/session/security behavior deliberately while replacing prototype storage boundaries and migration history.

## Reuse Rule
Before replacing a substantive prototype subsystem, inspect its current source/tests and apply **Keep**, **Adapt**, **Rebuild**, or **Retire** guidance from the disposition map. Accepted vNext decisions determine architecture; prototype source/tests provide behavioral and algorithmic evidence.

Do not create a permanent legacy-code archive inside the branch. Git history is the archive. Keep still-needed prototype code in place until its replacing slice is proven, then remove obsolete code in scoped work.

## Scope Guard
Do not pull Warband, units/dice/squads, Farm/run creation, combat, rewards, economy, permanent progression, kin, encounter depth, Codex/objectives, or onboarding implementation into Milestone 1 unless a minimal interface/null contract is strictly required by the walking skeleton.

## Working Agreement
- Active execution: `agent/ISSUES.md`, `agent/MILESTONES.md`.
- Roadmap: `documentation/07-development-path/vnext-game-overhaul.md`.
- Migration/reuse map: `documentation/07-development-path/vnext-prototype-code-disposition.md`.
- Architecture: relevant accepted `documentation/07-development-path/vnext-*.md` decisions.
- Historical documentation: Git history only when explicitly needed.
- Current prototype source/tests: inspect deliberately when replacing a subsystem so useful implementation is not lost.
