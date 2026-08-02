---
Title: "Codex Page Analysis"
Status: Needs Review
Last Updated: 2026-08-01
Owner: Product + UX
Depends On:
  - documentation/04-ux/page-analysis/00-index.md
  - documentation/04-ux/08-page-layout-zones.md
Category: 04-ux
Tags:
  - ux
  - page-analysis
---

# Codex Page Analysis

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
