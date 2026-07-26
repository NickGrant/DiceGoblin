# ISSUES BACKLOG
----

## Purpose
- `agent/ISSUES_BACKLOG.md` tracks deferred planning issues that are not part of the active execution lane.
- Keep `agent/ISSUES.md` focused on active/current milestone execution context.
- Move items from this file into `agent/ISSUES.md` when they become execution-ready.

## Issue Template
Use the same issue schema as `agent/ISSUES.md`.

## Backlog Issues

## Core UX Cleanup

### Refresh high-friction layouts and run presentation

**Milestone:** Core UX Cleanup
**Status:** Open
**Priority:** High

#### Problem
Several core screens feel overly padded, visually stale, or structurally hard to use. Run screens have too much vertical padding, the run icon set needs a refresh, the guide page layout needs a full rework, and the landing/login page should become a compact marketing landing page with login.

#### Acceptance Criteria

- Run screens use tighter vertical spacing without crowding combat or node controls.
- Run icons are refreshed as a cohesive set.
- Guide page layout is rebuilt around scannable player tasks and reference sections.
- Landing/login page presents a small marketing surface plus clear login entry.

### Repair flickering and missing visual assets

**Milestone:** Core UX Cleanup
**Status:** Open
**Priority:** Medium

#### Problem
The dropdown menu graphic flickers when opened, likely because it is not preloaded, and kobold dialogue images are not loading.

#### Acceptance Criteria

- Dropdown menu assets are preloaded or otherwise available before first open.
- Kobold dialogue image paths resolve correctly in the built app.
- Visual asset regressions are covered with a focused smoke test or documented manual check.

### Rework Academy and Shrine copy density

**Milestone:** Core UX Cleanup
**Status:** Open
**Priority:** Medium

#### Problem
Academy should feel closer to the shop UI, unit types should show their tier, and shrine currently repeats too much messaging. Vague phrases like "available now" and "requirements met" do not tell players what they need to know.

#### Acceptance Criteria

- Academy list presentation is visually aligned with shop patterns.
- Academy unit entries show role/type and tier in player-facing language.
- Shrine screen removes duplicate messaging while preserving required state and consequence text.
- Generic "available now" and "requirements met" labels are replaced with specific unlock/status information.

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
