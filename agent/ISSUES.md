# ISSUES FILE
----
Active issues only. Move completed entries to `agent/ISSUES_ARCHIVE.md`.

## Critical Path UAT

### Continue fresh-account July roadmap UAT

**Milestone:** Critical Path UAT
**Status:** Open
**Priority:** High

#### Problem
The July 25 roadmap implementation is complete at the planned issue-slice level, but the full player path still needs continued fresh-account UAT evidence before final release hardening.

#### Acceptance Criteria

- A fresh account is played through Farm, Mountains, Swamps, Wrong Machine recovery, Mystic Cave return, and first Pig Kin reconstruction.
- UAT notes capture story comprehension, unlock timing, reward visibility, and any blocking progression failures.
- Repeat-run behavior is checked for first-clear story, stolen pages, and unlock messaging.
- Any player-facing failures are logged as new active issues with severity and reproduction notes.
- If no blockers are found, the issue is archived with the UAT evidence location.

### Validate encounter and consumable feel

**Milestone:** Critical Path UAT
**Status:** Open
**Priority:** Medium

#### Problem
Hazard, shrine, chaos, healing-consumable, and energy-consumable systems are implemented, but their combined pacing and variety need hands-on validation across several seeds.

#### Acceptance Criteria

- Multiple Farm, Mountains, and Swamps runs are sampled for hazard, shrine, and chaos variety.
- Healing consumables are checked against rest-node value and attrition pressure.
- Energy consumables are checked against energy caps and intended pacing.
- Encounter copy is checked for readability and result clarity.
- Any balance or content-repeat issues are logged with affected region, run seed when available, and expected tuning direction.

## UAT Polish Backlog

### Confirm release merge and generated-artifact hygiene

**Milestone:** UAT Polish Backlog
**Status:** Open
**Priority:** Medium

#### Problem
The roadmap work moved through many stacked PRs, so release readiness needs a final hygiene pass that confirms `main` includes the intended stack and generated artifacts follow repository policy.

#### Acceptance Criteria

- `main` is synced with `origin/main` before release validation begins.
- The July 25 completion analysis and active tracker agree on remaining work.
- Generated frontend artifacts are either intentionally included or intentionally omitted according to release policy.
- A final validation command set is documented before UAT-confirmed fixes are merged.
- Any merge-order or missing-commit concern is logged as a blocker with exact commit/PR references.

## Pattern-Based Run Map Generation

### Implement pattern-v1 assembler behind generator version

**Milestone:** Pattern-Based Run Map Generation
**Status:** Open
**Priority:** Medium

#### Problem
The current `RunGraphGenerator` lane-walker strategy remains the runtime source of truth. The pattern assembler needs to exist as a separate versioned strategy before any region opts in.

#### Acceptance Criteria

- `pattern-v1` is implemented separately from the current lane generator.
- Generation builds a start pattern, guaranteed boss spine, terminal boss/exit segment, optional branches, caps, and encounter-template binding before persistence.
- Invalid graphs fail closed before `run_nodes` or `run_edges` are persisted.
- Generation provenance records generator version, profile version, catalog hash, attempt, and summary metadata.
- Deterministic fixture and property-style tests cover valid graph creation and failure fallback behavior.

### Opt Mountains into pattern-v1 maps

**Milestone:** Pattern-Based Run Map Generation
**Status:** Open
**Priority:** Medium

#### Problem
Mountains is one of the first procedural combat regions targeted for pattern-based generation and needs its own profile, pattern inventory, and rollout evidence.

#### Acceptance Criteria

- A Mountains pattern/profile catalog preserves required rest, chaos, boss, exit, story, reward, and path-length contracts.
- `lane-v1` and `pattern-v1` are compared across a deterministic seed batch.
- Simulation output reports node counts, spine depth, branch count, fallback rate, and validation failures.
- Manual sample review confirms readable left-to-right flow and meaningful route choices.
- New Mountains runs use `pattern-v1` only after the validation and review gates pass.

### Opt Swamps into pattern-v1 maps

**Milestone:** Pattern-Based Run Map Generation
**Status:** Open
**Priority:** Medium

#### Problem
Swamps needs a separate pattern/profile pass after Mountains because it has wider branching, different recovery pressure, and Wrong Machine progression significance.

#### Acceptance Criteria

