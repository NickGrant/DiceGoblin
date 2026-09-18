---
Title: "vNext Reward and Unlock Model"
Status: Accepted
Last Updated: 2026-09-17
Owner: Product + Engineering
Depends On:
  - documentation/07-development-path/vnext-game-overhaul.md
Category: 07-development-path
Tags:
  - vnext
  - rewards
  - unlocks
  - progression
  - events
---

# vNext Reward and Unlock Model

## Decision

vNext will use events as the common source for rewards and unlocks.

The core model is:

```text
Successful resolution
        |
        v
      Event
        |
        v
  Reward definition
        |
        v
   Reward rolls
        |
        v
 Finalized result
        |
        v
Transactional application
```

An event describes something that successfully happened. Events can result from node resolution or from a successful gameplay transaction initiated by the player, such as a Shop purchase or Academy action.

The initiating action is responsible for its own validation and costs. A UI interaction does not itself grant a reward merely because it was attempted.

## Reward Types

Rewards may grant:

- currency
- stackable items
- units
- dice
- XP or comparable additive progression
- Codex discoveries where treated as granted collection entries
- permanent unlocks

Unlocks are therefore treated as a unique reward type rather than as a separate trigger architecture.

Rewards do not include arbitrary state mutation. Damage, healing, costs, consuming assets, equipment changes, formation changes, node movement, run-state changes, and similar mutations remain effects or ordinary domain state transitions.

## Reward Probability

Each reward definition has a fixed authored probability. A deterministic reward is represented as a 100% reward rather than by a separate deterministic mechanism.

Examples:

```text
100%  Teeth
35%   Die
20%   Unit
100%  Region unlock
```

Reward definitions are not modified in response to player ownership or progression state.

## Unique Rewards and Duplicates

Some reward types, especially unlocks and certain collection entries, can only be granted once.

If a unique reward is rolled and the player already owns it, the result is nothing.

There is no automatic reroll, substitute reward, probability redistribution, or duplicate protection in the initial vNext model.

Example:

```text
10% Wrong Machine unlock
```

The 10% chance remains part of the authored reward definition even after the player owns the Wrong Machine unlock. If that reward is rolled again, it produces no grant.

This intentionally allows older reward sources to become somewhat less valuable as the player exhausts their unique rewards.

## Finalization and Idempotency

Reward rolls are finalized once for a resolved event. Retries, reconnects, repeated API requests, or repeated presentation of the result must not reroll rewards.

The finalized result, rather than the probabilities that produced it, is the authoritative record used for application and presentation.

Grant application must be transactional and idempotent so the same finalized event result cannot duplicate currency, inventory, unit, die, XP, Codex, or unlock grants.

The initial finalized-result contract is a strict versioned value. Version 1 records the event and reward-definition IDs, durable source identity, each authored entry in order, its basis-point probability and exact server roll, its outcome, and the exact typed before/after grant facts needed for application checks. Currency entries record balances, participating-unit XP entries record each unit's ordered level/XP transition, and unique unlock entries distinguish `granted` from `already_owned`. The persisted value contains no presentation text, timestamps, or random-source state.

Production rolls use server cryptographic randomness and consume exactly one integer in `1..10000` per authored entry. Application runs inside the initiating command's transaction: it inserts the finalized row, applies only its exact grants, and marks the same row applied. The reward service neither owns that transaction nor increments the player revision. An applied retry returns the persisted result without rerolling or reapplying; a committed finalized-but-unapplied row is treated as an integrity failure.

## Node Resolution

Node resolution is a major event source but is not the only event source.

A completed node may:

1. apply direct gameplay effects or state transitions; and
2. emit a resolved event whose reward definition is evaluated.

Boss nodes can therefore own end-of-run rewards and unlocks without requiring a separate special-case run-completion reward path.

For example:

```text
Farm boss node completed
        |
        v
farm_boss_completed event
        |
        +--> Teeth reward
        +--> XP reward
        +--> item reward
        +--> unit chance
        +--> die chance
        +--> Mountains unlock
```

## UI-Initiated Transactions

Player-facing systems such as the Shop, Academy, and Wrong Machine may also produce events, but only after their transaction succeeds.

For example:

```text
Purchase request
   |
   +--> validate offer
   +--> validate/spend cost
   |
   v
purchase_completed event
   |
   v
purchased reward granted
```

The event records the successful fact. It does not replace validation, spending, crafting inputs, or other transaction semantics.

## Effects Versus Rewards

The vNext boundary is:

> Rewards add assets, additive progression, collection entries, or permanent entitlements to the player. Effects and domain mutations change existing or temporary state.

Examples that should remain effects or ordinary mutations rather than rewards include:

- hazard damage
- rest healing
- temporary shrine or run modifiers
- spending Teeth or Raw Chaos
- consuming an item or die
- changing formation or loadout
- promoting or modifying an existing unit
- marking a node resolved or changing run position
- passive energy regeneration

These actions may still emit events, and those events may themselves have rewards.

## Current-System Fit

The current game does not require a known reward or unlock path that falls outside this model.

Existing combat rewards, boss rewards, dialogue grants, region and feature unlocks, Codex grants, bounty/objective rewards, Wrong Machine output, Shop purchases, Academy unlocks, consumable acquisition, Raw Chaos, Teeth, units, dice, and XP can all be expressed through the event-to-reward model.

Systems that do not fit as rewards are intentionally classified as effects, costs, transactions, or state changes rather than exceptions to the reward model.

## Deferred Complexity

The initial vNext implementation does not require:

- rerolling duplicate unique rewards
- replacement rewards
- dynamic reward percentages based on ownership
- weighted redistribution when a reward is unavailable
- select-N or choose-one reward pools
- a general event-sourced architecture for all player state

These can be introduced later only if game design or playtesting demonstrates a need.

## Consequences

This decision intentionally reduces the number of independent progression and grant paths in the backend.

It should become possible to inspect an event definition and understand what it can award without tracing separate feature-unlock, loot, dialogue-grant, purchase-grant, and boss-progression mechanisms.

The implementation should generalize event reward resolution and grant application while keeping actual player state in appropriate domain models rather than collapsing all owned state into one generic storage table.
