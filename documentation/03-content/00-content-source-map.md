---
Title: "Content Source Map"
Status: Canonical
Last Updated: 2026-08-23
Owner: Content Design + Engineering
Depends On:
  - documentation/03-content/README.md
  - documentation/05-technical/09-seed-catalog-ownership.md
  - documentation/07-development-path/01-base-game-content-roster.md
  - documentation/07-development-path/02-night-expansion-content-roster.md
Category: 03-content
Tags:
  - content
  - source-map
---

# Content Source Map

## Purpose

Identify the canonical document for each category of authored game content. Where a canonical catalog has not yet been written, this map records temporary implementation references that must be reconciled when that catalog is created.

Once a category has a canonical content document, implementation files are consumers of that document rather than competing sources of truth.

## Current Content Categories

| Category | Canonical content document | Status | Notes |
| --- | --- | --- | --- |
| Unit types | `documentation/03-content/01-unit-types.md` | Canonical | Defines current unit identities, roles, stats, growth, and native ability packages. |
| Kin types | `documentation/03-content/02-kin-types.md` | Canonical | Defines the complete current kin set and each current kin's stat identity. |
| Enemy types | `documentation/03-content/03-enemy-types.md` | Canonical | Defines current enemy identities, factions, stats, rewards, and native ability packages. |
| Unit and shared abilities | `documentation/03-content/04-unit-abilities.md` | Canonical | Defines current player abilities and shared abilities used by enemies. |
| Enemy-exclusive abilities | `documentation/03-content/05-enemy-abilities.md` | Canonical | Defines faction-specific enemy abilities and records enemy use of shared abilities. |
| Biomes and regions | `documentation/03-content/06-biomes-and-regions.md` | Canonical | Defines current playable regions, progression order, native factions, bosses, themes, and completion rewards. |
| Status effects | `documentation/03-content/07-status-effects.md` | Canonical | Defines player-visible buffs, debuffs, stack conditions, durations, and authored effects. |
| Encounter templates | `documentation/03-content/08-encounter-templates.md` | Canonical | Defines current combat, boss, loot, rest, and chaos encounter formations and presentation. |
| Hazards and shrines | `documentation/03-content/09-hazards-and-shrines.md` | Canonical | Defines current generated run hazards and shrine favors, including selection values and outcomes. |
| Items and consumables | `documentation/03-content/10-items-and-consumables.md` | Canonical | Defines current progression materials, catalysts, healing items, and energy items. |
| Loot and reward profiles | `documentation/03-content/11-loot-and-reward-profiles.md` | Canonical | Defines active node-and-outcome reward chances, guarantees, currency, and item grants. |
| Codex entries | `documentation/03-content/12-codex-entries.md` | Canonical | Defines current Codex categories, eligible keys, and discovery conditions. |
| Dialogue and lore | `documentation/03-content/13-dialogue-and-lore.md` | Canonical | Defines current scripts, participants, placement, eligibility, repeatability, choices, completion rewards, and Lore classification. |
| Dice definitions, materials, and affixes | Not yet created | Interim | Existing dice definition, rarity, material, and affix data must be reconciled when a canonical dice-content catalog is authored. |

## Approved Future Roster Sources

Approved planning content is deliberately separated from current content catalogs.

| Planning scope | Canonical planning document | What it owns | Current-content effect |
| --- | --- | --- | --- |
| Base game | `documentation/07-development-path/01-base-game-content-roster.md` | Ten standard biomes, Mystic Cave and Library special-biome roles, native enemy families, associated kin, and working order. | None until each entry is promoted into the current catalogs. |
| First expansion: Night | `documentation/07-development-path/02-night-expansion-content-roster.md` | Ruins/Raccoons/Raccoon Kin, Meadow/Moths/Moth Kin, and Cemetery/Crows/Crow Kin. | Excluded from base-game runtime and current Codex counts until expansion activation. |

A future name or pairing may be approved planning without having current stats, keys, abilities, encounters, recipes, rewards, or art. Runtime generation must use only current catalog entries.

## Interim Implementation References

These references help locate existing content while canonical catalogs are still missing. They are discovery aids, not durable content authority.

| Category | Temporary references |
| --- | --- |
| Dice definitions, materials, and affixes | Dice definition, rarity, material, and affix data plus current combat, inventory, reward, and Codex behavior. `documentation/02-systems/mvp-reference/01-dice-system.md` remains a useful supporting contract. |

## Dice Content Boundary

The current dice model retains separate permanent identity layers for die size/rarity, material, and affixes. Materials do not replace affixes, and affix capacity remains part of the existing rarity-driven dice model until a future change is deliberately approved.

The active alpha-launch combat sizes remain `d4`, `d6`, `d8`, and `d10` as documented in `documentation/02-systems/mvp-reference/01-dice-system.md`.

## Catalog Writing Rules

- Canonical current catalogs define concrete authored content: identity, display name, classification, authored values, and intended player-facing role.
- Canonical planning rosters define approved future allocation but do not make entries current.
- Implementation paths, storage details, migrations, and API contracts belong in technical documentation.
- Rules, formulas, targeting, generation behavior, and progression mechanics belong in system documentation.
- New content should be added to its current canonical catalog before or alongside implementation work.
- When implementation and a current canonical catalog disagree, treat the mismatch as implementation drift and resolve it explicitly.
- Cross-reference shared content rather than creating competing definitions in multiple catalogs.
- Dialogue entries must explicitly identify participants, effective repeatability, and Lore classification rather than relying on storage flags.

## Review Boundary

A category is current and canonical only after its implementation data, player-facing copy, and relevant design decisions have been reconciled into a complete catalog. Dice content remains `Interim`; implementation data is still the primary evidence for its concrete material and affix roster.

The approved base-game and Night rosters are canonical only for future content allocation. They do not bypass the content-promotion requirements of the current catalogs.
