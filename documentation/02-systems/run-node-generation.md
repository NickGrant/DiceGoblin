---
Title: "Run Node Generation"
Status: Canonical
Last Updated: 2026-09-13
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
