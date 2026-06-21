# Page Layout Zones
----

Status: active
Last Updated: 2026-03-08
Owner: UX + Frontend
Depends On: `frontend/src/layout/pageLayout.ts`, `raw-assets/new_ux`

## Purpose
- Define the UX rebuild layout contract from the reference overlay image.
- Keep home-scene navigation regions and the authenticated shell consistent.

## Asset Contract
- Source art lane: `raw-assets/new_ux/`
- Runtime copies must live under `frontend/public/assets/new_ux/`
- Global background texture for scenes: `textures/paper.png`
- Title bar texture for major action regions: `textures/red.png`
- Global bottom command strip visuals:
  - left segment container (warband, dice, energy)
  - right segment container (logout, player name)

## Color-Key Guide (Reference Overlay)
These colors are layout/debug references only, not canonical UI art palette tokens.

- `#0600ff`: Start a Run area
- `#00f6ff`: Manage Warband area
- `#00ff72`: Manage Inventory area
- `#ff9f00`: Bottom strip left segment
- `#8a00ff`: Bottom strip right segment

## Home Scene Structure
- Navigation regions follow split layout:
  - left column: Start a Run
  - right column top: Manage Warband
  - right column bottom: Manage Inventory
- Each region must include:
  - full-width title bar using `textures/red.png`
  - `12px` inner margin
  - solid-color or image-backed content body
  - click behavior on the full region that routes to target scene

## Authenticated Shell Contract
- Authenticated screens should read as one persistent game shell, not isolated website pages inside a generic container.
- The global HUD is anchored to the top edge and reserves vertical space for content via shared shell spacing variables.
- Major authenticated routes should fill the available viewport height intentionally.
- Shared page frames should stretch vertically when the screen has excess height instead of floating as short cards in the middle of the page.
- Shell/layout work should be mobile-first and progressively enhanced at these breakpoints:
  - `0-440px`
  - `441-760px`
  - `761px+`
- Breakpoint intent:
  - `0-440px`: compact handset layout, densest HUD treatment, drawer-based navigation
  - `441-760px`: expanded mobile/tablet layout with more breathing room but still one compact shell
  - `761px+`: full desktop presentation with persistent split HUD surfaces and wider page frames

## Global Scene Rules
- All scenes use `texture_paper` for `BackgroundImage`.
- Authenticated scenes render one persistent top HUD with navigation and player-state surfaces.
- Mobile authenticated scenes should prefer one compact HUD surface plus an expandable menu rather than stacking multiple persistent bars.
- Non-home scenes keep the shared dual-zone framing pattern (`content` + `buttons`) inside the authenticated shell rather than above a bottom strip.

## Shared Component Inventory
Shared layout/system components:
- Home navigation panel
- Bottom command strip
- Section title bar
- Content area frame
- List container
- Name/link list
- Grid list
- Formation grid
- Generic action button
- Accept button
- Reject button
- Confirmation dialog
- Toast/status feedback
- Tooltip
- Rest summary panel
- Run-end summary panel

Run flow components:
- Region selection card
- Run map graph surface
- Run node visual
- Run node-edge/unlock indicator
- Run action list

## Shared Interaction Rules
- List surfaces should normalize `loading`, `ready`, `empty`, and `error` states.
- Accept and Reject actions should be semantic variants of the same shared button behavior.
- Authenticated screens should keep the shared shell treatment consistent even when page content changes.
- Region selection should preserve inline feedback when a start action is blocked or fails.
- Run map invalid payloads should fail into a readable page-level fallback rather than silently breaking.
- Unknown node or icon variants should render a generic fallback instead of an empty surface.
- Invalid formation placements should be blocked with immediate user feedback.

## Implementation Notes
- Use `getPageLayout(scene)` to derive canonical content and bottom-strip bounds.
- The shared Angular shell should own top-HUD spacing and content viewport padding.
- Shared page frames should participate in the shell height contract rather than relying on Bootstrap container width alone.
- HUD surfaces can remain visually distinct while sharing one global component contract.
