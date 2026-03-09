# Missing Asset Descriptions and Prompts
----

Status: draft
Last Updated: 2026-03-08
Owner: UX + Art + Frontend
Depends On: `documentation/07-ux-rebuild/07-asset-review-draft.md`, `documentation/03-ux/01-visual-design-guide.md`

## Purpose
- Provide ready-to-use generation prompts for each missing non-dice UI asset in the review draft.
- Keep prompts aligned with the canonical visual style and locked palette.

## Global Prompt Constraints
Apply to all prompts unless explicitly overridden:
- style: harsh handmade propaganda diorama, militarized paper-craft bureaucracy
- materials: corrugated cardboard edges, layered paperboard, masking tape wear, brass fastener/pin marks, rubbed ink, stencil overspray, stamp bleed, adhesive residue
- palette: Stark Cream `#F3EFE0`, Revolutionary Red `#B91C1C`, Cold Slate `#4F5A65`, Dirty Teal `#006F7A`, Deep Charcoal `#23272A`
- framing: single isolated UI asset, front-facing game-UI readability, transparent background
- negatives: no characters, no mascots, no toy-like tone, no neon, no sci-fi glow, no glossy modern gradient polish

## Structural Surfaces
### `bg_paper_base`
Description: Full-scene base paper texture with subtle distress and high readability.
Prompt: Create a full-screen background texture tile for a tactical game UI, severe paper-craft propaganda style, layered cardstock feel with subtle grime and adhesive ghosts, mostly Stark Cream with Deep Charcoal edge vignetting, low-noise center for readability, seamless-friendly, transparent margin-safe PNG.

### `titlebar_registry_base`
Description: Reusable title strip texture for section headers.
Prompt: Create a horizontal registry title bar texture for game UI sections, distressed handmade militarized style, Revolutionary Red primary with Deep Charcoal stenciled framing blocks, slight print misalignment and rubbed ink, clean center lane for text, transparent background around silhouette.

### `panel_registry_body_lg`
Description: Large content panel body texture for major scene containers.
Prompt: Create a large modular registry panel body, severe handcrafted bureaucratic style, Stark Cream paperboard center with Cold Slate structural side bands, worn tape corners and pin marks, distressed edges but clean readable interior, transparent background.

### `panel_registry_body_md`
Description: Medium content panel body texture for secondary containers.
Prompt: Create a medium modular registry panel body matching large-panel style, propaganda paper-craft aesthetic, Stark Cream base with Dirty Teal utility accents and charcoal border stamping, controlled wear on edges only, transparent background.

### `panel_registry_body_sm`
Description: Small content panel body texture for compact utility modules.
Prompt: Create a small modular registry utility panel, distressed militarized paper-craft style, Cream base with Charcoal border bars and minimal red alert accents, tactile cardboard cut edges, readable clean center, transparent background.

## Bottom Command Strip Modules
### `cmdstrip_shell_base`
Description: Full-width outer strip shell anchoring bottom global controls.
Prompt: Create a full-width bottom command strip shell for game HUD, severe propaganda craft style, layered heavy paperboard and cardboard backing, Deep Charcoal structural base with Cream top plates and red stamped caution marks, strong left-right split geometry, transparent background.

### `cmdstrip_left_segment_base`
Description: Left segment plate for Warband, Dice, and Energy controls.
Prompt: Create a left command-strip segment plate for utility links and resource readout, handcrafted militarized UI style, Cold Slate and Cream composition with subtle red registration marks, clear slots for two links and one energy field, transparent background.

### `cmdstrip_right_segment_base`
Description: Right segment plate for Logout and player identity.
Prompt: Create a right command-strip segment plate for account controls, distressed bureaucratic paper-craft style, Deep Charcoal and Cream with restrained red warning stamp accents, clear zones for logout action and player-name line, transparent background.

### `cmdstrip_left_divider`
Description: Divider element between left utility groups within command strip.
Prompt: Create a vertical divider insert for bottom command strip, tactical propaganda style, dark stamped bar with worn edges and pin/rivet marks, simple readable silhouette for modular UI composition, transparent background.

### `cmdstrip_right_divider`
Description: Divider element separating logout and name area.
Prompt: Create a compact divider insert for account segment of bottom command strip, distressed paper-and-ink style, Deep Charcoal bar with minor red stencil ticks and edge wear, transparent background.

## Shared Buttons
### `btn_manifest_base`
Description: Default utility button face for shared action buttons.
Prompt: Create a base manifest action button face, militarized handcrafted paper-craft style, Cream label plate mounted on Charcoal backing with red registration stamp hints, distressed edges and light tape wear, high-contrast label zone, transparent background.

### `btn_manifest_hover`
Description: Hover state for manifest button with stronger focus readability.
Prompt: Create a hover-state variant of a manifest button, same shape as base, slightly brighter Cream face and clearer red emphasis marks, subtle raised shadow and increased contrast, keep distressed handmade texture, transparent background.

### `btn_manifest_pressed`
Description: Pressed state for manifest button with mechanical stamped feel.
Prompt: Create a pressed-state manifest button variant, visibly compressed/slammed look with darker tones, heavier ink transfer and slight skewed stamp artifact, tactile paperboard depth preserved, transparent background.

### `btn_manifest_disabled`
Description: Disabled state for manifest button with reduced interactivity clarity.
Prompt: Create a disabled-state manifest button variant, desaturated toward Cold Slate/Dirty Teal, reduced contrast, worn but readable silhouette, no active red emphasis, transparent background.

### `btn_metal_base`
Description: Default heavy-action button face for major actions.
Prompt: Create a heavy utility action button base with metal-plate illusion built from paper-craft textures, Deep Charcoal dominant with Cream label strip and red hazard accents, severe tactical mood, transparent background.

