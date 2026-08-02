---
Title: "Content Documentation"
Status: Canonical
Last Updated: 2026-08-01
Owner: Content Design
Depends On:
  - documentation/README.md
Category: 03-content
Tags:
  - content
---

# Content Documentation

## Purpose

Catalog concrete authored content such as units, kin, enemies, abilities, biomes, statuses, encounters, run effects, items, rewards, and Codex entries.

Canonical content catalogs are the source of truth for what content exists and for its authored values. Runtime data, seed data, and presentation code must remain consistent with them.

## Status Guidance

- `Canonical` documents are the source of truth for their scope.
- `Needs Review` documents are useful but should be verified before implementation decisions are made from them.
- `Legacy Reference` documents are preserved for history or comparison and do not override canonical docs.
- Categories marked `Interim` in the source map do not yet have a complete canonical catalog.

## Maintenance Principles

- Add or revise content in its canonical catalog before or alongside implementation changes.
- Treat implementation mismatches as drift to be corrected, not as implicit content changes.
- Keep game rules and formulas in system documentation.
- Keep storage, APIs, migrations, and implementation paths in technical documentation.
- Define shared abilities and statuses once and reference those definitions from every content package that uses them.
- Keep generated behavior separate from authored entries: encounter placement is a system concern, while encounter formations and presentation are content.

## Documents

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
- `11-loot-and-reward-profiles.md`
- `12-codex-entries.md`
- `TEMPLATE-catalog-entry.md`

## Deferred Catalogs

- Dice definitions and affixes.
- Dialogue groups and lore unlocks.

## Child Folders

- None.
