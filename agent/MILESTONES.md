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

Generic `items` and `user_items` now provide the progression inventory foundation for Pig Kin materials and boss catalysts. The current slice adds account-level lineage unlock state through the existing unlock table, with Basic Goblin implicit and Pig Kin as the first explicit lineage. The implementation still persists unit kin identity through legacy `splice_variant` fields, so follow-up work should avoid new splice terminology while planning the storage/API compatibility rename.

### Exit Criteria

- Account-level lineage unlock state exists.
- Profile/debug surfaces expose owned lineages.
- Player-facing UI renders kin language instead of splice language.
- The next branch can implement Pig Kin reconstruction costs and reward-claim unlock handling.

### Related Issues

- KRB-002: Add account-level lineage unlock state
