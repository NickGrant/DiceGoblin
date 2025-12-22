# Frontend Scene Flow

## Scene Order (MVP)

1. BootScene
2. LandingScene (unauthenticated only)
3. HomeScene
4. (Future) Region Select
5. (Future) Map Exploration
6. (Future) Combat

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
