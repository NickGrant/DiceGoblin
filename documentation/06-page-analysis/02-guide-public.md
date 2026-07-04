# Public Guide Page Analysis

Route: `/guide`  
Auth: public  
Component: `GuidePageComponent`

## UX Pieces

- Global guest HUD with public-safe navigation behavior.
- PageFrame header for `Field Guide`.
- Guide hero section with chapter eyebrow, chapter title, chapter summary, and a `Quick Start` ordered list.
- Chapter tab strip for `overview`, `warband`, `dice`, and `expeditions`.
- Chapter-specific article blocks, tiles, callouts, and codex grids.

## Data Displayed

- Static guide chapter metadata including chapter kickers, titles, and summaries.
- Static quick-start steps.
- Feature unlock reference cards with name, cost, description, and acquired state when profile data is available.
- Node legend entries with node icon, name, and description.
- Unit unlock codex data with unit name, role, tier, max level, summary, and art.
- Unit codex roster entries with class info and summaries.
- Dice family, die size, and affix reference data.
- Expedition tips and rules text.

## Notes

- This route uses the same component as the authenticated field guide.
- In guest use, the page still functions as a readable codex even without live player progression context.
