# Pattern-Based Run Map Generation
----

Status: proposed  
Last Updated: 2026-07-26  
Owner: Product + Systems Design + Backend Engineering  
Depends On: `documentation/02-systems-mvp/03-encounter-scope.md`, `documentation/01-architecture/04-data-model.md`, `documentation/01-architecture/08-seed-catalog-ownership.md`, `documentation/02-systems-mvp/14-balancing-strategy-and-simulation.md`, `backend/src/Services/RunGraphGenerator.php`

## Purpose

- Define the target replacement for the current lane-walker procedural run-map generator.
- Produce maps from small authored node patterns while preserving seeded randomness, replayability, and regional variation.
- Guarantee a viable boss route before optional branches consume map space or node budget.
- Establish explicit authoring, placement, weighting, persistence, validation, debugging, and rollout contracts.

## Decision Summary

Procedural combat regions should use a **spine-first pattern assembler**.

The generator MUST:

1. Resolve the region generation profile, story requirements, seed, and catalog version.
2. Place an authored starting pattern.
3. Build a guaranteed main route, called the **spine**, to a legal boss attachment point.
4. Attach the boss and exit terminal segment.
5. Expand optional branches from remaining open sockets using weighted authored patterns.
6. Cap or resolve every unused visible socket.
7. Bind concrete encounter templates after topology is complete.
8. validate the finished graph before it is persisted.

The generator MUST NOT create all nodes independently and then attempt to infer pacing from the result. Local pacing and topology should come from authored patterns; run-level pacing should come from generation budgets and contextual weighting.

This document is a target contract. Until implementation is complete, `RunGraphGenerator` remains the current runtime source of truth for procedural generation.

## Scope

This pattern applies to procedural combat regions, initially:

- `mountains`
- `swamps`

The following remain authored fixed graphs unless separately migrated:

- `mystic_cave`
- `the_farm`

The pattern system may later support fixed or semi-fixed story maps, but that is not required for the first implementation.

## Terminology

| Term | Definition |
| --- | --- |
| Pattern | An authored local graph containing nodes, internal edges, geometry, sockets, tags, and selection rules. |
| Pattern variant | A validated transform of a pattern, such as vertical reflection. |
| Pattern instance | One placed copy of a pattern in a generated run. |
| Socket | A declared connection point owned by a specific node in a pattern. |
| Entry socket | A socket through which a pattern may attach to an existing frontier. |
| Exit socket | A socket that may become a new frontier after placement. |
| Frontier | An open exit socket on the assembled map. |
| Spine | The guaranteed start-to-boss route created before optional branches. |
| Branch | Optional topology attached after the boss route is secured. |
| Cap | A terminal pattern or closure rule used to resolve an unused frontier. |
| Generation profile | Region-specific bounds, budgets, required content, and weighting configuration. |
| Placement request | A story or system requirement such as `start`, `before_boss`, `before_exit`, or a depth range. |
| Node budget | Target and limit values for total nodes and node categories. |
| Depth | Shortest directed path length from the run start node unless otherwise specified. |

## Design Principles

### Authored locally, procedural globally

Patterns should encode intentional local relationships, for example:

- shrine -> combat, with an optional loot branch from combat
- hazard -> rest
- elite combat -> guaranteed loot
- fork into a safer recovery route and a higher-risk reward route
- rest -> boss

The generator decides how these local motifs are arranged across a run. It should not recreate their internal design ad hoc.

### Guarantee required progression first

The generator MUST reserve and construct a valid boss route before spending space on optional branches. Optional content must never make the required route impossible.

### Deterministic by versioned inputs

Given the same:

- region
- player-dependent generation requirements
- seed
- generator version
- pattern catalog version
- encounter catalog version

…the generator MUST produce the same graph and encounter assignments.

### Backend authority

Pattern selection, graph construction, story placement, encounter binding, and validation remain backend responsibilities. The frontend renders the persisted graph and MUST NOT regenerate or reinterpret topology.

### Persist results, not only inputs

A run must persist its realized `run_nodes` and `run_edges`. Resuming a run must not rerun generation from a seed.

### Fail closed

An invalid graph must not be persisted. Generation should retry deterministically with bounded attempts and then use a guaranteed fallback profile or fail run creation with an actionable internal error.

## Current Generator and Replacement Boundary

The current procedural generator:

- creates several seeded walkers across columns
- connects the final column to a central boss and exit
- adds dead-end branches and nearby shortcuts
- assigns node types after topology exists
- applies dialogue insertion as a later graph mutation

The pattern assembler replaces procedural topology construction and node-type assignment for migrated regions.

