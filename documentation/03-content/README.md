---
Title: "Content Documentation"
Status: Transitional vNext Reference
Last Updated: 2026-09-10
Owner: Content Design
Depends On:
  - documentation/07-development-path/vnext-authored-content-model.md
  - documentation/07-development-path/01-base-game-content-roster.md
Category: 03-content
Tags: [content, vnext]
---

# Content Documentation

Canonical vNext authored gameplay definitions are Git-tracked JSON. This folder intentionally does **not** retain detailed prototype catalogs just to preserve their values; Git history and implementation source can be consulted when the relevant milestone reauthors that domain.

## Current References
- `00-content-source-map.md` - how authored JSON, MySQL, PHP, and client-safe projection relate.
- `02-kin-types.md` - accepted high-level kin/reconstruction identity needed by the vNext plan.
- `06-biomes-and-regions.md` - accepted high-level opening-region identity and progression boundary.

Detailed prototype unit/enemy/ability/status/encounter/hazard/item/reward/dialogue/dice catalogs were removed from active vNext context because they mix implemented values, abandoned ideas, obsolete dependencies, or pre-vNext ownership rules. Their useful content should be deliberately recovered and reconciled into canonical JSON when its milestone arrives.

A removed catalog is not permission to invent replacement content. When implementation reaches that domain, inspect current game behavior and Git history as migration evidence, reconcile it against accepted vNext decisions, and author an explicit vNext definition.

Static secret gameplay data remains server-only; Phaser receives allowlisted public projections or player-authorized revealed data.
