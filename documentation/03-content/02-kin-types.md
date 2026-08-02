---
Title: "Kin Type Catalog"
Status: Canonical
Last Updated: 2026-08-01
Owner: Content Design
Depends On:
  - documentation/03-content/00-content-source-map.md
Category: 03-content
Tags:
  - content
  - kin
  - lineages
---

# Kin Type Catalog

## Purpose

Catalog the goblin kin types currently recognized as game content. The implementation stores these records in `splice_variants`, but the player-facing concept is kin.

## Scope

- Content category: Goblin kin types.
- Current implementation source: `backend/migrations/63_seed_splice_variants.sql` and `backend/migrations/74_seed_pig_kin_variant.sql`.
- Player-facing surface: Wrong Machine results, unit identity, Warband displays, rewards, and Codex entries.
- Related system docs: Wrong Machine, kin unlock, reward eligibility, and unit stat resolution documentation.

## Reading the Catalog

- Stat modifiers use `Attack / Defense / Max HP / Precision / Resolve` order.
- Grant weight is implementation metadata and does not by itself determine whether a kin type is currently eligible from every reward source.
- This catalog intentionally recognizes only Basic Goblin and Pig Kin as current kin types.

## Entries

| Key | Display name | Description | Stat modifiers | Passive summary | Grant weight | Primary seed | Player-facing status | Notes |
| --- | --- | --- | --- | --- | ---: | --- | --- | --- |
| `basic_goblin` | Basic Goblin | Baseline goblin stock with no splice tendency. | `0 / 0 / 0 / 0 / 0` | No splice modifier. | 60 | `63_seed_splice_variants.sql` | Current | Default goblin kin and baseline comparison point. |
| `pig_kin` | Pig Kin | Stubborn farmyard goblin-kin with a thicker hide and a slightly slower hand. | `0 / +1 / +2 / -1 / +1` | +1 Defense, +2 HP, +1 Resolve, -1 Precision. | 12 | `74_seed_pig_kin_variant.sql` | Current | Farm-associated unlock and progression kin. |

## Legacy Implementation Records

Migration 63 also seeds the following `splice_variants` records:

| Key | Display name | Migration state | Content status | Notes |
| --- | --- | --- | --- | --- |
| `rat_splice` | Rat-Spliced | Seeded with `is_enabled = 1` | Not a current kin type | Retained as an older splice implementation record. |
| `toad_splice` | Toad-Spliced | Seeded with `is_enabled = 1` | Not a current kin type | Retained as an older splice implementation record. |
| `bat_splice` | Bat-Spliced | Seeded with `is_enabled = 1` | Not a current kin type | Retained as an older splice implementation record. |

These records should not be added to player-facing kin catalogs solely because they remain present in migration history. Reintroducing one as a current kin type requires an explicit content decision and corresponding documentation update.

## Open Questions

- The database seed state still marks the three legacy splice records as enabled. Their runtime eligibility should remain governed by the current reward and unlock rules until the storage model is cleaned up or the content is formally reintroduced.

## Maintenance Notes

- Treat the content decision in this catalog as authoritative for which records are current kin types.
- Update the catalog when a kin type is formally introduced, removed, renamed, or receives new stat modifiers.
- Keep reward weighting, unlock conditions, and stat-resolution formulas in their respective system documents.
