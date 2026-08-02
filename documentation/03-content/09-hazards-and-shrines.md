---
Title: "Hazard and Shrine Catalog"
Status: Canonical
Last Updated: 2026-08-01
Owner: Content Design
Depends On:
  - documentation/03-content/06-biomes-and-regions.md
  - documentation/03-content/07-status-effects.md
Category: 03-content
Tags:
  - content
  - hazards
  - shrines
  - runs
---

# Hazard and Shrine Catalog

## Purpose

Define the canonical hazards and goblin shrines that can appear during runs. This document owns effect identity, display text, eligible regions, severity or quality bands, minimum depth, selection weights, authored outcomes, and active status.

Run-map placement, weighted selection algorithms, persistence, claim processing, and deterministic randomization belong in system documentation.

## Reading the Catalog

- Hazard severity uses `minor`, `moderate`, and `severe`.
- Shrine quality uses `poor`, `good`, and `great`.
- Weights are relative within an eligible region and severity or quality band.
- “All regions” means The Farm, Mountains, and Swamps. Mystic Cave currently has no hazards or shrines.
- Next-combat modifiers expire after the next eligible combat.

## Hazards

| Key | Display name | Regions | Severity weights | Minimum depth | Authored outcome | Status |
| --- | --- | --- | --- | ---: | --- | --- |
| `hazard_cautious_footing` | Cautious Footing | Farm, Mountains, Swamps | Minor `8`; Moderate `4` | 3 | Applies route pressure: the squad slows down and loses momentum on the route. | Active |
| `hazard_mud_slick` | Mud Slick | Farm | Minor `6`; Moderate `5` | 3 | `-2 Precision` for the next combat. | Active |
| `hazard_broken_fence` | Broken Fence | Farm | Minor `4` | 4 | Applies route pressure through a slow detour. | Active |
| `hazard_splintered_trap` | Splintered Trap | Farm, Mountains | Minor `5`; Moderate `3` | 3 | Deals `5` damage to one random run unit. | Active |
| `hazard_bad_rations` | Bad Rations | Farm, Swamps | Minor `4`; Moderate `4` | 3 | `-2 Resolve` for the next combat. | Active |
| `hazard_loose_scree` | Loose Scree | Mountains | Minor `4`; Moderate `6`; Severe `3` | 4 | Deals `3` damage to every run unit. | Active |
| `hazard_thin_air` | Thin Air | Mountains | Moderate `5`; Severe `4` | 5 | Multiplies Resolve by `0.85` for the next combat. | Active |
| `hazard_toll_cairn` | Toll Cairn | Mountains | Minor `3`; Moderate `5` | 4 | Removes `6` Teeth. | Active |
| `hazard_rust_thicket` | Rust Thicket | Mountains, Swamps | Moderate `4`; Severe `3` | 5 | Multiplies Attack by `0.90` for the next combat. | Active |
| `hazard_bog_mire` | Bog Mire | Swamps | Minor `5`; Moderate `4` | 4 | Deals `4` damage to one random run unit; Pig Kin can mitigate the hazard. | Active |
| `hazard_biting_reeds` | Biting Reeds | Swamps | Minor `4`; Moderate `6`; Severe `3` | 3 | Deals `2` damage to every run unit. | Active |
| `hazard_sinking_cache` | Sinking Cache | Swamps | Moderate `3` | 5 | Removes `8` Teeth. | Active |
| `hazard_wrong_turn` | Wrong Turn | Mountains, Swamps | Moderate `2`; Severe `3` | 6 | Multiplies Defense by `0.85` for the next combat. | Active |
| `hazard_black_gnats` | Black Gnats | Swamps | Minor `5`; Moderate `4` | 3 | `-1 Precision` and `-1 Resolve` for the next combat. | Active |
| `hazard_collapse_warning` | Collapse Warning | Mountains, Swamps | Severe `0` | 6 | Offers a choice between `10` squad damage and losing `10` Teeth. | Authored, inactive |

### Hazard Families

| Family | Current entries | Content purpose |
| --- | --- | --- |
| HP attrition | Splintered Trap, Loose Scree, Bog Mire, Biting Reeds | Erode run-unit health outside combat. |
| Temporary modifier | Mud Slick, Bad Rations, Thin Air, Rust Thicket, Wrong Turn, Black Gnats | Change the next combat without permanently changing units. |
| Currency pressure | Toll Cairn, Sinking Cache | Trade run safety or progress for Teeth loss. |
| Route pressure | Cautious Footing, Broken Fence | Represent path and momentum setbacks. |
| Kin mitigation | Bog Mire | Give a canonical kin an environmental advantage. |
| Choice pressure | Collapse Warning | Present a direct downside choice; not currently selected. |

## Shrines

