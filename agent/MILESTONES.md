# MILESTONES FILE
----
Active milestones only. Move completed entries to `agent/MILESTONES_ARCHIVE.md`.

## Wrong Machine and Kin Foundation

**Status:** Active
**Purpose:** Build the foundation for goblin-kin progression, generic progression items, and the Wrong Machine loop without extending superseded region-item or splice-variant terminology.

### Goals

- Add a generic item foundation for lineage materials, boss catalysts, machine catalysts, and unlock keys.
- Retire `region_items` as the path for new progression rewards and profile work.
- Canonicalize player-facing terminology around goblins, goblin-kin, kin, and lineages.
- Preserve Basic Goblins as the implicit default while preparing account-level lineage unlocks.
- Make Pig Kin the guaranteed first reconstruction path.

### Current Code Context

Generic `items` and `user_items` now provide the progression inventory foundation for Pig Kin materials and boss catalysts. The current implementation still persists kin identity through legacy `splice_variant` fields, so the next slice should avoid new splice terminology while planning the storage/API compatibility rename.

### Exit Criteria

- Account-level lineage unlock state exists.
- Profile/debug surfaces expose owned lineages.
- Player-facing UI renders kin language instead of splice language.
- The next branch can implement account-level lineage unlocks and Pig Kin reconstruction costs.

### Related Issues

- KRB-001: Canonicalize kin and lineage terminology