The following existing contracts remain:

- `region_runs` stores run lifecycle state and seed.
- `run_nodes` stores realized nodes and node metadata.
- `run_edges` stores realized directed connectivity.
- `RunRepository::createRunGraph()` persists the complete graph transactionally.
- node availability continues to derive from cleared parent progression.
- exit remains a dedicated run-completion action.

## Authoring Ownership

Pattern definitions are complex authored content. The recommended ownership model is hybrid:

1. Version-controlled structured files are the authoring source of truth.
2. Seed or synchronization logic loads enabled definitions into a read-only runtime catalog.
3. Backend code owns placement, validation, random selection, and executable node behavior.
4. Runtime instances are persisted only in `region_runs`, `run_nodes`, and `run_edges`.

Recommended source location:

```text
backend/data/run-patterns/
  shared/
  mountains/
  swamps/
  profiles/
```

Raw migration SQL SHOULD NOT become the primary editing surface for pattern geometry. The database mirror exists for runtime lookup, joins, debug inspection, and future balancing tools.

## Suggested Catalog Data Model

### `run_pattern_definitions`

One row per immutable pattern version.

| Column | Purpose |
| --- | --- |
| `id` | Internal numeric identifier. |
| `slug` | Stable pattern identifier such as `shrine_combat_loot_branch`. |
| `version` | Positive authored version. Existing versions are not edited after use in persisted runs. |
| `status` | `draft`, `enabled`, or `disabled`. |
| `definition_json` | Nodes, edges, sockets, geometry, tags, transforms, and pattern limits. |
| `content_hash` | Hash used to detect seed drift. |
| `created_at` | Audit timestamp. |
| `updated_at` | Audit timestamp. |

Recommended uniqueness:

- unique (`slug`, `version`)
- unique `content_hash` where practical

### `run_pattern_region_rules`

Normalized region-specific selection rules.

| Column | Purpose |
| --- | --- |
| `pattern_definition_id` | Pattern version being configured. |
| `region_id` | Region in which the pattern may appear. |
| `base_weight` | Positive baseline selection weight. |
| `allowed_phase` | `start`, `spine`, `branch`, `cap`, `boss_approach`, or `terminal`. |
| `min_depth` | Earliest allowed attachment depth. |
| `max_depth` | Latest allowed attachment depth, nullable. |
| `max_per_run` | Hard repetition limit, nullable. |
| `cooldown_patterns` | Number of placed patterns before exact reuse is allowed. |
| `enabled` | Region-level enable switch. |
| `weight_modifiers_json` | Optional authored tuning modifiers that do not execute arbitrary code. |

A pattern may have several region-rule rows when it is eligible in several phases.

### `run_generation_profiles`

One active profile per region and generator version.

| Column | Purpose |
| --- | --- |
| `region_id` | Owning region. |
| `generator_version` | Stable algorithm contract, for example `pattern-v1`. |
| `profile_version` | Versioned authored tuning. |
| `enabled` | Whether this profile may be selected. |
| `bounds_json` | Grid width, height, margins, and edge-clearance policy. |
| `budgets_json` | Total, spine, branch, combat, reward, recovery, hazard, shrine, and chaos ranges. |
| `requirements_json` | Required content and path constraints. |
| `retry_policy_json` | Candidate, backtrack, and full-generation attempt limits. |
| `weight_policy_json` | Global contextual multiplier settings. |
| `content_hash` | Hash used to pin deterministic input. |

### Run provenance

`region_runs` SHOULD gain enough provenance to reproduce and diagnose generation decisions:

| Field | Purpose |
| --- | --- |
| `generator_version` | Algorithm version. |
| `generation_profile_version` | Region profile version. |
| `pattern_catalog_hash` | Exact enabled pattern catalog snapshot hash. |
| `generation_attempt` | Successful deterministic retry index. |
| `generation_summary_json` | Bounded summary of budgets, counts, and fallback use. |

If schema expansion is deferred, these values may temporarily live in one `generation_meta_json` field. They must not exist only in logs.

## Pattern Definition Contract

A pattern definition should be self-contained and immutable by version.

Illustrative structure:

