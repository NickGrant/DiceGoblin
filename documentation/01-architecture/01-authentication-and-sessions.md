# Authentication & Session Model

## Authentication Flow
1. User clicks "Log in with Discord"
2. Browser redirects to backend `/auth/discord/start`
3. Discord OAuth authorization
4. Discord redirects to `/auth/discord/callback`
5. Backend:
   - Validates OAuth state
   - Exchanges code for token
   - Fetches Discord identity
   - Upserts user into database
   - Regenerates session ID
   - Stores local `user_id` in session
6. Browser redirects back to frontend

## Session Model
- Cookie-based PHP sessions
- Session contains:
  - `user_id` (local DB ID)
  - `display_name`
  - `avatar_url` (optional)

## Cookie Settings
- `HttpOnly: true`
- `SameSite: Lax`
- `Secure: true` in production
- Session ID regenerated on login

## API Auth Rule
> Only BootScene is allowed to query `/api/v1/session`.

All other frontend scenes must trust data passed from BootScene.
