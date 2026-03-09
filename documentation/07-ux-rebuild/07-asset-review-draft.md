# Asset Review Draft (Component-First)
----

Status: draft  
Last Updated: 2026-03-08  
Owner: UX + Art + Frontend  
Depends On: `documentation/07-ux-rebuild/06-master-ui-asset-list.md`, `documentation/03-ux/01-visual-design-guide.md`

## Goal
Provide a review checkpoint for the new master UI asset list before production/implementation updates.

## Locked Decisions
- Asset planning method: component-first, then scene mapping.
- Carry forward only dice assets.
- Canonical style source: `documentation/03-ux/01-visual-design-guide.md`.

## Carry-Forward Assets (Approved)
- `dice_sheet` atlas and existing dice frames.

## Assets To Replace/Re-Author
- All non-dice UI textures currently registered in `frontend/public/assets/packs/ui.json`.

## Proposed Master Asset Families
### Structural Surfaces
- `bg_paper_base`
- `titlebar_registry_base`
- `panel_registry_body_lg`
- `panel_registry_body_md`
- `panel_registry_body_sm`

### Bottom Command Strip Modules
- `cmdstrip_shell_base`
- `cmdstrip_left_segment_base`
- `cmdstrip_right_segment_base`
- `cmdstrip_left_divider`
- `cmdstrip_right_divider`

### Shared Buttons
- `btn_manifest_base`
- `btn_manifest_hover`
- `btn_manifest_pressed`
- `btn_manifest_disabled`
- `btn_metal_base`
- `btn_metal_hover`
- `btn_metal_pressed`
- `btn_metal_disabled`

### Home/Run Hero Panels
- `panel_run_begin_base`
- `panel_run_continue_base`

### Region Selection Columns
- `column_region_mountain_base`
- `column_region_swamp_base`

### Navigation/Status Icons (Non-Dice)
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

### Optional Consolidation
- `atlas_ui_icons_core`

## Shared/OOP Reuse Notes
- `ClickablePanel` family should share button/panel surface assets.
- `ContentAreaFrame` + `SectionTitleBar` should share title/body surface assets.
- Bottom command strip should be one global split component shared across authenticated scenes.
- Scene-specific art should be avoided unless gameplay meaning is unique.

## Review Checklist
- Confirm asset key names.
- Confirm which assets require explicit visual states.
- Confirm whether icon family should ship as individual files or atlas.
- Confirm no additional carry-forward assets besides dice.
- Confirm this list is sufficient for all current scenes/components.

## Approval Outcome
- `approved` or `changes requested`
- notes:
