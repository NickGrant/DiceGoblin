---
Title: "Target Resolution"
Status: Canonical
Last Updated: 2026-09-10
Owner: Systems Design + Engineering
Depends On:
  - documentation/02-systems/combat-resolution.md
  - documentation/02-systems/warband-and-formation.md
Category: 02-systems
Tags: [systems, combat, targeting]
---

# Target Resolution

Players do not choose targets during combat. Each active ability has authored targeting behavior and the authoritative combat engine selects among currently valid living combatants.

## Core Semantics
Targeting may use concepts already established by Dice Goblins, including:
- self;
- ally/enemy side;
- front/back preference;
- lowest health;
- highest threat/attack where authored;
- wounded, marked, or debuffed preference;
- previous-target preference;
- seeded random choice.

Forced-target and taunt/guard effects take precedence over ordinary preference scoring when their authored rules say they do.

Formation-aware targeting uses resolved combat position. It does not imply a universal "front row must be attacked first" rule; the ability's target rule decides whether position matters.

Multi-target abilities select a primary valid target and then additional distinct valid targets according to authored behavior.

## Determinism and Transparency
Ties and random targeting use the battle's deterministic random state. Request retries and playback do not reroll target selection.

Playback/debug information should preserve enough targeting reason information to explain why an automatically resolved action chose a target, without requiring Phaser to reproduce the server's targeting algorithm.

Exact scoring weights are implementation/tuning details and should be documented alongside the combat engine only when they are intentionally stable game rules.
