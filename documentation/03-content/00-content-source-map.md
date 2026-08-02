---
Title: "Content Source Map"
Status: Canonical
Last Updated: 2026-08-02
Owner: Content Design + Engineering
Depends On:
  - documentation/03-content/README.md
  - documentation/02-systems/08-dice-material-model.md
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
| Unit types | `documentation/03-content/01-unit-types.md` | Canonical | Defines current unit identities, roles, stats, growth, and native ability packages. |
| Kin types | `documentation/03-content/02-kin-types.md` | Canonical | Defines the complete current kin set and each kin's stat identity. |
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
| Dice materials | Not yet created | Interim | The canonical target rules are defined in `documentation/02-systems/08-dice-material-model.md`; a material catalog must still define the concrete roster, rarity, effects, allowed sizes, stacking, valuation, and presentation. |

## Retired Target Content Model

Permanent die affixes are not a target-state content category.

Existing affix definitions, rarity-based affix capacity, and per-instance affix records are legacy migration inputs. Their effects must eventually be converted into materials, merged into another material, moved to another system, or removed according to the Dice Material Identity and Generation model.

Temporary run effects and global feature rules may still modify dice, but they are not permanent affixes attached to die instances.

## Interim Implementation References

These references help locate existing content while canonical catalogs are still missing. They are discovery aids, not durable content authority.

| Category | Temporary references |
| --- | --- |
| Dice materials | Legacy dice definitions, rarity and affix data, current combat and inventory behavior, and the canonical target rules in `documentation/02-systems/08-dice-material-model.md`. |

## Catalog Writing Rules

- Canonical catalogs define concrete authored content: identity, display name, classification, authored values, and intended player-facing role.
- Implementation paths, storage details, migrations, and API contracts belong in technical documentation.
- Rules, formulas, targeting, generation behavior, and progression mechanics belong in system documentation.
- New content should be added to its canonical catalog before or alongside implementation work.
- When implementation and a canonical catalog disagree, treat the mismatch as implementation drift and resolve it explicitly.
- Cross-reference shared content rather than creating competing definitions in multiple catalogs.
- Dialogue entries must explicitly identify participants, effective repeatability, and Lore classification rather than relying on storage flags.
- Dice material entries must explicitly identify rarity, effect, allowed sizes, stacking behavior, valuation, and enabled state rather than relying on independent rarity or affix records.

## Review Boundary

A category remains `Interim` until its existing implementation data, player-facing copy, and relevant design decisions have been reconciled into a canonical catalog.
