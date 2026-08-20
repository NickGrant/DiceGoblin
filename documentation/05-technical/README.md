---
Title: "Technical Documentation"
Status: Canonical
Last Updated: 2026-08-20
Owner: Engineering
Depends On:
  - documentation/README.md
  - documentation/02-systems/README.md
Category: 05-technical
Tags:
  - technical
---

# Technical Documentation

## Purpose

- Define implementation architecture, API contracts, data-model direction, frontend state, schemas, catalog ownership, and platform decisions.
- Distinguish current physical implementation from approved target-state migrations.

## Status Guidance

- `Canonical` documents are the default source of truth for their stated scope.
- A canonical technical document may describe both the current compatibility surface and a required target-state migration.
- The router, schema, and source code remain the evidence for what currently runs.
- Canonical system documents remain the authority for intended gameplay behavior.
- `Needs Review` documents are useful but should be verified before implementation decisions are made from them.
- `Legacy Reference` documents are preserved for history, migration context, or comparison and do not override canonical docs.

## Core Contract Read Order

For cross-layer gameplay work:

1. Read the relevant system contract under `documentation/02-systems/`.
2. Read `02-frontend-state-and-scene-contracts.md` for Angular route and state ownership.
3. Read `03-backend-api-contracts.md` for routes and payload boundaries.
4. Read `04-data-model.md` for physical-schema and target-state storage direction.
5. Read `09-seed-catalog-ownership.md` for database/config/hybrid ownership.
6. Inspect implementation and tests to measure remaining drift.

For dice migration, begin with `documentation/02-systems/dice-material-model.md`.

For Wrong Machine and kin production, begin with `documentation/02-systems/kin-reconstruction.md`.

## Documents

- `00-tech-stack.md`
- `01-authentication-and-sessions.md`
- `02-frontend-state-and-scene-contracts.md`
- `03-backend-api-contracts.md`
- `04-data-model.md`
- `05-angular-frontend-architecture-plan.md`
- `06-domain-events-evaluation.md`
- `07-angular-component-service-inventory.md`
- `08-hybrid-phaser-audio-architecture.md`
- `09-seed-catalog-ownership.md`
- `10-db-reset-and-api-architecture-review.md`

## Current Migration Boundaries

### Dice

- Current implementation may still contain independent rarity and permanent affix storage.
- Target identity is one active size plus one material.
- Technical contracts must describe compatibility fields as migration inputs, not target-state features.

### Kin reconstruction

- Current implementation may still gate Pig Kin reconstruction through a one-time lineage row.
- Target behavior is repeatable recipe-driven unit production with first-ownership side effects and request-level idempotency.
- Owned units are kin-ownership authority; discovery and eligibility are projections.

## Child Folders

- `json-schema/`
