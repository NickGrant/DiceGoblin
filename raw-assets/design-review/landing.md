Screenshot: `raw-assets/design-review/screenshots/landing.png`

## Purpose of the Screen
Introduce the game loop to unauthenticated players and give them a single login action that starts the session flow toward `HomeScene`.

## Needed Player Interactions
- Read the welcome copy and feature callouts.
- Click `Log in with Discord`.

## Information Need to Be Conveyed to Player
- The core loop is run selection, warband management, and reward carryover.
- This is the game entry point, not a decision-heavy menu.
- Login is the only meaningful next step.

## Current Visual Challenges
- The screen carries a lot of copy before the primary CTA, so the eye can linger on description instead of the login action.
- Feature cards, title copy, and body copy compete within the same content frame.
- The right action frame is sparse compared with the dense left frame, which makes the composition feel uneven.

## In-Screen Changes That Can Occur
- Clicking the login button shows a brief `Redirecting...` toast.
- The browser is redirected to the Discord auth start endpoint.
