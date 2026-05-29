# Technical Stack Decisions

Status: active  
Last Updated: 2026-05-29  
Owner: Engineering  
Depends On: `README.md`, `documentation/01-architecture/05-angular-frontend-architecture-plan.md`, `documentation/01-architecture/06-angular-component-service-inventory.md`, `documentation/01-architecture/03-backend-api-contracts.md`

These decisions are the active frontend architecture unless explicitly revisited.

## Frontend

Current frontend stack:

- Angular 20 LTS
- Angular CLI application builder and dev server
- TypeScript
- Angular Router
- Bootstrap 5 grid and utility classes for layout scaffolding
- Angular-owned application shell, routing, page composition, forms, lists, dialogs, HUD, and feature services
- Angular DOM/SVG run-map rendering
- Phaser 3 reserved for explicitly canvas-owned experiences if battle playback or similar rendering is restored

Legacy frontend stack:

- TypeScript
- Vite
- Phaser 3 as the full application shell and UI runtime

Rationale:

- Angular is better suited for page routing, ordinary application UI, forms, reusable DOM components, accessibility, and management screens.
- Angular now owns the player-facing shell and management flows.
- Phaser remains optional for future battle playback, animated grid rendering, sprite-heavy effects, and deterministic canvas workflows.
- The boundary is now: Angular owns app orchestration and state; Phaser is only reintroduced for explicitly canvas-owned experiences.

## Backend

- PHP 8.3
- Custom lightweight router
- Cookie-based sessions
- MySQL 8

Rationale:

- PHP session handling simplifies auth and persistence.
- No backend framework adoption is required for the current frontend architecture.
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

- The frontend now uses Angular CLI in `frontend/`, still served on port `5173` for local and Docker parity.
- Runtime asset delivery continues through `frontend/public/assets`.
