# Ability Loadout and Unit Naming UX Plan

Status: planning  
Last Updated: 2026-04-17  
Owner: UX + Frontend + Product  
Depends On: `documentation/02-systems-mvp/09-ability-loadout-combat-rework-plan.md`, `documentation/03-ux/02-warband-management.md`

## Purpose
- Define the player-facing UX consequences of the ability/dice/combat rework before scene implementation begins.
- Identify where loadout order, slot binding, promotion choice, and naming must be surfaced clearly.

## Core UX Shifts
- Dice management becomes ability-slot management.
- Combat preparation moves from "which dice are on this unit" to:
  - which abilities are equipped,
  - in what order,
  - with which shared base-ability dice slots.
- Unit identity becomes stickier because names and cumulative abilities persist across promotions.
- Starter units should present a complete default example of the new system, including starter-equipped abilities and common `d4` slot fills.

## Screen Responsibility Changes

### Warband Hub
- Keep as a navigation surface into unit and squad workflows.
- Do not force full ability editing here.
- At most, show summary information:
  - unit name
  - active-squad membership
  - quick at-a-glance loadout state
  - quick at-a-glance empty ability-slot warnings

### Unit Details
- Becomes the primary editing screen for:
  - unit name
  - unlocked ability list
  - equipped combat loadout
  - loadout order
  - 20-point budget visibility
  - per-ability slot dice assignment
- Must show:
  - cumulative inherited abilities
  - ability cost equals speed
  - current total spend out of 20
  - which ability copies share the same slot configuration
  - starter/default loadout clearly enough that a new player can understand the model before editing it

### Inventory
- Must indicate when a die is equipped to:
  - a unit
  - an ability
  - a specific slot index within that ability
- Must no longer imply that dice are equipped to a unit as a generic pool.

### Promotion
- Promotion screen must show:
  - destination choices
  - resulting tier
  - path legality
  - cumulative ability carry-forward
- Player should understand that promotion keeps existing abilities and adds new package abilities.

### Enemy Presentation
- Enemy behavior should be explainable through the same timing model players use.
- Combat logs and encounter-facing UI should support the fact that enemies also use authored cumulative loadouts.

## Interaction Requirements

### Ability Equipping
- Player can equip duplicate abilities.
- Loadout order must be directly editable.
- The UI must make order consequences visible because order changes trigger timing.

### Dice Slot Editing
- Ability slot editing should show:
  - slot count
  - equipped die or empty state
  - shared binding across duplicate equips of the same base ability
- Empty slots should render as explicit placeholders so `1` outcomes are understandable before combat.

### Naming
- Rename interaction belongs in `UnitDetailsScene`.
- Naming should be lightweight:
  - inline prompt, field, or modal are all acceptable
  - no uniqueness checks required
- System references should never depend on the player-facing name.

### Starter Experience
- First-session units should not appear blank or under-configured.
- The first playable state should show:
  - a valid equipped ability order
  - common `d4` dice assigned into the starter ability slots
  - a baseline example the player can modify later

## Readability Requirements
- A player must be able to answer these questions without leaving unit details:
  - What abilities can this unit use?
  - Which ones are equipped?
  - What order will they fire in this round?
  - How many points do I have left?
  - Which dice will each ability use?
  - Which slots are empty and will roll `1`?

## UX Risks to Watch
- Duplicate abilities may confuse players if shared slot binding is not obvious.
- Cumulative inherited abilities may create long lists that need grouping by source tier or promotion step.
- Loadout order can become visually noisy if reorder controls are awkward.
- Promotion choice can become overwhelming if eligible destinations are not framed clearly.

## UX Acceptance Targets
- Renaming a unit feels lightweight and immediate.
- Ability order can be understood at a glance.
- Shared slot binding for duplicate equips is obvious.
- Empty-slot `1` behavior is visible before battle, not only in logs.
- Promotion choices communicate both path freedom and path limits clearly.
- Starter units teach the system by example instead of appearing empty.
