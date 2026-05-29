# Authentication & Session Model

Status: active  
Last Updated: 2026-05-28  
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
  - `display_name`
  - `avatar_url` (optional)

## Cookie Settings

- `HttpOnly: true`
- `SameSite: Lax`
- `Secure: true` in production
- Session ID regenerated on login

## API Auth Rule

The frontend startup/session layer is the only application layer that should directly query `/api/v1/session`.

During the Angular migration, this responsibility moves from the legacy Phaser `BootScene` to an Angular session service, initializer, or route guard. Page components and Phaser-hosted renderers should consume session state through the Angular frontend state layer rather than each querying the session endpoint independently.

## Legacy Note

Before the Angular migration, `BootScene` owned session bootstrap and all other Phaser scenes trusted session data passed from that scene. That behavior remains the reference for the legacy Phaser branch, but it is not the target ownership model for new Angular work.
