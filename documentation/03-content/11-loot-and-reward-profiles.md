---
Title: "Loot and Reward Profile Catalog"
Status: Canonical
Last Updated: 2026-08-01
Owner: Content Design + Systems Design
Depends On:
  - documentation/03-content/08-encounter-templates.md
  - documentation/03-content/09-hazards-and-shrines.md
  - documentation/03-content/10-items-and-consumables.md
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

Define the canonical reward profiles currently attached to run-node outcomes. This document owns authored reward chances, guarantees, currency ranges, progression-item grants, and the distinction between normal rewards and generated encounter effects.

Deterministic randomization, reward materialization, inventory transactions, claim idempotency, XP application, and unlock persistence belong in system or technical documentation.

## Current Reward Profiles

| Node and outcome | XP | Teeth | Unit grant | Die grant | Item grant | Guarantee or special behavior |
| --- | --- | --- | ---: | ---: | --- | --- |
| Loot node | `0` | `8` | `55%` | `80%` | None by default | If neither unit nor die succeeds, one die is guaranteed. |
| Combat victory | Sum of defeated enemy XP | `(5 × difficulty) + 0–5` | `20%` | `35%` | Eligible progression items | No general grant guarantee. |
| Boss victory | Sum of defeated enemy XP | `(5 × difficulty) + 0–5` | `20%` | `35%` | Eligible progression items | No general grant guarantee. |
| Combat or boss defeat | `25%` of full encounter XP, rounded down | `0` | `0%` | `0%` | None | Run defeat behavior is resolved separately. |
| Chaos victory | Sum of defeated enemy XP | `(5 × difficulty) + 0–5` | `0%` in the current normal grant profile | `0%` in the current normal grant profile | None in the current normal grant profile | Chaos bonuses and Raw Chaos are separate gated systems. |
| Rest node | `0` | `0` | `0%` | `0%` | None | Restores the squad to full run health. |
| Hazard node | `0` | `0` | `0%` | `0%` | None | Resolves the selected hazard outcome instead of normal loot. |
| Shrine node | `0` | Effect-defined | `0%` | `0%` | None | Resolves a shrine favor; declineable offers require an accept or decline decision. |

## Unit Grant Pool

A successful unit grant selects one currently unlocked Tier 1 unit type and one currently eligible kin type.

Current Tier 1 unit candidates are defined by the unit type catalog:

- Bruiser
- Guardian
- Marksman
- Bannerbearer
- Saboteur

Current kin eligibility is defined by the kin catalog. This reward profile does not independently unlock unit types or kin types.

## Progression Item Grants

| Defeated enemy condition | Node requirement | Grant |
| --- | --- | --- |
| Encounter contains a pig-family enemy or the Mudking | Combat victory | Pig Ear × `1` |
| Encounter contains a pig-family enemy or the Mudking | Boss victory | Pig Ear × `2` |
| Encounter contains the Mudking | Boss victory | Mudking Crown Fragment × `1` |

Progression-item grants are additive. A Mudking boss victory therefore grants two Pig Ears and one Mudking Crown Fragment.

## Generated Hazard and Shrine Rewards

Hazards and shrines do not use the normal unit, die, or item grant chances.

### Hazard Outcomes

Hazards may:

- damage one unit or the full squad
- remove Teeth
- apply a next-combat stat penalty
- create route pressure
- offer a downside choice
- allow kin-based mitigation

The exact effects and selection weights are defined in the hazard and shrine catalog.

### Shrine Outcomes

Shrines may:

- grant Teeth
- restore run-unit health
- provide next-combat damage or stat bonuses
- double Teeth already earned during the run
- improve a unit found earlier in the run
- clear a future combat node
- offer a declineable sacrifice for squad recovery

The exact effects and selection weights are defined in the hazard and shrine catalog.

## Codex Rewards

Codex pages are independent from normal unit, die, item, and currency grants.

- Each defeated enemy copy in a victorious combat, boss, or chaos encounter has a `13%` deterministic chance to award that enemy's Codex entry when it is not already owned.
- Completing a biome awards the biome Codex entry.
- When a biome entry is first awarded, defeated boss enemies from that completed run are also awarded as Codex entries.

Full category and discovery rules are defined in the Codex catalog.

## Non-Current Named Loot Tables

The following named loot-table records remain in implementation data but do not determine current reward grants:

- `kobold_basic_loot`
- `kobold_boss_loot`
- `frogman_basic_loot`
- `frogman_boss_loot`

Their older contents include fixed Teeth bands, material-specific dice pools, faction-specific unit pools, Roc Egg, and Gator Head. Those values are not canonical reward content because the current reward resolver uses the node-and-outcome profiles defined above.

Encounter templates may still carry these identifiers as inactive metadata. Their presence does not reactivate the old tables.

## Open Questions

- Named loot-table metadata should either be removed from encounter templates or restored as an intentional reward-authoring layer. Maintaining ignored references creates misleading content data.
- Chaos victories currently receive XP and Teeth but are excluded from normal unit, die, and item grant rolls. The intended long-term chaos reward identity needs an explicit decision.
- The four consumables have no canonical acquisition profile.
- Mountains and Swamps have no modern progression-item drops despite older Roc Egg and Gator Head concepts.
- Reward tuning currently applies the same unit and die chances to normal combat and bosses. Bosses may need a distinct guaranteed or elevated profile.

## Maintenance Notes

- Change reward chances and guarantees here before or alongside runtime tuning.
- Keep item grants synchronized with the item catalog.
- Keep encounter difficulty synchronized with the encounter catalog.
- Do not treat inactive named loot tables as content authority.
- Keep grant selection algorithms, deterministic rolls, materialization, and claim handling in system documentation.
- Dice identities and affixes belong in their future dedicated catalogs and are intentionally not defined here.
