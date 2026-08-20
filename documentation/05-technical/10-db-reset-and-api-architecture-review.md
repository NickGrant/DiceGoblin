---
Title: "DB Reset and API Architecture Review"
Status: Needs Review
Last Updated: 2026-08-20
Owner: Engineering
Depends On:
  - documentation/05-technical/03-backend-api-contracts.md
  - documentation/05-technical/04-data-model.md
  - documentation/05-technical/09-seed-catalog-ownership.md
  - documentation/02-systems/kin-reconstruction.md
Category: 05-technical
Tags:
  - technical
  - architecture
  - db-reset
  - api
---

# DB Reset and API Architecture Review

## Purpose

Capture the recommended structure changes before the planned DB reset, with current and planned gameplay functionality in mind.

## Recommended Reset-Time Schema Shape

Keep these areas as durable account or run state:

- users, credentials, sessions, player currency, and energy state
- owned units, dice, equipped abilities, squads, formations, and run-unit state
- run graph, node state, battle state, battle logs, run rewards, and run summaries
- generic items, user item quantities, feature unlocks, unit-type unlocks, and Codex ownership
- chaos encounter results, bounty definitions, user bounties, and request/idempotency ledgers

Use the reset to add or formalize:

- `wrong_machine_recipes` for authored recipe costs, outputs, unlock rules, and availability.
- `wrong_machine_reconstruction_requests` for idempotent repeatable production attempts.
- `kin_*` storage or compatibility views/API aliases that retire `splice_variant_*` naming.
- dice material tables if the size plus material target model is still approved for this reset.
- data-backed catalogs for recipes, bounties, chaos reel symbols, and objective definitions when tuning visibility matters.

Do not extend legacy `region_items` for new progression materials. New materials, catalysts, and consumables should remain under `items` and `user_items`.

## Ownership Boundaries

The DB owns durable facts:

- account identity and persistent currencies
- inventory, unit, dice, squad, run, battle, reward, Codex, bounty, and recipe state
- authored data that designers or QA need to inspect, tune, or migrate

The backend owns rules and transactions:

- affordability, eligibility, validation, idempotency, side effects, and reward materialization
- combat, run resolution, chaos finalization, consumable use, and reconstruction production
- profile projection and repair of derived state such as Codex entries

The frontend owns presentation state:

- tabs, filters, selected cards, confirmation modals, previews, and disabled affordances
- route-local rendering of backend-provided eligibility and missing requirements
- no durable inference for recipes, affordability, item spending, or reward ownership

## Backend/API Cleanup Sequence

1. Standardize controller response helpers around `{ ok: true, data }` and `{ ok: false, error }`.
2. Extract run start from the broad API controller into a focused run-start service.
3. Extract shared run/node access helpers for ownership, active-run lookup, node availability, and edge unlocking.
4. Centralize reward payload materialization so battle, run, dialogue, loot, chaos, and summary surfaces share one shape.
5. Split battle-claim internals out of broad lifecycle orchestration after reward materialization is shared.
6. Align Wrong Machine payloads around recipe and kin terminology once the schema decision lands.
7. Pick a mutation refresh policy per endpoint family: refreshed profile, explicit profile delta, or surface-specific refreshed payload.

## DRY and KISS Guardrails

- Prefer focused services over a large abstraction layer.
- Extract duplicated controller plumbing before extracting domain logic.
- Keep executable behavior in code when it needs tests, branching, or versioned handlers.
- Move authored constants to DB only when inspection, tuning, admin tools, or reset migration justify the cost.
- Keep compatibility aliases temporary and documented.

## Open Decisions

- Whether repeatable Wrong Machine production lands before the first Pig Kin demo or immediately after.
- Whether dice material migration is included in the reset or explicitly deferred.
- Whether objective definitions should become DB-authored now or remain service-owned until the demo path stabilizes.
- Whether chaos reel symbols need DB authoring before broader content expansion.
