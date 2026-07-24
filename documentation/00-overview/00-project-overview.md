# Dice Goblins - Project Overview

Status: active  
Last Updated: 2026-07-24  
Owner: Product  
Depends On: `documentation/00-overview/01-core-gameplay-loop.md`, `documentation/00-overview/03-world-and-lore.md`, `documentation/01-architecture/02-frontend-state-and-scene-contracts.md`, `documentation/03-ux/00-ux-and-debug-scope.md`

## Purpose

- Describe the game as it currently exists in the alpha launch.
- Give new readers a reliable product-level summary before they dive into systems or code.
- Establish which experience assumptions are current and which older ideas are no longer the default.

## Current Game Summary

Dice Goblins is a browser-based tactical roguelite where the player acts as a goblin warchief, manages a growing warband between short runs, then sends an active squad through server-resolved node maps for rewards, power, and progression.

The current game is built around:

- an authenticated shell with a persistent top HUD
- squad and unit management between runs
- dice-driven unit loadouts
- region-based runs with dialogue, combat, loot, rest, boss, and exit flow
- backend-authoritative battle resolution with readable battle logs
- persistent progression through units, dice, currency, feature unlocks, and region unlocks

The game's current thematic frame is:

- a bright and colorful world that appears well-ordered on the surface
- goblins as cute, mischievous, dangerous agents of chaos
- dice as physical manifestations of chaos power
- the player's long-term ambition to build strength against the world's imposed order

## What The Player Is Doing

The player is trying to strengthen a goblin warband over repeated runs.

In the current implementation, that mostly means:

- acting from the perspective of an insider to goblin culture rather than an outside commander
- maintaining one active squad for the next run
- arranging that squad on a 3x3 formation grid
- tuning unit combat loadouts and assigning dice to ability slots
- buying supplies and unlocks from the shop
- unlocking new Tier I unit types in the academy
- leveling units and promoting them into stronger classes
- completing regions to unlock the next region in sequence
- accumulating more dice and power in service of a broader anti-order goblin agenda

## Current Experience Pillars

- Preparation affects outcomes:
  - squad choice, formation, equipped abilities, and assigned dice all shape battle results
- Runs are compact:
  - the game is currently oriented around short, readable run sessions rather than long campaigns
- Combat is resolved by the backend:
  - the player does not manually play battles; they inspect results, logs, and rewards after resolution
- Progress persists between runs:
  - units, dice, soft currency, feature unlocks, and region unlocks carry forward
- Bright presentation, sharp intent:
  - the world and UI can look cheerful and adventurous while the goblins remain disruptive, ambitious, and dangerous

## Current Alpha Launch Surface Area

The current player-facing product includes:

- login and authenticated session flow
- a public guide page and an in-shell field guide route
- home hub
- region selection
- warband overview
- squad editing
- unit details, loadout, capstone, promotion, and dice-slot management
- dice inventory and selling
- shop supplies, daily deals, and feature unlocks
- academy unit-type unlocks and promotion flow
- run map
- dialogue-node resolution
- combat and loot node resolution
- rest resolution
- run summary
- environment-gated debug tooling

## Current Content Shape

The currently surfaced region sequence is:

1. **Mystic Cave**
   - initial unlocked region
   - zero-energy introductory run
   - conversation with The Whim followed by an exit
   - completion unlocks The Farm
2. **The Farm**
   - fixed introductory combat route
   - completion unlocks Mountains
3. **Mountains**
   - procedural kobold region
   - completion unlocks Swamps
4. **Swamps**
   - procedural frogman region

The shop currently covers:

- basic dice
- basic units
- daily deals
- feature unlocks

The academy currently covers:

- Tier I unit-type unlock research
- promotion management for eligible units

## Directional Notes

These points reflect the current product direction rather than older planning ideas:

- the game is in alpha launch, not pre-product concept stage
- current-state docs should prefer implemented behavior over superseded roadmap language
- the player should be framed as a goblin warchief operating from inside goblin values and culture
- goblins are protagonists, but not sanitized heroes
- the current aesthetic target is bright, saturated, cartoon-styled, and JRPG-influenced rather than grim propaganda craft
- direct PvP is not part of the current active game loop
- debug tooling exists to accelerate testing and tuning, but it is environment-gated rather than player-facing

## Canonical Follow-Up Docs

- Core loop: `documentation/00-overview/01-core-gameplay-loop.md`
- World and lore: `documentation/00-overview/03-world-and-lore.md`
- Frontend route and state behavior: `documentation/01-architecture/02-frontend-state-and-scene-contracts.md`
- Backend/API surface: `documentation/01-architecture/03-backend-api-contracts.md`
- UX and player-facing scope: `documentation/03-ux/00-ux-and-debug-scope.md`
- Future gameplay systems: `documentation/07-roadmap/00-gameplay-systems-roadmap.md`
