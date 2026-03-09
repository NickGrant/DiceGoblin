# UX Rebuild - Master UI Asset List (Component-First)
----

Status: active
Last Updated: 2026-03-08
Owner: UX + Art + Frontend
Depends On: `documentation/03-ux/01-visual-design-guide.md`, `frontend/src/components/`, `frontend/public/assets/packs/ui.json`

## Purpose
- Define the master UI asset list from shared component dependencies, not scene-by-scene one-offs.
- Preserve only approved carry-forward assets during the style reset.

## Carry-Forward Decision
Carry forward now:
- `dice_sheet` atlas (all dice faces/frames)

Do not carry forward (replace/re-author):
- all non-dice UI textures, panel art, icons, and column/panel body art currently registered in `ui.json`

## Shared Component Dependency Map
This map shows where OOP/shared behavior will drive asset reuse.

### `ClickablePanel` family
Base component:
- `ClickablePanel`

Inheritors/wrappers that share panel texture behavior:
- `ActionButton` (`manifest_strip`)
- `AcceptButton` (inherits `ActionButton`)
- `RejectButton` (inherits `ActionButton`)
- `MetalActionButton` (`metal_strip`)
- `RegionSelect` (`panel_begin_run`, `panel_continue_run`)
- `ClickablePanelRegionColumn` (`column_mountain`, `column_swamp`)
- `RegionSelectionPanel` (defaults to `manifest_strip` via embedded `ClickablePanel`)

### `ContentAreaFrame` family
Base composition:
- `ContentAreaFrame` + `SectionTitleBar`

Shared texture dependencies:
- title texture fallback key: `texture_red`
- optional body image keys injected by scenes: `ux_start_run`, `ux_continue_run`

### Bottom command strip family
- New shared component target: persistent bottom strip split into left/right segments
- Left segment content: warband link, dice link, current energy level
- Right segment content: logout action, player name

### Encounter map family
- `Node`: `icon_encounter_boss`, `icon_encounter_locked`, `icon_encounter_combat`, `icon_encounter_loot`, `icon_encounter_rest`, plus `icon_encounter_boss` for exit fallback

### Card/grid family
- `UnitCardGrid`: currently uses `icon_warband` as portrait placeholder
- `DiceCardGrid`: uses `dice_sheet` atlas frames (carry forward)

### Global background family
- `BackgroundImage`: `texture_paper`

## Current Runtime UI Keys (For Replacement Planning)
Non-dice keys currently in `frontend/public/assets/packs/ui.json` and referenced by components/scenes:
- `texture_paper`
- `texture_red`
- `ux_start_run`
- `ux_continue_run`
- `panel_begin_run`
- `panel_continue_run`
- `column_swamp`
- `column_mountain`
- `manifest_strip`
- `metal_strip`
- `icon_home`
- `icon_warband`
- `icon_inventory`
- `icon_energy`
- `icon_energy_75`
- `icon_energy_50`
- `icon_energy_25`
- `icon_energy_0`
- `icon_encounter_boss`
- `icon_encounter_combat`
- `icon_encounter_locked`
- `icon_encounter_loot`
- `icon_encounter_rest`
- `icons_core_sheet`

Carry-forward key:
- `dice_sheet`

Deprecated for replacement planning (do not recreate in new direction):
- `ux_corner_left`
- `ux_corner_right`

## Master Asset List (New Art Required)
This is the component-first production list to author next.

### A) Structural Surfaces
- `bg_paper_base`
- `titlebar_registry_base`
- `panel_registry_body_lg`
- `panel_registry_body_md`
- `panel_registry_body_sm`

### B) Bottom Command Strip Modules
- `cmdstrip_shell_base`
- `cmdstrip_left_segment_base`
- `cmdstrip_right_segment_base`
- `cmdstrip_left_divider`
- `cmdstrip_right_divider`

### C) Button Surfaces (Shared)
- `btn_manifest_base`
- `btn_manifest_hover`
- `btn_manifest_pressed`
- `btn_manifest_disabled`
- `btn_metal_base`
- `btn_metal_hover`
- `btn_metal_pressed`
- `btn_metal_disabled`

### D) Home/Run Hero Panels
- `panel_run_begin_base`
- `panel_run_continue_base`

### E) Region Selection Columns
- `column_region_mountain_base`
- `column_region_swamp_base`

### F) Navigation/Status Icons (Non-Dice)
- `icon_warband_registry`
- `icon_inventory_registry`
- `icon_logout_registry`
- `icon_energy_100`
- `icon_energy_75`
- `icon_energy_50`
- `icon_energy_25`
- `icon_energy_0`
- `icon_node_combat`
- `icon_node_loot`
- `icon_node_rest`
- `icon_node_boss`
- `icon_node_locked`

### G) Optional Consolidation Targets
- `atlas_ui_icons_core` (if non-dice icon family is packed into one atlas)

### H) Carry-Forward Dice
- `dice_sheet` atlas and all existing dice frames

## Naming/Reuse Rules
- Prefer component-family names over scene-specific names.
- Add state suffixes (`_base`, `_hover`, `_pressed`, `_disabled`) only where interaction states are explicitly visual.
- Reuse one key across all inheriting classes when behavior is shared (`ClickablePanel` family first).

## Next Pass
- Add required pixel dimensions per key (derived from component constants and layout contracts).
- Mark each key as: `required-now`, `optional`, or `defer`.
- Map each key to first consumer component and validation scene.
