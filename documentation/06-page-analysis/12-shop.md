# Shop Page Analysis

Route: `/shop`  
Auth: authenticated  
Component: `ShopPageComponent`

## UX Pieces

- Shared authenticated HUD.
- PageFrame header for the shop.
- Alert stack for errors, success messages, and loading state.
- Tab strip with shop categories.
- Two shop modes:
  - `supplies`
  - feature unlocks

## Data Displayed

### Supplies Tab

- `Starter Dice` object grid.
- `Daily Deals` object grid.
- `Fresh Meat` unit recruitment object grid.
- Purchase buttons for each object card with teeth cost and busy state.

### Feature Unlock Tab

- `Camp Upgrades` header with current teeth chip.
- Feature unlock card grid.
- Per unlock card:
  - eyebrow label
  - name
  - teeth cost
  - description
  - requirement text when locked
  - CTA label based on state
  - unlocked visual state

## Notes

- The shop is split between consumable or repeatable economy content and long-term system unlocks.