- A Swamps pattern/profile catalog supports wider branching and stronger regional recovery/reward variation.
- Required rest, chaos, boss, exit, story, reward, and path-length contracts are preserved.
- Deterministic and simulation tests cover Swamps-specific generation distributions.
- Manual sample review accepts repeated motifs, route choices, and boss approach pacing.
- New Swamps runs use `pattern-v1` only after the validation and review gates pass.

### Migrate story placement into generation requests

**Milestone:** Pattern-Based Run Map Generation
**Status:** Open
**Priority:** Medium

#### Problem
Current dialogue insertion can mutate completed graphs after topology generation. Pattern generation should treat story requirements as first-class placement requests so they cannot invalidate provenance, boss depth, or socket closure.

#### Acceptance Criteria

- Current start, after-start, depth-range, before-boss, before-exit, and branch-terminal dialogue placements are represented as generation requests.
- One-time and prerequisite state are resolved before topology assembly.
- Required story placement participates in start, spine, branch, or terminal planning.
- Post-hoc dialogue insertion remains only as a temporary adapter for unmigrated regions.
- Tests prove migrated story placements satisfy graph validation and do not bypass required boss routes.

#### Progress

- Pattern-v1 now resolves user-specific start, before-boss, and before-exit dialogue placement requests before assembly and persists those requests in generation provenance.
- Lane-v1 still uses the temporary post-generation dialogue insertion adapter.
- Future pattern work still needs explicit after-start, depth-range, and branch-terminal placement support if/when active story definitions require those shapes.

### Add pattern generation debug and simulation gates

**Milestone:** Pattern-Based Run Map Generation
**Status:** Open
**Priority:** Medium

#### Problem
The pattern system needs diagnostic visibility before rollout, otherwise invalid candidates, fallback usage, repetition, and poor distribution will be hard to evaluate during UAT.

#### Acceptance Criteria

- Local/test generation traces record profile/catalog versions, candidate rejection reasons, placements, backtracks, caps, encounter binding, and validation failures.
- Persisted run summaries include bounded generation metadata suitable for support and debug inspection.
- Debug map tooling can inspect pattern provenance, spine nodes, depth, pattern slug/version, and fallback use.
- Simulation reports success rate, fallback rate, backtracks, generation duration, node distributions, pattern frequency, and boss path metrics.
- Quality gates require valid graphs, no boss bypasses, no unreachable nodes, no overlaps, and no unresolved visible sockets across the committed seed suite.

#### Progress

- Pattern simulation now reports success rate, fallback rate, validation failures, node/edge distributions, branch counts, spine depth, backtracks, generation duration, node-type frequency, and pattern frequency.
- `run-patterns:gate:mountains:docker` and `run-patterns:gate:swamps:docker` run committed 25-seed gates with strict validity, fallback, branch-count, and backtrack thresholds.
- Pattern-v1 profile budgets were raised before UAT to reduce the map-size gap versus lane-v1: Mountains now gates at roughly 26-27 nodes with 3 branches; Swamps now gates at roughly 33-34 nodes with 4 branches.
- Pattern-v1 branch topology now uses reconnecting branch segments instead of shallow cap-only offshoots, rejects non-forward/crossing edges, and simulation gates report/enforce a maximum of 3 consecutive same-row spine nodes; the current committed gates hold at 2.
- Boss path metrics and frontend debug-map overlays remain future work.

## Pattern-V2 Run Map Generation

### Seed database-owned pattern-v2 tile catalog

**Milestone:** Pattern-V2 Run Map Generation
**Status:** Open
**Priority:** High

#### Problem

Pattern-v2 needs a tile catalogue that can generate squarer maps, but production content cannot rely on command-line sync tools or a duplicated JSON source of truth.

#### Acceptance Criteria

- Pattern-v2 starter, middle, connector/reward, and terminal tile definitions are seeded through forward-only migrations.
- The seeded profile and rules can be loaded from `run_pattern_definitions`, `run_pattern_region_rules`, and `run_generation_profiles`.
- Connector cells are represented as edge/waypoint authoring metadata and do not compile into runtime node rows.
- Request building for `pattern-v2` returns compiled grid tiles instead of V1 transform variants.
- Tests prove the V2 request path can load DB-owned grid tiles.

### Validate pattern-v2 grid catalog contracts

