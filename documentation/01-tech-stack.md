# Technical Stack Decisions

These decisions are considered locked for MVP unless explicitly revisited.

## Frontend
- Phaser 3
- TypeScript
- Vite (dev + build)
- Vanilla Phaser scenes (no React/Angular)

Rationale:
- Phaser is well-suited for game state and rendering
- Avoids framework overhead
- Keeps scene flow explicit and controllable

## Backend
- PHP 8.3
- Custom lightweight router
- Cookie-based sessions
- MySQL 8

Rationale:
- PHP session handling simplifies auth and persistence
- No premature framework adoption
- Easy to host and debug

## Authentication
- Discord OAuth 2.0
- Cookie-based session auth
- No guest accounts

Rationale:
- Multiplayer features require identity
- Discord provides frictionless login for target audience
- Removes complexity of anonymous state merging

## Infrastructure
- Docker + Docker Compose (dev)
- Vite dev server (frontend)
- PHP built-in server (backend)
- MySQL + phpMyAdmin (dev)

Production hosting strategy TBD.
