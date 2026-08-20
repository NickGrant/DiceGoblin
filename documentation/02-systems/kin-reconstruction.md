---
Title: "Kin Reconstruction and First Ownership"
Status: Canonical
Last Updated: 2026-08-19
Owner: Systems Design + Product
Depends On:
  - documentation/03-content/02-kin-types.md
  - documentation/03-content/10-items-and-consumables.md
  - documentation/03-content/11-loot-and-reward-profiles.md
  - documentation/03-content/12-codex-entries.md
  - documentation/03-content/13-dialogue-and-lore.md
  - documentation/02-systems/loot-determination.md
Category: 02-systems
Tags:
  - systems
  - kin
  - wrong-machine
  - progression
---

# Kin Reconstruction and First Ownership

## Purpose

Define the canonical Wrong Machine contract for producing kin units, discovering a kin for the first time, expanding future unit-reward eligibility, and repeating a reconstruction recipe after discovery.

This document owns reconstruction flow, first-ownership side effects, transaction boundaries, idempotency requirements, and the current Pig Kin recipe. Kin stats and identity belong in the Kin Type Catalog. Ingredient identity and acquisition belong in the Item and Reward catalogs. Storage, endpoints, migrations, and service implementation belong in technical documentation and code.

## Current Phase Scope

The current phase contains only two active kin:

- Basic Goblin
- Pig Kin

Pig Kin is the only current Wrong Machine reconstruction recipe. Lizard Kin, Frog Kin, later biome lineages, existing-unit transformation, recipe upgrades, and bulk reconstruction are explicitly deferred.

## Core Rule

A reconstruction recipe always produces one unit of the recipe's kin.

```text
valid recipe payment -> one new unit
```

The recipe does not merely toggle an account unlock. First-time unlock behavior is a side effect of producing the player's first owned unit of that kin.

Every later successful use of the same recipe consumes the same authored inputs and produces another unit of that kin.

## Terminology

| Term | Meaning |
| --- | --- |
| Reconstruction recipe | A repeatable Wrong Machine operation with authored inputs and one kin-unit output. |
| First ownership | The transition from owning no unit of a kin to owning at least one. |
| Kin discovery | Permanent account knowledge that the kin has been produced or owned. |
| Kin reward eligibility | Permission for the kin to appear in applicable future unit grants and recruitment pools. |
| Reconstruction output | The newly created owned unit. This occurs on every successful recipe completion. |
| First-ownership side effects | One-time Codex, discovery, and reward-pool changes applied alongside the first unit. |

Discovery and production must not be represented as competing outcomes. The first reconstruction produces a unit and then establishes the account-level state associated with owning that kin.

## Access Requirements

A player may use kin reconstruction only when:

- the `wrong_machine` feature is unlocked
- the requested recipe is enabled
- every ingredient and currency requirement can be satisfied in one transaction
- the player can receive the produced unit
- the request includes an idempotency boundary that prevents duplicate spending or production

Viewing a recipe, previewing costs, declining, closing the interface, or submitting an invalid request consumes nothing.

## Current Pig Kin Recipe

| Recipe key | Output | Pig Ears | Crown Fragments | Raw Chaos | Repeatability |
| --- | --- | ---: | ---: | ---: | --- |
| `reconstruct_pig_kin` | One level-1 Tier-1 Pig Kin unit | `10` | `1` | `25` | Unlimited while requirements are met |

The recipe uses:

- `pig_ear` as its repeatable lineage material
- `mudking_crown_fragment` as its boss catalyst
- Raw Chaos as its general reconstruction currency

These are initial authored balance values. Changing them is a system-content change and must update this document and every player-facing cost display together.

## Unit-Type Selection

The Pig Kin recipe guarantees the unit's kin, not a separate fixed combat role.

For the current phase, reconstruction selects one currently unlocked Tier-1 unit type through the same deterministic candidate pool used by ordinary Tier-1 unit grants. The output is then created as Pig Kin rather than rolling a kin from the normal reward pool.

The valid unit-type candidates are currently:

- Bruiser
- Guardian
- Marksman
- Bannerbearer
- Saboteur

A future recipe or interface may allow the player to select a unit type, but that is not part of the current contract.

## First Pig Kin Reconstruction

The first successful Pig Kin reconstruction performs all normal reconstruction work and then applies first-ownership side effects.

### Normal output

- Consume `10` Pig Ears.
- Consume `1` Mudking Crown Fragment.
- Consume `25` Raw Chaos.
- Create one level-1 Tier-1 Pig Kin unit.
- Add the unit to the player's owned-unit inventory.

### First-ownership side effects

- Record Pig Kin as discovered for the account.
- Award or derive the Pig Kin Codex entry.
- Add Pig Kin to eligible kin pools for applicable future unit drops and recruitment.
- Return a first-discovery marker in the reconstruction result so presentation can distinguish the milestone from later production.

The first reconstruction does not grant a second bonus unit. The recipe's normal unit output is the unit that causes first ownership.

## Later Pig Kin Reconstructions

Every later successful use of `reconstruct_pig_kin`:

- consumes the complete authored recipe
- produces one additional Pig Kin unit
- leaves Pig Kin discovery and Codex state unchanged
- does not repeat first-discovery presentation or rewards
- does not alter existing Pig Kin units

There is no authored recipe-completion limit in the current phase.

## First-Reconstruction Protection

