---
Title: "Dice Salvage and Raw Chaos"
Status: Canonical
Last Updated: 2026-08-23
Owner: Systems Design + Engineering
Depends On:
  - backend/src/Services/DiceSalvageService.php
  - backend/src/Controllers/GameplayController.php
  - documentation/02-systems/mvp-reference/01-dice-system.md
Category: 02-systems
Tags:
  - systems
  - dice
  - raw-chaos
---

# Dice Salvage and Raw Chaos

## Current Runtime

Raw Chaos is stored on `player_state.currency_raw_chaos` and appears in profile currency payloads as `raw_chaos`.

Dice salvage is available only after the Wrong Machine feature is unlocked. `DiceSalvageService` rejects equipped dice, deletes the salvaged die, awards Raw Chaos, and returns the awarded amount plus the updated Raw Chaos balance.

Chaos victories can also award Raw Chaos through finalized chaos rewards. Wrong Machine reconstruction spends Raw Chaos as part of the Pig Kin requirement.

## Salvage Valuation

Salvage remains part of the current rarity, material, and affix dice model. The backend is authoritative for the final payout.

The existing dice contract allows salvage value to scale from die size, die rarity, and affix rarity. Materials remain a separate die property and do not replace permanent affixes for salvage purposes.

## Frontend Boundary

Dice Inventory shows salvage only when the Wrong Machine unlock is present. The command HUD and Home can display Raw Chaos from the profile payload.

## Maintenance Rule

Do not migrate salvage to a material-only valuation model unless a future dice-system change explicitly approves that direction. Keep salvage behavior synchronized with the current dice definition, material, and affix contract.
