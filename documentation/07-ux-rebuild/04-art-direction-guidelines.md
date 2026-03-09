# UX Rebuild - Art Direction Guidelines
----

Status: active
Last Updated: 2026-03-08
Owner: UX + Art + Frontend
Depends On: `documentation/03-ux/01-visual-design-guide.md`, `documentation/03-ux/08-page-layout-zones.md`

## Purpose
- Translate the canonical visual style into implementation-ready rebuild guidance.
- Keep scene composition, component styling, and asset production aligned.

## Core Rebuild Direction
- Tone: grim, tactical, improvised, and slightly absurd.
- Visual language: militarized propaganda diorama built from distressed paper-craft materials.
- Readability: hierarchy and interaction clarity take priority over decoration.

## Rebuild Asset Baseline
- Global scene background: `new_ux/textures/paper.png`
- Section title bars: `new_ux/textures/red.png`
- Global control treatment:
  - bottom split command strip with distinct left and right visual segments
  - left segment contains warband link, dice link, and energy display
  - right segment contains logout action and player-name display

## Color Direction (Locked)
Primary UI palette tokens:
- Stark Cream: `#F3EFE0`
- Revolutionary Red: `#B91C1C`
- Cold Slate: `#4F5A65`
- Dirty Teal: `#006F7A`
- Deep Charcoal: `#23272A`

Optional accents:
- Drab Olive: `#5E6B3C`
- Dirty Brass: `#8A6D3B`
- Smoked Gray: `#6B7075`

Usage rules:
- Keep color use restrained and tactical.
- Use red as the strongest action/alert emphasis.
- Avoid bright playful color combinations.

## Typography Direction (Updated)
- Display/Headers/CTA labels: `Big Shoulders Stencil Text` (fallback: `Saira Stencil One`)
- Body/support text: `IBM Plex Sans Condensed` (fallback: `Roboto Condensed`)

Typography rules:
- Command labels and major headers should be uppercase.
- Preserve readability over textured surfaces.
- Allow subtle print imperfections, not heavy distortion.

## Texture and Surface Rules
- Show physical assembly cues: layered paperboard, tape wear, ink bleed, pinning/fastener details.
- Distress must cluster on edges and corners.
- Keep content zones clean enough for high-contrast text and icon legibility.
- Avoid glossy, sci-fi, polished fantasy, or toy-like surfaces.

## Component-Level Visual Rules
- Home navigation panel:
  - image-backed variants render flush beneath title bars
  - body width matches title bar width
- Bottom command strip:
  - full-width anchored bar with clear left/right segmentation
  - left side prioritizes utility links and resource readout
  - right side prioritizes account identity and exit action
- List surfaces:
  - clear row segmentation and high contrast for scanability
  - utilitarian registry look over decorative cards
- Buttons:
  - use stamped-order feel for decisive actions
  - Accept/Reject remain semantic variants of shared base behavior

## Iconography Direction
- Icons should read as stencil-signage marks at small sizes.
- Keep forms bold, simple, and procedural.
- If energy is represented with icons, tiers (`100`, `75`, `50`, `25`, `0`) must remain visually consistent as one family.

## Asset Pipeline Rules
- Source lane: `raw-assets/new_ux/`
- Runtime lane: `frontend/public/assets/new_ux/`
- Copy any newly used art to runtime lane and register in `frontend/public/assets/packs/ui.json`.
- Do not bind runtime scene logic directly to raw asset paths.

## Do / Don't
### Do
- Keep silhouettes strong and modular.
- Prioritize practical usability and tactical severity.
- Reuse shared textures/frames before adding one-off motifs.

### Don't
- Mix unrelated themes per scene.
- Over-distress controls until labels become hard to read.
- Introduce cute/mascot-like or playful toy presentation.
