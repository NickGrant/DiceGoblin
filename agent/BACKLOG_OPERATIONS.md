# Backlog Operations
----

Status: active
Last Updated: 2026-09-10
Owner: Product + Engineering
Depends On: `agent/ISSUES.md`, `agent/ISSUES_BACKLOG.md`, `agent/MILESTONES.md`, `agent/MILESTONES_BACKLOG.md`, `documentation/07-development-path/vnext-game-overhaul.md`

## Authority
- The 0-14 milestone roadmap lives in `documentation/07-development-path/vnext-game-overhaul.md`.
- `agent/MILESTONES.md` contains the active/next milestone execution context.
- `agent/ISSUES.md` contains execution-ready work for that milestone.
- Backlog files may hold explicitly deferred vNext work but do not redefine the roadmap.
- Git history is the only historical issue/milestone archive.

## Workflow
1. Decompose the next roadmap milestone into dependency-ordered execution-sized issues.
2. Keep only active/current-milestone issues in `agent/ISSUES.md`.
3. Mark an issue `In Progress` when implementation begins.
4. Implement that issue within accepted vNext architecture.
5. Run applicable `agent/QUALITY_GATES.md` checks.
6. Remove completed issue entries from active context after their milestone/status is reflected in the roadmap or normal Git history.
7. Mark a milestone complete only when its documented exit criterion is satisfied through the real vertical path.
8. Promote/decompose the next roadmap milestone.

## Scope
Prefer walking slices and shallow dependency chains. Do not create a separate horizontal frontend/backend/database roadmap that competes with the accepted milestone sequence.

## History
Do not maintain `ISSUES_ARCHIVE.md` or `MILESTONES_ARCHIVE.md`. Git already preserves completed execution history and avoids accidental retrieval of obsolete requirements.