```json
{
  "slug": "shrine_combat_loot_branch",
  "version": 1,
  "tags": ["shrine", "combat", "optional_reward", "medium"],
  "allowed_transforms": ["identity", "mirror_y"],
  "node_cost": 3,
  "limits": {
    "max_per_run": 1,
    "cooldown_patterns": 4
  },
  "nodes": [
    {
      "key": "shrine",
      "type": "shrine",
      "x": 0,
      "y": 0,
      "role": "entry",
      "tags": ["preparation"]
    },
    {
      "key": "combat",
      "type": "combat",
      "x": 1,
      "y": 0,
      "role": "continuation",
      "tags": ["required_within_pattern"]
    },
    {
      "key": "loot",
      "type": "loot",
      "x": 1,
      "y": -1,
      "role": "optional_terminal",
      "tags": ["reward"]
    }
  ],
  "edges": [
    {"from": "shrine", "to": "combat"},
    {"from": "combat", "to": "loot"}
  ],
  "sockets": [
    {
      "id": "entry_left",
      "kind": "entry",
      "node": "shrine",
      "direction": "left",
      "path_eligibility": ["spine", "branch"]
    },
    {
      "id": "exit_right",
      "kind": "exit",
      "node": "combat",
      "direction": "right",
      "path_eligibility": ["spine", "branch"]
    }
  ]
}
```

### Required pattern fields

Every enabled pattern MUST define:

- stable `slug`
- positive `version`
- at least one node
- unique local node keys
- local integer coordinates
- valid internal directed edges
- at least one entry socket, except a dedicated start pattern
- zero or more exit sockets
- allowed generation phases through region rules
- explicit transform allowlist
- tags sufficient for selection and reporting

### Node fields

Each pattern node MUST include:

- `key`
- `type`
- `x`
- `y`

Each node SHOULD include:

- `role`
- semantic `tags`
- optional encounter-selection constraints
- optional authored metadata copied into the runtime node

Valid node types remain those defined by `documentation/02-systems-mvp/03-encounter-scope.md`.

### Socket fields

Each socket MUST include:

- unique socket `id` within the pattern
- `kind`: `entry` or `exit`
- owning local node key
- cardinal direction
- path eligibility

Optional socket rules may include:

- compatible socket tags
- forbidden socket tags
- whether merging is allowed
- whether it may remain visible if capped
- minimum clearance around the connection

A socket belongs to a specific node. Pattern-level sockets without an owning node are invalid.

### Internal graph rules

- Every pattern node MUST be reachable from at least one entry node, except explicitly declared decorative nodes that are not persisted as run nodes.
- Every exit socket MUST be reachable from every entry socket for phases that require continuation.
- A spine-eligible pattern MUST expose at least one spine-eligible exit.
- A terminal or cap pattern MUST expose no unresolved required exit.
- Patterns MUST NOT contain a boss or exit node unless their phase is `terminal` or an explicitly approved fixed-story phase.
- Internal cycles are forbidden for `pattern-v1`.

### Geometry rules

- Coordinates are pattern-local integer grid cells.
- Multiple nodes may not occupy one local cell.
- Internal edges must use the supported visual connection geometry.
- Patterns should advance primarily left-to-right.
- `identity` is always explicit; transforms are never inferred.
- `mirror_y` is the default safe variation for vertically symmetric map layouts.
- `rotate_90`, `rotate_180`, `rotate_270`, or `mirror_x` are disallowed unless explicitly authored and validated. Backward-facing transforms must not create progression edges that visually imply reverse travel.

## Pattern Variants

The generator SHOULD compile enabled patterns into validated variants before attempting placement.

For each allowed transform:

1. Transform node coordinates.
2. Transform socket directions.
3. Normalize the local bounding box to a stable origin.
4. Recalculate footprint and edge segments.
5. Re-run pattern validation.
6. Assign a deterministic variant key such as `slug@version:mirror_y`.

Invalid variants are catalog errors and MUST fail startup validation or seed validation. They should not be silently removed during a player run.

## Generation Request

Before assembling a graph, the backend builds a generation request containing:

- `user_id`
- `region_id`
- `region_slug`
- run seed
- generator version
- generation profile version
- pattern catalog hash
- encounter catalog version or hash
- applicable feature unlocks
- applicable one-time dialogue state
- required story placement requests
- optional modifiers from squad perks such as Treasure Sense

Player-dependent inputs that alter topology MUST be included in deterministic provenance or resolved before the seed is generated.

## Story Placement Requests

Story placement should be represented as requirements, not arbitrary post-generation mutation.

Supported initial placements:

- `start`
- `after_start`
- `depth_range`
- `before_boss`
- `before_exit`
- `branch_terminal`

Each request SHOULD define:

- stable request id
- node type and authored content id
- placement category
- required or optional
- one-time or repeatable behavior
- prerequisite unlock/dialogue state
- path requirement: spine, branch, or either
- allowed depth range
- fallback behavior

