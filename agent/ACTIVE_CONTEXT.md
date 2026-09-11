# Active Context Snapshot
----

Status: active
Last Updated: 2026-09-10
Owner: Product + Engineering
Depends On: `agent/ISSUES.md`, `agent/MILESTONES.md`, `documentation/07-development-path/vnext-game-overhaul.md`

## Current Focus
**Milestone 1 - Walking Skeleton is active.** Its dependency-ordered implementation packages are defined in `agent/ISSUES.md`.

The current execution target is **Inventory and classify prototype code for vNext reuse**. Complete the reuse/disposition map before beginning the fresh vNext database baseline.

The purpose of this first package is to prevent both failure modes of a rewrite: carrying forward obsolete prototype architecture and throwing away proven algorithms/infrastructure that can be kept or adapted safely.

## Reuse Rule
Before replacing a substantive prototype subsystem, classify relevant implementation as **Keep**, **Adapt**, **Rebuild**, or **Retire**. Accepted vNext decisions determine architecture, but current source/tests should be inspected for useful behavior before replacement.

Do not create a permanent legacy-code archive inside the branch. Git history is the archive. Keep still-needed prototype code in place until the replacing slice is proven, then remove obsolete code in scoped work.

## Scope Guard
Do not pull Warband, units/dice/squads, Farm/run creation, combat, rewards, economy, permanent progression, kin, encounter depth, Codex/objectives, or onboarding implementation into Milestone 1 unless a minimal interface/null contract is strictly required by the walking skeleton.

## Working Agreement
- Active execution: `agent/ISSUES.md`, `agent/MILESTONES.md`.
- Roadmap: `documentation/07-development-path/vnext-game-overhaul.md`.
- Architecture: relevant accepted `documentation/07-development-path/vnext-*.md` decisions.
- Historical documentation: Git history only when explicitly needed.
- Current prototype source/tests: inspect deliberately when replacing a subsystem so useful implementation is not lost.
