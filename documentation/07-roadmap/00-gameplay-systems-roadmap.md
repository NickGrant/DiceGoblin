# Gameplay Systems Roadmap
----

Status: planning
Last Updated: 2026-07-25
Owner: Product + Systems Design
Depends On: `documentation/README.md`, `agent/ISSUES.md`, `agent/MILESTONES.md`, `agent/MILESTONES_ARCHIVE.md`

## Purpose

- Keep a truthful snapshot of gameplay/system direction after completed roadmap milestones.
- Separate completed foundations from the next execution-ready feature lane.
- Capture future candidates without treating the entire roadmap as one implementation contract.
- Keep developer-support tooling visible when it is the next practical accelerator.

This document is directional planning. `agent/ISSUES.md` and `agent/MILESTONES.md` define the active execution lane.

## Current Implemented Baseline

The current game already includes:

- authenticated Discord and local-account sessions
- local registration, login, logout, and password reset
- a persistent authenticated shell and command HUD
- a home hub, guide/codex surfaces, region selection, and debug panel entry
- one active squad with a 3x3 formation
- persistent player-owned units, dice, currencies, region items, unlocks, and objectives
- ordered unit ability loadouts with dice assigned to authored ability slots
- backend-authoritative combat resolution, battle logs, reward preview, reward claim, run cleanup, and summaries
- Precision and Resolve as authored, visible, combat-relevant stats
- unit XP, leveling, mastery, Tier II and Tier III class progression, capstones, inherited abilities, and promotion UX
- persistent goblin-kin with acquisition and player-facing display
- shop, daily deals, feature unlocks, academy requirements, and academy research
- bounty-board backend foundation with list, accept, sync, and claim flows
- Raw Chaos currency and dice salvage
- run maps with combat, loot, rest, boss, exit, dialogue, hazard, shrine, and chaos nodes
- persisted chaos reel results, one single-use reroll, reachable chaos nodes, backend finalization rewards, and downstream node progression

## Current Region Order

1. **Mystic Cave**
   - Introductory narrative region.
   - Costs no energy.
   - Contains The Whim introductory conversation and an exit.
   - Completing it unlocks The Farm.
2. **The Farm**
   - Fixed introductory combat route.
   - Completing it unlocks the Mountains.
3. **Mountains**
   - Procedural kobold region.
   - Completing it unlocks the Swamps.
4. **Swamps**
   - Procedural frogman region.

Mystic Cave is not a future combat biome unless a later design explicitly changes that.

## Completed Roadmap Foundations

These milestone lanes are complete in the archive and should be treated as implemented foundations, not upcoming work:

- Backend Structural Cleanup
- Expanded Combat Stats
- Goblin-Kin
- Progression Guidance and Home Dashboard foundation
- Bounty Board foundation
- Academy and Feature-Unlock Expansion
- Wrong Machine and Raw Chaos foundation
- Expanded Run Encounters
- Slot-Machine-Style Random Encounters
- Complete Tier III Progression
- Developer Seed Catalog Browser
- Legacy Unit Dice Removal
- Wrong Machine and Kin Foundation

## Active Planning Priority

No active planning priority is selected after the Wrong Machine and Kin Foundation branch. Promote the next implementation lane from the detailed roadmap when ready.

## Next Gameplay Candidates

After hybrid catalog ownership cleanup, the strongest gameplay candidates are:

1. **Wrong Machine v2**
   - dice fabrication
   - bulk salvage
   - catalyst support from region items
   - limited quality targeting
2. **Bounty Board v2**
   - visible frontend board if the current surface is not enough
   - rotations and expiration
   - richer bounty categories
   - better reward variety
3. **Chaos Encounters v2**
   - turn persisted reel results into authored combat encounters
   - connect reel symbols to enemy composition, encounter rules, and reward modifiers
   - add explicit confirmation for high-risk results
4. **Goblin-Kin v2**
   - stronger passive behaviors
   - acquisition tuning
   - academy influence over recruitment visibility or odds
5. **Home Guidance v2**
   - richer next-action recommendations across bounties, mastery, Wrong Machine, regions, and run state
   - clearer return-to-game summaries

## Parallel Presentation Track

Battle playback and animation improvements remain valuable, but they are not a prerequisite for gameplay systems.

Guidelines:

- Angular owns routes, API orchestration, controls, and accessibility.
- The backend remains the only combat authority.
- Phaser may consume immutable battle-playback snapshots where it improves readability.
- Gameplay milestones must preserve a readable non-Phaser fallback.

## Deliberately Deferred Features

The following are still out of near-term scope:

- direct PvP
- full breeding and genetic inheritance simulation
- multiple simultaneous kin families per unit
- unrestricted item rerolling or perfect-item crafting
- monetized gambling or purchasable slot-machine spins
- replacing Angular management screens with Phaser
- expanding Mystic Cave into a combat biome without a separate design decision

## Roadmap Maintenance Rules

- Update this file when a milestone closes or when the next execution lane changes.
- Promote only the next execution-ready slice into `agent/ISSUES.md` and `agent/MILESTONES.md`.
- Keep completed work in `agent/ISSUES_ARCHIVE.md` and `agent/MILESTONES_ARCHIVE.md`.
- Do not use this document as acceptance criteria for a branch without first creating a focused active issue.
