---
Title: "Codex Ownership and Discovery"
Status: Canonical
Last Updated: 2026-08-20
Owner: Systems Design + Engineering
Depends On:
  - documentation/03-content/12-codex-entries.md
  - backend/src/Services/CodexOwnershipService.php
  - backend/migrations/87_user_codex_entries.sql
Category: 02-systems
Tags:
  - systems
  - codex
---

# Codex Ownership and Discovery

## Current Runtime

Codex ownership is durable in `user_codex_entries`, uniquely keyed by user, entry type, and entry key. `CodexOwnershipService` grants entries idempotently, produces the profile Codex payload, and syncs derived ownership from durable account facts.

Current entry types include features, unit types, kin, affixes, enemies, biomes, lore, and items.

## Discovery Sources

- Feature and unit-type unlocks grant entries at unlock time.
- Owned units grant unit-type and kin entries.
- Owned dice grant affix entries in the current compatibility model.
- Owned items grant item entries.
- Combat can drop enemy Codex pages.
- Completed biome runs grant biome and boss/enemy entries.
- Dialogue unlocks lore entries.

## Frontend Boundary

Codex renders `owned_by_type` when present and falls back to older profile facts only for compatibility. Locked placeholders remain a presentation concern.

## Known Gaps

- Dice material Codex identity is target-state and still overlaps with current affix discovery.
- Codex entry detail richness varies by category.
