---
Title: "Warband, Unit, Squad, and Dice UX Contract"
Status: Canonical
Last Updated: 2026-08-01
Owner: Product + UX
Depends On:
  - documentation/02-systems/mvp-reference/01-dice-system.md
  - documentation/02-systems/mvp-reference/02-units-and-progression.md
  - documentation/05-technical/02-frontend-state-and-scene-contracts.md
Category: 04-ux
Tags:
  - ux
---

# Warband, Unit, Squad, and Dice UX Contract

## Purpose

- Define the canonical management UX outside and around runs.
- Keep warband, unit, squad, promotion, and dice behavior in one place.
- Make it clear which screen owns which player decisions.

## Surface Split

- Warband hub:
  - browse units and squads
  - jump into deeper editing flows
- Unit details:
  - inspect and edit one unit
- Squad details:
  - edit one saved squad
- Dice inventory:
  - browse owned dice and handle equip or sell flows

## Warband Hub

The warband page is a hub, not a catch-all editor.

It should show:

- unit list
- squad list
- active squad state
- lightweight status summaries

Unit cards should communicate at a glance:

- unit name
- role or archetype
- active-squad membership when relevant
- quick equipped-dice or loadout summary
- warnings when a loadout is incomplete

## Unit Details

Unit details is the primary unit management screen.

It owns:

- unit name
- unit identity and role
- tier, level, XP, and max-level state
- unlocked ability catalog
- equipped combat loadout
- loadout ordering
- ability-slot dice assignment
- promotion decisions

It must make these rules obvious:

- duplicate equipped abilities are allowed
- duplicate copies of the same base ability share the same slot configuration
- empty ability slots resolve as `1`
- starter units begin with a complete usable default setup

### Required Information

- display name
- role or archetype label
- tier
- level
- XP
- max-level state
- equipped dice summary
- ability inventory grouped in a stable way

### Run-Scoped Overlay

When a run is active, unit details should also show read-only run data:

- current HP
- current status effects
- defeated state

If run state prevents editing, the screen should stay readable and explain why actions are disabled.

## Squad Details

Squad details owns saved-squad editing only.

It supports:

- squad rename
- squad membership edits
- 3x3 formation editing
- squad activation
- save and validation feedback

The screen does not become the main editor for unit abilities, promotion, or dice details.

Formation language should stay consistent with combat orientation:

- left is `Back`
- right is `Front`

## Dice Inventory

Dice inventory is the dedicated dice-management surface.

It must show:

- die size
- rarity
- slot capacity
- affix list
- whether a die is equipped
- where an equipped die is assigned

Equip context should identify:

- unit
- ability
- slot index

The inventory should never imply that dice are attached to a unit as a generic shared pool.

## Promotion Contract

Promotion belongs to unit details.

The UI must support:

- valid destination choices
- blocked choices with reasons
- visibility into resulting tier
- visibility into retained and newly added abilities

Promotion availability rules:

- enabled between runs when requirements are met
- disabled for units in an active run

## Management Windows

### Between Runs

Allowed:

- squad membership and formation edits
- unit promotion
- dice equip and unequip
- dice selling

### During Active Run

Allowed:

- read-only inspection

Blocked:

- squad mutation
- promotion
- dice mutation

Messaging:

- explain that active-run units cannot be changed until the run ends

## Shared UX Rules

- desktop and mobile layouts must preserve readability of all required fields
- loading, empty, and error states should be explicit
- blocked actions should be disabled or explained directly
- success and failure feedback should appear immediately after save, activation, sale, equip, or promotion actions
