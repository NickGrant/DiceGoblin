# Frontend Scene Flow

## Scene List (MVP)

1. BootScene
2. LandingScene (unauthenticated only)
3. HomeScene
4. Region Select
5. Map Exploration
6. Combat
7. Loot
8. Rest
9. Boss
10. Warband Management
11. Dice Inventory
12. Dice Details
13. Unit Details

## BootScene Responsibilities
- Fetch `/api/v1/session`
- Determine authentication state
- Route user:
  - Authenticated → HomeScene
  - Unauthenticated → LandingScene

BootScene is the single source of truth for auth.

## LandingScene
- Title screen
- Login with Discord button
- No guest play
- No game state decisions

## HomeScene (MVP)
- Displays user identity
- Logout button
- Placeholder navigation for future systems