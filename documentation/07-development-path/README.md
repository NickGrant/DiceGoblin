---
Title: "Development Path Documentation"
Status: Canonical
Last Updated: 2026-09-10
Owner: Product
Depends On:
  - documentation/README.md
Category: 07-development-path
Tags:
  - development-path
---

# Development Path Documentation

## Purpose

Roadmaps, milestone direction, release planning, approved future content rosters, expansion thinking, overhaul decisions, and change history.

## Status Guidance

- `Canonical` documents are the default source of truth for their scope.
- `Active Implementation Plan` documents coordinate work currently in progress and defer domain details to accepted/canonical documents where stated.
- `Accepted` vNext decision documents are authoritative for their defined overhaul scope until their durable concepts are reconciled into canonical system/technical documentation.
- A canonical planning document can own an approved future roster without making that content current or implemented.
- `Needs Review` documents are useful but should be verified before making implementation decisions from them.
- `Legacy Reference` documents are preserved for history, migration context, or comparison and do not override canonical or accepted docs.

## vNext Overhaul

The vNext overhaul is coordinated by:

- `vnext-game-overhaul.md` - primary implementation roadmap and milestone status tracker.

Accepted domain/architecture decisions:

- `vnext-reward-unlock-model.md` - event, reward, grant, duplicate, and unlock semantics.
- `vnext-currency-economy-model.md` - Teeth, Raw Chaos, and economy roles.
- `vnext-energy-model.md` - Energy regeneration, overcharge, and run-start spending.
- `vnext-progression-state-model.md` - unlocks, Codex, objectives/bounties, dialogue knowledge, and progression-state boundaries.
- `vnext-authored-content-model.md` - Git-tracked JSON ownership, stable IDs, registry, and validation.
- `vnext-storage-model.md` - clean MySQL runtime/player-state model, accounts, collections, squads, runs, retention, and lifecycle storage.
- `vnext-api-contract-model.md` - persistent-Phaser API philosophy, bootstrap, queries/commands, revisions, and idempotency.
- `vnext-endpoint-inventory.md` - accepted starting route inventory and endpoint responsibilities.
- `vnext-backend-internal-architecture.md` - controller/application/domain/repository/content-registry boundaries and transaction ownership.
- `vnext-phaser-client-architecture.md` - Angular/Phaser boundary, scenes/screens, cache, content exposure, responsive landscape rendering, assets, audio, and visual testing.

During implementation, the dedicated accepted document is authoritative when a summarized statement in the roadmap is less detailed. Material architectural changes should update the relevant accepted decision rather than drifting silently in implementation.

After vNext stabilizes, durable choices must be reconciled into canonical `05-technical` and relevant `02-systems` documentation. The `07-development-path` vNext documents are an implementation decision record, not intended to remain a permanent parallel source of truth.

## Other Documents

- `00-gameplay-systems-roadmap.md`
- `01-base-game-content-roster.md`
- `02-night-expansion-content-roster.md`
- `2026-07-25-completion-analysis.md`
- `2026-07-25-roadmap.md`
- `2026-07-30-first-pig-kin-demo-roadmap.md`
- `2026-08-19-figma-architecture-audit-plan.md`
- `CHANGELOG.md`

## Content Planning Authority

- `01-base-game-content-roster.md` owns the approved ten standard base-game biomes, the Mystic Cave and Library special-biome boundary, and the associated enemy-family and kin pairings.
- `02-night-expansion-content-roster.md` owns the three biomes, enemy families, and kin reserved for the first expansion, **Night**.
- Current playable content remains owned by the catalogs under `documentation/03-content/` until the relevant content is promoted/reconciled for vNext implementation.
- Completing the vNext technical overhaul does not itself require shipping the entire approved future base-game roster.

## Child Folders

- None.
