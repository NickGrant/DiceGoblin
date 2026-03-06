# Page Layout Zones
----

Status: active  
Last Updated: 2026-03-06  
Owner: UX + Frontend  
Depends On: `frontend/src/layout/pageLayout.ts`, `raw-assets/new_ux`

## Purpose
- Define the new UX rebuild layout contract from the reference overlay image.
- Keep home-scene navigation areas and top-corner widgets visually consistent.

## Asset Contract
- Source art lane: `raw-assets/new_ux/`
- Runtime copies must live under `frontend/public/assets/new_ux/`
- Global background texture for scenes: `textures/paper.png`
- Title bar texture for major action regions: `textures/red.png`
- Top-left corner plate: `ui_kit/corner_left.png`
- Top-right corner plate: `ui_kit/corner_right.png`

## Color-Key Guide (Reference Overlay)
- `#e00000`: corner-left plate area (home icon inside the circular socket)
- `#ff0000`: corner-right plate area (energy icon inside the circular socket)
- `#0600ff`: Start a Run area
- `#00f6ff`: Manage Warband area
- `#00ff72`: Manage Inventory area

## Home Scene Structure
- Top corner plates are fixed `300x218`:
  - left at `(0, 0)`
  - right at `(width - 300, 0)`
- Navigation regions start below the corner band and follow split layout:
  - left column: Start a Run
  - right column top: Manage Warband
  - right column bottom: Manage Inventory
- Each non-corner region must include:
  - full-width title bar using `textures/red.png`
  - `12px` inner margin
  - solid-color content box filling remaining area
  - click behavior on the full region that routes to target scene

## Global Scene Rules
- All scenes use `texture_paper` for `BackgroundImage`.
- Home icon should only be rendered inside `corner_left` on scenes where a home control exists.
- Energy icon should render inside `corner_right`; numeric energy appears via hover tooltip.
- Non-home scenes should use the shared dual-zone framing pattern (`content` + `buttons`)
  with red title bars and solid color fills to keep navigation and action placement consistent.

## Implementation Notes
- Use `getPageLayout(scene)` to get canonical top-band and content/button split bounds.
- Keep corner widgets anchored to scene edges; avoid extra offsets.
