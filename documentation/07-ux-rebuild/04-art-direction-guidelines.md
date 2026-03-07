# UX Rebuild - Art Direction Guidelines
----

Status: active  
Last Updated: 2026-03-06  
Owner: UX + Art + Frontend  
Depends On: `documentation/03-ux/01-visual-design-guide.md`, `documentation/03-ux/08-page-layout-zones.md`

## Purpose
- Capture current visual direction for the UX rebuild in implementation-ready form.
- Keep art generation and UI implementation aligned while redesign iteration is active.

## Core Style Direction
- Tone: devious + whimsical, with a salvage-shop / propaganda-signage feel.
- Visual language: worn printed materials, hazard placards, riveted corners, stencil-forward motifs.
- Readability priority: bold hierarchy first, texture/noise second.

## Current Rebuild Layout Aesthetic
- Global scene background: `new_ux/textures/paper.png`.
- Section title bars: `new_ux/textures/red.png`.
- Corner treatments:
  - top-left: `new_ux/ui_kit/corner_left.png` (home icon inside circle)
  - top-right: `new_ux/ui_kit/corner_right.png` (energy icon inside circle)
- Home scene key body images:
  - Start/Continue run panel image (state-based)
  - Warband and Inventory sections remain panel-framed with section-specific content treatment

## Color Direction
- Foundational neutrals:
  - Deep brown background tones for contrast anchors
  - Ink-brown text over pure black where possible
- Rebuild overlay zones (layout guidance colors):
  - `#0600ff` run-focused section
  - `#00f6ff` warband-focused section
  - `#00ff72` inventory-focused section
- Rule: preserve one dominant accent per section; avoid rainbow noise in a single panel.

## Typography Direction
- Display/Headers/CTA labels: stencil-style family (current: Saira Stencil One in legacy guide).
- Body/support text: condensed sans (current: Roboto Condensed in legacy guide).
- Text hierarchy:
  - section titles must remain highly legible over textured title bars
  - helper text should remain readable at gameplay distance

## Texture and Surface Rules
- Distress should cluster on edges/corners, not over critical text.
- Body content images should feel printed/applied onto paper rather than floating UI chrome.
- Avoid high-gloss, sci-fi gradients, or clean fantasy filigree.

## Iconography Direction
- Icons should read as stencil/signage marks at small sizes.
- Energy icon set is state-driven and must stay visually consistent across tiers (`100`, `75`, `50`, `25`, `0`).
- Home icon should remain clear and centered in the corner-left circular socket.

## Component-Level Visual Rules
- Home Navigation Panel:
  - image-backed variants should render flush under title bar
  - image body width must match title bar width
- List surfaces:
  - Name/Link lists: strong row contrast and simple labels
  - Grid lists: consistent card bounds, icon/image-first readability
- Buttons:
  - shared base button behavior; Accept/Reject as semantic style variants

## Asset Pipeline Rules
- Source lane: `raw-assets/new_ux/`
- Runtime lane: `frontend/public/assets/new_ux/`
- Any newly used art must be copied to runtime lane and registered in `frontend/public/assets/packs/ui.json`.
- Do not bind scene logic directly to raw asset paths.

## Do / Don’t
### Do
- Keep silhouettes bold and interaction zones obvious.
- Favor clear section framing and consistent corner anchoring.
- Reuse shared textures/frames before introducing new decorative one-offs.

### Don’t
- Introduce competing art motifs per scene.
- Add noise that reduces button/label readability.
- Mix unrelated visual themes (high fantasy / sci-fi / neon UI) into rebuild surfaces.
