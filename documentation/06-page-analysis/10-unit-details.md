# Unit Details Page Analysis

Route: `/warband/units/:unitId`  
Auth: authenticated  
Component: `UnitDetailsPageComponent`

## UX Pieces

- Shared authenticated HUD.
- PageFrame header using the unit name, class label, and level.
- Alert stack for errors, success messages, and active-run lock warning.
- Tab strip with `stats` and `abilities`.

## Data Displayed

### Stats Tab

- Rename input for display name.
- Promotion CTA pointing to academy or shop depending on unlock state.
- Unit portrait or portrait-pending placeholder.
- Stats panel showing:
  - tier
  - level and max level
  - current HP and max HP
  - total attack
  - total defense
  - promotion eligibility label
  - mastery state
  - selected capstone label
- Capstone section showing:
  - selected capstone chip when present
  - explanatory copy for current capstone state
  - capstone choice cards when available
- Inherited passives section showing inherited passive abilities and their source class lineage.

### Abilities Tab

- Learned active abilities list with:
  - display name
  - short description
  - speed
  - equipped count
  - plus action to add into loadout
  - inline dice slots with art or empty states
- Learned passive abilities list.
- Combat loadout lane with:
  - equipped ability bars
  - speed and dice-cost metadata
  - move-up, move-down, and remove controls
  - total loadout budget out of 20
- Save loadout button.
- Dice picker modal for slot assignment.

## Notes

- This is one of the densest player pages in the product.
- It combines identity, progression, capstone, active ability ordering, and dice slot assignment in one route.