Required story content MUST participate in spine planning or reserved branch planning. Post-hoc edge rewiring should remain only as a temporary migration adapter.

## Generation Budgets

A generation profile MUST define ranges rather than one total node count.

Recommended budgets:

| Budget | Purpose |
| --- | --- |
| `total_nodes` | Overall run size. |
| `spine_nodes` | Start-to-boss pacing. |
| `branch_nodes` | Optional exploration size. |
| `combat_nodes` | Attrition and XP pacing. |
| `reward_nodes` | Loot/economy pacing. |
| `recovery_nodes` | Rest and recovery pacing. |
| `hazard_nodes` | Regional pressure and identity. |
| `shrine_nodes` | Preparation and favor pacing. |
| `chaos_nodes` | Required chaos encounter presence. |
| `branch_count` | Number of meaningful optional routes. |
| `frontier_count` | Maximum simultaneous open sockets. |
| `pattern_instances` | Protection against many tiny patterns. |

Each budget may define:

- minimum
- target
- maximum
- hard or soft enforcement

Hard minimums and maximums affect candidate eligibility. Soft targets affect contextual weights.

The initial migrated regions MUST preserve current encounter guarantees, including at least one rest node and at least one chaos node unless the active region contract is deliberately changed.

## Generation Algorithm

### Phase 0: Resolve and validate inputs

1. Load the enabled generation profile for the region.
2. Load enabled pattern versions and region rules.
3. Resolve story placement requests for the user.
4. Compile pattern variants.
5. Verify that the catalog contains the guaranteed fallback set.
6. Initialize the deterministic random stream from all pinned generation inputs.
7. Start generation attempt `0`.

The implementation MUST use one deterministic random abstraction. Direct calls to nondeterministic random functions are forbidden after the run seed has been created.

### Phase 1: Place the starting pattern

Starting-pattern priority:

1. required story start pattern
2. region-specific authored start pool
3. shared generic start pool
4. guaranteed minimal fallback start

The starting pattern is placed at the profile origin. One start node is marked `available`; all other runtime nodes begin `locked` unless a fixed-story contract says otherwise.

After placement:

- register occupied cells
- register occupied edge segments
- create the initial frontier set from exit sockets
- choose one spine-eligible frontier as the active spine frontier
- record the pattern instance in generation state

A start pattern may expose several exits, but only one is initially designated as the spine continuation. Other exits remain reserved for branch expansion.

### Phase 2: Build the boss spine

The spine is generated before optional branch expansion.

Continue attaching spine-eligible patterns until all of the following are true:

- minimum spine depth is met
- required spine story placements are satisfied or reserved
- hard minimum node budgets that must occur before the boss can still be satisfied
- a legal boss-approach or terminal pattern can fit

At each spine step:

1. Evaluate all variants that can attach to the active spine frontier.
2. Reject variants that fail socket compatibility or physical fit.
3. Reject variants that violate hard budgets, repetition limits, story reservations, or terminal clearance.
4. Reject variants that leave no viable spine exit unless the boss terminal may attach immediately.
5. Score remaining candidates with contextual weights.
6. Select one candidate using deterministic weighted randomness.
7. Place the candidate and choose its next spine frontier.
8. Preserve its other exits as branch frontiers where allowed.
9. Update budgets, depth, history, and occupancy.

The spine builder SHOULD use bounded depth-first search with backtracking rather than a single greedy pass.

Recommended limits:

- candidate retries per frontier
- backtrack depth
- spine placement attempts
- full generation attempts

When a chosen pattern later makes the boss terminal impossible, the generator should roll back placed pattern instances to the most recent decision point and try the next deterministic candidate ordering.

### Phase 3: Attach boss approach, boss, and exit

The preferred terminal structure is an authored terminal pattern containing:

- optional boss-approach node or nodes
- exactly one boss node
- exactly one exit node
- edge from boss to exit

A separate boss-approach pattern plus boss/exit pattern is also valid.

Terminal placement rules:

- boss MUST be reachable from the start
- exit MUST be downstream of boss
- exit MUST NOT have a path that bypasses boss
- required `before_boss` and `before_exit` story placements MUST be satisfied
- terminal nodes MUST fit inside reserved bounds
- the boss path MUST meet profile minimum and maximum depth

Once terminal placement succeeds, the required route is considered secured. Later branch expansion may not remove, bypass, or invalidate it.

### Phase 4: Expand optional branches

Branch expansion continues until one of these conditions is met:

- target total-node budget is met
- branch-node budget is met
- branch-count target is met
- no legal branch placements remain
- maximum branch passes are exhausted

Each pass:

