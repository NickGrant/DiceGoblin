---
Title: "Target Resolution"
Status: Canonical
Last Updated: 2026-09-15
Owner: Systems Design + Engineering
Depends On:
  - documentation/02-systems/combat-resolution.md
  - documentation/02-systems/warband-and-formation.md
Category: 02-systems
Tags: [systems, combat, targeting]
---

# Target Resolution

Players do not choose targets during combat. Each active ability has authored targeting behavior and the authoritative combat engine selects among currently valid living combatants.

## Current Farm Rules
`self` selects the actor. `enemy_front_prefer` selects living enemies at x=2 before x=1 before x=0. `enemy_back_prefer` selects living enemies at x=0 before x=1 before x=2. `ally_lowest_hp_pct` selects the living ally with the lowest exact `current_hp / max_hp` ratio, including the actor when eligible. Positions are side-relative 3x3 coordinates; x=2 is front for both sides.

`wrestled` takes precedence for the affected unit's next eligible enemy-targeted damaging attack while the wrestler is living and remains on the opposing side. The forced target is consumed when that attack selects it, even if the attack later misses. Otherwise ordinary authored preference applies; the status also expires after its authored duration. No other forced-target modes are established in this slice.

## Determinism and Transparency
Equally valid ties sort by stable combatant key and choose one index from the battle's single deterministic RNG stream. No RNG is consumed for a sole candidate. Identical normalized input and seed choose the same target on retries/replays.

Playback records the chosen target and reason (`self`, front/back preference, preference tie, lowest-HP percentage or tie, or `wrestled_forced`). Phaser presents that fact; it does not rerun target selection.
