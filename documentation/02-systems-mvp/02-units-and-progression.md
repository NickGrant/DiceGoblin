# Units and Progression - Alpha Launch (Authoritative Rework Contract)

Status: active  
Last Updated: 2026-04-18  
Owner: Systems Design  
Depends On: `documentation/02-systems-mvp/00-combat-system.md`, `documentation/01-architecture/04-data-model.md`

This document defines the authoritative alpha-launch unit, loadout, naming, and promotion rules for the current rework lane.

## 1. Design Goals

The unit system must:
- preserve tier-based progression
- make unit identity persist across promotions
- let players shape combat through ability loadouts and ability-slot dice
- keep the roster understandable despite branching promotion choices

## 2. Tier Model

- Tier 1, Tier 2, and Tier 3 remain enabled.
- Promotion always advances a unit by one tier.
- Existing tier caps and promotion requirements remain in force.
- Promotion no longer discards the unit's accumulated ability history.

## 3. Unit Identity

Each persistent unit instance now owns:
- its current unit type
- level and xp state
- player-facing name
- cumulative unlocked ability catalog
- equipped combat loadout
- ability-slot dice configuration
- promotion path history

Names are player-facing labels only and must not be used as system identifiers.

## 4. Ability Catalog and Loadout Rules

### 4.1 Unlocked Ability Catalog
- Unit types still author ability packages.
- A unit's accessible combat catalog is cumulative across its promotion path.
- Tier 2 units retain Tier 1 abilities and gain the selected Tier 2 package.
- Tier 3 units retain Tier 1 and Tier 2 abilities and gain the selected Tier 3 package.

### 4.2 Equipped Combat Loadout
- Units equip abilities for combat resolution.
- Each unit has a 20-point equip budget.
- An ability's equip cost is exactly equal to its speed.
- Duplicate equips are allowed.
- No separate hard cap on number of equipped abilities exists beyond the 20-point budget.

### 4.3 Ability Dice Configuration
- Dice are equipped onto the unit's base ability definitions.
- If a unit equips the same ability multiple times, all copies use the same configured slots for that base ability.
- Empty slots always resolve as `1`.

## 5. Starter Unit Rules

- Initial player units must be seeded with a default equipped ability loadout.
- All starter-equipped ability slots must begin with common `d4` dice.
- Starter units should present a valid playable baseline rather than a blank configuration problem.

## 6. Levels and Growth

- Each unit type defines `max_level`.
- Units stop gaining XP at max level.
- Unit types continue to define growth for attack, defense, and max HP.
- Derived stats are recalculated whenever level or unit type changes.

## 7. XP Rules

### 7.1 Sources
- XP is awarded only from combat and boss encounters in the alpha launch.
- Loot and rest nodes do not directly award XP.

### 7.2 Recipients
- A unit participates if it was fielded in the battle and survives to battle end.
- Participating surviving units receive the same encounter XP award.
- Units at max level ignore awarded XP.

### 7.3 Level-Up Timing
- Level-up resolution remains backend-authoritative.
- Auto-level remains tied to rest finalization and run cleanup, not immediate post-battle claim.

## 8. Promotion Rules

### 8.1 Base Requirement
To promote from Tier N to Tier N+1:
- combine 3 units of the same current type and tier
- each consumed unit must be at max level

### 8.2 Promotion Outcome
- The promoted primary unit persists as the continuing identity.
- The unit changes into the selected next-tier destination.
- Level resets to 1.
- Secondary units are consumed.
- Tier 3 promotion still requires the authored rare region item.

### 8.3 Destination Options
On promotion, the player may:
- follow the authored next step in the current chain, or
- promote sideways into another eligible type at the tier being exited

Eligibility rule:
- sideways options are limited by the path the unit has actually traveled
- a unit cannot jump into later branches it has not earned through prior promotion choices

### 8.4 Ability Inheritance
- Promotion preserves all previously accumulated abilities.
- The destination type adds its authored package on top of the inherited catalog.

## 9. Naming Rules

- Units receive generated names when created.
- Players may rename units from the unit-details screen.
- Duplicate names are allowed.
- Names are not referenced by combat, persistence, promotion, or API identity rules.

## 10. Roster Scope

The existing alpha-launch roster count remains in scope unless a later content doc explicitly changes it.
This rework changes how units fight and progress, not the approved roster size by itself.

## 11. Explicit Non-Goals

This rework does not add:
- uniqueness enforcement for names
- cosmetic naming rewards
- promotion skipping across tiers
- per-instance enemy customization
- direct warband-hub editing of every unit system without entering unit details

## 12. Alpha Launch Validation Criteria

The unit and progression system is correct for this rework when:
- units can equip duplicate abilities within a 20-point budget
- cumulative unlocked abilities persist across promotions
- sideways promotion only allows authored eligible branches
- player-facing names persist and remain non-systemic labels
- starter units begin with default equipped abilities and common `d4` slot assignments
