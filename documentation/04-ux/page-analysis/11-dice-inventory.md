---
Title: "Dice Inventory Page Analysis"
Status: Needs Review
Last Updated: 2026-08-02
Owner: Product + UX
Depends On:
  - documentation/04-ux/page-analysis/00-index.md
  - documentation/04-ux/08-page-layout-zones.md
  - documentation/03-content/14-dice-materials.md
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
- PageFrame header for inventory.
- Alert stack for errors and success messages.
- Filter and sort controls.
- Dice rail for owned dice.
- Inspect side panel for the selected die.
- Sell confirmation modal for unequipped dice.

## Data Displayed

### Controls

- Filters for active size (`d4`, `d6`, `d8`, `d10`), material rarity, material tag, and equip state.
- Sort by size, material name, rarity, or value.
- Result count as `filteredDice / totalDice`.
- No `d12` or `d20` filter is current.

### Dice Rail

- One compact `dg-dice-grid-object` tile per die.
- Tile identity is material-led, such as `Peach Pit d4` or `Glass d10`.
- Rarity color and label are derived from material.
- Equipped state marker appears when the die is bound to an ability slot.

### Inspect Panel

- Material rarity.
- Material-led die title.
- Equipped or loose state.
- Large die art preview.
- Material effect summary.
- Allowed active sizes for that material.
- Stacking or cap summary when relevant.
- Sell value and Raw Chaos salvage value when available.
- Primary action:
  - `View {unit}` when equipped
  - `Sell` when unequipped

Permanent affix lists are not part of the target-state panel. Legacy affix data may remain temporarily during migration but should not be presented as a second permanent customization layer once material dice are active.

### Modal

- Confirm-sell copy uses material-led die title and `sell_value`.
- Salvage confirmation, when offered, must show Raw Chaos payout and must be unavailable for equipped dice.

## Notes

- This page is inventory-focused rather than loadout-focused.
- Dice editing happens indirectly through the owning unit or a future Wrong Machine material-replacement workflow.
- Two dice with the same size and material are mechanically identical; tile differences should communicate ownership and equip state, not hidden quality.