| Key | Display name | Regions | Quality weights | Authored outcome | Cost or choice | Status |
| --- | --- | --- | --- | --- | --- | --- |
| `shrine_bone_whisper` | Bone Whisper | Farm, Mountains, Swamps | Poor `8`; Good `5`; Great `3` | Grants `4–8` Teeth. | None | Active |
| `shrine_rust_blessing` | Rust Blessing | Farm, Mountains, Swamps | Poor `7`; Good `5`; Great `3` | Grants `4–8` Teeth. | None | Active |
| `shrine_clean_water` | Clean Water | Farm, Swamps | Poor `4`; Good `6`; Great `5` | Restores `35%` HP to one random wounded run unit. | None | Active |
| `shrine_old_goblin_mark` | Old Goblin Mark | Farm, Mountains, Swamps | Good `4`; Great `6` | Multiplies squad damage by `1.10` for the next combat. | None | Active |
| `shrine_stone_hide` | Stone Hide | Mountains, Swamps | Good `3`; Great `5` | Multiplies Defense by `1.25` for the next combat. | None | Active |
| `shrine_clear_eye` | Clear Eye | Farm, Mountains, Swamps | Good `3`; Great `4` | Grants `+2 Precision` and `+2 Resolve` for the next combat. | None | Active |
| `shrine_hidden_footpath` | Hidden Footpath | Mountains, Swamps | Good `3`; Great `5` | Clears one available combat node from the current run. | None | Active |
| `shrine_bog_luck` | Bog Luck | Swamps | Good `2`; Great `6` | Doubles the Teeth already earned during the run. | None | Active |
| `shrine_borrowed_future` | Borrowed Future | Farm, Mountains, Swamps | Great `4` | Raises one unit found earlier in the run by one tier, to a maximum of Tier 3. | None | Active |
| `shrine_crooked_bargain` | Crooked Bargain | Mountains, Swamps | Good `2`; Great `5` | Drains `50%` of the healthiest unit's current life to restore the rest of the squad. | Player may decline | Active |

### Shrine Families

| Family | Current entries | Content purpose |
| --- | --- | --- |
| Currency favor | Bone Whisper, Rust Blessing, Bog Luck | Increase Teeth immediately or amplify earlier run earnings. |
| Recovery | Clean Water, Crooked Bargain | Restore run-unit health, sometimes at a cost. |
| Next-combat blessing | Old Goblin Mark, Stone Hide, Clear Eye | Temporarily strengthen the squad. |
| Route manipulation | Hidden Footpath | Remove a future combat obstacle. |
| Reward transformation | Borrowed Future | Improve a unit acquired during the current run. |

## Player-Facing Copy

| Key | Result copy |
| --- | --- |
| `hazard_cautious_footing` | The squad slows down and loses momentum on the route. |
| `hazard_mud_slick` | Mud gums up the squad before the next fight. |
| `hazard_broken_fence` | The route snarls into a slow detour. |
| `hazard_splintered_trap` | A crude trap bites one unlucky unit. |
| `hazard_bad_rations` | Something disagrees with the squad before the next fight. |
| `hazard_loose_scree` | The slope gives way under the warband. |
| `hazard_thin_air` | The climb steals breath before the next fight. |
| `hazard_toll_cairn` | The stones take their old road-price. |
| `hazard_rust_thicket` | Rusty growth dulls the squad before the next fight. |
| `hazard_bog_mire` | The mire pulls at the squad until somebody hauls them loose. |
| `hazard_biting_reeds` | The reeds rake across every exposed ankle. |
| `hazard_sinking_cache` | Salvage sinks into the muck before the squad can grab it. |
| `hazard_wrong_turn` | The wrong path leaves the squad exposed before the next fight. |
| `hazard_black_gnats` | A biting cloud breaks the squad focus. |
| `hazard_collapse_warning` | A bad passage demands a hard choice. |
| `shrine_bone_whisper` | The bones clatter into a useful omen. |
| `shrine_rust_blessing` | The shrine leaves a dull glint of favor behind. |
| `shrine_clean_water` | A clean sip steadies one wounded goblin. |
| `shrine_old_goblin_mark` | An old mark sharpens the next fight. |
| `shrine_stone_hide` | A slab-mark settles over the squad before the next fight. |
| `shrine_clear_eye` | The next fight looks a little less crooked. |
| `shrine_hidden_footpath` | A hostile patrol gets lost before the warband reaches it. |
| `shrine_bog_luck` | The swamp doubles what the run has already shaken loose. |
| `shrine_borrowed_future` | A newly found goblin comes back sharper than before. |
| `shrine_crooked_bargain` | The healthiest goblin pays for everyone else to stand tall. |

## Open Questions

- Collapse Warning is fully authored but has zero selection weight. It should either be activated with deliberate tuning or removed from the current catalog.
- Route-pressure hazards currently communicate a setback without a distinct canonical numeric consequence. Their intended gameplay effect needs a durable definition.
- Pig Kin mitigates Bog Mire, but no other current kin has a biome interaction. Future kin mitigation should be authored in both catalogs.
- Bone Whisper and Rust Blessing currently have identical numeric rewards. Their separate identities may need differentiated effects.

## Maintenance Notes

- Add or revise an effect here before or alongside run-generation changes.
- Treat weights, eligibility, and numeric outcomes as authored content values.
- Keep temporary combat effects synchronized with the status and combat documentation.
- Keep claim-time processing and deterministic selection in system documentation.
- Do not make a zero-weight entry player-obtainable without changing its content status here.
