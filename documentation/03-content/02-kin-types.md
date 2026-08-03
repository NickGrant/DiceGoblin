---
Title: "Kin Type Catalog"
Status: Canonical
Last Updated: 2026-08-02
Owner: Content Design
Depends On:
  - documentation/03-content/00-content-source-map.md
  - documentation/02-systems/09-kin-reconstruction.md
Category: 03-content
Tags:
  - content
  - kin
  - lineages
---

# Kin Type Catalog

## Purpose

Define the canonical goblin kin types and the authored traits that distinguish them. Only kin listed in this document are part of the current content set.

Reconstruction, first-ownership behavior, reward eligibility, and stat-resolution formulas belong in system documentation. This document defines which kin exist and the modifiers associated with them.

## Scope

- Content category: Goblin kin types.
- Player-facing surfaces: Unit identity, Warband displays, Wrong Machine recipes, rewards, and Codex entries.
- Related system docs: Kin reconstruction, reward eligibility, unit generation, and stat resolution.

## Reading the Catalog

- Stat modifiers use `Attack / Defense / Max HP / Precision / Resolve` order.
- A value of `0` means the kin does not modify that stat.
- Content keys are stable identifiers used to connect this catalog to implementation and saved data.
- A kin may be both a unit identity and the output identity of a repeatable Wrong Machine recipe.

## Entries

| Key | Display name | Description | Stat modifiers | Gameplay identity | Current acquisition | Content status | Notes |
| --- | --- | --- | --- | --- | --- | --- | --- |
| `basic_goblin` | Basic Goblin | Baseline goblin stock with no specialized kin traits. | `0 / 0 / 0 / 0 / 0` | Neutral baseline with no stat adjustments. | Default unit kin and ordinary unit-grant eligibility. | Active | Remains viable and eligible after Pig Kin discovery. |
| `pig_kin` | Pig Kin | Stubborn farmyard goblin-kin with thicker hides and a less precise fighting style. | `0 / +1 / +2 / -1 / +1` | More durable and resolute, but less precise. | Repeatable `reconstruct_pig_kin` Wrong Machine recipe; ordinary unit grants after first ownership. | Active | First owned Pig Kin establishes Codex discovery and future reward eligibility. Every recipe completion still creates one Pig Kin unit. |

## Current Phase Boundary

Basic Goblin and Pig Kin are the complete current kin roster.

Planning references to Lizard Kin, Frog Kin, or later biome lineages are next-phase concepts. They are not current content, do not belong in current reward pools, and do not have current reconstruction recipes.

## Pig Kin Ownership Contract

- Pig Kin is not merely an account toggle; it is an owned-unit kin.
- The Wrong Machine recipe always creates one new Pig Kin unit.
- The first owned Pig Kin also records discovery, awards or derives the Codex entry, and enables Pig Kin for applicable future unit grants.
- Later recipe uses create additional Pig Kin units without replaying first-discovery effects.
- Existing units are not transformed into Pig Kin in the current phase.
- Pig Kin persists through promotion like any other unit kin.

## Open Questions

- None for the current Pig Kin phase.

## Maintenance Notes

- Adding a kin type requires adding it to this catalog and defining its identity, modifiers, acquisition, and progression association.
- Add a Wrong Machine recipe to the reconstruction system before presenting a kin as reconstructable.
- Content changes should update this catalog before or alongside implementation changes.
- A mismatch between this catalog and runtime data is implementation drift; it does not make an undocumented kin type canonical.
- Keep reconstruction, unlock conditions, reward weighting, generation rules, and stat-resolution formulas in their respective system documents.
