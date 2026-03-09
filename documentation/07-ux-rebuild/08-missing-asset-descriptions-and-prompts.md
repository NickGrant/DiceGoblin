# Missing Asset Descriptions and Prompts
----

Status: draft
Last Updated: 2026-03-09
Owner: UX + Art + Frontend
Depends On: `documentation/07-ux-rebuild/07-asset-review-draft.md`, `documentation/03-ux/01-visual-design-guide.md`

## Purpose
- Provide compact generation inputs for required non-dice UI assets.

## Shared Prompt Base
Use this as the fixed base for every asset prompt:
- style: harsh handmade propaganda diorama, militarized paper-craft bureaucracy
- materials: corrugated cardboard edges, layered paperboard, masking tape wear, brass fastener/pin marks, rubbed ink, stencil overspray, stamp bleed, adhesive residue
- palette: Stark Cream `#F3EFE0`, Revolutionary Red `#B91C1C`, Cold Slate `#4F5A65`, Dirty Teal `#006F7A`, Deep Charcoal `#23272A`
- framing: single isolated UI asset, front-facing game-UI readability, transparent background
- negatives: no characters, no mascots, no toy-like tone, no neon, no sci-fi glow, no glossy modern gradient polish

## Prompt Formula
`[Shared Prompt Base] + [subject line below] + [size/format constraint from implementation]`

## Subject Lines By Key

### Structural Surfaces
- `bg_paper_base`: full-scene paper texture; low-noise center, distressed edges.
- `titlebar_registry_base`: reusable red section title strip with stencil framing.
- `panel_registry_body_lg`: large modular content panel body.
- `panel_registry_body_md`: medium modular content panel body.
- `panel_registry_body_sm`: compact utility panel body.

### Bottom Command Strip
- `cmdstrip_shell_base`: full-width outer shell with clear left/right segmentation.
- `cmdstrip_left_segment_base`: left plate for home/warband/dice/energy zone.
- `cmdstrip_right_segment_base`: right plate for player-name/logout zone.
- `cmdstrip_left_divider`: internal divider for left zone groups.
- `cmdstrip_right_divider`: divider between right-zone name and logout areas.

### Shared Buttons
- `btn_manifest_base|hover|pressed|disabled`: standard action button family states.
- `btn_metal_base|hover|pressed|disabled`: heavy action button family states.

### Home and Run Panels
- `panel_run_begin_base`: large CTA panel for starting a run.
- `panel_run_continue_base`: large CTA panel for continuing a run.

### Region Columns
- `column_region_mountain_base`: mountain region selection column.
- `column_region_swamp_base`: swamp region selection column.

### Navigation and Status Icons
- `icon_home_registry`: home navigation icon.
- `icon_warband_registry`: warband navigation icon.
- `icon_inventory_registry`: dice inventory icon.
- `icon_logout_registry`: logout action icon.
- `icon_energy_100|75|50|25|0`: energy state icon family.
- `icon_node_combat|loot|rest|boss|locked`: node-type icon family.

### Optional Consolidation
- `atlas_ui_icons_core`: combined atlas for non-dice nav + node icons.

## Notes
- Dice assets are excluded (carry-forward).
- Store generated outputs using keys and naming from `06-master-ui-asset-list.md`.
