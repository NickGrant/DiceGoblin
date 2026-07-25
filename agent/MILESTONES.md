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

The current implementation persists kin identity through legacy `splice_variant` fields and stores older boss-item concepts through `region_items`. The July 25 roadmap now supersedes both terms for new work: use kin/lineage language and generic item ownership going forward.

### Exit Criteria

- A generic item catalog and ownership path exists.
- New progression rewards no longer depend on `region_items`.
- Profile/debug surfaces expose generic item quantities.
- Player-facing UI renders kin language instead of splice language.
- The next branch can implement account-level lineage unlocks and Pig Kin reconstruction costs.

### Related Issues

- PIF-001: Add generic progression item foundation
- KRB-001: Canonicalize kin and lineage terminology
