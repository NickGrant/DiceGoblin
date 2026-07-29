# Regions Page Analysis
----

Status: active  
Last Updated: 2026-07-29  
Owner: UX + Engineering  
Depends On: `documentation/06-page-analysis/00-index.md`, `documentation/03-ux/08-page-layout-zones.md`  


Route: `/regions`  
Auth: authenticated  
Component: `RegionsPageComponent`

## UX Pieces

- Shared authenticated HUD.
- PageFrame header for starting the next run.
- Success and error alert stack.
- Region badge rail showing all currently surfaced regions.
- Hover/focus/selection-driven inspection panel for the active region card.
- Start-run confirmation modal.

## Data Displayed

- Region rail entries for:
  - `The Farm`
  - `Mountains`
  - `Swamps`
- Per region:
  - name
  - theme badge art
  - unlocked or locked state
  - current-run state
- In the inspection panel:
  - energy cost
  - recommended level
  - unlocked or current-run state label
  - region summary text
- Modal confirmation copy using the selected region name.

## Notes

- The page disables unavailable routes when the player already has an active run.
- If the active run already belongs to the selected region, the page continues to `/run/map` instead of starting a fresh run.
