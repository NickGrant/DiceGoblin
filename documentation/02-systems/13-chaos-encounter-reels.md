---
Title: "Chaos Encounter Reels"
Status: Canonical
Last Updated: 2026-08-20
Owner: Systems Design + Engineering
Depends On:
  - backend/src/Services/ChaosEncounterService.php
  - backend/src/Controllers/ChaosEncounterController.php
  - backend/migrations/66_chaos_encounter_results.sql
Category: 02-systems
Tags:
  - systems
  - chaos
---

# Chaos Encounter Reels

## Current Runtime

Chaos nodes generate a `chaos_encounter_results` row for the run node. The result stores reel symbols, seed, risk multiplier, status, reroll state, and finalized rewards.

The player can generate, reroll one reel when manipulation is available, and finalize. Finalization locks the fight setup and rewards before combat resolution.

## Reel Categories

The current service-authored reels cover:

- enemy family
- encounter shape
- rule or reward result

Reward symbols can add teeth, dice grants, or Raw Chaos. Combat effects are surfaced through battle log metadata so QA can verify application.

## Backend Boundary

The backend owns reel generation, reroll limits, finalization, reward construction, and Raw Chaos crediting. Frontend surfaces reel labels, effects, reroll affordances, and finalized reward previews.

## Known Gaps

- Reel symbols are service-authored constants. Move them to a data-backed catalog if design tuning becomes frequent.
- Chaos balance still needs focused demo verification.
