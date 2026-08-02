---
Title: "Loot and Drop Scope"
Status: Legacy Reference
Last Updated: 2026-08-01
Owner: Systems Design + Engineering
Depends On:
  - documentation/02-systems/mvp-reference/01-dice-system.md
  - documentation/02-systems/mvp-reference/03-encounter-scope.md
Category: 02-systems
Tags:
  - systems
  - mvp-reference
---

# Loot and Drop Scope

## Purpose

- Define the reward categories and delivery model currently used in the alpha build.
- Keep loot documentation aligned with live reward preview and claim behavior.

## Reward Delivery Model

The current run system uses a two-step combat reward flow:

1. node resolution produces a stored battle result and reward preview
2. reward claim applies the rewards and progression snapshot

For non-combat loot nodes:

- resolution still produces a reward preview
- the player claims the result from the node page

## Current Reward Categories

Encounter rewards may currently include:

- soft currency
- new unit grants
- new dice grants
- XP for eligible units in combat-like encounters

The player profile also tracks `region_items`, which remain part of the persistent account state even when not surfaced heavily in the current player flow.

## Combat and Boss Rewards

- Combat and boss nodes may grant XP, soft currency, units, and dice.
- Reward preview currently summarizes:
  - `xp_total`
  - `currency_soft`
  - `new_unit_labels`
  - `new_dice_labels`

## Loot Node Rewards

- Loot nodes do not use combat XP messaging.
- Loot nodes currently preview:
  - teeth
  - dice labels
  - unit labels
- Loot node visuals use the run node's `node_quality_tier` metadata when present.
- Older loot nodes without quality metadata render as `good` quality.

## Unit Rewards

- Rewarded units enter the persistent roster as owned unit instances.
- The current shop and academy model centers future recruitment around unlocked Tier I families.
- The live reward path does not document any general-purpose Tier II or Tier III direct drop loop for players.

## Dice Rewards

- Rewarded dice enter the persistent inventory as owned dice instances.
- Dice labels are surfaced in both preview and claim-facing UX when available.

## Currency Rewards

- Soft currency is the active player-facing reward currency.
- Reward claim updates the player's persistent soft currency immediately.

## Region Items

- The backend reward and profile models support persistent region items.
- Region items are still part of the account progression surface and debug tooling.
- They should be treated as supported persistent rewards even where the current UI emphasis is light.

## Exclusions

The current alpha reward docs should not assume:

- crafting materials as a broad player economy
- consumable loot systems
- cosmetics
- pity systems
- smart loot targeting
- reward choice drafts

## Validation Rules

The loot system documentation is aligned when:

- combat rewards are documented as preview plus claim
- loot nodes are documented as non-XP reward nodes
- soft currency, units, dice, and profile-backed region items are treated as supported reward shapes
