---
Title: "Engineering Standards"
Status: Canonical
Last Updated: 2026-09-10
Owner: Engineering
Depends On:
  - documentation/06-testing-release/00-testing-strategy.md
  - documentation/07-development-path/vnext-game-overhaul.md
Category: 08-operations
Tags: [operations, engineering, vnext]
---

# Engineering Standards

## Architectural Authority
Implementation follows accepted vNext decisions. Prototype code is migration evidence, not authority when it conflicts with those decisions.

- Angular owns web/auth/account shell and Phaser hosting, not gameplay pages/services.
- Phaser owns gameplay presentation/navigation/cache/API interaction after mount.
- PHP owns authoritative gameplay and application transactions.
- Repositories own persistence; domain/engine rules should not depend on HTTP.
- Canonical authored gameplay content is JSON; MySQL stores mutable runtime/player state.

## Change Scope
Build milestone-sized vertical capabilities. Avoid unrelated refactors, but remove obsolete compatibility code when the vNext slice that replaces it is proven. Do not preserve a prototype abstraction solely to reduce diff size.

## Types and Boundaries
Prefer explicit typed contracts at important API/application/engine/client boundaries. Avoid unstructured catch-all state and god services. Do not create abstraction layers without a concrete responsibility.

## Testing
Test behavior at its owning layer and satisfy applicable gates in `documentation/06-testing-release/00-testing-strategy.md` and `agent/QUALITY_GATES.md`. Durable spending/randomness/gameplay commands require idempotency coverage. Fresh-database support is a permanent vNext requirement.

## Frontend
Gameplay UI is Phaser-first. Reusable UI should accept calculated layout regions rather than hardcoding physical screen pixels. Preserve the 1600x900 reference coordinate model, landscape-only mobile rule, and deterministic screenshot capability.

## Backend
Controllers are HTTP adapters. Application operations represent complete player intentions and own transaction boundaries. Domain rules/engines stay computational where practical. Repositories remain SQL/persistence focused. Avoid nested independent transactions.

## Content
Stable authored IDs are durable contracts. Validate cross-references in CI. Client content is allowlisted; new canonical fields are private by default unless explicitly projected.

## Documentation
Current intent belongs in the active tree; history belongs in Git. Update or delete conflicting documentation as part of the same change that changes the contract.
