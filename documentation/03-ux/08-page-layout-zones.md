# Page Layout Zones
----

Status: active  
Last Updated: 2026-03-04  
Owner: UX + Frontend  
Depends On: `frontend/src/layout/pageLayout.ts`

## Purpose
- Define a consistent page layout contract for Phaser scenes.
- Preserve readable UI spacing while keeping background art full-bleed.

## Color-Key Guide (Reference Overlay)
- `#778cff`: padding/safe boundary. UI elements should not overlap this boundary band.
- `#00ff0c`: top-right HUD zone (energy + player name).
- `#fff000`: top-left home icon zone.
- `#ff0000`: right-side action button column.
- `#00d3e0`: main screen content zone.

## Layout Rules
- Background images should always fill the entire scene width/height.
- UI elements must be placed inside the shared layout zones from `pageLayout.ts`.
- Header row is reserved for navigation/HUD only.
- Main content should live in `content`.
- Action controls should live in `buttons`.

## Canonical Zone Geometry (1280x720)
- Outer safe frame (`padding`):
  - `x: 16`, `y: 16`, `width: 1248`, `height: 688`
- Header row (`header`):
  - `x: 16`, `y: 16`, `width: 1248`, `height: 96`
- Home icon (`homeIcon`):
  - `x: 16`, `y: 16`, `width: 96`, `height: 96`
- HUD (`hud`):
  - `x: 944`, `y: 16`, `width: 320`, `height: 96`
- Content (`content`):
  - `x: 16`, `y: 128`, `width: 912`, `height: 576`
- Buttons (`buttons`):
  - `x: 944`, `y: 128`, `width: 320`, `height: 576`

## Implementation Notes
- Use `getPageLayout(scene)` to fetch normalized zones per scene size.
- Avoid hardcoded scene coordinates unless they are derived from these zone rects.
