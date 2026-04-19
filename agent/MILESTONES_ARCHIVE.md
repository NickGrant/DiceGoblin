# MILESTONES ARCHIVE
----

## Purpose
- Historical record for completed or otherwise inactive milestones moved from `agent/MILESTONES.md`.
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

---

name: Milestone 38 - Combat Scheduler and Resolution Rewrite
status: complete
execution_window: open
is_current: no
issues:
  - Replace pooled combat dice resolution with ability-slot reads
  - Expand battle logs for equipped ability instances and slot traces
description: |
  Replace the old modulo scheduler and pooled-dice combat model with cumulative
  equipped-ability timing and slot-driven resolution for both players and enemies.
Resolution: |
  Completed the combat-engine transition to cumulative equipped-ability scheduling,
  slot-driven action dice reads, deterministic empty-slot fallback, and richer
  battle-log traces for equipped ability instances and slot usage. Backend and
  frontend verification passed, while a temporary reset/test parallelization mishap
  was resolved by rerunning backend integration tests sequentially.

---

name: Milestone 39 - Unit Details and Promotion UX
status: complete
execution_window: open
is_current: no
issues:
  - Rework promotion flow for cumulative abilities and sideways destinations
description: |
  Bring the player-facing management surfaces into alignment with the new combat
  model by centering unit details, slot-based dice editing, renaming, and the new
  promotion flow.
Resolution: |
  Completed the Unit Details surface so players can rename units, reorder equipped
  abilities, edit ability-slot dice, and choose eligible promotion destinations from
  the cumulative promotion model. Frontend tests/build, backend PHPUnit, and backlog
  validation all passed with the destination-aware promotion UX wired end to end.
