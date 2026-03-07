# MILESTONES FILE
----
Active milestones only. Move completed entries to `MILESTONES_ARCHIVE.md`.

name: Milestone 14 - Run Node Resolution Consolidation
status: blocked
execution_window: open
is_current: no
issues:
  - Add regression coverage for consolidated non-rest node resolution flow
description: |
  Consolidate non-rest node resolution behavior into a dedicated flow so map exploration focuses on navigation while resolution UX and API orchestration stay centralized and consistent.
entry_criteria: |
  * Existing map exploration and run-end behavior is stable enough to refactor routing.
  * Resolution requirements for combat/loot/boss/exit outcomes are documented.
exit_criteria: |
  * Non-rest node handling no longer duplicates resolve logic inside `MapExplorationScene`.
  * Resolution outcomes use one unified scene/flow with consistent messaging and return behavior.
---
name: Milestone 16 - Scene Migration to UX Rebuild Components
status: not-started
execution_window: open
is_current: yes
issues:
  - Migrate HomeScene to standardized UX rebuild components
  - Migrate RegionSelectScene and MapExplorationScene to shared component library
  - Migrate warband management scenes to shared list/action components
  - Migrate inventory, rest, and run summary scenes to shared component library
  - Remove superseded scene-local UI implementations after migration
  - Update UX rebuild docs to reflect final component adoption per scene
description: |
  Migrate existing scenes onto the shared UX rebuild component library and retire superseded local UI implementations.
entry_criteria: |
  * Milestone 15 component implementations are available and verified.
  * Scene migration order and UX acceptance expectations are defined.
exit_criteria: |
  * Target scenes use shared components as primary UI implementation path.
  * Legacy duplicated scene-local UI code is removed or explicitly deprecated.
