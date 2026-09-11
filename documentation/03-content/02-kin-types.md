---
Title: "Kin Type Design Reference"
Status: Transitional vNext Reference
Last Updated: 2026-09-10
Owner: Content Design
Depends On:
  - documentation/07-development-path/vnext-progression-state-model.md
  - documentation/07-development-path/01-base-game-content-roster.md
Category: 03-content
Tags: [content, kin, vnext]
---

# Kin Type Design Reference

Kin are restored goblin forms associated with creature families. The durable account concept is a unique kin unlock; individual owned units also carry their kin identity.

## Opening Roster
- Basic Goblin - neutral/default goblin identity.
- Pig Kin - associated with the Farm/pigs.
- Lizard Kin - associated with Mountains/kobolds.
- Frog Kin - associated with Swamps/frogmen.

The complete planned base-game family/kin allocation is in `07-development-path/01-base-game-content-roster.md`. Later entries are planning allocation, not automatically implemented content.

## vNext Reconstruction Rule
When the Wrong Machine targets a kin not yet unlocked, successful reconstruction grants the kin unlock and creates a random unit of that kin from currently unlocked unit types. When the kin is already unlocked, the player may reconstruct an exact combination of that kin and an unlocked unit type.

There is no generic "first kin" flag and no separate first-ownership progression record. Kin unlock ownership is the durable capability truth.

Exact stat modifiers, recipes/costs, presentation, and reward eligibility must be authored in canonical JSON when each kin enters implementation. Prototype Pig Kin recipes and first-ownership behavior are not automatically carried forward.
