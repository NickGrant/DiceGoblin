---
Title: "Run Summary Page Analysis"
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

# Run Summary Page Analysis

Route: `/run/summary`  
Auth: authenticated  
Component: `RunSummaryPageComponent`

## UX Pieces

- Shared authenticated HUD.
- PageFrame header using the summary title.
- `Return Home` CTA.
- Two main summary cards:
  - `Rewards`
  - `Progression`

## Data Displayed

### Rewards Section

- Teeth reward value when greater than zero.
- Unit reward card grid when full unit payloads are available.
- Unit fallback label list when only strings are available.
- Dice reward card grid when full dice payloads are available.
- Dice fallback label list when only strings are available.
- Empty-state copy when no rewards were recorded.

### Progression Section

- Unit progression card grid when progression cards can be built.
- Progress bars for unit XP progression.
- Fallback progression string list when only text items are available.
- Empty-state copy when no progression milestones were recorded.

## Notes

- This page handles completed, failed, and abandoned runs through one summary surface.
