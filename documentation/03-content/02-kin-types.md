---
Title: "Kin Type Catalog"
Status: Canonical
Last Updated: 2026-08-02
Owner: Content Design
Depends On:
  - documentation/03-content/00-content-source-map.md
  - documentation/02-systems/09-kin-reconstruction.md
  - documentation/07-development-path/01-base-game-content-roster.md
  - documentation/07-development-path/02-night-expansion-content-roster.md
Category: 03-content
Tags:
  - content
  - kin
  - lineages
---

# Kin Type Catalog

## Purpose

Define the canonical current goblin kin types and the authored traits that distinguish them. Only kin in the current-entry table are active content.

Reconstruction, first-ownership behavior, reward eligibility, and stat-resolution formulas belong in system documentation. This document defines which current kin exist and the modifiers associated with them.

Approved future kin names and biome relationships are recorded separately from current entries so planning decisions do not create incomplete runtime content.

## Scope

- Content category: Goblin kin types.
- Player-facing surfaces: Unit identity, Warband displays, Wrong Machine recipes, rewards, recruitment, and Codex entries.
- Related system docs: Kin reconstruction, reward eligibility, unit generation, and stat resolution.

## Reading the Catalog

- Stat modifiers use `Attack / Defense / Max HP / Precision / Resolve` order.
- A value of `0` means the kin does not modify that stat.
- Content keys are stable identifiers used to connect this catalog to implementation and saved data.
- A kin may be both a unit identity and the output identity of a repeatable Wrong Machine recipe.
- Approved planning names without keys, modifiers, acquisition, and mechanics are not current kin definitions.

## Current Entries

| Key | Display name | Description | Stat modifiers | Gameplay identity | Current acquisition | Content status | Notes |
| --- | --- | --- | --- | --- | --- | --- | --- |
| `basic_goblin` | Basic Goblin | Baseline goblin stock with no specialized kin traits. | `0 / 0 / 0 / 0 / 0` | Neutral baseline with no stat adjustments. | Default unit kin and ordinary unit-grant eligibility. | Active | Remains viable and eligible after Pig Kin discovery. |
| `pig_kin` | Pig Kin | Stubborn farmyard goblin-kin with thicker hides and a less precise fighting style. | `0 / +1 / +2 / -1 / +1` | More durable and resolute, but less precise. | Repeatable `reconstruct_pig_kin` Wrong Machine recipe; ordinary unit grants after first ownership. | Active | First owned Pig Kin establishes Codex discovery and future reward eligibility. Every recipe completion still creates one Pig Kin unit. |

## Current Phase Boundary

Basic Goblin and Pig Kin are the complete current kin roster.

Later base-game and Night kin are approved planning concepts only. They are not current content, do not belong in current reward pools, and do not have current reconstruction recipes.

## Pig Kin Ownership Contract

- Pig Kin is not merely an account toggle; it is an owned-unit kin.
- The Wrong Machine recipe always creates one new Pig Kin unit.
- The first owned Pig Kin also records discovery, awards or derives the Codex entry, and enables Pig Kin for applicable future unit grants.
- Later recipe uses create additional Pig Kin units without replaying first-discovery effects.
- Existing units are not transformed into Pig Kin in the current phase.
- Pig Kin persists through promotion like any other unit kin.

## Approved Base-Game Planning Roster

| Associated biome | Planned kin | Planning status |
| --- | --- | --- |
| The Farm | Pig Kin | Current |
| Mountains | Lizard Kin | Approved post-demo planning |
| Swamps | Frog Kin | Approved post-demo planning |
| Island | Monkey Kin | Approved post-demo planning |
| Tundra | Walrus Kin | Approved post-demo planning |
| Volcano | Salamander Kin | Approved post-demo planning |
| Wasteland | Vulture Kin | Approved post-demo planning |
| River | Otter Kin | Approved post-demo planning |
| Orchard | Wasp Kin | Approved post-demo planning |
| Savanna | Hyena Kin | Approved post-demo planning |

Mystic Cave establishes Basic Goblin and is not another enemy-derived kin recipe. The Library has no standard enemy-derived kin pairing currently approved.

The full roster identity and sequence are owned by `documentation/07-development-path/01-base-game-content-roster.md`.

## Night Expansion Planning Roster

| Associated biome | Planned kin | Planning status |
| --- | --- | --- |
| Ruins | Raccoon Kin | Reserved for Night |
| Meadow | Moth Kin | Reserved for Night |
| Cemetery | Crow Kin | Reserved for Night |

These kin are excluded from base-game reward, recruitment, reconstruction, and Codex pools. The allocation is owned by `documentation/07-development-path/02-night-expansion-content-roster.md`.

## Promotion Requirements

A planned kin becomes current only after this catalog defines at least:

- a stable key and display name;
- description and gameplay identity;
- complete stat modifiers or equivalent mechanical contract;
- acquisition and reconstruction relationship;
- first-ownership, reward, and recruitment eligibility;
- Codex presentation and art direction;
- synchronization with its biome, enemy family, items, and rewards.

A name in an approved planning roster is not sufficient for runtime use.

## Open Questions

- Mechanical identities, modifiers, recipes, materials, catalysts, and traversal roles for all post-Pig and Night kin remain future design work.

## Maintenance Notes

- Adding a current kin requires adding a complete entry to the current table.
- Add a Wrong Machine recipe to the reconstruction system before presenting a kin as reconstructable.
- Update the approved roster documents before changing future biome-kin allocation.
- Content changes should update this catalog before or alongside implementation changes.
- A mismatch between this catalog and runtime data is implementation drift; it does not make an undocumented kin current.
- Keep reconstruction, first-ownership behavior, reward weighting, generation rules, and stat-resolution formulas in their respective system documents.
- Keep Mountains and Swamps plural when naming their associated regions.
