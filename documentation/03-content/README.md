---
Title: "Content Documentation"
Status: Canonical
Last Updated: 2026-08-23
Owner: Content Design
Depends On:
  - documentation/README.md
  - documentation/07-development-path/01-base-game-content-roster.md
  - documentation/07-development-path/02-night-expansion-content-roster.md
Category: 03-content
Tags:
  - content
---

# Content Documentation

## Purpose

Catalog concrete authored content such as units, kin, enemies, abilities, biomes, statuses, encounters, run effects, items, rewards, Codex entries, dialogue, and lore.

Canonical current content catalogs are the source of truth for what content exists now and for its authored values. Runtime data, seed data, and presentation code must remain consistent with them.

Approved future content rosters are maintained under `documentation/07-development-path/`. They own release allocation and high-level pairings, but do not make entries current until complete packages are promoted into these catalogs.

## Status Guidance

- `Canonical` current catalogs are the source of truth for active content in their scope.
- A canonical planning roster may own approved future allocation without creating runtime content.
- `Needs Review` documents are useful but should be verified before implementation decisions are made from them.
- `Legacy Reference` documents are preserved for history or comparison and do not override canonical docs.
- Categories marked `Interim` in the source map do not yet have a complete canonical catalog.

## Maintenance Principles

- Add or revise current content in its canonical catalog before or alongside implementation changes.
- Record approved future biome, enemy-family, and kin allocation in the base-game or Night roster before detailed implementation begins.
- Treat implementation mismatches as drift to be corrected, not as implicit content changes.
- Keep game rules and formulas in system documentation.
- Keep storage, APIs, migrations, and implementation paths in technical documentation.
- Define shared abilities and statuses once and reference those definitions from every content package that uses them.
- Keep generated behavior separate from authored entries: encounter placement is a system concern, while encounter formations and presentation are content.
- For dialogue, explicitly document spoken participants, effective repeatability, eligibility, choices, completion rewards, and Lore classification.

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
- `13-dialogue-and-lore.md`
- `TEMPLATE-catalog-entry.md`

## Approved Planning Rosters

- `documentation/07-development-path/01-base-game-content-roster.md`: ten standard base-game biomes, Mystic Cave and Library, native enemy families, associated kin, and working order.
- `documentation/07-development-path/02-night-expansion-content-roster.md`: Ruins, Meadow, Cemetery, and their Raccoon, Moth, and Crow pairings.

Planned entries must not enter current encounters, rewards, reconstruction, recruitment, Codex totals, or generation pools until promoted into the relevant current catalogs.

## Dice Content

Dice continue to use the existing size, rarity, material, and affix model. The current repository does not have a standalone canonical dice-content catalog; use runtime data and `documentation/02-systems/mvp-reference/01-dice-system.md` as supporting references until that catalog is written.

## Deferred Catalogs

- Dice definitions, materials, and affixes.

Approved future content remains deliberately incomplete until promoted.

## Child Folders

- None.
