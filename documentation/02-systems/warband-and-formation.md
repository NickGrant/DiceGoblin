---
Title: "Warband, Squads, and Formation"
Status: Canonical
Last Updated: 2026-09-12
Owner: Systems Design + Engineering
Depends On:
  - documentation/07-development-path/vnext-storage-model.md
  - documentation/02-systems/target-resolution.md
Category: 02-systems
Tags: [systems, squads, warband, formation]
---

# Warband, Squads, and Formation

## Squad Model
A player may own multiple named saved squads. Exactly one may be active for between-run use. A unit may appear in multiple saved squads.

Each squad has exactly nine ordered formation positions identified as `0` through `8`. Persistence uses normalized squad/unit/position rows rather than one column per position or the prototype `team` compatibility model.

Each occupied position references an owned unit. `(squad_id, position)` is unique. Additional footprint behavior, if retained for large units, should be derived from authored unit geometry rather than represented by duplicating the same squad membership across arbitrary position columns.

## Active Squad
The active squad ID is player-wide dynamic state. Activation is permitted outside a run and locked while a run is active.

The first saved squad becomes active. Additional squad creation and edits preserve the active selection. Activating the already-active squad is a no-op. An active squad cannot be deleted while another saved squad remains; after explicitly activating a replacement, the former squad may be deleted. Deleting the only squad leaves the active squad null.

## Run Lock
When a run successfully begins, participating player-controlled combat configuration is locked until terminal success, failure, or abandonment. This includes participating squad membership/positions and relevant unit ability/loadout/dice configuration.

The backend enforces the lock. Phaser disabling controls is presentation, not authority.

## Targeting
Formation provides spatial input to automatic target resolution. Front/back semantics and other position-aware rules are authored combat behavior, not an unconditional formation rule.

## Terminology
New vNext API, persistence, UI, and documentation use **squad**, not the prototype `team` terminology.
