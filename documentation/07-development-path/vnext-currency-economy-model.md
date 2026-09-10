---
Title: "vNext Currency and Economy Model"
Status: Accepted
Last Updated: 2026-09-09
Owner: Product + Engineering
Depends On:
  - documentation/07-development-path/vnext-game-overhaul.md
  - documentation/07-development-path/vnext-reward-unlock-model.md
Category: 07-development-path
Tags:
  - vnext
  - economy
  - currency
  - teeth
  - raw-chaos
  - energy
---

# vNext Currency and Economy Model

## Decision

vNext will treat Teeth and Raw Chaos as currencies using the same underlying wallet and transaction semantics, while Energy remains a separate regenerating-resource system.

The currencies are mechanically similar but intentionally serve different economic roles.

The guiding distinction is:

> Teeth buy ordinary, repeatable things. Raw Chaos changes what the player is capable of, with the Wrong Machine as the intentional repeatable exception.

## Teeth

Teeth are the common operating currency of Dice Goblins.

They should be comparatively easy to earn, frequently awarded, and commonly spent.

Primary Teeth sinks include:

- consumable items
- base-level units
- ordinary/basic dice
- routine Shop purchases
- other repeatable or replaceable goods and services

Teeth should support the normal earn-and-spend economy without carrying the weight of major permanent progression decisions.

Individual unit acquisition remains a Teeth-scale activity once the relevant unit type or kin is available to the player.

## Raw Chaos

Raw Chaos represents the chaotic/divine essence from which goblins are made. It is intentionally much harder to accumulate than Teeth and should be attached to more consequential choices.

Its primary role is permanent or transformative progression.

Primary Raw Chaos sinks include:

- Academy progression and permanent Academy unlocks
- permanent unit-type or capability unlocks where appropriate
- racial/kin variant unlocks
- other major permanent progression choices tied to expanding what the player can create, recruit, or do

Raw Chaos should feel meaningfully more valuable than Teeth because its sources are rarer and its sinks are more consequential.

## The Wrong Machine Exception

The Wrong Machine is the deliberate repeatable exception to the normal permanent-progression role of Raw Chaos.

Because Raw Chaos is literally goblin essence, it is thematically appropriate for the Wrong Machine to consume Raw Chaos when reconstructing a specific goblin or kin type.

This exception also provides a long-term Raw Chaos sink after the player has exhausted most permanent progression purchases.

The intended economy is:

```text
Raw Chaos
   |
   +--> permanent progression
   |
   +--> targeted Wrong Machine reconstruction
```

Wrong Machine reconstruction should provide greater certainty or specificity than ordinary Teeth-based recruitment. Its value comes from targeted acquisition rather than economic efficiency.

For example, a player may be able to use the Wrong Machine to deliberately reconstruct a desired kin/type combination rather than relying on ordinary recruitment or random acquisition.

Wrong Machine costs should therefore remain meaningfully higher in effective value than buying an ordinary unit with Teeth so that Raw Chaos does not become the dominant general-purpose roster-building currency.

## Unlock Versus Acquisition

The economy intentionally separates permanent access from individual ownership.

Example:

```text
Spend Raw Chaos
-> permanently unlock Saboteur unit type

Spend Teeth
-> acquire an individual base-level Saboteur
```

Likewise, permanently restoring or unlocking a kin may use Raw Chaos, while acquiring additional individual members of that kin can occur through the normal Teeth/reward economy once the kin is available.

This principle keeps permanent progression choices distinct from repeatable roster management.

## Currency Implementation Boundary

Teeth and Raw Chaos should use the same underlying currency infrastructure.

Their differences should be expressed through authored economy data and system rules such as:

- reward frequency
- reward quantities
- purchase prices
- available sinks
- progression requirements

Raw Chaos does not require technically special storage or transaction semantics merely because it is rarer or more valuable.

Currency systems should not themselves know what they are allowed to unlock or purchase. The consuming system defines its cost and validates the transaction.

For example, an Academy upgrade may define a Raw Chaos cost while a Shop offer defines a Teeth cost.

## Energy Is Not Currency

Energy is fundamentally different from Teeth and Raw Chaos and should not use the generic currency wallet model.

Energy is a regenerating resource with time-based state, including concepts such as:

- current value
- maximum value
- regeneration timing
- last regeneration timestamp
- spend rules
- explicit recovery effects

An item or event may restore Energy, but that restoration is an effect on Energy state rather than a generic currency grant.

## Relationship to Rewards

The accepted reward model may grant Teeth or Raw Chaos through ordinary reward definitions.

For example:

```text
combat_completed
  100% -> Teeth
    5% -> Raw Chaos
```

The exact percentages and quantities are authored balance decisions rather than currency-system behavior.

Energy recovery may also result from an event, but it remains an effect rather than a currency reward.

## Late-Game Behavior

Raw Chaos is allowed to become less important for permanent progression as the player exhausts permanent unlocks.

The Wrong Machine provides the intended renewable sink by allowing surplus Raw Chaos to be converted into targeted roster acquisition.

No additional repeatable Raw Chaos sink is required for the initial vNext design unless later playtesting demonstrates that the Wrong Machine is insufficient.

## Consequences

This model creates distinct economic identities without introducing separate currency architectures:

- Teeth support common, repeatable, replaceable purchases.
- Raw Chaos supports scarce, transformative progression and targeted goblin reconstruction.
- Energy remains a separate time-regenerating gameplay resource.

The design avoids forcing permanent progression and mundane purchases to compete for the same currency while preserving a useful endgame purpose for surplus Raw Chaos.
