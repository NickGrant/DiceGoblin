---
Title: "Warband and Formation"
Status: Canonical
Last Updated: 2026-08-20
Owner: Systems Design + Engineering
Depends On:
  - documentation/02-systems/combat-resolution.md
  - documentation/02-systems/target-resolution.md
  - documentation/04-ux/02-warband-management.md
  - backend/src/Controllers/TeamController.php
  - backend/src/Repositories/TeamRepository.php
  - backend/src/Services/SquadCapacityService.php
  - backend/src/Support/FormationGeometry.php
Category: 02-systems
Tags:
  - systems
  - warband
  - squads
  - formation
  - positioning
---

# Warband and Formation

## Purpose

Warband management determines how owned units are organized into saved squads and how each squad places its members on the 3x3 combat formation grid.

The player-facing term is **squad**. Current backend and database code commonly use **team** for the same persisted concept (`teams`, `team_units`, and `team_formation`).

## Squad Model

A player can own multiple saved squads.

Each squad has:

- an id
- a player-facing name
- membership containing zero or more owned unit instances
- a formation describing where squad members occupy the 3x3 grid
- active/inactive state

Membership and formation are persisted separately. A unit may belong to the squad without having a valid occupied formation entry until the configuration is completed.

Only one squad is active for a player at a time. Activating one squad clears the active flag from the player's other squads.

## Squad Names and Lifecycle

Squad names:

- are required
- are trimmed before persistence
- may contain at most 64 characters

A player cannot delete their only remaining squad.

The backend also prevents deleting the currently active squad while a run is active.

## Squad Capacity

Squad capacity is account progression.

| State | Maximum members |
| --- | ---: |
| Default | `4` |
| Bigger Squad feature unlocked | `6` |
| Biggerest Squad feature unlocked | `9` |

If both capacity upgrades are present, the highest cap applies.

The backend enforces the current cap when a full squad configuration is updated.

## Membership Rules

A squad may contain only unit instances owned by the same player.

When a complete squad configuration is saved:

- duplicate member ids are normalized away
- every member id is ownership-validated
- formation entries may reference only units included in the submitted membership

Removing a unit from a squad also clears that unit from the squad's formation.

Unit consumption during promotion removes consumed units from squad membership and formation as part of the promotion transaction.

## Formation Grid

The formation is a 3x3 grid with these canonical cell ids:

```text
A1  A2  A3
B1  B2  B3
C1  C2  C3
```

Valid cells are exactly `A1` through `C3`.

Player-facing orientation is:

- left = **Back**
- right = **Front**

This orientation must remain consistent anywhere formation, targeting, or combat position is explained.

## Unit Footprints

Most units occupy one cell, but the formation system supports larger rectangular footprints.

A unit type's footprint comes from its base stats through either:

- `formation: { w, h }`
- legacy `formation_width` and `formation_height` values

Width and height are normalized to the range `1..3`.

For a unit occupying more than one cell:

- all occupied cells must form the exact rectangle implied by its footprint
- the footprint must remain entirely inside the 3x3 grid
- the unit cannot overlap another unit

The formation anchor is derived from the top-left-most occupied cell in grid coordinates, and the expected footprint is validated from that anchor.

## Formation Validation

A complete formation update is rejected when:

- a cell id is invalid
- a formation references a unit not included in squad membership
- two different units occupy the same cell
- a multi-cell unit's submitted cells do not exactly match its rectangular footprint
- a footprint would extend beyond formation bounds

The backend normalizes and stores occupied formation rows in cell order after validation.

## Combat Position Profile

Combat converts each unit's occupied footprint into a position profile:

- `front_x` — furthest occupied position toward the front
- `back_x` — furthest occupied position toward the back
- `top_y` — topmost occupied row
- `bottom_y` — bottommost occupied row

Position-sensitive rules use this full footprint rather than treating a large unit as though it occupied only its anchor cell.

A unit is treated as occupying the front row when its footprint reaches the combat engine's front edge. It is treated as occupying the back row when its footprint reaches the back edge.

## Position and Targeting

Formation is a direct input to automatic target resolution.

Abilities with front preferences favor candidates whose occupied footprint reaches furthest toward the front. Back preferences favor candidates whose footprint reaches furthest toward the back.

Formation does not create a generic rule that all attacks must hit the front row first. The authored target preference on each ability determines whether front/back position matters for target choice.

See `target-resolution.md` for scoring and forced-target precedence.

## Position and Damage

Current combat applies several direct positional damage modifiers.

For normal ability damage:

- a melee attacker whose footprint reaches the front row deals `1.10x` positional damage
- a target whose footprint reaches the front row takes `1.10x` positional damage
- a melee attack against a target whose footprint reaches the back row receives a `0.90x` positional multiplier

These multipliers can combine when more than one condition applies.

Formation-specific passive abilities can add further rules. Those ability-specific effects belong to ability/content documentation; the formation system provides the spatial inputs they consume.

## Active Squad and Runs

The active squad is the squad selected for current gameplay use. Run creation and run snapshots consume squad/unit configuration as authoritative input for the run.

Unit-level mutations such as promotion and ability/dice loadout changes are guarded while affected units are locked into an active run snapshot.

### Known cross-layer drift: squad mutation during a run

The canonical UX contract states that squad membership and formation editing should be blocked during an active run.

Current `TeamController::updateTeam()` enforces ownership, capacity, and formation validity, but it does **not** itself check for an active run before replacing membership or formation. Deleting the active squad is explicitly blocked during a run, but ordinary squad-update enforcement is incomplete at the backend boundary.

Treat this as a runtime hardening gap. Documentation and UX should not reinterpret the missing backend guard as permission to mutate the active run's squad configuration.

## System Boundaries

This document owns:

- squad membership rules
- squad capacity
- active-squad semantics
- formation geometry and footprints
- the general combat meaning of front/back position

It does not own:

- unit ability/loadout editing — see `ability-loadouts-and-dice-binding.md`
- promotion rules — see `unit-promotion.md`
- target scoring — see `target-resolution.md`
- page layout and drag/drop interaction — see `documentation/04-ux/02-warband-management.md` and page-analysis docs

## Related Documents

- `target-resolution.md` — how formation influences automatic target choice
- `combat-resolution.md` — deterministic combat lifecycle
- `unit-promotion.md` — removal of consumed units from squads/formation
- `documentation/04-ux/02-warband-management.md` — player-facing management contract
- `documentation/04-ux/page-analysis/09-squad-details.md` — current squad editor surface
