# Technical Stack Decisions

Status: active  
Last Updated: 2026-05-29  
Owner: Engineering  
Depends On: `README.md`, `documentation/01-architecture/05-angular-frontend-architecture-plan.md`, `documentation/01-architecture/06-angular-component-service-inventory.md`, `documentation/01-architecture/03-backend-api-contracts.md`

These decisions are considered active for the Angular migration unless explicitly revisited.

## Frontend

Target frontend stack:

- Angular
- Angular CLI application builder and dev server
- TypeScript
- Angular Router
- Bootstrap 5 grid and utility classes for layout scaffolding
- Angular-owned application shell, page composition, forms, lists, dialogs, HUD, and state facades
- Phaser 3 embedded only through Angular host components for canvas/gameplay surfaces

Legacy/current frontend stack before migration:

- TypeScript
- Vite
- Phaser 3 as the full application shell and UI runtime

Rationale:

- Angular is better suited for page routing, ordinary application UI, forms, reusable DOM components, accessibility, and management screens.
- Phaser remains valuable for combat playback, animated grid rendering, sprite-heavy effects, and deterministic canvas workflows.
- The migration should create a clean boundary: Angular owns app orchestration and state; Phaser renders canvas-owned experiences from snapshots and bridge events.

## Backend

- PHP 8.3
- Custom lightweight router
- Cookie-based sessions
- MySQL 8

Rationale:

- PHP session handling simplifies auth and persistence.
- No backend framework adoption is required for this frontend migration.
- Existing API contracts should remain stable unless a route-specific frontend requirement exposes a real contract gap.

## Authentication

- Discord OAuth 2.0
- Cookie-based session auth
- No guest accounts

Rationale:

- Multiplayer features require identity.
- Discord provides frictionless login for target audience.
- Removes complexity of anonymous state merging.

## Infrastructure

Current/dev infrastructure:

- Docker + Docker Compose
- Frontend dev server
- PHP built-in server
- MySQL + phpMyAdmin

Migration note:

- The frontend now uses Angular CLI in `frontend/`, still served on port `5173` for local and Docker parity.
- Runtime asset delivery continues through `frontend/public/assets`.
