---
Title: "Dice Goblins - Project Overview"
Status: Canonical
Last Updated: 2026-09-10
Owner: Product
Depends On:
  - documentation/00-overview/01-core-gameplay-loop.md
  - documentation/01-lore/00-world-and-lore.md
  - documentation/07-development-path/vnext-game-overhaul.md
Category: 00-overview
Tags: [overview, vnext]
---

# Dice Goblins - Project Overview

Dice Goblins is a browser-delivered tactical roguelite about a goblin warchief rebuilding goblinkind and restoring chaos to a world stabilized by Order.

## Player Experience
Between runs, the player operates from Camp: managing saved squads, units, ability loadouts, dice, progression systems, purchases, reconstruction, objectives, and discovered knowledge as those systems become available.

Runs consume Energy when successfully created. The player traverses an authored/generated node graph and makes route/encounter choices. Combat itself is resolved authoritatively by the server; Phaser presents the resulting battle playback rather than simulating authoritative outcomes locally.

Run success, combat, encounters, purchases, Academy actions, and other successful gameplay transactions can produce semantic events. Reward-bearing events finalize and apply their rewards immediately and transactionally; reward screens present the already-owned result.

## Persistent Pillars
- Preparation matters: squad composition, formation, unit abilities, ability order, and exact dice bindings shape combat.
- Combat is server-resolved and presentation-rich rather than directly controlled.
- Runs preserve temporary HP/modifiers while account progression persists between runs.
- Dice are physical shards of chaos and use authored profiles composed from material, aspects, rarity, and size eligibility.
- Teeth fund ordinary repeatable purchases; Raw Chaos primarily changes capability and powers reconstruction.
- Kin restoration reconnects goblins with forms suppressed by Order.
- The world is bright, colorful, adventurous, and mischievous rather than grimdark.

## Platform Shape
Angular owns the public website, authentication/account shell, and `/game` host. Phaser owns all gameplay presentation and in-game navigation. PHP/MySQL remain authoritative for mutable gameplay state.

Mobile gameplay is landscape-only. Portrait displays a rotate-device gate rather than a second gameplay layout.

## Implementation Scope
The vNext overhaul first proves the architecture through Camp and the Farm, then Mountains, before migrating broader economy/progression/encounter systems and Swamps. Completing vNext does not require producing every future base-game biome.

See `documentation/07-development-path/vnext-game-overhaul.md` for implementation order and accepted vNext decision documents for detailed contracts.