**Milestone:** Pattern-V2 Run Map Generation
**Status:** Open
**Priority:** High

#### Problem

The V2 catalogue introduces grid cells, connector cells, explicit connections, and perimeter exits that need validation before any assembler relies on them.

#### Acceptance Criteria

- Pattern-v2 validation rejects invalid width/height, malformed grids, out-of-bounds exits, unknown connection endpoints, duplicate node keys, and connector cells treated as runtime nodes.
- Validation supports DB-loaded V2 definitions without requiring JSON files.
- Tests cover valid and invalid V2 catalog examples.

#### Progress

- Added a V2 grid catalog validator for DB-loaded definitions and migration-seeded catalogue validation.
- Tightened the initial V2 seed exits so perimeter exits are enforced before composer work begins.

### Implement pattern-v2 tile composer

**Milestone:** Pattern-V2 Run Map Generation
**Status:** Open
**Priority:** High

#### Problem

Pattern-v1 remains spine-first and cannot reliably produce the square, multi-row map shape desired for long-term run generation.

#### Acceptance Criteria

- A `pattern-v2` assembler composes grid tiles into a global map within width, height, and cost budgets.
- Tiles connect through compatible perimeter exits while preserving forward progression.
- Internal connector cells create edges/waypoints only.
- The composed graph preserves required node types, boss gating, exit gating, reachability, and no-crossing constraints.
- The composer is deterministic by seed and remains behind explicit `pattern-v2` tooling until opt-in.

#### Progress

- Added the first Pattern-V2 tile composer for explicit `pattern-v2` preview/simulation requests.
- Composer places DB-loaded grid tiles, keeps connector cells out of runtime nodes, bridges tile sinks to compatible next-tile roots, and validates the assembled graph before returning it.
- Docker DB sampling for Mountains Pattern-V2 produced 10/10 valid previews with 25-32 nodes, 2-3 internal branches, and at most 3 consecutive same-row spine nodes.
- Pattern-V2 can now be requested through the runtime run graph generator with the same persistence-facing node, edge, encounter, hazard, quality-tier, and provenance shape used by Pattern-V1.
- Terminal V2 reward sinks now route onward into the exit so runtime validation does not leave post-boss reward dead ends.

### Add pattern-v2 preview and simulation gates

**Milestone:** Pattern-V2 Run Map Generation
**Status:** Open
**Priority:** Medium

#### Problem

V2 maps need inspection and quality gates before runtime rollout, especially around occupied rows, occupied columns, crossing edges, and dead-end sockets.

#### Acceptance Criteria

- Preview tooling renders V2 assembled maps with nodes, connectors, exits, and generated edges.
- Simulation reports success rate, fallback rate, occupied rows, occupied columns, cost, node count, crossing failures, and boss path metrics.
- Gates fail on invalid graphs, excessive width, insufficient row usage, branchless boss approaches, and unresolved required exits.

#### Progress

- Pattern simulation now reports occupied row and occupied column distributions for every generator version.
- Simulation gate options now support minimum occupied rows and columns, giving Pattern-V2 review a direct shape-quality gate once V2 assembly lands.
- Pattern inspection now reports V2 tile counts plus per-tile dimensions, cost, runtime node count, connector count, edge count, exit count, and tags.
- Pattern-V2 composition now honors per-pattern `max_per_run` and the profile pattern-instance budget; the 25-seed Mountains V2 gate passes with at least 2 branches, 3 occupied rows, and 10 occupied columns in every sample.
- Added committed Docker commands for the strict Mountains Pattern-V2 gate and Pattern-V1 vs Pattern-V2 comparison evidence, including branch count, occupied row/column, straight-spine, and boss-path metrics.

### Opt Mountains into pattern-v2 maps

**Milestone:** Pattern-V2 Run Map Generation
**Status:** Open
**Priority:** Medium

#### Problem

Mountains should move to pattern-v2 only after the catalogue and composer produce a stable square map shape that survives UAT.

#### Acceptance Criteria

- Mountains pattern-v2 simulation and preview samples pass review.
- Pattern-v2 output is compared against pattern-v1 for node count, row usage, route choice, and boss pacing.
- Runtime selection supports Mountains opt-in without affecting other regions.
- New Mountains runs use pattern-v2 only after validation, review, and rollout notes are complete.
