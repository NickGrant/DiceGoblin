Screenshot: `raw-assets/design-review/screenshots/inventory.png`

## Purpose of the Screen
Provide the dedicated dice inventory surface for browsing, filtering, inspecting, and routing dice back into unit loadouts.

## Needed Player Interactions
- Select dice cards from the grid.
- Change sort order and cycle size, rarity, and equipped filters.
- Sell an unequipped die or jump to the unit currently using the selected die.
- Read hover or selection details before making a management decision.

## Information Need to Be Conveyed to Player
- Die size, rarity, slot capacity, affixes, and sell value.
- Whether a die is free, equipped, and if so where it is currently bound.
- Which filters are active and why some dice are hidden from the grid.
- Whether the player is in normal inventory flow or a rest-context return path.

## Current Visual Challenges
- The screen presents a lot of similar card shapes, so strong selected and equipped states are important for quick scanning.
- Hover-detail behavior risks hiding important information on touch devices if the selected state is not strong enough on its own.
- Filter and sort controls are compact, which can make the current inventory state easy to overlook.
- The action column must balance utility with clarity so selling and navigation do not feel equally primary.

## In-Screen Changes That Can Occur
- Sort and filter controls immediately change the visible dice grid.
- Selecting or hovering a die updates the action summary and details panel.
- Sell actions mutate the profile-backed inventory and redraw the grid.
- In rest flow, the return destination can shift away from `HomeScene` to `RestManagementScene`.
