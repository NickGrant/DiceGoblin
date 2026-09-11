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

vNext canonical authored gameplay definitions are Git-tracked JSON. This folder temporarily retains only Markdown catalogs that provide useful design/content input while those JSON definitions are created milestone by milestone.

These Markdown files are **not** a second runtime catalog. When a vNext JSON domain is implemented, reconcile the retained design information into JSON and either reduce the Markdown file to human-facing guidance or remove it.

## Retained References
- `00-content-source-map.md`
- `01-unit-types.md`
- `02-kin-types.md`
- `03-enemy-types.md`
- `04-unit-abilities.md`
- `05-enemy-abilities.md`
- `06-biomes-and-regions.md`
- `07-status-effects.md`
- `08-encounter-templates.md`
- `09-hazards-and-shrines.md`
- `10-items-and-consumables.md`

Prototype reward, Codex, dialogue, and material-only dice catalogs were removed because they conflict with accepted vNext models. Those domains should be reauthored when their milestone is reached rather than copied forward blindly.

Static secret gameplay data must remain server-only; Phaser receives only allowlisted public projections or player-authorized revealed data.
