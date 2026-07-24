# ISSUES BACKLOG
----

## Purpose
- `agent/ISSUES_BACKLOG.md` tracks deferred planning issues that are not part of the active execution lane.
- Keep `agent/ISSUES.md` focused on active/current milestone execution context.
- Move items from this file into `agent/ISSUES.md` when they become execution-ready.

## Issue Template
Use the same issue schema as `agent/ISSUES.md`.

## Backlog Issues

### DNA-002: Implement splice variant catalog and acquisition

**Milestone:** Goblin DNA Splice Variants
**Status:** Planned
**Priority:** High

#### Problem

Units now persist `splice_variant_slug`, but all units still default to `basic_goblin`. The game needs authored splice definitions, acquisition rules, and player-facing visibility before variants affect builds.

#### Acceptance Criteria

- Define a small launch set of splice variants with modest stat tendencies or conditional behaviors.
- Persist rolled variants when units are recruited, purchased, or granted.
- Display splice identity in unit details, rewards, recruitment, and relevant filters.
- Preserve `basic_goblin` as the migration/default lineage.

### PGH-002: Record objective progress from gameplay events

**Milestone:** Progression Guidance and Home Dashboard
**Status:** Planned
**Priority:** High

#### Problem

The home dashboard and profile payload now expose objective structure, but objective progress is not yet updated by gameplay outcomes.

#### Acceptance Criteria

- Record progress for the first objective set from backend-owned gameplay facts.
- Keep progress idempotent across retries and refreshes.
- Show completed and next objective states on the home dashboard.

### BB-002: Build bounty board service and API

**Milestone:** Bounty Board
**Status:** Planned
**Priority:** High

#### Problem

Bounty tables now exist, but players cannot view a generated board, accept contracts, record progress, or claim rewards.

#### Acceptance Criteria

- Add backend services and API endpoints for board listing, acceptance, progress, completion, and reward claim.
- Enforce active-slot limits and duplicate prevention.
- Keep generated board state stable for its rotation period.
- Add backend and frontend coverage for the core bounty flow.

### AFE-001: Expand academy and feature-unlock tree

**Milestone:** Academy and Feature-Unlock Expansion
**Status:** Planned
**Priority:** Medium

#### Problem

The academy is ready for broader medium-term progression, but unlocks remain mostly linear and teeth-driven.

#### Acceptance Criteria

- Add at least one new unlock path that depends on gameplay progress rather than only currency.
- Keep unlock requirements visible and backend-authoritative.
- Document how the expanded tree connects to bounties, splice research, or future crafting.

### WM-001: Add Wrong Machine and Raw Chaos foundation

**Milestone:** Wrong Machine and Raw Chaos
**Status:** Planned
**Priority:** Medium

#### Problem

Unwanted dice currently have no long-term sink, and Raw Chaos does not exist as a second economy.

#### Acceptance Criteria

- Add Raw Chaos account balance storage.
- Add backend-authored dice salvage rules.
- Prevent equipped dice from being salvaged without explicit player action.
- Document fabrication and catalyst work as follow-up scope.

### REE-002: Add expanded run encounter families

**Milestone:** Expanded Run Encounters
**Status:** Planned
**Priority:** Medium

#### Problem

Run nodes now support hazard vocabulary, but the game still needs authored encounter families that create new decisions and biome identity.

#### Acceptance Criteria

- Implement at least one meaningful non-combat encounter family beyond the current dialogue/rest/loot/hazard baseline.
- Persist any generated encounter result before player resolution.
- Add player-facing copy and tests for the new encounter flow.

### SME-001: Design and implement slot-machine-style chaos encounter foundation

**Milestone:** Slot-Machine-Style Random Encounters
**Status:** Planned
**Priority:** Medium

#### Problem

The roadmap calls for a signature chaos encounter that generates readable risk and reward, but no design contract or persistence model exists yet.

#### Acceptance Criteria

- Document reel responsibilities, result persistence, and reward-scaling constraints.
- Add backend persistence for generated chaos encounter results.
- Implement one limited player agency mechanic such as locking or rerolling one reel.

### T3P-001: Complete Tier III progression coverage

**Milestone:** Complete Tier III Progression
**Status:** Planned
**Priority:** Medium

#### Problem

Tier III should wait until expanded stats and splice variants are real, but the late-game class map needs an explicit implementation issue for later promotion.

#### Acceptance Criteria

- Define Tier III destinations for every major Tier I family.
- Add capstone coverage and inherited-passive review.
- Add promotion requirements that use regions, mastery, research, or region items.
