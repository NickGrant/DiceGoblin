# Warband UX Flows and Screen Contracts
----

Status: active  
Last Updated: 2026-04-18  
Owner: UX + Frontend  
Depends On: `frontend/src/scenes/WarbandManagementScene.ts`, `frontend/src/scenes/UnitDetailsScene.ts`, `frontend/src/scenes/SquadDetailsScene.ts`, `documentation/02-systems-mvp/02-units-and-progression.md`

## Purpose
- Define the split warband UX model after the ability-loadout rework.
- Keep squad composition separate from unit-specific editing.
- Make `UnitDetailsScene` the primary editor for naming, loadouts, and ability-slot dice.

## Flow Split

- Flow A: `Unit Details`
  - entry from Warband hub unit list
  - owns unit naming, cumulative ability visibility, equipped loadout editing, and ability-slot dice editing
- Flow B: `Squad Details`
  - entry from Warband hub squad list
  - edits squad membership and 3x3 formation
  - supports squad activation and rename

## Scene Responsibilities

### WarbandManagementScene
- Acts as a hub, not the full editor for every unit subsystem.
- Shows:
  - units list
  - squads list
  - lightweight status summaries
- Unit cards should surface quick-glance information such as:
  - unit name
  - active-squad membership
  - equipped dice summary by ability slot or shorthand
  - empty-slot warnings when useful

### UnitDetailsScene
- Is the primary editing screen for:
  - unit name
  - unlocked ability catalog
  - equipped combat loadout
  - loadout order
  - remaining equip budget out of 20
  - ability-slot dice assignment
- Must make clear:
  - duplicate equipped abilities are allowed
  - repeated copies of the same ability share the same base-ability slot configuration
  - empty slots resolve as `1`
  - starter units already begin with a default equipped setup and common `d4` dice

### SquadDetailsScene
- Owns squad membership and formation only.
- Supports:
  - placing or removing units
  - saving squad state
  - setting active squad
  - renaming squad
- Does not become the main editor for unit abilities or dice.

### DiceInventoryScene
- Remains the inventory surface for choosing dice instances.
- Must route selections back into an ability-slot target, not a generic unit pool.
- Should identify where a die is equipped by:
  - unit
  - ability
  - slot index

## Promotion UX Contract

- Promotion remains a unit-details concern.
- The UI must support:
  - promoted destination choice
  - straight-chain option
  - sideways eligible options
  - blocked options with readable reasons
- The screen should show that previous abilities are retained and new package abilities are added.

## Data Contract Notes

- Squad persistence still uses team endpoints and formation payloads.
- Unit editing contracts now need to support:
  - rename
  - equipped-loadout updates
  - ability-slot dice assignment updates
  - promotion destination selection
- Any legacy "manage dice" contract that assumes unit-wide pool equips is superseded.

## Button Composition Rule

- Screen-side actions should continue using shared button components when practical.
- Shared button composition should not force confusing flows; clarity of unit editing takes priority.
