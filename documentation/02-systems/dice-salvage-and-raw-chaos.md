---
Title: "Dice Salvage and Raw Chaos"
Status: Canonical
Last Updated: 2026-08-20
Owner: Systems Design + Engineering
Depends On:
  - backend/src/Services/DiceSalvageService.php
  - backend/src/Controllers/GameplayController.php
  - documentation/02-systems/dice-material-model.md
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

## Frontend Boundary

Dice Inventory shows salvage only when the Wrong Machine unlock is present. The command HUD and Home can display Raw Chaos from the profile payload.

## Known Drift

- Current salvage values are tied to existing dice rarity/affix behavior.
- Target-state dice materials define material-owned salvage classes. Reconcile salvage values when the material migration lands.
