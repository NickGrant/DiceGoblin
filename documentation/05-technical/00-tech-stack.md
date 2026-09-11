---
Title: "Technical Stack Decisions"
Status: Canonical
Last Updated: 2026-09-10
Owner: Engineering
Depends On:
  - documentation/07-development-path/vnext-backend-internal-architecture.md
  - documentation/07-development-path/vnext-phaser-client-architecture.md
Category: 05-technical
Tags: [technical, stack, vnext]
---

# Technical Stack Decisions

## Client
- Angular + TypeScript for public site, authentication/account shell, and `/game` hosting.
- Phaser 3 for the complete gameplay client once `/game` mounts.
- Angular Router remains a website/platform concern; Phaser owns in-game navigation.

## Backend
- PHP 8.3 authoritative application.
- Lightweight HTTP routing may remain; backend internals follow controller -> application -> domain/engine -> repositories/content-registry boundaries.
- Cookie/session authentication with CSRF protection for authenticated mutations.

## Storage and Content
- MySQL 8.4 for mutable player and runtime state.
- Git-tracked JSON for canonical authored gameplay content.
- No required duplicate SQL catalog for authored definitions.

## Development Infrastructure
- Docker/Docker Compose remain the preferred local PHP/MySQL environment.
- Existing source may temporarily contain prototype migrations/services/pages during the overhaul. Their presence is migration evidence, not permission to preserve their architecture.

## Non-Goals
vNext does not introduce microservices, a new backend language/framework, an ORM requirement, full event sourcing, or asynchronous gameplay messaging without a separate explicit decision.
