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
---
name: Milestone 15 - Build UX Rebuild Component Library
status: not-started
execution_window: closed
is_current: no
issues:
  - Implement core layout shell components from 03-component-specifications
  - Implement reusable navigation components from 03-component-specifications
  - Implement shared list framework with loading, error, and pagination states
  - Implement standardized action controls and button variants from component spec
  - Implement HUD and feedback utility components from component spec
  - Add component-level test coverage and usage examples for UX rebuild library
description: |
  Build the reusable component library defined in `documentation/07-ux-rebuild/03-component-specifications.md` so scenes can migrate to consistent UI primitives.
entry_criteria: |
  * Component specification document is accepted as implementation baseline.
  * Asset dependencies for base component visuals are available in runtime assets.
exit_criteria: |
  * Specified core components are implemented and test-covered.
  * Components are ready for scene-by-scene migration without ad hoc UI duplication.
---
name: Milestone 16 - Scene Migration to UX Rebuild Components
status: not-started
execution_window: closed
is_current: no
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


