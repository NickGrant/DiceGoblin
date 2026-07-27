# ISSUES BACKLOG
----

## Purpose
- `agent/ISSUES_BACKLOG.md` tracks deferred planning issues that are not part of the active execution lane.
- Keep `agent/ISSUES.md` focused on active/current milestone execution context.
- Move items from this file into `agent/ISSUES.md` when they become execution-ready.

## Issue Template
Use the same issue schema as `agent/ISSUES.md`.

## Backlog Issues

## Node Quality Art Expansion

### Implement loot and shrine quality tiers

**Milestone:** Node Quality Art Expansion
**Status:** Open
**Priority:** Medium

#### Problem
Loot and shrine nodes should use the generated quality-tier assets, with optional A/B variants, so the map better communicates node value.

#### Acceptance Criteria

- Loot nodes select quality tiers that match the relevant generated assets.
- Shrine nodes select quality tiers that match the relevant generated assets.
- A/B variants are chosen by a stable rule, such as deterministic node id parity, or by documented backend randomness.
- Node visuals remain consistent between map display, node details, and reward expectations.
