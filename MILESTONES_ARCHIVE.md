# MILESTONES ARCHIVE
----

## Purpose
- Historical record for completed or otherwise inactive milestones moved from `MILESTONES.md`.
- Preserve milestone resolution history without bloating active execution context.

<!-- Archive history prior to purge can be recovered from git at commit fb22ebc and earlier. -->

name: Milestone 37 - Ability Loadout Rework Foundations
status: complete
execution_window: open
is_current: no
issues:
  - Add unit naming and ability-loadout persistence schema
  - Seed starter units with default abilities and common d4 slot assignments
  - Author enemy equipped-loadout definitions for cumulative scheduling
  - Add backend validators for ability equip budget and slot legality
description: |
  Establish the schema, authored data, and starter-state foundations required for
  the ability-loadout rework. This milestone creates the persistence and content
  contracts that later combat and UX slices will depend on.
Resolution: |
  Landed the first rework persistence layer, starter state seeding, enemy loadout
  definitions, and shared validator rules needed to start combat-scheduler
  implementation. Backend integration verification remained partially blocked by
  the local test database refusing connections, but the milestone's source,
  backlog, and syntax checks were completed.
