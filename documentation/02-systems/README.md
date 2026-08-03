---
Title: "Systems Documentation"
Status: Canonical
Last Updated: 2026-08-02
Owner: Systems Design + Engineering
Depends On:
  - documentation/README.md
Category: 02-systems
Tags:
  - systems
---

# Systems Documentation

## Purpose

- Define how implemented and target-state game systems work, including combat, runs, loot, units, progression, hazards, dice, kin reconstruction, and multiplayer concepts.
- Separate current behavior from canonical replacement models and legacy MVP references.

## Status Guidance

- `Canonical` documents are the default source of truth for their scope.
- A canonical target-state document may define required migration direction while implementation remains in transition.
- `Needs Review` documents are useful but should be verified before making implementation decisions from them.
- `Legacy Reference` documents are preserved for history, migration context, or comparison and do not override canonical docs.

## Documents

- `00-current-system-index.md`
- `01-unit-naming.md`
- `02-unit-stat-advancement.md`
- `03-combat-resolution.md`
- `04-dialogue-flow-determination.md`
- `05-run-node-generation.md`
- `06-loot-determination.md`
- `07-hazard-severity-and-downsides.md`
- `08-dice-material-model.md`
- `09-kin-reconstruction.md`

## Reading Guidance

- Start with `00-current-system-index.md` to determine whether a document describes implemented behavior or an approved target state.
- Read `08-dice-material-model.md` before legacy dice or affix references.
- Read `09-kin-reconstruction.md` before legacy lineage-unlock or one-time reconstruction descriptions.
- Pair system contracts with `documentation/05-technical/` when storage, API, or frontend-state behavior matters.

## Child Folders

- `multiplayer/`
- `mvp-reference/`
