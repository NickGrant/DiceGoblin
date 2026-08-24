---
Title: "Loot and Reward Profile Catalog"
Status: Canonical
Last Updated: 2026-08-23
Owner: Content Design + Systems Design
Depends On:
  - documentation/03-content/08-encounter-templates.md
  - documentation/03-content/09-hazards-and-shrines.md
  - documentation/03-content/10-items-and-consumables.md
  - documentation/03-content/13-dialogue-and-lore.md
  - documentation/02-systems/loot-determination.md
  - documentation/02-systems/kin-reconstruction.md
Category: 03-content
Tags:
  - content
  - loot
  - rewards
  - runs
  - kin
---

# Loot and Reward Profile Catalog

## Purpose

Define canonical reward profiles attached to run-node outcomes. This document owns authored chances, guarantees, currency ranges, progression-item grants, dialogue grants, reconstruction outputs, and the distinction between normal rewards and generated encounter effects.

Deterministic randomization, reward materialization, inventory transactions, claim idempotency, XP application, reconstruction transactions, and unlock persistence belong in system or technical documentation.

## Current Reward Profiles

| Source and outcome | XP | Teeth | Unit grant | Die grant | Item grant | Special behavior |
| --- | --- | --- | ---: | ---: | --- | --- |
| Loot node | `0` | `8` | `55%` | `80%` | None by default | If neither unit nor die succeeds, one die is guaranteed. |
| Combat victory | Sum of defeated enemy XP | `(5 × difficulty) + 0–5` | `20%` | `35%` | Eligible progression items | No general grant guarantee. |
| Boss victory | Sum of defeated enemy XP | `(5 × difficulty) + 0–5` | `20%` | `35%` | Eligible progression items | No general grant guarantee. |
| Combat or boss defeat | `25%` of full XP, rounded down | `0` | `0%` | `0%` | None | Run defeat behavior is resolved separately. |
| Chaos victory | Sum of defeated enemy XP | `(5 × difficulty) + 0–5` | `0%` in normal profile | `0%` in normal profile | None in normal profile | Chaos bonuses and Raw Chaos are separate gated systems. |
| Rest node | `0` | `0` | `0%` | `0%` | None | Restores squad to full run health. |
| Hazard node | `0` | `0` | `0%` | `0%` | None | Resolves hazard outcome instead of normal loot. |
| Shrine node | `0` | Effect-defined | `0%` | `0%` | None | Resolves shrine favor. |
| Dialogue completion | `0` | `0` | `0%` | `0%` | Script-defined | May grant tutorial items or progression. |
| Pig Kin reconstruction | `0` | `0` | Exactly one Pig Kin unit | `0%` | Consumes recipe inputs | Repeatable; first unit also establishes discovery and reward eligibility. |

## Ordinary Unit Grant Pool

A successful normal unit grant selects one unlocked Tier-1 unit type and one account-eligible kin.

Tier-1 candidates:

- Bruiser
- Guardian
- Marksman
- Bannerbearer
- Saboteur

### Current kin eligibility

| Account state | Eligible kin |
| --- | --- |
| Before first Pig Kin ownership | Basic Goblin |
| After first Pig Kin ownership | Basic Goblin, Pig Kin |

Normal reward profiles do not independently discover Pig Kin. The first Pig Kin is produced by the Wrong Machine recipe. Once the player owns a Pig Kin unit, future applicable unit grants may select Pig Kin.

Basic Goblin remains an intentional result after discovery and is not a failed Pig Kin roll.

## Pig Kin Reconstruction Output

The current recipe is:

| Recipe | Inputs | Output | First-ownership addition |
| --- | --- | --- | --- |
| `reconstruct_pig_kin` | Pig Ear × `10`; Mudking Crown Fragment × `1`; Raw Chaos × `25` | One level-1 Tier-1 Pig Kin unit | Pig Kin Codex discovery and ordinary unit-grant eligibility |

Every successful recipe completion creates one unit. Later completions continue creating Pig Kin units and do not repeat first-ownership presentation or Codex awards.

The recipe's Tier-1 unit type is selected from the player's unlocked Tier-1 candidate pool using deterministic standard unit-grant selection. The output kin is always Pig Kin.

## Die Grants

