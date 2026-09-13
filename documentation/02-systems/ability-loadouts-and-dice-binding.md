---
Title: "Ability Loadouts and Dice Binding"
Status: Canonical
Last Updated: 2026-09-13
Owner: Systems Design + Engineering
Depends On:
  - documentation/02-systems/combat-resolution.md
  - documentation/02-systems/dice-profiles-and-aspects.md
  - documentation/07-development-path/vnext-storage-model.md
Category: 02-systems
Tags: [systems, units, abilities, loadouts, dice]
---

# Ability Loadouts and Dice Binding

A unit's durable combat configuration has distinct concerns:
- permanently available/unlocked abilities;
- the equipped ordered ability loadout;
- exact owned dice assigned to ability slots.

Ability order matters to server-side combat scheduling. Authored ability definitions determine their timing/behavior; the player controls the valid ordered configuration offered by the game.

Dice are not a generic unit pool. The durable binding answers: **when Unit A uses Ability X, which exact owned die instance is rolled in each slot?** Conceptually this is persisted by unit + ability + slot -> dice instance.

One physical die instance may be bound to only one ability slot across the player's entire Warband. A complete replacement may move a die between slots on the unit being configured, but it must reject a die bound to another unit rather than stealing or implicitly reassigning it.

A complete loadout update should be submitted and validated atomically rather than assembled through a sequence of partially valid per-slot mutations.

The backend validates ownership, ability availability, slot eligibility, die ownership/eligibility, and active-run locks. Once a run begins, participating units' relevant loadout/order/dice configuration cannot be changed until the run terminates. Non-participating unit loadouts and participating unit display names remain editable.

Phaser may maintain an editable local draft while the player configures a unit, but the committed GameStore state changes only after the server accepts the complete configuration.
