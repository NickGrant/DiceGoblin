# Codex Page Analysis
----

Status: active  
Last Updated: 2026-07-29  
Owner: UX + Engineering  
Depends On: `documentation/06-page-analysis/00-index.md`, `documentation/03-ux/08-page-layout-zones.md`  


Route: `/codex`  
Auth: authenticated  
Component: `frontend/src/app/pages/codex-page/CodexPageComponent`

## UX Pieces

- Shared authenticated HUD.
- PageFrame and codex hero.
- Vertical subnavigation in a two-column layout.
- Category-specific layouts for feature unlocks, unit unlocks, affixes, enemies, and lore.

## Data Displayed

- Progress snapshot metrics derived from player profile data.
- Feature unlock acquisition state, with locked entries marked by a lock icon.
- Unit unlock acquisition state, with locked sprite-backed entries shown as silhouettes.
- Seen affixes inferred from owned dice.
- Enemy records unlocked by cleared biomes, with locked sprite-backed entries shown as silhouettes.
- Seen dialogue entries rendered as lore pages.
- Shared HUD values:
  - energy
  - teeth
  - commander name

## Notes

- `/field-guide` is retained only as a legacy redirect to `/codex`.
- The public `/guide` route is a separate how-to-play and map glossary page.
