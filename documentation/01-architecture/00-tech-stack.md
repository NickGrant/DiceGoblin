# Technical Stack Decisions

Status: active  
Last Updated: 2026-05-29  
Owner: Engineering  
Depends On: `README.md`, `documentation/01-architecture/05-angular-frontend-architecture-plan.md`, `documentation/01-architecture/06-angular-component-service-inventory.md`, `documentation/01-architecture/03-backend-api-contracts.md`

These decisions are the active architecture unless explicitly revised.

## Frontend

Current frontend stack:

- Angular 20 LTS
- Angular CLI application builder and dev server
- TypeScript
- Angular Router
- Bootstrap 5 grid and utility classes for layout scaffolding
- Angular-owned application shell, routing, page composition, forms, lists, dialogs, HUD, and feature services
- Angular DOM or SVG run-map rendering
- Phaser 3 only for explicitly canvas-owned experiences if battle playback or similar rendering is restored

Rationale:

- Angular is well suited for page routing, ordinary application UI, forms, reusable DOM components, accessibility, and management screens.
- Angular owns the player-facing shell and core flows.
- Phaser remains optional for battle playback, animated grid rendering, sprite-heavy effects, and deterministic canvas workflows when those surfaces are worth the complexity.

## Backend

- PHP 8.3
- custom lightweight router
- cookie-based sessions
- MySQL 8

Rationale:

- PHP session handling simplifies auth and persistence.
- No backend framework adoption is required for the current frontend architecture.
- Existing API contracts should stay stable unless a route-specific frontend requirement exposes a real gap.

## Authentication

- Discord OAuth 2.0
- cookie-based session auth
- no guest accounts

Rationale:

- Multiplayer features require identity.
- Discord provides low-friction login for the target audience.
- This avoids anonymous-state merge complexity.

## Infrastructure

Current and local infrastructure:

- Docker and Docker Compose
- Angular frontend dev server
- PHP built-in server
- MySQL and phpMyAdmin

Notes:

- The frontend runs from `frontend/` on port `5173` in local and Docker workflows.
- Runtime asset delivery continues through `frontend/public/assets`.
