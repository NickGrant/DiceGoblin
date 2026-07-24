# Authentication & Session Model

Status: active  
Last Updated: 2026-07-23
Owner: Backend/API  
Depends On: `backend/public/index.php`, `documentation/01-architecture/03-backend-api-contracts.md`, `documentation/01-architecture/05-angular-frontend-architecture-plan.md`

## Authentication Flow

Dice Goblins supports two account entry paths that converge on the same PHP session model.

### Local Credentials

1. User submits local registration or sign-in from `/login`.
2. Frontend posts credentials to the backend local auth endpoint.
3. Backend validates the request.
4. For registration, backend creates a local user row with normalized email and password hash.
5. For sign-in, backend verifies the submitted password against the stored hash.
6. Backend regenerates the PHP session ID, stores local `user_id`, rotates CSRF, and ensures baseline account state exists.
7. Frontend refreshes `/api/v1/session` and routes the user into the authenticated shell.

Password reset uses the same local credential store:

1. User submits an email to `/api/v1/auth/local/password-reset/request`.
2. Backend returns a generic success response for any valid email shape.
3. If a matching local account exists, backend stores a hashed one-hour reset token and marks previous open tokens used.
4. Non-production environments may return the raw token directly for Docker/dev workflows.
5. User submits token and new password to `/api/v1/auth/local/password-reset/confirm`.
6. Backend consumes the token, updates the password hash, regenerates the session ID, and returns the normal session payload.

### Discord OAuth

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
- Local email and password hash fields are credential data only and must not be exposed in session or profile payloads.
- Password reset stores only hashed reset tokens and never returns account-existence information from reset requests.

## Cookie Settings

- `HttpOnly: true`
- `SameSite: Lax`
- `Secure: true` in production
- Session ID regenerated on login

## API Auth Rule

- The Angular session/bootstrap layer remains the only feature-facing owner of session state.
- Page components and any future Phaser-hosted renderers should consume session state through the Angular frontend state layer rather than querying `/api/v1/session` directly.
- The low-level HTTP client is allowed to re-read `/api/v1/session` before CSRF-protected mutations so it can attach a fresh token without duplicating session logic inside feature services.
- Login and registration endpoints do not require an existing CSRF token because they create the session. Authenticated state-changing endpoints remain CSRF-protected.
