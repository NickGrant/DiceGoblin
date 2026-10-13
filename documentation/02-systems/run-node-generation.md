---
Title: "Run Node Generation"
Status: Canonical
Last Updated: 2026-10-12
Owner: Systems Design + Engineering
Depends On:
  - documentation/07-development-path/vnext-authored-content-model.md
  - documentation/07-development-path/vnext-storage-model.md
Category: 02-systems
Tags: [systems, runs, generation]
---

# Run Node Generation

Run generation is a reusable computational subsystem that creates the graph a player traverses. The existing generator/pattern algorithms are valuable behavior to preserve where they still produce the intended game, but prototype storage/catalog ownership is not preserved.

## vNext Boundary
A run-start application command:
1. validates the player, region, active squad, Energy, and run eligibility;
2. loads authored region/generation definitions from the validated JSON ContentRegistry;
3. invokes the run generator with deterministic inputs;
4. receives a generated graph;
5. persists the run, nodes, edges, run-unit state, and required generated metadata transactionally;
6. spends Energy exactly once with successful run creation.

The generator itself should not own HTTP, SQL transactions, player wallets, or authored database catalogs.

## Graph
Persist enough generated state to resume the same run after reload/reconnect. Nodes/edges and their runtime status determine availability; a separate path-history log is not required unless a later mechanic needs it.

Generated node details that the player is not yet entitled to know must not automatically appear in the client representation.

## Content Ownership
Region rules, generation configuration, patterns/definitions, encounter references, and tuning are authored JSON where they are static game content. Do not reintroduce migration-seeded run-pattern catalogs as the vNext source of truth.

## Determinism and Validation
Given the same intended generation inputs, generation should be reproducible enough for testing/debugging. Generated graphs must be validated for required connectivity, valid authored references, terminal/boss reachability, and other region invariants before persistence.

Specific Farm/Mountains topology and tuning are implemented in their milestones. The architecture must make adding Mountains primarily a content/configuration exercise rather than a second region-specific persistence/API design.

## Farm Fixed Graph
`region.the_farm` references the server-owned `run_generation.the_farm` canonical definition. That definition uses the supported `fixed_graph_v1` algorithm and authors one left-to-right path: combat, loot, rest, boss, exit. Its local node keys, node-type references, placement coordinates, and edges are validated before entering the registry.

`FixedGraphRunGenerator` receives the already-validated generation definition and returns only an in-memory graph with sequential run-local indexes, initial status, placement metadata, nullable encounter references, and index-based edges. It does not load content, use PDO, assign database IDs, or persist state. `GeneratedRunGraphValidator` checks the generated boundary again for sequential identity, endpoint integrity, coherent initial availability, connectivity, and a reachable exit.

`POST /api/v1/runs` now supplies that definition to the generator after locking the player's `user_state` and validating the server-selected active squad. The same transaction persists the run root, maps generated indexes to relational node IDs, persists edges and participating units, spends Energy, increments `player_revision` once, and finalizes the idempotency receipt.

Only `run_node_type.*` presentation fields are projected to the browser. The generation definition, fixed topology, and region-to-generation relationship remain server-private even though they participate in the global content revision.

## Branching Traversal

The same `fixed_graph_v1` node and directed-edge lists support exploration graphs with branches, convergence, and optional dead ends. The first authored node is the start; every node must be reachable from it, and a single terminal Exit must be reachable through a completion path. Edges form a directed acyclic graph so a completed encounter cannot become reachable again. Node list order gives stable generated identities and read order, but does not determine edge direction or reachability. Farm retains its authored linear path. Mountains adds a Rockfall side trail as an optional dead end and Loose Scree on the continuing path.

Persisted node and edge state determines the authoritative frontier. A newly generated run has only its start node available. Resolving any available node completes it and unlocks its direct locked successors in the same transaction; every other available node remains available. A successor with multiple incoming routes unlocks when any incoming predecessor completes and is unlocked only once. A dead-end node adds no successors and does not itself end the run. A failed resolution rolls back the transition. A finalized idempotency receipt replays the result without changing the frontier again. Knowing a locked or completed node ID does not authorize resolution. Exit is the successful terminal node.

`GET /api/v1/runs/current` returns the persisted graph with safe node-type presentation and `available_node_ids` in node-index order. The client uses this list for interaction and may display completed battle nodes for replay. Reload and reconnect reproduce the same persisted frontier. Terminal runs have no current-run graph; the terminal resolution returns no newly available nodes.

## Non-combat encounter actions

