# Combat System - Alpha Launch (Authoritative Rework Contract)

Status: active  
Last Updated: 2026-07-24
Owner: Systems Design  
Depends On: `documentation/02-systems-mvp/01-dice-system.md`, `documentation/02-systems-mvp/02-units-and-progression.md`, `documentation/02-systems-mvp/07-combat-math-and-modifiers.md`, `backend/src/Combat/`

This document is the authoritative combat contract for the current rework lane.  
Numeric formulas remain defined in `07-combat-math-and-modifiers.md`.

## 1. Design Goals

The alpha-launch combat system must:
- remain deterministic and replayable
- preserve tactical value in 3x3 positioning
- make ability loadout order a meaningful combat input
- make dice choice matter through ability slots rather than a shared pool
- keep combat logs readable enough to explain timing and slot resolution

## 2. Combat Grid

- Each side uses a fixed 3x3 grid.
- Positions are represented as `{ r: 0|1|2, c: 0|1|2 }`.
- Soft screening remains in effect.
- Front-row and back-row numeric modifiers remain defined in `07-combat-math-and-modifiers.md`.

## 3. Round, Tick, and Scheduling

### 3.1 Timeline
- A round is exactly 20 ticks.
- Tick index is 1 through 20 inclusive.
- Combat still resolves by ordered phases on each tick.

### 3.2 Equipped Ability Scheduling
- Units act through equipped ability instances, not through all authored abilities on their type.
- Each round first walks the equipped loadout once in order.
- Trigger timing is determined by cumulative loadout order.
- For a unit with equipped ability speeds `[a, b, c]`, the firing ticks are:
  - first ability: `a`
  - second ability: `a + b`
  - third ability: `a + b + c`
- If the initial pass leaves unused ticks, the resolver fills the remaining budget with repeatable hostile abilities that still fit, rechecking equipped order from the top each time.
- Self-targeted and ally-targeted utility abilities do not repeat as filler actions.
- An equipped ability only fires if its cumulative tick is 20 or less.
- The schedule resets at the start of the next round.

Examples:
- Loadout `6, 6, 8` fires at ticks `6, 12, 20`
- Loadout `8, 6, 6` fires at ticks `8, 14, 20`
- Loadout `6, 6, 6` fires at ticks `6, 12, 18`
- Loadout `4, 10` with `basic_attack_melee` then `shield_up` fires at ticks `4, 14, 18`
- Loadout `4, 8` with `basic_attack_melee` then `heavy_strike` fires at ticks `4, 12, 16, 20`

### 3.3 Player and Enemy Parity
- Player units and enemies use the same cumulative scheduling model.
- Enemy loadouts are authored per enemy type in data.
- All enemies of the same type use the same authored equipped-ability order.
- No per-enemy-instance combat loadout customization exists in the alpha launch.

## 4. Tick Processing Order

For each tick, phases execute in this exact order:
1. Player Status Phase
2. Enemy Status Phase
3. Neutral Status Phase
4. Player Action Phase
5. Enemy Action Phase
6. Neutral Action Phase

## 5. Same-Tick Ordering Rules

- Multiple actions may resolve on the same tick.
- Within an action phase, units act in ascending `unitId` order unless a later combat-contract revision explicitly changes that rule.
- If one unit has multiple equipped ability instances on the same tick, they resolve in equipped order.
- A dead unit performs no further actions, including actions later in the same tick.

## 6. Ability Scope

### 6.1 Authored Ability Catalog
- Unit types still author the abilities a unit can gain across its promotion path.
- Enemy types author the abilities they can use plus the equipped order they will use in combat.

### 6.2 Combat Participation Rule
- Combat-relevant abilities must be equipped to participate in scheduling.
- Unequipped abilities do not fire.
- Duplicate equips are allowed if the unit remains within the equip budget defined in `02-units-and-progression.md`.

### 6.3 Ability Data Needs
Each combat ability must define enough metadata to resolve:
- speed
- point cost
- slot count
- slot-resolution mode
- targeting behavior
- effect semantics
- whether it is equip-eligible

## 6.4 Expanded Stat Vocabulary

The shared combat stat payload now supports:
- Max HP
- Attack
- Defense
- Precision
- Resolve

Precision and Resolve now participate in combat with conservative first-pass rules. Existing unit and enemy stat JSON that omits them resolves to neutral `5` values. Neutral `5` Precision has no miss chance and no critical-hit chance; neutral `5` Resolve has no status resistance. Speed is not a universal stat; timing remains governed by equipped ability speed costs and the 20-tick scheduler.

## 7. Dice Resolution in Combat

- Combat no longer consumes from a shared per-unit dice pool.
- Dice are resolved from ability slots.
- Empty slots always resolve as `1`.
- If an ability rolls multiple slots separately, slot order is authoritative.
- If the same base ability is equipped multiple times, every copy uses the same configured slot assignments for that base ability.

## 8. Positioning Effects

- Front-row and back-row modifiers remain part of combat.
- Positioning still affects offensive and defensive outcomes as defined in `07-combat-math-and-modifiers.md`.
- This rework changes timing and dice binding, not the existence of positioning as a tactical layer.

## 9. Status Effects

The closed alpha-launch status list remains:
- Poison
- Bolstered
- Sleep
- Bleeding

Global rules:
- statuses are evaluated server-side
- status applications, ticks, and removals are logged
- status timing still resolves in status phases
- harmful status application can be resisted when target Resolve is higher than source Precision
- resistance is deterministic, logged on the action event, and prevents the resisted status from entering status state
- no immunity or cleanse systems are added by this rework

Any further status redesign is out of scope for this combat rework unless explicitly documented elsewhere.

## 10. Battle Logs and Readability

Battle logs must remain readable enough to explain:
- which equipped ability instance fired
- on which tick it fired
- which slot values were used
- when an empty slot contributed `1`
- whether the action came from a player loadout or an enemy authored loadout

## 11. Explicit Non-Goals

This combat rework does not add:
- reaction systems
- terrain systems
- summoning
- multiplayer combat rules
- per-enemy-instance scripted loadout overrides

## 12. Alpha Launch Validation Criteria

Combat is correct for this rework when:
- a 20-tick round reproduces the documented cumulative scheduling examples exactly
- equipped abilities fire once per round based on cumulative order rather than modulo triggers
- player units and enemy units both follow the same scheduling model
- repeated copies of the same equipped ability reuse the same base-ability slot configuration
- empty slots visibly and deterministically resolve as `1`
- combat logs make timing and slot usage understandable to testers