1. Build the eligible frontier list.
2. Score frontiers.
3. Select one frontier using deterministic weighted randomness.
4. Build and weight eligible branch/cap pattern candidates.
5. Place one candidate or mark the frontier for capping.
6. Update budgets, repetition history, and frontier state.

Frontier selection SHOULD consider:

- path depth
- distance from occupied map boundaries
- nearby occupancy density
- age of the frontier
- current number of open frontiers
- branch length so far
- whether the branch needs a payoff or recovery node
- whether the map needs more vertical spread
- whether the frontier originates on the spine

The generator SHOULD penalize creating new exits when the current frontier count is above target. This prevents uncontrolled fragmentation.

### Phase 5: Resolve open frontiers

Every visible open frontier MUST be resolved before persistence.

Resolution priority:

1. attach a legal authored cap pattern
2. attach a required branch-terminal story or reward pattern
3. convert the socket to a declared hidden/closed socket when the owning pattern allows it
4. backtrack the branch placement that created an unresolvable visible socket

The generator MUST NOT leave visual stubs that imply a route but have no node.

Recommended cap inventory:

- one-node loot terminal
- one-node shrine terminal
- one-node hazard terminal
- combat -> loot terminal
- dialogue terminal
- no-reward scenic closure, represented as socket closure rather than a persisted encounter node

### Phase 6: Apply optional generation modifiers

Modifiers such as Treasure Sense should operate through the same pattern placement contract whenever they alter topology.

Preferred Treasure Sense behavior:

- reserve or attach a hidden-treasure cap pattern to an eligible frontier
- annotate the created node with reveal provenance
- respect bounds, collision, node budgets, and deterministic seed inputs

Topology-changing modifiers MUST NOT append arbitrary nodes after validation without rerunning full graph validation.

### Phase 7: Bind encounter templates

After topology and node types are final:

1. Build encounter-template pools by region and node type.
2. Filter by node tags, depth, role, and authored constraints.
3. Apply deterministic weighted selection.
4. Store the selected `encounter_template_id` on each applicable runtime node.
5. Validate that every required node type has a valid template.

Pattern definitions should describe encounter intent, not hard-code database numeric ids.

Examples of encounter-intent tags:

- `normal`
- `elite`
- `ambush`
- `reward_guard`
- `low_pressure`
- `boss_approach`
- `guaranteed_loot`

Stable template slugs may be used in story-specific patterns, but numeric ids must be resolved during generation.

### Phase 8: Normalize and persist

Before persistence:

- sort runtime nodes by stable display order, preferably column then row then local provenance
- assign contiguous `node_index` values
- rewrite edges to final node indexes
- assign initial node statuses
- produce generation summary metadata
- run all graph validators

Persistence remains transactional through the run repository.

### Phase 9: Retry and fallback

Generation failure must be bounded and deterministic.

Recommended escalation:

1. candidate backtracking within the current spine or branch
2. restart the current phase with a deterministic sub-seed
3. restart the full generation with incremented `generation_attempt`
4. use the region's guaranteed fallback profile
5. abort run creation and emit a structured internal error

A fallback profile MUST still produce a valid region run. It may be less varied, but it must preserve required story, rest, chaos, boss, and exit contracts.

## Placement Mapping Algorithm

### Socket compatibility

An existing frontier and candidate entry socket are compatible when:

- both socket kinds are complementary: existing `exit`, candidate `entry`
- their directions face each other
- path eligibility includes the active phase
- required tags are present
- forbidden tags are absent
- connection clearance rules are compatible

For left-to-right progression, a common attachment is:

- existing exit direction: `right`
- candidate entry direction: `left`

### Transform and translation

For every compatible pattern variant:

1. Read the local position of the entry socket's owning node.
2. Compute the target global cell one step beyond the frontier's owning node in the frontier direction.
3. Translate every local node coordinate so the candidate entry node lands on that target cell.
4. Translate internal edge segments and socket positions using the same offset.
5. Evaluate physical fit.

The socket connection becomes one runtime edge from the frontier's owning node to the candidate entry node. Internal pattern edges are then added unchanged after local-to-global mapping.

### Physical fit checks

A candidate fits only when all checks pass:

- all node cells are inside profile bounds and margins
- no node cell overlaps an occupied cell
- no internal edge crosses a node cell it does not own
- no new edge crosses an existing edge unless crossing is explicitly supported
- connection lines meet spacing/clearance rules
- no node is placed in reserved boss-terminal cells
- no branch placement consumes required future spine clearance
- visual row/column spacing remains renderable at supported viewports

Occupancy should track at least:

- node cells
- edge segments
- reserved cells
- optional clearance cells around large or special nodes

### Candidate ordering

For deterministic backtracking, candidate evaluation should produce a stable ordered list before weighted selection.

Stable ordering keys:

1. pattern slug
2. pattern version
3. variant key
4. entry socket id
5. translated origin

The deterministic random stream may then assign weighted pick keys without relying on database row order.

## Weighting Model

Candidate weights should combine authored likelihood with run state.

```text
final_weight =
  base_weight
  * region_multiplier
  * phase_multiplier
  * depth_multiplier
  * budget_deficit_multiplier
  * path_pacing_multiplier
  * repetition_multiplier
  * frontier_pressure_multiplier
  * size_compensation_multiplier
  * story_multiplier
```

### Required weight behavior

- A hard-ineligible candidate has weight `0` and is excluded.
- A candidate with a positive final weight remains selectable.
- Weight calculations must be deterministic and inspectable.
- Arbitrary executable expressions in authored data are forbidden.
- Multipliers should be selected from a bounded vocabulary implemented by backend code.

### Budget deficit multiplier

For a category with minimum, target, and maximum:

- below minimum: strongly increase patterns that contribute the category
- between minimum and target: moderately increase contribution
- at target: neutral
- above target: reduce contribution
- at hard maximum: exclude candidates that add the category

### Repetition multiplier

Recommended behavior:

- exact pattern within cooldown window: exclude or sharply penalize
- same motif/tag combination used recently: moderate penalty
- same geometry silhouette used recently: small penalty
- exceeding `max_per_run`: exclude

### Size compensation

Large patterns have fewer legal placements than small patterns. Without compensation, actual appearance rates will be lower than authored base weights.

The system SHOULD measure realized selection frequency in simulations and support a bounded size-compensation multiplier. This multiplier must not override fit or budget rules.

## Spine Selection Rules

The spine is a generation designation and should be persisted as provenance.

- Every spine pattern instance contributes at least one ordered continuation from start toward boss.
- A runtime node may be tagged `is_spine=true` when it lies on the selected guaranteed route.
- Optional edges may reconnect to the spine only when they do not bypass required spine nodes.
- Branch merges are allowed but must preserve directed acyclic progression.
- Boss depth is measured on the shortest legal start-to-boss path after all edges are finalized.
- Validation must detect shortcuts that reduce boss depth below the profile minimum.

If several valid routes reach the boss after branch merging, the originally constructed guaranteed route remains the spine for diagnostics, but graph validation uses actual shortest and longest path metrics.

## Runtime Node Metadata

Each generated runtime node SHOULD include generation provenance in `meta_json`:

```json
{
  "col": 4,
  "row": 2,
  "generation": {
    "pattern_slug": "shrine_combat_loot_branch",
    "pattern_version": 1,
    "pattern_variant": "mirror_y",
    "pattern_instance": 7,
    "local_node_key": "combat",
    "phase": "spine",
    "is_spine": true,
    "depth": 6,
    "tags": ["combat", "preparation_followup"]
  }
}
```

This metadata supports:

- reproducibility
- debug map inspection
- player-support investigation
- pattern-frequency simulation
- telemetry
- future visual treatments

Pattern instance identifiers need only be unique within one run.

## Graph Validation

Validation is mandatory before persistence.

### Structural invariants

- exactly one start node is available initially
- every persisted node is reachable from start
- every edge references valid nodes
- no self edges
- no duplicate edges
- directed graph is acyclic for `pattern-v1`
- exactly one boss in procedural combat regions
- exactly one exit in procedural combat regions
- exit is reachable from boss
- no route reaches exit without passing through boss
- boss and exit have no invalid outgoing edges

### Spatial invariants

- all node coordinates are unique
- all nodes are inside region bounds
- no forbidden edge crossings
- no edges visually pass through unrelated nodes
- all connection directions match the rendered relationship
- no unresolved visible sockets

### Pacing invariants

- spine depth is inside configured range
- shortest start-to-boss depth is inside configured range
- total nodes are inside hard range
- hard category minimums are met
- hard category maximums are not exceeded
- required rest and chaos guarantees are met
- branch count and frontier count do not exceed hard limits

### Content invariants

- all required story requests are satisfied
- one-time dialogue rules are respected
- every combat, boss, loot, rest, shrine, and chaos node that requires a template has a valid template
- every runtime pattern provenance entry resolves to the pinned catalog version
- disabled catalog content is not selected

## Determinism Contract

The deterministic random abstraction MUST:

- accept string namespaces or equivalent stable keys
- avoid dependence on loop iteration over unordered maps or database result order
- record the successful generation attempt
- separate random streams by concern where practical

Recommended namespaces:

```text
start-pattern
spine-frontier:{step}
spine-candidate:{step}
branch-frontier:{pass}
branch-candidate:{pass}
cap:{frontier-id}
encounter-template:{node-index}
modifier:treasure-sense
```

Adding a new random call to one phase should not unnecessarily perturb unrelated later phases. Namespaced hash-derived rolls are preferred over one long mutable sequence when feasible.

Determinism is version-scoped. A generator-version change may intentionally produce a different map for the same seed, but already persisted runs remain unchanged.

## Minimum Guaranteed Pattern Set

Every procedural region profile MUST have an enabled fallback set containing:

- one start pattern
- one single-node or short spine connector
- one combat-bearing spine connector
- one rest-bearing spine connector
- one chaos-bearing spine or branch connector
- one branch connector
- one terminal cap
- one boss-approach pattern
- one boss -> exit terminal pattern

Catalog validation should prove that a minimal map can be assembled inside the region bounds using only the fallback set.

## Recommended Initial Pattern Inventory

The first production catalog should favor structural variety over cosmetic duplication.

### Start patterns

- single combat start with one exit
- combat start with two exits
- story/dialogue start with one exit

### Spine connectors

- single combat connector
- combat -> loot connector
- hazard -> rest connector
- shrine -> combat connector
- combat -> shrine connector
- chaos connector with continuation
- short two-combat pressure chain

### Branch patterns

- optional loot dead end
- combat -> loot dead end
- shrine branch returning to continuation
- safe rest branch versus risky combat reward branch
- two-way fork with unequal node counts
- short merge pattern

### Caps

- loot cap
- shrine cap
- hazard cap
- dialogue cap
- combat -> loot cap

### Boss approaches

- rest -> boss socket
- shrine -> boss socket
- elite combat -> boss socket
- dialogue -> boss socket

### Terminal

- boss -> exit
- dialogue -> boss -> exit
- boss -> dialogue -> exit, only where story contract permits exit dialogue after victory

## Branches and Merges

Branches should create meaningful route choices, not only visual width.

- A branch should generally contain a payoff, recovery opportunity, story beat, or risk tradeoff.
- Repeated one-node dead ends should be limited by profile budgets.
- Merges are allowed and encouraged to control map width.
- A merge pattern must declare all supported entry sockets and one or more exits.
- A merge must not require both parent routes to be cleared unless the encounter contract explicitly supports an AND gate.

Current availability behavior unlocks a node from cleared parent progression. The generator and repository must preserve the intended OR-parent semantics unless a separate gate model is introduced.

## Story and Dialogue Migration

The current dialogue insertion path mutates completed graphs for placements such as start, before boss, and before exit.

Migration target:

1. Convert dialogue definitions into placement requests.
2. Resolve one-time and prerequisite state before generation.
3. Fulfill required requests during start, spine, terminal, or branch planning.
4. Retain post-hoc insertion only as a temporary adapter for unmigrated definitions.
5. Remove the adapter after parity tests cover all dialogue placements.

This prevents dialogue rewiring from invalidating pattern provenance, boss depth, or socket closure.

## Observability and Debugging

The backend SHOULD produce a bounded generation trace in local/test environments.

Recommended trace events:

- profile and catalog versions loaded
- story requirements resolved
- start pattern selected
- each spine candidate rejection reason
- each placed pattern instance
- backtrack events
- boss-terminal reservation and placement
- frontier selections
- cap decisions
- encounter-template assignments
- validation failures
- full-attempt restart
- fallback-profile use

Production persistence should store a compact summary, not the full candidate trace.

Recommended summary fields:

- seed and attempt
- total patterns and nodes
- spine length
- shortest and longest start-to-boss paths
- branch count
- node counts by type
- pattern counts by slug
- validation duration
- backtrack count
- fallback used

The debug map surface should be able to overlay:

- pattern-instance boundaries
- socket locations
- spine nodes
- depth
- pattern slug/version
- rejected or reserved cells when viewing a captured local trace

## Testing Strategy

### Catalog validation tests

- required fields are present
- local node keys and socket ids are unique
- edges and sockets reference valid local nodes
- transformed variants remain valid
- spine patterns have legal continuation exits
- terminal patterns contain valid boss/exit relationships
- region rules reference enabled pattern versions
- fallback set can construct a minimal valid run

### Deterministic fixture tests

Pin representative seeds for each migrated region and assert:

- complete node/edge snapshot
- pattern provenance snapshot
- story placement
- encounter-template assignment
- generation attempt

