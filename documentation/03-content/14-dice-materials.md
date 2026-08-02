---
Title: "Dice Material Catalog"
Status: Canonical
Last Updated: 2026-08-02
Owner: Content Design + Systems Design
Depends On:
  - documentation/02-systems/08-dice-material-model.md
  - documentation/02-systems/03-combat-resolution.md
  - documentation/03-content/07-status-effects.md
  - documentation/03-content/11-loot-and-reward-profiles.md
  - documentation/03-content/12-codex-entries.md
Category: 03-content
Tags:
  - content
  - dice
  - materials
  - rarity
  - combat
---

# Dice Material Catalog

## Purpose

Define the canonical initial roster of permanent dice materials. This document owns material identity, rarity, allowed sizes, effect, trigger, stacking, valuation, salvage, tags, enabled state, starter assignment, Codex identity, and legacy-affix disposition.

The Dice Material Identity and Generation document owns generation order, validity rules, effect-resolution boundaries, and migration mechanics. Storage, migrations, behavior handlers, APIs, and presentation implementation belong in technical documentation and code.

## Current Scope

The initial roster contains **32 enabled materials**:

| Rarity | Count |
| --- | ---: |
| Common | 8 |
| Uncommon | 8 |
| Rare | 7 |
| Epic | 5 |
| Legendary | 4 |

The only active sizes are `d4`, `d6`, `d8`, and `d10`.

No material permits `d12`, `d20`, or any other size. Those sizes are not current content and must not be generated, migrated into the target model, displayed as available combinations, or inferred from legacy valuation records.

## Vocabulary

- **Natural roll:** the face result before material rerolls, replacements, or bonuses.
- **Die result:** the value contributed after the material modifies the natural roll.
- **Direct damage:** damage from the resolving active ability, excluding poison ticks, retaliation, reflection, delayed detonation, and other indirect events.
- **Participating die:** a die rolled by the resolving ability.
- **Passive while equipped:** active whenever the die is bound to one of the unit's ability slots.
- **Material Guard:** each point reduces the next incoming direct attack by `1`; all Material Guard is consumed by that attack.

All materials are enabled for standard generation. None is unique or quantity-limited. Duplicate dice are valid subject to each entry's stacking rule.

## Standard Generation Profile

Generation remains size-first. After selecting an active size, an unrestricted source selects rarity using these relative weights:

| Rarity | Weight |
| --- | ---: |
| Common | 60 |
| Uncommon | 25 |
| Rare | 10 |
| Epic | 4 |
| Legendary | 1 |

Within the selected rarity, eligible materials have equal base weight. A source may narrow the pool, but it may not create a material-size pair absent from this catalog.

## Common Materials

| Key | Material | Sizes | Effect and trigger | Stacking | Value | Salvage | Tags |
| --- | --- | --- | --- | --- | ---: | ---: | --- |
| `cardboard` | Cardboard | `d4`, `d6`, `d8`, `d10` | No special combat effect. Explicit neutral baseline. | None. | `1.00x` | `1.00x` | baseline, neutral, starter |
| `bone` | Bone | `d4`, `d6`, `d8`, `d10` | Each participating Bone die adds `+1` final direct damage when its ability deals direct damage. | Additive per die; excludes indirect damage. | `1.05x` | `1.05x` | offense, flat-damage |
| `iron` | Iron | `d6`, `d8`, `d10` | Grants `+1` Defense while equipped. | Additive to `+3` Defense per unit. | `1.05x` | `1.05x` | defense, passive |
| `copper` | Copper | `d4`, `d6`, `d8`, `d10` | An even natural roll adds `+1` to this die result. | Per die; result may exceed side count. | `1.08x` | `1.05x` | roll-control, consistency |
| `clay` | Clay | `d4`, `d6` | A natural `1` contributes `2`. | Per die; replacement, not reroll. | `1.08x` | `1.05x` | roll-control, floor |
| `flint` | Flint | `d4`, `d6`, `d8` | A natural maximum on a direct-damage ability adds `+1` final damage. | Additive per qualifying die. | `1.08x` | `1.05x` | offense, maximum-roll |
| `chalk` | Chalk | `d4`, `d6`, `d8` | Add `+1` to this die result when the ability targets self or an ally and deals no direct damage. | Per die. | `1.05x` | `1.05x` | support, roll-bonus |
| `leather` | Leather | `d4`, `d6`, `d8` | After participating, grant the acting unit `1` Material Guard. | Stacks to `2`; all consumed by next direct attack. | `1.08x` | `1.05x` | defense, temporary-guard |

