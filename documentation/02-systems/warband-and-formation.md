---
Title: "Warband, Squads, and Formation"
Status: Canonical
Last Updated: 2026-09-13
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
The active squad ID is player-wide dynamic state. While a run is active, switching to a different squad is locked; reactivating the participating active squad remains a no-op.

The first saved squad becomes active. Additional squad creation and edits preserve the active selection. Activating the already-active squad is a no-op. An active squad cannot be deleted while another saved squad remains; after explicitly activating a replacement, the former squad may be deleted. Deleting the only squad leaves the active squad null.

## Run Lock
When a run successfully begins, participating player-controlled combat configuration is locked until terminal success, failure, or abandonment. This includes participating squad membership/positions, participating-squad deletion, and relevant unit ability/loadout/dice configuration. The participating squad may still be renamed when its nine-position formation is unchanged, and other saved squads may still be created, renamed, reconfigured, or deleted under the normal squad rules.

The backend enforces the lock. Phaser disabling controls is presentation, not authority.

## Targeting
Formation provides spatial input to automatic target resolution. Squad positions are row-major `0..8` on the combat grid: `x = position % 3` and `y = intdiv(position, 3)`. Therefore `x=0` is the back column, `x=1` is the middle column, and `x=2` is the front column. Authoritative combat uses stable battle-local player keys `player_p0` through `player_p8`; durable unit identity remains in the battle participant manifest.

## Terminology
New vNext API, persistence, UI, and documentation use **squad**, not the prototype `team` terminology.
