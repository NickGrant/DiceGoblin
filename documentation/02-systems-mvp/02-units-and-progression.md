# Units & Progression — MVP (Authoritative)

Status: active  
Last Updated: 2026-03-04  
Owner: Systems Design  
Depends On: `documentation/02-systems-mvp/03-encounter-scope.md`, `documentation/01-architecture/04-data-model.md`


This document defines the authoritative MVP unit roster scope and authoritative unit progression rules.

Any unit, role, tier, promotion path, or progression mechanic not listed here is out of scope for MVP.

## 1. Design goals

The MVP unit system must:
- Validate tier-based progression (Tier 1 to Tier 3)
- Exercise frontline vs backline positioning
- Support core combat roles
- Minimize content overhead while validating the full loop

## 2. Tier model

- Tiers function similarly to evolution
- Promotions replace the previous unit; the result is a new unit instance
- Promotions reset level to 1 and increase base stat floors
- Each unit type defines its own max level

Enabled tiers in MVP:
- Tier 1: enabled
- Tier 2: enabled
- Tier 3: enabled

## 3. Supported roles (closed list)

1) Frontline Melee (Tank or Bruiser)
2) Backline Ranged (DPS)
3) Support
4) Control

No hybrid or branching roles in MVP.

## 4. MVP roster scope (exact counts)

Promotion targets:
- One Frontline Melee chain: Tier 1 → Tier 2 → Tier 3
- One Backline Ranged chain: Tier 1 → Tier 2 → Tier 3

Tier 2 cap:
- One Support chain: Tier 1 → Tier 2 (caps at Tier 2)
- One Control chain: Tier 1 → Tier 2 (caps at Tier 2)

Total unit types:

| Role             | Tier 1 | Tier 2 | Tier 3 | Total |
|------------------|--------|--------|--------|-------|
| Frontline Melee  | 2      | 2      | 2      | 6     |
| Backline Ranged  | 1      | 1      | 1      | 3     |
| Support          | 1      | 1      | —      | 2     |
| Control          | 1      | 1      | —      | 2     |
| **Total**        |        |        |        | **13**|

The MVP roster contains exactly 13 unit types.

## 5. Abilities per unit type (MVP)

Each unit type has:
- 2 active abilities
  - one must be the unit’s base attack
  - one must be a specialty action
- up to 2 passive traits

No unit exceeds 4 total abilities in MVP.

## 6. Levels and stat growth

- Each unit type defines `max_level`
- Units do not gain XP once they reach max level
- UnitType defines per-level growth for `attack`, `defense`, `max_hp`

Derived stats are recalculated whenever level changes.

## 7. Gaining XP (MVP)

### 7.1 XP sources

XP is awarded only for Combat and Boss encounters in MVP.
Loot and Rest nodes do not award XP.

### 7.2 Who receives XP

A unit participates if it was fielded in the battle and is not defeated at battle end.
All participating surviving units receive the same XP award.

The award is the sum of `xp_reward` for each enemy in the encounter.
If a unit is at max level, the award is ignored for that unit.

## 8. Spending XP (level-up math)

XP is tracked per unit as progress-within-current-level.

For a unit at tier `T` and level `L`, XP to advance from `L → L+1`:

`xp_to_next = T * (L + 1) * 50`

Level-up loop:
- While `level < max_level` and `xp >= xp_to_next`:
  - subtract `xp_to_next`
  - increment `level`
  - recompute `xp_to_next`

### 8.1 Auto-level timing (authoritative)
- Level-up calculations are backend-authoritative.
- Auto-level is applied:
  - when a rest workflow is finalized,
  - during run cleanup (completed/failed/abandoned).
- Auto-level is not applied on every battle claim.

## 9. Promotions (tier advancement)

### 9.1 Promotion requirement

To promote from Tier N to Tier N+1:
- Combine 3 units of the same unit type at the same tier
- Each consumed unit must be at its unit type’s max level

### 9.2 Promotion outcome

- Promotion is a manual action available:
  - between runs,
  - or during an open rest workflow in an active run.
- Request model:
  - one primary unit id (persisted),
  - two secondary unit ids (consumed).
- The primary unit is upgraded to the next tier and reset to level 1.
- Secondary units are removed.

Power targets (guidance):
- Tier 2 level 1 is roughly comparable to Tier 1 level 4–5
- Tier 3 level 1 is below Tier 1 max; Tier 3 scales via higher cap and growth

### 9.3 Tier 3 special requirement

Tier 3 promotion additionally requires a rare, region-specific item.

### 9.4 Promotion constraints
- Promotion can occur during active runs only while resolving an open rest workflow.
- Secondary unit ids must be distinct.
- All involved units must satisfy promotion qualifications and must not be part of active run snapshots.

## 10. Explicit non-goals (MVP)

The MVP unit system does not include:
- Branching promotion paths
- Hybrid roles
- Unit-specific dice restrictions
- Cosmetic variants tied to mechanics

## 11. MVP validation criteria

Units and progression are MVP-complete when:
- A player can promote at least one unit to Tier 2
- A player can attempt a Tier 3 promotion
- Frontline and backline positioning materially affects outcomes
- Support and Control provide non-damage value