Snapshots must include generator and catalog versions so intentional changes are explicit.

### Property tests

Across a large deterministic seed batch, assert zero violations of:

- reachability
- boss gating
- overlap
- bounds
- cycle rules
- unresolved sockets
- hard budgets
- missing templates
- story requirements

### Distribution tests

Simulation should report:

- generation success rate on first attempt
- fallback rate
- average backtracks
- generation duration
- total-node distribution
- spine-depth distribution
- branch-count distribution
- node-type distribution
- pattern appearance frequency
- pattern rejection reasons
- boss shortest-path distribution
- open-frontier peak

Initial quality gates SHOULD include:

- 100% valid graphs over the committed seed suite
- 0% boss bypasses
- 0% unreachable nodes
- 0% overlaps
- 0% unresolved visible sockets
- bounded fallback rate agreed by Systems Design and Engineering
- no pattern with meaningful configured weight appearing effectively never unless fit constraints explain it

### Manual map review

Automated validity does not establish map quality. Review generated samples for:

- readable left-to-right flow
- meaningful route choices
- repeated motifs
- visual crowding
- branches that are obviously dominated
- recovery placement before high-pressure segments
- boss approach pacing
- regional identity

## Performance Expectations

Generation runs during run creation and must remain bounded.

- Compile and cache validated catalog variants per process where safe.
- Use coordinate and edge occupancy indexes for constant-time or near-constant-time collision checks.
- Apply strict candidate and backtrack limits.
- Avoid database queries inside candidate loops.
- Load profiles, patterns, region rules, and encounter pools before assembly.
- Persist only after successful validation.

Generation metrics should separate:

- catalog load/compile time
- topology assembly time
- encounter binding time
- validation time
- persistence time

## Migration Plan

### Phase 1: Catalog and validator foundation

- Define structured pattern and profile schemas.
- Add catalog seed/sync path.
- Add pattern validator and transform compiler.
- Add debug inspection of loaded patterns.
- Do not change live region generation.

### Phase 2: Pattern assembler behind a generator version

- Add `pattern-v1` as a separate generation strategy.
- Preserve the current lane-walker strategy as `lane-v1` during migration.
- Persist generator/profile/catalog provenance.
- Add deterministic and property tests.

### Phase 3: Mountains opt-in

- Build a Mountains starter catalog.
- Run both generators across the same seed batch for structural comparison.
- Validate rest, chaos, boss, story, reward, and path-length distributions.
- Enable `pattern-v1` for Mountains after manual review.

### Phase 4: Swamps opt-in

- Expand the catalog for wider branching and stronger recovery/reward variation.
- Tune profile budgets separately from Mountains.
- Enable after the same simulation and manual-review gates.

### Phase 5: Story integration and cleanup

- Convert dialogue insertion definitions into placement requests.
- Move Treasure Sense topology changes into pattern attachment.
- Remove migrated post-hoc topology mutations.
- Retire `lane-v1` only after no enabled region references it and active runs are safely persisted.

## Compatibility Rules

- Existing active runs must continue from persisted `run_nodes` and `run_edges` without regeneration.
- Changing the active generator affects only newly created runs.
- Pattern versions referenced by persisted provenance must remain resolvable for support/debug purposes, even when disabled for new selection.
- Pattern catalog cleanup may archive old structured definitions, but must not reuse a prior (`slug`, `version`) pair for different content.
- Frontend map payloads may remain unchanged initially because coordinates and graph data continue through node metadata and edges.

## Acceptance Criteria

This design is ready for implementation planning when:

- pattern, socket, region-rule, and profile schemas are agreed
- story placement requests cover current dialogue placements
- a guaranteed fallback set is authored for Mountains
- deterministic RNG and versioning contracts are selected
- validation invariants are represented in automated test plans
- generator provenance has a persistence location
- simulation output includes structural generation metrics

The first region migration is complete when:

- new runs use `pattern-v1`
- fixed regions remain unaffected
- required rest and chaos nodes are guaranteed
- boss and exit gating is valid for all test seeds
- no invalid graph is persisted
- map-generation distributions and manual samples are accepted
- the PR records simulation and validation evidence

## Explicit Non-Goals for `pattern-v1`

- runtime map editing by players
- arbitrary freeform coordinates
- cyclic routes
- AND-gated merge nodes requiring multiple branches to be cleared
- multi-region runs
- procedural encounter behavior authored as executable expressions
- frontend-generated topology
- live editing of active-run pattern instances
- replacing fixed Mystic Cave or Farm graphs without a separate design decision
