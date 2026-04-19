# Active Context Snapshot
----

Status: active  
Last Updated: 2026-04-18  
Owner: Product + Engineering  
Depends On: `agent/ISSUES.md`, `agent/MILESTONES.md`, `documentation/README.md`

## Purpose
- Fast startup snapshot for current delivery focus.

## Current Focus
- Execute from `agent/ISSUES.md` and `agent/MILESTONES.md` only.
- Keep API contracts and UX docs aligned with implementation.
- Prioritize the ability-loadout, cumulative scheduler, promotion, and naming rework.

## Key Risks
- API/doc drift while old and new combat models coexist during rollout.
- Migration complexity around unit dice, loadouts, and active runs.
- Sparse regression coverage around new persistence and scheduler behavior.

## Working Agreement
- Active execution: `agent/ISSUES.md`, `agent/MILESTONES.md`.
- Deferred planning: backlog files.
- Historical context: archive files on demand.
