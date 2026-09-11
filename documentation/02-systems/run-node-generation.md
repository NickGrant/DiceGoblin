---
Title: "Run Node Generation"
Status: Canonical
Last Updated: 2026-09-10
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
