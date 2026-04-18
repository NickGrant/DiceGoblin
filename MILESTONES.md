# MILESTONES FILE
----
Active milestones only. Move completed entries to `MILESTONES_ARCHIVE.md`.

name: Milestone 39 - Unit Details and Promotion UX
status: not-started
execution_window: open
is_current: yes
issues:
  - Add unit details support for renaming, loadout order, and ability-slot dice editing
  - Update inventory flows to target ability-slot equips instead of unit pools
  - Rework promotion flow for cumulative abilities and sideways destinations
description: |
  Bring the player-facing management surfaces into alignment with the new combat
  model by centering unit details, slot-based dice editing, renaming, and the new
  promotion flow.

---

name: Milestone 40 - Rework Normalization Pass
status: not-started
execution_window: open
is_current: no
issues:
  - Normalize rework migrations after schema stabilizes
  - Consolidate legacy combat and loadout test fixtures
  - Refactor repeated scene layout and styling after rework UI lands
description: |
  Compact the repo after the rework is functionally complete by normalizing schema
  history, test scaffolding, and repeated frontend structure introduced during the
  transition.
