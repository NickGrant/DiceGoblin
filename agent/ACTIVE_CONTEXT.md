# Active Context Snapshot
----

Status: active
Last Updated: 2026-07-24
Owner: Product + Engineering
Depends On: `agent/ISSUES.md`, `agent/MILESTONES.md`, `documentation/README.md`

## Purpose
- Fast startup snapshot for current delivery focus.

## Current Focus
- Execute from `agent/ISSUES.md` and `agent/MILESTONES.md` only.
- Current active lane: Expanded Combat Stats.
- Prioritize turning Precision and Resolve from schema-visible fields into deterministic combat behavior, seed tuning, logs, and comparison UI.

## Key Risks
- Combat balance drift if Precision and Resolve formulas are too swingy.
- API/doc drift while roadmap foundations are promoted into implemented systems.
- Migration sequencing around roadmap schema files 59-61 during production deploys.

## Working Agreement
- Active execution: `agent/ISSUES.md`, `agent/MILESTONES.md`.
- Deferred planning: backlog files.
- Historical context: archive files on demand.