## Uncommon Materials

| Key | Material | Sizes | Effect and trigger | Stacking | Value | Salvage | Tags |
| --- | --- | --- | --- | --- | ---: | ---: | --- |
| `peach_pit` | Peach Pit | `d4`, `d6` | Heal the acting unit `1` HP after the ability resolves. | Independent per die; cannot exceed max HP. | `1.30x` | `1.20x` | healing, sustain |
| `glass` | Glass | `d8`, `d10` | Grants `+20%` Attack and `-2` Defense while equipped. | Attack caps at `+40%`; Defense penalties add but cannot reduce below `0`. | `1.25x` | `1.20x` | offense, drawback, volatile |
| `lead` | Lead | `d6`, `d8`, `d10` | Grants `+2` Defense and `-10%` Attack while equipped. | Caps at `+6` Defense and `-30%` Attack per unit. | `1.30x` | `1.25x` | defense, drawback, heavy |
| `obsidian` | Obsidian | `d6`, `d8`, `d10` | Direct damage from the containing ability ignores `2` Defense. | Additive to `4` ignored Defense per ability. | `1.35x` | `1.30x` | offense, armor-piercing |
| `rubber` | Rubber | `d4`, `d6` | Reroll a natural minimum once and use the second result. | One reroll per die; second result cannot reroll again. | `1.35x` | `1.25x` | roll-control, reroll |
| `amber` | Amber | `d4`, `d6`, `d8` | Timed statuses applied by the ability last `+1` round. | Non-stacking; maximum `+1` round per status application. | `1.35x` | `1.30x` | status, duration |
| `salt` | Salt | `d4`, `d6`, `d8` | A natural maximum removes one hostile status from the acting unit after resolution. | One removal per qualifying die; cap `2` per ability. | `1.30x` | `1.25x` | cleanse, maximum-roll |
| `brass` | Brass | `d6`, `d8`, `d10` | Grants `+10%` Precision while equipped. | Additive to `+30%` Precision per unit. | `1.30x` | `1.25x` | precision, passive |

## Rare Materials

| Key | Material | Sizes | Effect and trigger | Stacking | Value | Salvage | Tags |
| --- | --- | --- | --- | --- | ---: | ---: | --- |
| `powder_keg` | Powder Keg | `d4`, `d6` | On a natural maximum, roll this die once more and add the result. | Extra roll cannot trigger Powder Keg again. | `1.70x` | `1.55x` | offense, explode, maximum-roll |
| `butchers_tooth` | Butcher's Tooth | `d8`, `d10` | Deal `20%` more final direct damage when the target was below `50%` HP before the action. | Additive to `40%` per ability. | `1.70x` | `1.60x` | offense, execute |
| `bloodstone` | Bloodstone | `d6`, `d8` | Heal the actor for `10%` of the ability's final direct damage, rounded up. | Adds `10%` per die to `30%`; calculate once from combined damage. | `1.80x` | `1.65x` | healing, lifesteal |
| `sporewood` | Sporewood | `d4`, `d6` | A natural maximum on a hostile ability applies `poison` to the primary target at `0.15x` Attack for `2` rounds. | One application per target per ability; additional maxima refresh only. | `1.70x` | `1.60x` | status, poison |
| `moonstone` | Moonstone | `d6`, `d8` | A natural maximum grants the actor `lucky`: `+2` to the next eligible action for up to `2` rounds. | Non-stacking; reapplication refreshes. | `1.75x` | `1.60x` | buff, lucky |
| `rusted_iron` | Rusted Iron | `d6`, `d8`, `d10` | A natural maximum on direct damage applies `cracked_armor` for `2` rounds. | Each qualifying die contributes `-1` Defense, capped at `-2` from the ability. | `1.70x` | `1.60x` | status, armor-break |
| `gold` | Gold | `d8`, `d10` | The first two natural maximum Gold rolls in a battle each add `2` Teeth to the victory reward. | Team-wide cap `4`; defeat grants none; claim is idempotent. | `1.90x` | `1.50x` | economy, maximum-roll |