An authored encounter with `kind: non_combat` has a stable ID, display name, description, prompt, and one or more actions with stable IDs, labels, and server-owned effects. Farm and Mountains Rest nodes reference separate authored encounters that use the same `restore_participating_units_to_max` effect. Authored `family` and node-type `encounter_family` fields bind Rest, Hazard, Shrine, and Chaos references to compatible effects without making the current-run projection aware of concrete node IDs. The content validator checks encounter shape, action identity, supported effect semantics, and run-node reference compatibility. This is deliberately a single-step action contract; it does not persist encounter progress.

An authored run-node type declares the encounter kind it requires. The generated node persists its authored encounter ID, and the current-run query checks that association, the encounter kind, and the run region before projection. Any available node associated with a supported `non_combat` encounter receives an `interaction` in `GET /api/v1/runs/current`; locked and completed nodes do not. The projection contains only encounter ID, visible title/body/prompt, and action IDs/labels. The canonical encounter catalog and action effects stay on the server. `POST /api/v1/runs/:runId/nodes/:nodeId/resolve` accepts the selected `encounter_id` and `action_id` for this node and rejects missing, mismatched, or unauthorized selections. The existing node-resolution transaction applies the selected effect, completes the node, unlocks direct successors while retaining other available nodes, increments player revision once, and finalizes the idempotency receipt. An exact-key retry replays the receipt. Re-querying the run returns persisted HP and frontier state.

Mountains Hazards use the same single-step interaction and receipt contract. Loose Scree randomly selects one participating unit, including units at zero HP, and damages it by up to 3 HP; Rockfall damages each participating unit by up to 2 HP. Damage cannot lower HP below zero. The server resolves the authored action, persists run HP, completes the node, and advances only its outgoing frontier edges in one transaction. The client receives finalized HP transitions and re-queries the current run; effect types and amounts are excluded from the current-run interaction projection.

Shrine has a validated node family and reuses the immediate participating-unit HP effects. Package 4 proves two distinct choices in a test-only encounter: restore the participating units to maximum HP or damage each participating unit by up to 2 HP. Neither Farm nor Mountains production generation contains a Shrine. The selected HP transitions, completed node, frontier change, revision, and receipt share one transaction. The browser receives the selected action and finalized HP transitions, while effect type and amount remain private in the interaction projection. RunScene renders multiple authorized actions from that generic projection. Currency grants remain governed by the event and reward-definition pipeline.

Chaos has a validated node family with one test-only authorized action and two weighted, server-private immediate HP outcomes. The server selects one outcome with a cryptographically secure integer roll, then records its stable outcome ID and resulting HP transitions in the finalized node receipt. An exact-key retry replays that receipt without another roll or HP mutation. The current-run interaction exposes only the encounter's visible action, and RunScene submits it through the same interaction path. Farm and Mountains contain no production Chaos node or encounter. Currency and other rewards continue through the event and reward-definition pipeline.

## Run-scoped modifiers

An authored `run_modifier` definition currently supports only `next_combat_attack_bonus`: a bounded positive Attack bonus and exactly one eligible combat. A validated test-only Shrine action creates a `run_modifiers` row containing the run, source node, authored modifier ID, and remaining eligible combats. Creation shares the node-resolution transaction and finalized receipt. Exact-key replay returns that receipt without creating another row or refreshing duration. No Farm or Mountains production node creates a modifier.

The next resolved Combat or Boss in that run applies the bonus to every participating player combatant while assembling the authoritative combat snapshot. The combat engine then uses those effective stats for deterministic damage. The same resolution transaction persists the battle and consumes the modifier row; its receipt lists applied modifier identities and zero remaining combats. Rejected or rolled-back resolutions leave the row unchanged, and unrelated non-combat nodes do not consume it. Chaos is a non-combat action, so it is not eligible. The modifier never updates permanent unit stats, inventory, or reward state.

The current-run read projects only active modifier ID, visible name/description, and remaining combat count. Authored tuning and effect configuration remain server-private. Terminal runs have no current-run projection; retained rows belong to their original run and cannot apply to a later run. Existing run cleanup removes those rows with the run.

## Combat Resolution Lifecycle
An available ordinary Combat node is finalized once its authoritative battle is persisted, regardless of outcome. Victory marks the node completed and unlocks only directly outgoing persisted edges whose destination is still locked. The server does not reconstruct the Farm sequence or unlock later descendants. Defeat or stalemate also completes the node, unlocks nothing, and transitions the run to terminal `failed`; its battle, graph, and terminal run HP remain durable.
