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

Define the canonical goblin kin types and the authored traits that distinguish them. Only kin listed in this document are part of the current content set.

Unlock behavior, reward eligibility, and stat-resolution formulas belong in system documentation. This document defines which kin exist and the modifiers associated with them.

## Scope

- Content category: Goblin kin types.
- Player-facing surfaces: Unit identity, Warband displays, kin unlocks, rewards, and Codex entries.
- Related system docs: Kin unlocking, reward eligibility, unit generation, and stat resolution.

## Reading the Catalog

- Stat modifiers use `Attack / Defense / Max HP / Precision / Resolve` order.
- A value of `0` means the kin does not modify that stat.
- Content keys are stable identifiers used to connect this catalog to implementation and saved data.

## Entries

| Key | Display name | Description | Stat modifiers | Gameplay identity | Content status | Notes |
| --- | --- | --- | --- | --- | --- | --- |
| `basic_goblin` | Basic Goblin | Baseline goblin stock with no specialized kin traits. | `0 / 0 / 0 / 0 / 0` | Neutral baseline with no stat adjustments. | Active | Default kin and comparison point for other kin types. |
| `pig_kin` | Pig Kin | Stubborn farmyard goblin-kin with thicker hides and a less precise fighting style. | `0 / +1 / +2 / -1 / +1` | More durable and resolute, but less precise. | Active | Associated with progression through The Farm. |

## Open Questions

- None.

## Maintenance Notes

- Adding a kin type requires adding it to this catalog and defining its identity, modifiers, and progression association.
- Content changes should update this catalog before or alongside implementation changes.
- A mismatch between this catalog and runtime data is implementation drift; it does not make an undocumented kin type canonical.
- Keep unlock conditions, reward weighting, generation rules, and stat-resolution formulas in their respective system documents.
