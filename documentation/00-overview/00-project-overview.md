# Dice Goblins - Project Overview

Status: active  
Last Updated: 2026-05-29  
Owner: Product  
Depends On: `documentation/00-overview/01-core-gameplay-loop.md`, `documentation/02-systems-mvp/03-encounter-scope.md`, `documentation/03-ux/01-visual-design-guide.md`

## Purpose

- Define the game at a product level.
- Give new readers a stable summary of player goals, progression, and scope.
- Anchor the more detailed systems and UX documents.

## Game Summary

Dice Goblins is a browser-based tactical roguelite where the player builds a goblin warband, equips units with dice-driven abilities, and sends squads through short node-based runs for rewards and progression.

The game combines:

- roster management between runs
- automated backend-resolved combat
- branching run decisions with combat, loot, rest, boss, and exit nodes
- persistent progression through units, dice, currency, and region unlocks

## Player Goal

The player is trying to make their warband stronger over repeated runs.

That means:

- collecting better dice
- recruiting or earning stronger units
- leveling and promoting units
- assembling effective squads and formations
- clearing regions to unlock more dangerous and rewarding content

## Core Experience Pillars

- Preparation matters:
  - squad composition, formation, unit loadouts, and dice choices should change run outcomes
- Runs are short and readable:
  - a run should feel like a compact decision loop rather than a long campaign session
- Combat is deterministic after resolution:
  - the backend resolves outcomes and the frontend presents the results clearly
- Progression is persistent:
  - units, dice, currency, and unlocks carry forward between runs

## MVP Product Scope

The active MVP focuses on:

- login and authenticated session flow
- warband management
- squad editing and activation
- unit details, promotion, and dice equipment
- region selection
- run map traversal
- node resolution, rest management, and run-end summary
- shop and debug tooling

The MVP is PvE-first. Direct PvP combat is not part of the active scope.

## Multiplayer Position

Dice Goblins is built as a shared-world multiplayer game, but MVP multiplayer interaction is indirect rather than head-to-head combat.

Multiplayer pressure can come from:

- shared progression spaces
- economy or region competition
- future social or betrayal mechanics

Those systems are secondary to getting the single-player-equivalent progression loop clear and enjoyable first.

## Tone and Presentation

The game should feel like goblin military bureaucracy:

- harsh
- improvised
- tactical
- funny without becoming cute

The visual language is governed by `documentation/03-ux/01-visual-design-guide.md`.

## Canonical Follow-Up Docs

- Core loop: `documentation/00-overview/01-core-gameplay-loop.md`
- Glossary: `documentation/00-overview/02-glossary.md`
- Systems: `documentation/02-systems-mvp/`
- UX: `documentation/03-ux/`
