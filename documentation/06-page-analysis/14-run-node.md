# Run Node Page Analysis
----

Status: active  
Last Updated: 2026-07-29  
Owner: UX + Engineering  
Depends On: `documentation/06-page-analysis/00-index.md`, `documentation/03-ux/08-page-layout-zones.md`  


Route: `/run/node/:nodeId`  
Auth: authenticated  
Component: `RunNodePageComponent`

## UX Pieces

- Shared authenticated HUD.
- PageFrame header with dynamic title and subtitle.
- Reward-claim button once a result is available.
- Loading and error alerts.
- Dynamic content that branches by node outcome:
  - loot node presentation
  - combat or boss node presentation
  - encounter-loading placeholder

## Data Displayed

### Common Result State

- Claim button with dynamic label from `claimButtonLabel()`.
- Dynamic page title and subtitle from `pageTitle()` and `pageSubtitle()`.

### Loot Node Mode

- Treasure hero block.
- Loot summary stats for:
  - teeth
  - number of dice found
  - number of units found
- Node summary for status and unlocked next-path count.
- Chip lists for found dice labels and unit labels.

### Combat or Boss Mode

- Outcome summary showing:
  - battle outcome label
  - round count
- Ability catalog error alert when combat text enrichment fails.
- Battle log entries showing:
  - actor unit card
  - target unit card
  - ability name
  - action result text with tooltip segments where applicable

### Pre-Result Mode

- Encounter-loading placeholder with dynamic copy for prepare versus resolve state.
- Retry-resolution button when applicable.

## Notes

- This route is doing two jobs today: combat resolution review and loot-claim review.
