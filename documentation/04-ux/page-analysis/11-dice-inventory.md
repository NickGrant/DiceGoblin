---
Title: "Dice Inventory Page Analysis"
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

# Dice Inventory Page Analysis

Route: `/dice`  
Auth: authenticated  
Component: `DicePageComponent`

## UX Pieces

- Shared authenticated HUD.
- PageFrame header for the inventory.
- Alert stack for errors and success messages.
- Filter and sort controls.
- Dice rail for owned dice.
- Inspect side panel for the selected die.
- Sell confirmation modal for unequipped dice.

## Data Displayed

### Controls

- Filters for size, rarity, and equip state.
- Sort dropdown for size and rarity ordering.
- Result count as `filteredDice / totalDice`.

### Dice Rail

- One compact `dg-dice-grid-object` tile per filtered die.
- Equipped state marker on tiles when the die is in use.

### Inspect Panel

- Rarity label.
- Die title.
- Equipped or loose state.
- Large die art preview.
- Affix list with affix name and description when present.
- Primary action:
  - `View {unit}` when equipped
  - `Sell` with sell value when unequipped

### Modal

- Confirm-sell copy using die title and `sell_value`.

## Notes

- This page is inventory-focused rather than loadout-focused.
- Dice editing happens indirectly by navigating to the owning unit.
