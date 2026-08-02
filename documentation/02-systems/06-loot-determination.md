---
Title: "Loot Determination"
Status: Canonical
Last Updated: 2026-08-01
Owner: Systems Design + Engineering
Depends On:
  - backend/src/Combat/Engine/DeterministicRunNodeResolver.php
  - backend/src/Controllers/RunNodeController.php
  - backend/src/Services/UserAssetGrantService.php
  - backend/src/Services/ItemInventoryService.php
  - backend/src/Services/RunLifecycleService.php
Category: 02-systems
Tags:
  - systems
---

# Loot Determination

## Purpose

Loot determination describes how node rewards are selected, materialized into user-owned assets, and surfaced for claiming.

## Reward Flow

```mermaid
sequenceDiagram
  participant E as DeterministicRunNodeResolver
  participant C as RunNodeController
  participant U as UserAssetGrantService
  participant I as ItemInventoryService
  participant L as RunLifecycleService

  E->>E: Build reward specs
  E-->>C: unit_grants, dice_grants, item_grants, currency
  C->>U: materialize unit grants
  C->>U: materialize dice grants
  C->>I: materialize item grants
  C->>C: store completed battle and reward snapshot
  L->>L: apply currency and XP when claimed
  L->>L: apply or decline persisted shrine offers
```

Units, dice, and items are materialized when the node is resolved. Currency and XP are applied when the completed battle is claimed.

## Node Reward Defaults

| Node type | Reward behavior |
| --- | --- |
| `loot` | Starts with `8` soft currency and rolls for units/dice. If neither roll succeeds, a dice grant is guaranteed. |
| `combat` | Rolls for units/dice only on victory; can grant progression items from defeated enemies. |
| `boss` | Rolls for units/dice only on victory; can grant progression items from defeated enemies. |
| `chaos` | Uses combat resolution and battle claim behavior, but current reward rolls are not in the combat/boss/loot grant set. |
| `rest` | No loot grant payload. |
| `hazard` | No loot grant payload. |
| `shrine` | No unit/dice/item grants in the normal loot payload; resolves a generated shrine effect that can grant teeth, heal run units, persist next-combat damage/stat modifiers, double earlier run teeth, upgrade a unit gained earlier in the run, or clear an available combat node. Costly shrines surface accept/decline actions before claim-time effects are applied. |

Declining a declineable shrine stores a normal claim snapshot with zero awarded currency, no XP, and no shrine effects. Repeating the claim returns that stored decision rather than reapplying or changing the offer.

## Shrine Tuning Samples

Use `npm.cmd run sim:shrines:docker` to sample generated shrine effect distribution across Farm, Mountains, and Swamps by quality tier. Region-specific shortcuts are also available:

- `npm.cmd run sim:shrines:farm:docker`
- `npm.cmd run sim:shrines:mountains:docker`
- `npm.cmd run sim:shrines:swamps:docker`

The sampler reports effect counts, primitive counts, primitive mix percentages, average soft-currency output, and declineable-offer counts for each quality tier. Add `--format=json` when calling `backend/bin/sample-shrines.php` directly if raw evidence needs to be saved for tuning notes.

## Unit and Dice Grant Chances

| Node type | Unit grant chance | Dice grant chance | Guarantee |
| --- | ---: | ---: | --- |
| `loot` | `55%` | `80%` | At least one die if no unit or die rolled. |
| `combat` victory | `20%` | `35%` | None. |
| `boss` victory | `20%` | `35%` | None. |

The resolver uses deterministic `nextInt(100)` rolls for each chance.

## Unit Grant Selection

```mermaid
flowchart TD
  A[Unit grant roll succeeds] --> B[Load user's unlocked unit type slugs]
  B --> C[Filter unit_types to tier 1 slugs]
  C --> D{Any candidates?}
  D -- no --> E[No unit grant]
  D -- yes --> F[Deterministically pick one]
  F --> G[Roll enabled kin variant]
  G --> H[Grant level 1 tier 1 unit]
```

Unit grants only select from unit types already unlocked for the user in the `unit_type` unlock namespace. If no tier-one unlocked candidate exists, the grant is skipped.

## Dice Grant Selection

```mermaid
flowchart TD
  A[Dice grant roll succeeds] --> B[Load all dice_definitions ordered by id]
  B --> C{Any definitions?}
  C -- no --> D[No dice grant]
  C -- yes --> E[Deterministically pick one]
  E --> F[Use rarity and sides]
  F --> G[Materialize owned die]
```

Dice grant defaults:

| Field | Default |
| --- | --- |
| `rarity` | `common` if missing. |
| `sides` | At least `2`; missing sides default to `6`. |

## Progression Item Grants

Progression items are granted only for victories on `combat` and `boss` nodes.

| Enemy condition | Item grant |
| --- | --- |
| Any enemy slug contains `pig` or `boar`, or equals `mudking` | `pig_ear`, quantity `1` on combat and `2` on boss. |
| Boss node includes enemy slug `mudking` | `mudking_crown_fragment`, quantity `1`. |

Enemy slugs are read from `template_slug`, then `slug`, then `id`.

## Materialization Rules

| Grant type | Materialization behavior |
| --- | --- |
| Unit | Malformed grants are ignored. Slug must be unlocked for the user. Tier is clamped to `1-3`; level is at least `1`; XP starts at `0`; generated display name is used. Runtime grant exceptions are skipped. |
| Dice | Malformed grants are ignored. Rarity defaults to `common`; sides are at least `2`. Runtime grant exceptions are skipped. |
| Item | Slug is required and quantity is at least `1`. Items are granted as inventory stacks. Runtime grant exceptions are skipped. |

## Raw Chaos Gate

`RunLifecycleService` zeroes raw chaos rewards unless the user has the Wrong Machine feature unlock. This gate is applied at battle claim time.