A successful die grant uses the current dice generation contract and authored runtime data for size, rarity, material, and affixes.

The active alpha-launch combat sizes are `d4`, `d6`, `d8`, and `d10`. Materials remain part of die identity, but they do not replace permanent affixes. Rarity continues to control affix capacity, and affixes remain fixed on the die instance after generation.

Until a canonical dice-content catalog is written, use `documentation/02-systems/mvp-reference/01-dice-system.md` together with current dice definition, material, affix, reward, and inventory implementation as the supporting source for die-generation details.

## Progression Item Grants

| Defeated enemy condition | Node requirement | Grant |
| --- | --- | --- |
| Encounter contains a pig-family enemy or Mudking | Combat victory | Pig Ear × `1` |
| Encounter contains a pig-family enemy or Mudking | Boss victory | Pig Ear × `2` |
| Encounter contains Mudking | Boss victory | Mudking Crown Fragment × `1` |

A Mudking boss victory therefore grants two Pig Ears and one Crown Fragment.

These grants continue after Pig Kin is discovered. They are repeatable production inputs, not obsolete unlock tokens.

## First-Reconstruction Tutorial Subsidy

The first Pig Kin reconstruction tutorial may supply only the missing amount required for one recipe:

- Pig Ears up to a total of `10`
- Raw Chaos up to a total of `25`

Already-owned quantities count. The subsidy grants no surplus and does not grant another Crown Fragment because Mudking boss victory already guarantees that catalyst.

The subsidy is one-time and idempotent. The full recipe is still consumed when the unit is produced.

## Dialogue Completion Grants

| Dialogue | Repeatability | Permanent progression | Item grants |
| --- | --- | --- | --- |
| `mountains-llamaver-energy-gift` | One-time | Recorded by the dialogue's seen state | Travel Ration x `1` |
| `mountains-llamaver-health-gift` | One-time | Requires `mountains-llamaver-energy-gift`; recorded by the dialogue's seen state | Field Poultice x `1` |
| `swamps-wrong-machine-recovered` | One-time | Unlocks Wrong Machine | None |

No current dialogue directly creates a Pig Kin unit. The post-recovery Mystic Cave scene introduces reconstruction, while the actual unit is produced by the Wrong Machine transaction.

Other dialogue rewards are idempotent.

## Wrong Machine Recovery Reward

- First Bog Tyrant victory opens `swamps-wrong-machine-recovered` before exit.
- Completing it grants Wrong Machine and records the dialogue as seen.
- Standard Swamps completion may verify the feature idempotently.
- Later runs do not grant it again.
- Wrong Machine ownership enables the Pig Kin reconstruction interface; it does not itself grant Pig Kin.

## Generated Hazard and Shrine Rewards

Hazards and shrines do not use normal unit, die, or item chances.

Hazards may damage units, remove Teeth, apply next-combat penalties, create route pressure, offer downside choices, or allow kin mitigation.

Shrines may grant Teeth, restore health, provide next-combat modifiers, double earned Teeth, improve a found unit, clear a combat node, or offer a declineable sacrifice.

Exact effects and weights are defined in the Hazard and Shrine Catalog.

## Codex Rewards

Codex pages are independent from normal grants except when ownership itself is the discovery condition.

- Each defeated enemy copy in a victorious combat, boss, or chaos encounter has a `13%` deterministic page chance when not owned.
- Completing a biome awards its page.
- First biome-page award also grants defeated boss pages from that completed run.
- Completing canonical Lore dialogue awards its Lore page.
- First acquisition of an affix may award that affix's Codex page through the current acquisition/discovery flow.
- Producing the first Pig Kin unit awards or derives the Pig Kin page.

Later Pig Kin reconstructions do not award duplicate Codex pages.

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

## Maintenance Notes

- Change reward chances and guarantees here before or alongside tuning.
- Keep item and feature grants synchronized with item, biome, dialogue, and reconstruction catalogs.
- Keep ordinary kin eligibility synchronized with Kin Reconstruction and Loot Determination.
- Keep die grants synchronized with the current dice definition, material, and affix contract until a canonical dice-content catalog is created.
- Keep algorithms, deterministic rolls, materialization, reconstruction transactions, and claim handling in system documentation.