## Epic Materials

| Key | Material | Sizes | Effect and trigger | Stacking | Value | Salvage | Tags |
| --- | --- | --- | --- | --- | ---: | ---: | --- |
| `porcelain` | Porcelain | `d8`, `d10` | Natural `1` contributes `0` and deals `2` backlash damage to the actor; other results contribute `floor(result × 1.5)`. | Per die; backlash cannot reduce actor below `1` HP. | `2.15x` | `1.95x` | volatile, roll-scaling |
| `diamond` | Diamond | `d8`, `d10` | Direct damage ignores `50%` of target Defense. | Non-stacking. | `2.35x` | `2.10x` | offense, armor-piercing |
| `voidstone` | Voidstone | `d6`, `d8` | Replace natural result `r` with `max(r, sides + 1 - r)`. | Per die; replacement occurs once. | `2.40x` | `2.15x` | roll-control, consistency |
| `phoenix_ash` | Phoenix Ash | `d4`, `d6` | Once per battle, fatal damage leaves the equipped unit at `1` HP. | One shared trigger per unit regardless of copies. | `2.50x` | `2.25x` | survival, passive |
| `clockwork_brass` | Clockwork Brass | `d6`, `d8`, `d10` | A natural maximum reduces the containing ability's next scheduled delay by `2` ticks. | At most once per ability per round; minimum delay `1`. | `2.30x` | `2.10x` | speed, timing |

## Legendary Materials

| Key | Material | Sizes | Effect and trigger | Stacking | Value | Salvage | Tags |
| --- | --- | --- | --- | --- | ---: | ---: | --- |
| `chaos_shard` | Chaos Shard | `d4` | Replace the normal roll with two d4 rolls and add them. If both are `4`, roll one additional d4 and add it. | Internal rolls are one die event; third roll cannot repeat. | `3.25x` | `2.85x` | chaos, burst |
| `living_bone` | Living Bone | `d6`, `d8` | Once per battle when the unit would die, roll its largest equipped Living Bone and revive with HP equal to the result. | All copies share one trigger. | `3.50x` | `3.10x` | survival, revive |
| `star_metal` | Star Metal | `d10` | Natural results `1` through `5` contribute `6`; `6` through `10` are unchanged. | Per die; replacement, not reroll. | `3.40x` | `3.00x` | roll-control, floor |
| `worldroot` | Worldroot | `d8` | After the ability resolves, heal every living ally `1` HP. | Independent per die; cap `2` healing per ally per ability. | `3.45x` | `3.05x` | healing, squad |

## Size Coverage

| Size | Common | Uncommon | Rare | Epic | Legendary | Total |
| --- | ---: | ---: | ---: | ---: | ---: | ---: |
| `d4` | 7 | 4 | 2 | 1 | 1 | 15 |
| `d6` | 8 | 7 | 5 | 3 | 1 | 24 |
| `d8` | 7 | 6 | 5 | 4 | 2 | 24 |
| `d10` | 4 | 4 | 3 | 3 | 1 | 15 |

- `d4` emphasizes repeated triggers, healing, rerolls, survival, and explosive small-die identities.
- `d6` is the broad general-purpose pool.
- `d8` is the broad advanced pool for risk, armor interaction, and squad effects.
- `d10` has fewer low-rarity choices but concentrated offense, defense, economy, timing, and high-floor effects.

## Effect Order

1. Roll the natural face.
2. Resolve material rerolls or internal rolls, such as Rubber or Chaos Shard.
3. Resolve result replacements, such as Clay, Porcelain, Voidstone, or Star Metal.
4. Resolve additive die-result bonuses, such as Copper or Chalk.
5. Combine participating results.
6. Resolve ability scaling and normal stat formulas.
7. Resolve Defense ignoring and final direct-damage modifiers.
8. Apply damage, healing, buffs, debuffs, and other ability results.
9. Resolve after-action effects such as Peach Pit, Salt, Moonstone, Gold, Material Guard, or Worldroot.
10. Resolve scheduling changes and persist idempotent reward markers.

