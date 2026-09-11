---
Title: "Dice Profiles, Materials, and Aspects"
Status: Canonical
Last Updated: 2026-09-10
Owner: Systems Design + Engineering
Depends On:
  - documentation/07-development-path/vnext-storage-model.md
  - documentation/07-development-path/vnext-authored-content-model.md
Category: 02-systems
Tags: [systems, dice, profiles, materials, aspects]
---

# Dice Profiles, Materials, and Aspects

## Identity
An owned die is a mutable instance pointing at an authored dice profile.

A profile composes:
- stable profile ID;
- material ID;
- explicit rarity;
- zero or more aspect IDs;
- allowed/effective die sizes.

Material does not determine rarity. Aspects are independent authored modifiers/effects and may restrict die-size eligibility. Basic dice are represented by valid profiles with no aspects rather than by missing profile data.

## Instances
Runtime dice instances persist identity/ownership, die size, profile ID, and lifecycle status. Material, rarity, and aspects are resolved from the profile rather than duplicated onto the instance.

## Eligibility
A profile is valid only for sizes allowed by its material and all included aspects. Content validation must reject impossible combinations before they reach players.

## Combat
When an ability uses a die, combat resolves the profile's material and aspect behavior using authoritative deterministic combat state. Phaser may present profile information the player is permitted to know but never becomes the authoritative effect engine.

## Authoring
Profiles, materials, aspects, rarity, size restrictions, and effect configuration are canonical authored JSON. The previous material-only identity experiment is not part of vNext.
