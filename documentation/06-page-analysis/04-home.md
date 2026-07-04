# Home Page Analysis

Route: `/home`  
Auth: authenticated  
Component: `HomePageComponent`

## UX Pieces

- Shared authenticated HUD with energy, teeth, commander name, navigation, and logout.
- PageFrame header with home breadcrumbs and dynamic subtitle.
- Large primary media card for `Start Run` or `Continue Run`.
- Compact media cards for `Warband`, `Academy` when unlocked, `Shop`, and `Inventory`.
- Optional `Dev Panel` tile when runtime config enables it.

## Data Displayed

- Whether the player has an active run from `hasActiveRun()`.
- Dynamic primary CTA label and route from `primaryLabel()` and `primaryRoute()`.
- Academy unlock state from `academyUnlocked()`.
- Dev panel availability from runtime config.
- Shared HUD values:
  - `profile().energyCurrent`
  - `profile().energyMax`
  - `profile().softCurrency`
  - `session().displayName`

## Notes

- This is the authenticated hub for all between-run navigation.
