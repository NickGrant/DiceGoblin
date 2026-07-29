# Academy Page Analysis
----

Status: active  
Last Updated: 2026-07-29  
Owner: UX + Engineering  
Depends On: `documentation/06-page-analysis/00-index.md`, `documentation/03-ux/08-page-layout-zones.md`  


Route: `/academy`  
Auth: authenticated, feature-gated  
Component: `AcademyPageComponent`

## UX Pieces

- Shared authenticated HUD.
- PageFrame header for the academy.
- Alert stack for errors, success messages, and unit lock warnings.
- Two-column layout:
  - `Research Wing`
  - `Promotion Hall`

## Data Displayed

### Research Wing

- Current teeth from `profile().softCurrency`.
- Available Tier I unit unlock cards.
- Per unlock:
  - role label
  - unit type name
  - description
  - teeth cost
  - working state while purchase is in progress

### Promotion Hall

- Dropdown of promotable units with:
  - unit name
  - unit type name or slug
  - tier
  - current level and max level
- Selected primary unit summary.
- Promotion readiness state from `promotionContext()`.
- Mastery and capstone status.
- Capstone choice cards when applicable.
- Promotion destination dropdown and promotion preview data:
  - target unit type
  - promotion mode
  - immediate promotion grants
  - inherited passive summary
  - warning when current capstone would be skipped
- Eligible secondary units to consume for promotion.

## Notes

- This page combines roster research and promotion management.
- The route redirects to `/shop` until the academy feature unlock is owned.
