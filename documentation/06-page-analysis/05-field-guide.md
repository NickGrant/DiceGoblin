# Field Guide Page Analysis

Route: `/field-guide`  
Auth: authenticated  
Component: `GuidePageComponent`

## UX Pieces

- Shared authenticated HUD.
- Same PageFrame, hero, quick-start, and tab-strip structure as the public guide.
- Chapter-specific codex layouts for overview, warband, dice, and expeditions.

## Data Displayed

- Everything listed on the public guide page.
- Live acquired-state overlays when player profile data is available:
  - feature unlock acquisition state
  - unit unlock acquisition state
- Shared HUD values:
  - energy
  - teeth
  - commander name

## Notes

- This is the in-shell version of the guide.
- The component doubles as both the public guide and authenticated field guide surface.
