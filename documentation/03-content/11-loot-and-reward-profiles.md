---
Title: "Loot and Reward Profile Catalog"
Status: Canonical
Last Updated: 2026-08-02
Owner: Content Design + Systems Design
Depends On:
  - documentation/03-content/08-encounter-templates.md
  - documentation/03-content/09-hazards-and-shrines.md
  - documentation/03-content/10-items-and-consumables.md
  - documentation/03-content/13-dialogue-and-lore.md
  - documentation/03-content/14-dice-materials.md
  - documentation/02-systems/06-loot-determination.md
Category: 03-content
Tags:
  - content
  - loot
  - rewards
  - runs
---

# Loot and Reward Profile Catalog

## Purpose

Define canonical reward profiles attached to run-node outcomes. This document owns authored chances, guarantees, currency ranges, progression-item grants, dialogue grants, material-generated reward bonuses, and the distinction between normal rewards and generated encounter effects.

Deterministic randomization, reward materialization, inventory transactions, claim idempotency, XP application, and unlock persistence belong in system or technical documentation.

## Current Reward Profiles

| Node and outcome | XP | Teeth | Unit grant | Die grant | Item grant | Special behavior |
| --- | --- | --- | ---: | ---: | --- | --- |
| Loot node | `0` | `8` | `55%` | `80%` | None by default | If neither unit nor die succeeds, one die is guaranteed. |
| Combat victory | Sum of defeated enemy XP | `(5 × difficulty) + 0–5` | `20%` | `35%` | Eligible progression items | Material reward markers may add capped Teeth. |
| Boss victory | Sum of defeated enemy XP | `(5 × difficulty) + 0–5` | `20%` | `35%` | Eligible progression items | Material reward markers may add capped Teeth. |
| Combat or boss defeat | `25%` of full XP, rounded down | `0` | `0%` | `0%` | None | Victory-only material markers grant nothing. |
| Chaos victory | Sum of defeated enemy XP | `(5 × difficulty) + 0–5` | `0%` in normal profile | `0%` in normal profile | None in normal profile | Chaos bonuses and Raw Chaos are separate gated systems. |
| Rest node | `0` | `0` | `0%` | `0%` | None | Restores squad to full run health. |
| Hazard node | `0` | `0` | `0%` | `0%` | None | Resolves hazard outcome instead of normal loot. |
| Shrine node | `0` | Effect-defined | `0%` | `0%` | None | Resolves shrine favor. |
| Dialogue completion | `0` | `0` | `0%` | `0%` | Script-defined | May grant tutorial items or progression. |

## Unit Grant Pool

A successful unit grant selects one unlocked Tier 1 unit type and one eligible kin.

Tier 1 candidates:

- Bruiser
- Guardian
- Marksman
- Bannerbearer
- Saboteur

This profile does not independently unlock unit types or kin.

## Die Grant Identity

A successful die grant selects:

1. one active die size from the source size profile
2. one rarity using the source or standard material rarity profile
3. one enabled material of that rarity that explicitly allows the selected size

The only active sizes are `d4`, `d6`, `d8`, and `d10`. Rewards must not create `d12`, `d20`, materialless dice, independent-rarity dice, or material-plus-affix dice.

The standard material rarity profile and complete material-size matrix are defined in the Dice Material Catalog.

## Progression Item Grants

| Defeated enemy condition | Node requirement | Grant |
| --- | --- | --- |
| Encounter contains a pig-family enemy or Mudking | Combat victory | Pig Ear × `1` |
| Encounter contains a pig-family enemy or Mudking | Boss victory | Pig Ear × `2` |
| Encounter contains Mudking | Boss victory | Mudking Crown Fragment × `1` |

A Mudking boss victory therefore grants two Pig Ears and one Crown Fragment.

## Dialogue Completion Grants

| Dialogue | Repeatability | Permanent progression | Item grants |
| --- | --- | --- | --- |
| `mountains-traveler-consumable-gifts` | One-time | Recorded by the dialogue's seen state | Field Poultice × `1`; Travel Ration × `1` |
| `swamps-wrong-machine-recovered` | One-time | Unlocks Wrong Machine | None |

No other current dialogue grants direct currency, units, dice, items, or features. Dialogue rewards are idempotent.

## Wrong Machine Recovery Reward

- First Bog Tyrant victory opens `swamps-wrong-machine-recovered` before exit.
- Completing it grants Wrong Machine and records the dialogue as seen.
- Standard Swamps completion may verify the feature idempotently.
- Later runs do not grant it again.

## Material-Generated Battle Rewards

Gold is the only initial material that directly modifies a claimed battle reward.

| Material | Trigger | Reward | Cap | Outcome requirement |
| --- | --- | --- | --- | --- |
| `gold` | Natural maximum roll while participating in battle | `+2` Teeth per qualifying marker | First two markers; `+4` Teeth maximum per battle | Victory |

Gold markers are persisted in the deterministic battle result. They are not calculated again during claim and cannot duplicate across retries. They add to normal victory Teeth after the base combat reward is determined.

No other current material directly grants XP, Teeth, units, dice, items, Raw Chaos, or Codex pages as a battle reward. Material Codex discovery is derived from owning the die, not from rolling it.

## Generated Hazard and Shrine Rewards

Hazards and shrines do not use normal unit, die, or item chances.

Hazards may damage units, remove Teeth, apply next-combat penalties, create route pressure, offer downside choices, or allow kin mitigation.

Shrines may grant Teeth, restore health, provide next-combat modifiers, double earned Teeth, improve a found unit, clear a combat node, or offer a declineable sacrifice.

Exact effects and weights are defined in the Hazard and Shrine Catalog.

## Codex Rewards

Codex pages are independent from normal grants.

- Each defeated enemy copy in a victorious combat, boss, or chaos encounter has a `13%` deterministic page chance when not owned.
- Completing a biome awards its page.
- First biome-page award also grants defeated boss pages from that completed run.
- Completing canonical Lore dialogue awards its Lore page.
- Owning a die awards the page for its material.

Full rules are defined in the Codex Catalog.

## Non-Current Named Loot Tables

These implementation records do not determine current grants:

- `kobold_basic_loot`
- `kobold_boss_loot`
- `frogman_basic_loot`
- `frogman_boss_loot`

Their older fixed Teeth bands, old material pools, faction unit pools, Roc Egg, and Gator Head are not canonical. Encounter metadata does not reactivate them.

## Open Questions

- Named loot-table metadata should be removed or deliberately restored as an authoring layer.
- Chaos victories receive XP and Teeth but no normal unit, die, or item rolls.
- Hearty Bone Broth and Sparkroot Tonic lack acquisition profiles.
- Field Poultice and Travel Ration lack renewable acquisition.
- Mountains and Swamps lack modern progression-item drops.
- Combat and boss nodes currently share unit and die grant chances.
- Source-specific material pools, guarantees, and regional weighting remain to be authored if standard unrestricted generation is insufficient.

## Maintenance Notes

- Change reward chances and guarantees here before or alongside tuning.
- Keep item and feature grants synchronized with item, biome, and dialogue catalogs.
- Keep die grants synchronized with the Dice Material Catalog.
- Do not treat inactive loot tables or legacy affix data as authority.
- Keep algorithms, deterministic rolls, materialization, and claim handling in system documentation.