### `btn_metal_hover`
Description: Hover state for heavy-action button.
Prompt: Create hover-state heavy action button variant, same silhouette as base, slightly brighter highlights and stronger edge contrast, subtle tactical red cue increase, distressed analog finish, transparent background.

### `btn_metal_pressed`
Description: Pressed state for heavy-action button.
Prompt: Create pressed-state heavy action button variant, compressed and darkened look, stronger shadow and ink-smear impact, feels mechanically decisive, transparent background.

### `btn_metal_disabled`
Description: Disabled state for heavy-action button.
Prompt: Create disabled-state heavy action button variant, muted slate/teal tone, softened contrast, retains silhouette clarity but clearly non-interactive, transparent background.

## Home/Run Hero Panels
### `panel_run_begin_base`
Description: Large CTA panel for starting a new run.
Prompt: Create a large run-start hero panel surface, propaganda bureaucratic diorama style, bold Cream and Red command framing, distressed paper layers and pin marks, strong central zone for "BEGIN RUN" label, transparent background.

### `panel_run_continue_base`
Description: Large CTA panel for resuming active run.
Prompt: Create a large run-continue hero panel surface matching run-start family, severe handcrafted tactical style, Cream/Charcoal base with red operational stamp accents, clear central zone for "CONTINUE RUN" label, transparent background.

## Region Selection Columns
### `column_region_mountain_base`
Description: Region selection surface themed for mountain conflict lane.
Prompt: Create a tall region selection column panel for a mountain theater, militarized handmade propaganda style, Cold Slate and Charcoal dominant with restrained red command marks, rugged paperboard layering and worn edges, transparent background.

### `column_region_swamp_base`
Description: Region selection surface themed for swamp conflict lane.
Prompt: Create a tall region selection column panel for a swamp theater, severe paper-craft propaganda style, Dirty Teal and Charcoal dominant with muted cream labels and restrained red stamps, wet-worn paper texture cues without photorealism, transparent background.

## Navigation and Status Icons (Non-Dice)
### `icon_warband_registry`
Description: Warband navigation icon in stencil signage style.
Prompt: Create a warband management icon as a bold stencil pictogram, militarized registry style, Deep Charcoal primary with optional red accent notch, simple geometric readable silhouette at small sizes, transparent background.

### `icon_inventory_registry`
Description: Dice inventory navigation icon.
Prompt: Create a dice inventory icon as a stencil pictogram, severe handcrafted propaganda UI style, charcoal silhouette with restrained cream/red contrast details, optimized for small HUD use, transparent background.

### `icon_logout_registry`
Description: Logout action icon.
Prompt: Create a logout icon as a strict procedural stencil mark, tactical bureaucracy aesthetic, bold directional form with red alert accent and charcoal core shape, highly legible at small size, transparent background.

### `icon_energy_100`
Description: Full energy indicator icon tier.
Prompt: Create energy icon tier 100, stencil-signage style for militarized paper-craft UI, full-charge visual state, cream and red highlights over charcoal core, clear at tiny sizes, transparent background.

### `icon_energy_75`
Description: High energy indicator icon tier.
Prompt: Create energy icon tier 75 matching the same icon family, severe registry style, 75 percent filled/readable state, restrained palette and distressed print cues, transparent background.

### `icon_energy_50`
Description: Mid energy indicator icon tier.
Prompt: Create energy icon tier 50 in same stencil family, exactly half-state readability, tactical propaganda craft treatment, clear silhouette and tier differentiation, transparent background.

### `icon_energy_25`
Description: Low energy indicator icon tier.
Prompt: Create energy icon tier 25 in matching style family, low-resource state with strong clarity, muted tones with limited red caution accent, transparent background.

### `icon_energy_0`
Description: Empty energy indicator icon tier.
Prompt: Create energy icon tier 0 in matching family, depleted state with strongest caution expression, distressed but clean silhouette, transparent background.

### `icon_node_combat`
Description: Encounter-map combat node icon.
Prompt: Create a combat node icon for encounter map, stencil propaganda mark style, aggressive geometric symbol in charcoal with restrained red emphasis, designed for map node readability, transparent background.

### `icon_node_loot`
Description: Encounter-map loot node icon.
Prompt: Create a loot node icon for encounter map, bureaucratic stencil-signage style, clear reward symbol in cream/charcoal with minimal red accents, small-size readability, transparent background.

### `icon_node_rest`
Description: Encounter-map rest node icon.
Prompt: Create a rest node icon for encounter map, tactical registry pictogram style, calm utility symbol using cream/teal/charcoal contrast, distinct from combat and loot icons, transparent background.

### `icon_node_boss`
Description: Encounter-map boss node icon.
Prompt: Create a boss node icon for encounter map, severe high-threat stencil symbol, strong red and charcoal emphasis, bold silhouette clearly distinct from other node types, transparent background.

### `icon_node_locked`
Description: Encounter-map locked node icon.
Prompt: Create a locked node icon for encounter map, procedural denial pictogram style, charcoal core with muted red/teal lockout cues, unmistakably unavailable state, transparent background.

## Optional Consolidation
### `atlas_ui_icons_core`
Description: Packed atlas containing non-dice navigation and node icons.
Prompt: Create a cohesive icon atlas sheet containing warband, inventory, logout, five energy tiers, and five node-state icons, all in one consistent militarized paper-craft stencil style, equal visual weight and spacing, transparent atlas-ready sprite sheet.

## Notes
- Dice assets are excluded from generation because they are approved carry-forward assets.
- Prompts are intentionally production-focused; add exact dimensions and padding constraints in the next implementation pass.
