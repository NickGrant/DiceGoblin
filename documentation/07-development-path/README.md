---
Title: "Development Path Documentation"
Status: Canonical
Last Updated: 2026-09-10
Owner: Product + Engineering
Depends On:
  - documentation/README.md
Category: 07-development-path
Tags: [development-path, vnext]
---

# Development Path Documentation

## Active vNext Plan
- `vnext-game-overhaul.md` - primary implementation roadmap/status tracker.
- `vnext-prototype-code-disposition.md` - active migration/reuse guide for deciding what prototype code to keep, adapt, rebuild, or retire as each vNext slice replaces it.

## Accepted vNext Decisions
- `vnext-reward-unlock-model.md`
- `vnext-currency-economy-model.md`
- `vnext-energy-model.md`
- `vnext-progression-state-model.md`
- `vnext-authored-content-model.md`
- `vnext-storage-model.md`
- `vnext-api-contract-model.md`
- `vnext-endpoint-inventory.md`
- `vnext-backend-internal-architecture.md`
- `vnext-phaser-client-architecture.md`

The dedicated accepted decision is authoritative for its scope. Material architecture changes update that document instead of drifting silently in implementation. The prototype disposition file is subordinate to these decisions: it guides migration/reuse but does not preserve prototype behavior that conflicts with accepted vNext architecture.

## Planned Content Scope
- `01-base-game-content-roster.md` owns the approved high-level base-game biome/enemy/kin allocation. It does not make every planned biome part of the vNext overhaul implementation scope.

Historical roadmaps, demo plans, audits, changelogs, and expansion planning were removed from the active vNext tree. Git history is the archive.

After vNext stabilizes, durable decisions move into canonical system/technical docs and transitional decision/migration records may be retired.
