---
Title: "Dice Profiles, Materials, and Aspects"
Status: Canonical
Last Updated: 2026-09-15
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
Each exact bound die rolls once per executed ability action, uniformly from `1..sides`. Explosive adds one extra roll when the initial result is maximum and cannot chain. Material has no current combat modifier beyond profile identity/eligibility. Striking adds +1 flat damage for a participating damaging die; Executioner adds +15% damage when the target is strictly below half max HP. Guarding adds +1 flat Defense, Bulwark +10% Defense, and Precise +10% Attack while the die is equipped/bound. Always-on effects compose across unique bound dice: flats first, then summed same-stat percentages with one floor. Phaser may present permitted profile information but never resolves effects authoritatively.

## Authoring
Profiles, materials, aspects, rarity, size restrictions, and effect configuration are canonical authored JSON. The previous material-only identity experiment is not part of vNext.