The opening progression must not be blocked by random ingredient accumulation after the Wrong Machine is recovered.

When the player first enters the Pig Kin reconstruction tutorial, a one-time introductory subsidy may fill only the missing amount required for one recipe:

- missing Pig Ears, up to the `10`-ear requirement
- missing Raw Chaos, up to the `25`-Chaos requirement

The subsidy does not grant surplus beyond one recipe. The Mudking Crown Fragment is protected by the existing guaranteed Mudking boss grant and is not duplicated by the subsidy.

Already-owned ingredients always count. The subsidy is idempotent and must not be claimable again after it has been applied or after the first Pig Kin reconstruction succeeds.

The actual reconstruction still spends the full recipe. The subsidy protects tutorial completion without creating a separate discounted recipe identity.

## Ongoing Ingredient Economy

Pig Kin discovery does not disable Pig Ear or Mudking Crown Fragment rewards.

Those items remain useful because every recipe completion creates another Pig Kin unit. Therefore:

- pig-family victories continue granting Pig Ears after Pig Kin is discovered
- Mudking victories continue granting Crown Fragments after Pig Kin is discovered
- previously collected ingredients remain spendable
- reward logic must not suppress these items merely because the Codex entry or kin discovery flag is owned

Later content may add other uses, but no additional use is required to justify continued drops in the current phase.

## Ordinary Unit-Grant Eligibility

Before first Pig Kin ownership:

- Basic Goblin is the only current kin eligible for ordinary unit grants
- Pig Kin can be obtained through its Wrong Machine recipe

After first Pig Kin ownership:

- Basic Goblin remains eligible
- Pig Kin becomes eligible for applicable ordinary unit grants and recruitment
- reward selection may choose either eligible kin according to its authored weighting

Basic Goblin must remain viable and must not be treated as a failed Pig Kin roll.

## Transaction Order

A successful reconstruction transaction follows this order:

1. Validate Wrong Machine ownership and recipe availability.
2. Resolve the deterministic Tier-1 unit-type candidate.
3. Validate ingredient quantities, Raw Chaos, and unit-receipt capacity.
4. Lock or otherwise protect all mutable account resources involved.
5. Spend Pig Ears, Crown Fragment, and Raw Chaos.
6. Create the Pig Kin unit.
7. Detect whether the player had any Pig Kin unit before this transaction.
8. If this is first ownership, record discovery, Codex ownership, and reward eligibility.
9. Mark the idempotency key complete with the complete result payload.
10. Commit all changes together.

A failure at any step must roll back ingredient spending, currency spending, unit creation, and first-ownership state together.

## Idempotency

Retrying the same logical reconstruction request must return the original result rather than:

- consuming the recipe twice
- creating a second unit
- rerolling the unit type
- replaying the first-ownership transition
- awarding duplicate Codex or discovery state

A new deliberate reconstruction uses a new idempotency key and pays the recipe again.

## First-Ownership Detection

First ownership is based on durable owned-unit state, not solely on a separate unlock flag.

The first-ownership transition should be safe when:

- the account has no prior Pig Kin unit and completes reconstruction
- a retry arrives after the unit was committed
- historical or migrated ownership already contains a Pig Kin unit
- discovery state is missing but a Pig Kin unit exists

Synchronization may repair missing discovery, Codex, or eligibility state from owned units. It must never remove legitimate ownership because a secondary flag is absent.

## Presentation Contract

The reconstruction result should distinguish:

| Result field | Meaning |
| --- | --- |
| Produced unit | The unit created by this recipe completion. |
| Recipe cost | The exact items and Raw Chaos consumed. |
| First discovery | Whether this completion caused first ownership. |
| Newly eligible kin | Pig Kin when first ownership expands ordinary reward pools; otherwise none. |
| Codex awarded | Pig Kin when newly discovered; otherwise already owned. |

Later reconstructions should be presented as successful unit production, not as repeated lineage unlocks.

## Explicit Non-Goals

The current contract does not include:

- Lizard Kin, Frog Kin, or another lineage
- transforming an existing unit into Pig Kin
- choosing a specific Tier-1 unit type
- changing a unit's kin after creation
- multiple units from one recipe
- recipe discounts based on prior ownership
- suppression of Pig materials after discovery
- guaranteed Pig Kin from ordinary drops before first ownership
- dice fabrication or material replacement

## Validation Rules

The system is aligned when:

- every successful recipe creates exactly one Pig Kin unit
- the first successful recipe also establishes Pig Kin discovery, Codex ownership, and reward eligibility
- later recipes continue producing units without replaying first-ownership effects
- Pig Ear and Crown Fragment rewards continue after discovery
- ordinary unit grants cannot produce Pig Kin before first ownership
- ordinary unit grants may produce Pig Kin after first ownership
- all spending and state changes are transactional
- retries cannot duplicate spending, units, or unlock effects
- the introductory subsidy can complete at most one recipe and grants no surplus
- Basic Goblin remains eligible after Pig Kin discovery
- no future kin is implied to be current content

## Maintenance Notes

- Add a recipe here before exposing it through the Wrong Machine.
- Keep recipe ingredients synchronized with the Item Catalog and Reward Catalog.
- Keep first-ownership Codex behavior synchronized with the Codex Catalog.
- Keep ordinary unit-grant eligibility synchronized with Loot Determination.
- Treat legacy global kin rolling as implementation drift where it bypasses account eligibility.