Passive equipped effects are included when combat stats are constructed. Fatal-damage prevention and revival resolve when fatal damage would be applied.

## Interaction Rules

### Explosions

Powder Keg and the global Exploding D4s feature are separate effects. If both apply to one d4 maximum, each adds one non-recursive extra roll. Chaos Shard's internal rolls do not independently trigger other maximum-roll effects.

### Statuses

Sporewood uses `poison`, Moonstone uses `lucky`, and Rusted Iron uses `cracked_armor`. Amber extends only round-based timed statuses; it does not extend consumed-on-use, battle-long, untimed, or internal readiness state.

### Passive Stats

Glass, Lead, Iron, and Brass modify combat stats before scheduling and action resolution. Caps are evaluated per unit across all equipped slots.

### Gold Rewards

Gold creates deterministic battle reward markers. Markers are capped, victory-only, stored with the battle result, and applied once during claim.

## Starter Dice

All starter-equipped dice are:

```text
Cardboard d4
```

Cardboard is the only default material. It is valid on every active die size and has no special effect. Missing material data is invalid and must not be interpreted as Cardboard.

## Codex

Each material defines one Codex page regardless of owned sizes. Owning any valid die using the material discovers the page. The page shows name, rarity, effect, allowed sizes, stacking, value and salvage class, tags, and representative art.

This roster adds **32 canonical material Codex keys**.

## Legacy Affix Disposition

| Legacy affix | Preferred material | Compatibility |
| --- | --- | --- |
| `atk_plus` | Bone | Valid on all active sizes. |
| `guard_plus` | Iron | Use when size is valid; otherwise deterministic same-rarity fallback. |
| `bulwark_plus` | Lead | Use when size is valid; otherwise deterministic same-rarity fallback. |
| `precision_plus` | Brass | Use when size is valid; otherwise deterministic same-rarity fallback. |
| `execute_below_half` | Butcher's Tooth | Only `d8` and `d10`; smaller dice use deterministic Rare fallback. |
| `explode_once` | Powder Keg | Only `d4` and `d6`; larger dice use deterministic Rare fallback. |

Rarity-only dice preserve active size and legacy rarity where an eligible material exists. Starter and explicitly neutral Common dice become Cardboard while preserving their active size. Other choices are deterministic using the die identifier. If a rarity-size pair has no material, use the nearest lower eligible rarity and record the downgrade.

Cardboard is not a universal silent fallback for malformed content. Generation and migration may choose it only through an authored neutral-die rule or an explicit conversion rule.

Multi-affix dice preserve size, owner, and highest valid legacy rarity, then select deterministically from eligible materials. No affix remains as hidden secondary behavior. Removed affixes must be retained only in migration audit data.

No migration rule may create `d12`, `d20`, or another inactive size.

## Balance Review Flags

Focused telemetry is required for Glass, Lead, Gold, Porcelain, Phoenix Ash, Clockwork Brass, Chaos Shard, Living Bone, and Worldroot because they cross stat, economy, timing, multi-roll, fatal-damage, or squad-healing boundaries.

## Reconciliation Requirements

Implementation is aligned when:

- exactly these 32 materials are enabled
- every pair uses only `d4`, `d6`, `d8`, or `d10`
- rarity is derived from material
- generation uses the rarity profile after size selection
- starter dice are Cardboard d4
- Cardboard is valid on every active size and has no special effect
- all caps and once-per-action or once-per-battle limits are enforced
- effects follow the declared order and remain deterministic
- Gold claims are idempotent
- Codex discovery uses these 32 keys
- legacy affixes are inactive after cutover
- no `d12` or `d20` enters current inventory or generation

## Maintenance Notes

- Add a material here before or alongside implementation, art, Codex, and generation eligibility.
- Changing allowed sizes requires an owned-die migration decision.
- Changing rarity affects generation, valuation, and presentation.
- Temporary shrine, hazard, feature, or run effects are not permanent materials.
- Do not add `d12`, `d20`, or another size until the active dice system is deliberately expanded and every affected material is reviewed.