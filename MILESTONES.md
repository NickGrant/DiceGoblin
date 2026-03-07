# MILESTONES FILE
----
Active milestones only. Move completed entries to `MILESTONES_ARCHIVE.md`.

name: Milestone 14 - Run Node Resolution Consolidation
status: in-progress
execution_window: open
is_current: yes
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
