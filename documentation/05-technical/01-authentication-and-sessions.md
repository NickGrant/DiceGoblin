---
Title: "Authentication and Session Model"
Status: Canonical
Last Updated: 2026-09-10
Owner: Engineering
Depends On:
  - documentation/07-development-path/vnext-storage-model.md
  - documentation/07-development-path/vnext-endpoint-inventory.md
Category: 05-technical
Tags: [technical, auth, sessions, vnext]
---

# Authentication and Session Model

A Dice Goblins `users.id` represents the player independently of how that player proves identity.

## Supported Identity Paths
- Local email/password credentials.
- Discord OAuth identity.
- A Discord-first user may later add local credentials.
- A local-first user may later link Discord.

Authentication methods attach to the existing player account; gameplay tables reference the player user ID, never an OAuth/local credential identity.

Email similarity may signal a likely duplicate but must not silently merge accounts. Linking requires proof/control of the existing authenticated account. General account merging is not part of the initial vNext baseline.

## Storage Boundary
- `users` stores player/profile identity and server-controlled role.
- local credentials/password hashes live in a separate credentials table.
- provider identities live in an external-identity table keyed by provider + provider user ID.
- password reset state is separate credential infrastructure.
- `role` is a server-validated string with `user` as the default and `admin` as the anticipated second role; no general RBAC system is required now.

## Session Boundary
Browser authentication remains cookie/session based. Mutating authenticated endpoints use CSRF protection. Angular owns login/account flows and protects entry to `/game`.

Once Phaser is mounted it is a first-class gameplay API client using the existing authenticated browser session; Angular does not proxy or own Phaser gameplay calls.

`GET /api/v1/session` remains account/session infrastructure. `GET /api/v1/game/bootstrap` is the Phaser gameplay bootstrap and returns the session/system metadata needed by the game.
