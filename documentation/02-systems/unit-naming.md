---
Title: "Unit Naming"
Status: Canonical Boundary
Last Updated: 2026-09-10
Owner: Systems Design + Engineering
Depends On:
  - documentation/07-development-path/vnext-storage-model.md
  - documentation/07-development-path/vnext-endpoint-inventory.md
Category: 02-systems
Tags: [systems, units, naming]
---

# Unit Naming

Owned unit instances may have a player-visible name. Names survive promotion because promotion changes the existing unit rather than replacing its identity.

## vNext Rules
- A newly created unit receives a valid display name when it is materialized.
- The player may rename an owned unit outside restrictions imposed by authoritative gameplay state.
- Rename is a narrow unit command and returns the authoritative updated unit state plus `player_revision` when persistent state changes.
- Name validation is server-authoritative.
- Name generation/presentation must not determine combat identity, kin, unit type, progression, or other gameplay state.

The prototype generator word lists, service call sequence, controller names, exact validation limits, and grant defaults are implementation evidence rather than vNext design contracts. Those details should be chosen/reconfirmed when Milestone 2 implements the unit domain.
