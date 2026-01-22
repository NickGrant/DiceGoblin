# Units & Progression

## Unit Levels
- Each unit type defines its own max level cap (`max_level`)
- Stats increase per level using a unit-type growth model (attack/defense/max HP)
- Units do not gain XP once they reach max level

## Level-Based Stat Growth (MVP)
Each unit type defines:
- `max_level`
- Growth per ability per level for:
  - `attack`
  - `defense`
  - `max_hp`

Derived stats MUST be recalculated whenever level changes.

## Quality / Tier System
Internal-only naming:
- Tier 1
- Tier 2
- Tier 3

Flavor titles are unit-specific and cosmetic.

## Gaining Levels
- Levels are gained by spending XP (XP is tracked as progress-within-current-level)
- The only way a unit's level goes down is when it gets reset as part of promotion
- XP cost to advance from level L → L+1 is:
  - `xp_to_next = tier * (L + 1) * 50`
- Level-up is performed by repeatedly spending XP while:
  - `level < max_level` and `xp >= xp_to_next`
- Units stop gaining XP once `level == max_level`

## Gaining XP
- XP is tracked per unit (`unit_instances.xp`) as progress-within-current-level
- XP is awarded only for Combat and Boss encounters
- "Participate" means: the unit was fielded in that battle and was not defeated at battle end
- Awarded XP is the sum of `xp_reward` from each enemy in the encounter
- All surviving participating units receive the same awarded XP (not split by squads/teams)
- If a unit is at max level, it receives no XP (award is ignored)

## Tier Advancement
- Combine 3 max-level units to advance tier
- Tier advancement:
  - Resets level to 1
  - Increases base stat floor
  - Tier 2 L1 ≈ Tier 1 L4–5 (slightly stronger)
  - Tier 3 L1 < Tier 1 max

## Special Requirements
- Tier 3 advancement requires:
  - Units + rare, region-specific item
