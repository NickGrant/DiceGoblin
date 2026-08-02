---
Title: "Content Source Map"
Status: Canonical
Last Updated: 2026-08-01
Owner: Content Design + Engineering
Depends On:
  - documentation/03-content/README.md
  - documentation/05-technical/09-seed-catalog-ownership.md
Category: 03-content
Tags:
  - content
  - source-map
---

# Content Source Map

## Purpose

Identify the canonical document for each category of authored game content. Where a canonical catalog has not yet been written, this map records temporary implementation references that must be reconciled when that catalog is created.

Once a category has a canonical content document, implementation files are consumers of that document rather than competing sources of truth.

## Content Categories

| Category | Canonical content document | Status | Notes |
| --- | --- | --- | --- |
| Biomes and regions | Not yet created | Interim | Current region data must be reviewed when the biome and region catalog is authored. |
| Unit types | `documentation/03-content/01-unit-types.md` | Canonical | Defines current unit identities, roles, stats, growth, and native ability packages. |
| Kin types | `documentation/03-content/02-kin-types.md` | Canonical | Defines the complete current kin set and each kin's stat identity. |
| Enemy types | `documentation/03-content/03-enemy-types.md` | Canonical | Defines current enemy identities, factions, stats, rewards, and native ability packages. |
| Unit and shared abilities | `documentation/03-content/04-unit-abilities.md` | Canonical | Defines current player abilities and the shared abilities that retain the same behavior when assigned to enemies. |
| Enemy-exclusive abilities | `documentation/03-content/05-enemy-abilities.md` | Canonical | Defines faction-specific enemy abilities and records current enemy use of shared abilities. |
| Dice definitions and affixes | Not yet created | Interim | Existing implementation data must be reconciled when separate dice and affix catalogs are authored. |
| Items and consumables | Not yet created | Interim | The future catalog should distinguish progression materials, catalysts, healing items, and energy items. |
| Encounters | Not yet created | Interim | The future catalog should identify combat, boss, loot, rest, dialogue, hazard, shrine, and chaos encounters. |
| Hazards and shrines | Not yet created | Interim | Current behavior is summarized in system documentation, but individual content entries still need a catalog. |
| Dialogue and lore unlocks | Not yet created | Interim | A complete catalog requires narrative review and should not be inferred only from implementation keys. |
| Codex entries | Not yet created | Interim | Catalog ownership should be established after the Codex Discovery Reward Rework settles the content model. |

## Interim Implementation References

These references help locate existing content while canonical catalogs are still missing. They are discovery aids, not durable content authority.

| Category | Temporary references |
| --- | --- |
| Biomes and regions | Region seed data, run graph generation, and frontend region presentation data. |
| Dice definitions and affixes | Dice definition, rarity, material, and affix seed data. |
| Items and consumables | Item definitions, reward grants, and consumable services. |
| Encounters | Encounter definitions and run generation services. |
| Hazards and shrines | Encounter primitive definitions and related system documentation. |
| Dialogue and lore unlocks | Dialogue scripts, dialogue services, and run graph generation. |
| Codex entries | Codex ownership services and current discovery reward work. |

## Catalog Writing Rules

- Canonical catalogs define concrete authored content: identity, display name, classification, authored values, and intended player-facing role.
- Implementation paths, storage details, migrations, and API contracts belong in technical documentation.
- Rules, formulas, targeting, generation behavior, and progression mechanics belong in system documentation.
- New content should be added to its canonical catalog before or alongside implementation work.
- When implementation and a canonical catalog disagree, treat the mismatch as implementation drift and resolve it explicitly.

## Review Boundary

A category remains `Interim` until its existing implementation data, player-facing copy, and relevant design decisions have been reconciled into a canonical catalog.
