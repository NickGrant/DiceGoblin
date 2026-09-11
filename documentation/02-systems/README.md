---
Title: "Systems Documentation"
Status: Canonical
Last Updated: 2026-09-10
Owner: Systems Design + Engineering
Depends On:
  - documentation/README.md
  - documentation/07-development-path/vnext-game-overhaul.md
Category: 02-systems
Tags: [systems, index, vnext]
---

# Systems Documentation

This folder keeps only gameplay rules that remain useful for building vNext. Prototype implementation histories, MVP rework plans, and future multiplayer concepts were removed from the active tree.

Accepted vNext decision documents under `07-development-path/` override these system docs whenever a narrower vNext decision exists.

## Retained System Contracts
- `combat-resolution.md` - authoritative combat lifecycle and playback boundary.
- `target-resolution.md` - automatic target-selection semantics.
- `ability-loadouts-and-dice-binding.md` - unit ability ordering and exact die bindings.
- `dice-profiles-and-aspects.md` - vNext dice identity model.
- `warband-and-formation.md` - saved squads, nine positions, active-squad and locking rules.
- `unit-stat-advancement.md` - persistent unit progression and run HP boundary.
- `unit-promotion.md` - vNext promotion storage/transaction boundary; detailed balance remains a later milestone.
- `unit-naming.md` - unit naming rules.
- `run-node-generation.md` - reusable run-generation behavior and persistence boundary.

## Deferred Systems
Economy, Shop, Academy, Wrong Machine, kin reconstruction, encounter depth, Codex/dialogue, objectives/bounties, and onboarding are intentionally governed by accepted vNext decisions and milestone requirements until their implementation milestone produces a durable canonical system contract.

Do not recover deleted prototype system documents as implementation guidance merely because these narrower contracts are deferred.
