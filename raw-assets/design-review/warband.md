Screenshot: `raw-assets/design-review/screenshots/warband.png`

## Purpose of the Screen
Act as the warband hub by separating unit browsing from squad browsing, while giving the player a light summary before they drill into details.

## Needed Player Interactions
- Click a unit card to open `UnitDetailsScene`.
- Click a squad entry to open `SquadDetailsScene`.
- Create a new squad with the `+` button and name it through the modal.
- Use the bottom command strip for global navigation.

## Information Need to Be Conveyed to Player
- This screen is a hub, not the full editor for every warband system.
- Units and squads are separate concepts with different editing destinations.
- The summary panel should quickly answer how many units and squads exist and which squad is active.
- Unit cards use highlighting cues to show active-squad membership.

## Current Visual Challenges
- The summary column is narrow and text-light compared with the much denser unit and squad browsing areas.
- The `+` new-squad affordance is small and easy to miss relative to the rest of the layout.
- Unit cards communicate status mostly through subtle corner markers, which may be hard to read quickly.
- The screen depends on the player understanding that units and squads open different detail scenes, but that distinction is mostly structural rather than explicitly explained on-screen.

## In-Screen Changes That Can Occur
- The screen begins in a loading or failure state while profile data is fetched.
- Creating a squad opens an input modal, validates naming rules, and can show success or failure toast text.
- Successful squad creation routes directly into the new squad's details screen.
- Active squad summary values change when profile data changes.
