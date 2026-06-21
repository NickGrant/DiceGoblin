# Units and Progression

Status: active  
Last Updated: 2026-06-21  
Owner: Systems Design + Engineering  
Depends On: `documentation/01-architecture/04-data-model.md`, `documentation/02-systems-mvp/01-dice-system.md`, `documentation/02-systems-mvp/12-academy-and-feature-unlocks.md`

## Purpose

- Define how persistent units currently progress in the alpha build.
- Document the live rules for levels, promotions, capstones, loadouts, and run locks.

## Unit Record

Each unit instance persists as a player-owned record with:

- name
- unit type and tier
- level and current-level XP
- derived combat stats
- unlocked ability history
- equipped combat loadout
- equipped dice and per-ability slot dice
- capstone and inherited passive state

Unit names are cosmetic labels only. They are not identifiers.

## Tier and Roster Model

- The game currently uses Tier I, Tier II, and Tier III player units.
- Promotion always advances one tier at a time.
- The promoted primary unit persists as the continuing identity.
- Secondary units are consumed during promotion.

Current documented roster coverage in the guide includes:

- Tier I starters: Bruiser, Guardian, Marksman, Bannerbearer, Saboteur
- Tier II branches: Enforcer, Pit Fighter, Bulwark, Shieldbreaker, Deadeye, Trapper, Warcaller, Mascot, Trickshot, Plaguehand
- Tier III examples currently surfaced in the guide: Juggernaut, Ironwall, Sharpshot

## Levels and XP

- Each unit type defines `max_level`.
- XP is progress within the current level, not a lifetime total.
- Units stop gaining XP when they reach max level.
- Combat and boss encounters are the active XP sources.
- Loot and rest nodes do not directly grant combat XP.

## XP Timing

- Combat resolution stores the result and reward preview first.
- XP is formally applied during battle reward claim.
- Rest finalization runs auto-level processing for any stored XP progress.
- Successful run completion and run cleanup preserve earned XP unless a specific failure rule resets it.

## Ability Inventory and Loadout Rules

### Unlocked Abilities

- Units track unlocked abilities separately from equipped abilities.
- Promotion grants additional abilities based on the destination unit type.
- Promotion can also preserve inherited passive abilities from earlier classes.

### Equipped Combat Loadout

- Combat uses the equipped ability list, not the full unlocked catalog.
- Equipped abilities are ordered.
- Duplicate equips are supported by the current system.
- Each equipped entry stores its own `speed_cost`.

### Ability-Slot Dice

- Units can socket dice into authored ability slots.
- Missing slot dice resolve through normal fallback behavior rather than blocking use.
- Ability-slot dice are part of the persistent unit build and can be changed outside an active run.

## Starter Expectations

- Starter and recruited units are expected to remain immediately usable.
- The game seeds baseline combat-ready units rather than forcing blank build setup before first use.

## Promotion Eligibility

Promotion is currently driven by authored `promotion_level`, not only by max level.

- A unit is `promotion_eligible` when:
  - its unit type exposes a `promotion_level`
  - its current level is at least that `promotion_level`
  - it is below its maximum tier
- The academy UI currently requires:
  - one primary unit
  - two additional units of the same current type and tier
  - all three units to be promotion-eligible

In practice, many Tier I classes can promote early at level 6 and can still continue leveling to 10 to master the class first.

## Promotion Flow

- The player selects a promotable primary unit.
- The player selects exactly two same-type, same-tier secondary units to consume.
- The player selects a destination from the available promotion options.
- The promoted unit resets to level 1 in the new tier.
- The primary unit identity persists.

## Promotion Options

The current implementation supports two option modes:

- `chain`: continue down the current family line
- `sideways`: branch into another unlocked or previously progressed family line

Sideways branching is not fully open-ended. It depends on:

- the unit's own promotion-family history
- the player's unlocked Tier I families
- authored unit-type progression by family and tier

## Capstones and Mastery

- A unit is considered mastered when it reaches its class max level.
- Some unit types expose capstone choices.
- Current capstone states are:
  - `none`
  - `unearned`
  - `ready_to_select`
  - `selected`

Current behavior:

- Promoting early can skip the current class capstone.
- A mastered unit with pending capstone choice must choose a capstone before promotion if the state is `ready_to_select`.
- A selected capstone carries forward into later promotions.

## Active Run Mutation Lock

Units that are part of the active run snapshot cannot be mutated until the run ends.

This lock applies to:

- promotion
- capstone selection
- equipment changes
- ability loadout changes
- ability-slot dice changes

## Validation Rules

The current alpha implementation is aligned when:

- promotion eligibility is based on authored promotion level
- promotion consumes two secondary same-type units plus the primary
- promotion can happen before max level for eligible classes
- mastered classes can lock in capstones before promotion
- active run units cannot be changed until the run ends
