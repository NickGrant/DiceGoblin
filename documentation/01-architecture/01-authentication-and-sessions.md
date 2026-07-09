# Authentication & Session Model

Status: active  
Last Updated: 2026-07-09  
Owner: Backend/API  
Depends On: `backend/public/index.php`, `documentation/01-architecture/03-backend-api-contracts.md`, `documentation/01-architecture/05-angular-frontend-architecture-plan.md`

## Authentication Flow

1. User clicks "Log in with Discord".
2. Browser redirects to backend `/auth/discord/start`.
3. Discord OAuth authorization completes.
4. Discord redirects to `/auth/discord/callback`.
5. Backend:
   - validates OAuth state
   - exchanges code for provider identity
   - upserts user into database
   - regenerates session ID
   - stores local `user_id` in session
6. Browser redirects back to frontend.
7. Frontend session bootstrap checks `/api/v1/session` and routes the user to the appropriate Angular page.

## Session Model

- Cookie-based PHP sessions
- Session contains:
  - `user_id` (local DB ID)
- Display-facing profile fields such as `display_name` and `avatar_url` are loaded from the database-backed session payload, not treated as canonical PHP session keys.

## Cookie Settings

- `HttpOnly: true`
- `SameSite: Lax`
- `Secure: true` in production
- Session ID regenerated on login

## API Auth Rule

- The Angular session/bootstrap layer remains the only feature-facing owner of session state.
- Page components and any future Phaser-hosted renderers should consume session state through the Angular frontend state layer rather than querying `/api/v1/session` directly.
- The low-level HTTP client is allowed to re-read `/api/v1/session` before CSRF-protected mutations so it can attach a fresh token without duplicating session logic inside feature services.
