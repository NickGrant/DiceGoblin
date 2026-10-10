---
Title: "Kin Type Design Reference"
Status: Transitional vNext Reference
Last Updated: 2026-10-10
Owner: Content Design
Depends On:
  - documentation/07-development-path/vnext-progression-state-model.md
  - documentation/07-development-path/01-base-game-content-roster.md
Category: 03-content
Tags: [content, kin, vnext]
---

# Kin Type Design Reference

Kin are restored goblin forms associated with creature families. The durable account concept is a unique kin unlock; individual owned units also carry their kin identity.

## Implemented Definitions

Basic Goblin, Pig Kin, and Lizard Kin are implemented in `backend/content/kin/goblins.json`. That JSON owns their stable IDs, presentation, traits, and exact stat modifiers. The Pig and Lizard reconstruction recipes, materials, and Kin unlocks are authored in `backend/content`; restored Kin ownership is recorded once in the player-scoped `user_unlocks` table.

## Planned Opening Allocation

- Basic Goblin - neutral/default goblin identity (implemented).
- Pig Kin - associated with the Farm/pigs (implemented).
- Lizard Kin - associated with Mountains/kobolds (implemented).
- Frog Kin - associated with Swamps/frogmen (planned).

The complete planned base-game family/kin allocation is in `07-development-path/01-base-game-content-roster.md`. Later entries are planning allocation, not automatically implemented content.

## vNext Reconstruction Rule
When the Wrong Machine targets a kin not yet unlocked, successful reconstruction grants the kin unlock and creates a random unit of that kin from currently unlocked unit types. When the kin is already unlocked, the player may reconstruct an exact combination of that kin and an unlocked unit type.

There is no generic "first kin" flag and no separate first-ownership progression record. Kin unlock ownership is the durable capability truth.

Pig and Lizard recipes each require permanent Wrong Machine access, 5 Raw Chaos, 3 lineage materials, and 1 boss catalyst for either mode. The recipe JSON owns these costs and the selection rule; the server read contract derives current availability from the player's unlocks, wallet, inventory, and unlocked unit types.

`POST /api/v1/wrong-machine/reconstruct` accepts a recipe ID, expected mode, expected Raw Chaos price, expected ingredient quantities, and a unit type ID only for repeat reconstruction. It requires the shared `Idempotency-Key` and CSRF conventions. The server locks player state, checks authoritative Kin ownership and requirements, consumes resources, grants the first Kin unlock, creates one normal active unit, advances player revision once, and commits a finalized receipt together. First restoration draws only from currently unlocked unit types inside this transaction; exact retries replay the stored unit and result. A changed mode or cost returns a refresh conflict. An existing run keeps its participating-unit snapshot; a newly reconstructed unit does not join that run.

The item catalog authors victory drops by source region, encounter kind, and enemy unit type. The vNext run resolver grants materials inside the node-resolution transaction on victory: Farm mud combat grants one Pig Ear, the Mudking boss grants two Pig Ears and one crown fragment, Mountains Kobold combat grants one Kobold Scale, and the Chief Engineer boss grants one lens. The reward resolver remains authoritative; the browser receives only the resulting inventory and resolution facts.
