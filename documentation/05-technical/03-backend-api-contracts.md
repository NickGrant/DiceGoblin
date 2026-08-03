---
Title: "Backend API Contracts - Current Alpha and Target-State Boundaries"
Status: Canonical
Last Updated: 2026-08-02
Owner: Engineering
Depends On:
  - backend/public/index.php
  - backend/src/Controllers/
  - frontend/src/app/core/services/api-http/api-http.service.ts
  - documentation/02-systems/08-dice-material-model.md
  - documentation/02-systems/09-kin-reconstruction.md
  - documentation/05-technical/04-data-model.md
Category: 05-technical
Tags:
  - technical
---

# Backend API Contracts - Current Alpha and Target-State Boundaries

## Purpose

- Summarize the backend routes the current frontend depends on.
- Describe implemented alpha payloads without presenting them as a permanently frozen public API.
- Identify approved target-state payload contracts where implementation still uses compatibility fields or legacy behavior.
- Keep route truth anchored to `backend/public/index.php` and behavior truth anchored to controllers, services, and canonical system documents.

## Contract State Labels

This document uses two labels:

- **Current:** behavior or payload shape implemented on `main`.
- **Target:** the approved contract that implementation must move toward.

A target contract does not claim that the migration has already landed. Current compatibility fields may remain temporarily, but they must not become a second source of design authority.

## Core Contract Rules

- API routes live under `/api/v1/...`.
- OAuth entry routes live under `/auth/...`.
- Authentication is cookie/session based.
- Mutating requests use CSRF protection.
- The backend is authoritative for session, profile, purchases, runs, battles, progression, rewards, dice mutation, and reconstruction.
- JSON ids should be treated as strings by the frontend even when backed by numeric database ids.
- Mutations that spend resources or create durable assets must be transactional.
- Retry-sensitive mutations must expose or internally enforce an idempotency boundary.

## Authentication and Session

Current routes:

- `GET /auth/discord/start`
- `GET /auth/discord/callback`
- `POST /api/v1/auth/local/register`
- `POST /api/v1/auth/local/login`
- `POST /api/v1/auth/local/password-reset/request`
- `POST /api/v1/auth/local/password-reset/confirm`
- `POST /api/v1/auth/logout`
- `GET /api/v1/session`

Current behavior:

- Local registration accepts email, password, and display name, then establishes the normal session.
- Local login accepts email and password, then establishes the normal session.
- Password-reset request returns generic success without revealing whether an account exists.
- Password-reset confirmation consumes the token, updates the password hash, and establishes the normal session.
- Session bootstrap reports authentication and supplies the CSRF token used by mutations.
- Logout clears local shell state even when the backend request fails.
- Auth responses never expose password hashes or local credential internals.
- Non-production reset requests may include a raw reset token for local Docker development until email delivery exists.

## Profile and Shared Player State

Current routes:

- `GET /api/v1/profile`
- `GET /api/v1/health`
- `GET /api/v1/abilities`

### Current profile behavior

The profile is the main shared authenticated payload. It currently contains or derives:

- energy
- currency, including `soft`, `hard`, and `raw_chaos`
- active run
- squads and formation data
- units
- dice inventory
- feature unlocks
- unit-type unlocks
- region access and inferred completion state
- generic `items`
- legacy `region_items`
- objectives
- kin or lineage compatibility state

Current unit records expose `kin_*` aliases plus legacy `splice_variant_*` fields. Clients should prefer kin terminology and treat splice fields as compatibility-only.

Current profile and Wrong Machine behavior still expose explicit lineage-unlock state through `lineage_unlocks`. That field may remain as a compatibility projection for discovery and ordinary unit-grant eligibility, but it must not be treated as the authority for whether the account owns a kin. Durable owned units are the ownership authority.

### Target dice payload

Every target-state owned die exposes enough data to render and resolve its complete permanent identity:

- die instance id
- active size or sides
- material key and display identity
- material-derived rarity
- material summary and tags where needed by the surface
- equipment binding or usage information
- backend-authored sale value
- backend-authored salvage value

A target-state die does not require permanent affix fields. During migration, legacy affix fields may remain in payloads as compatibility-only data, but new clients and new surfaces should not depend on them.

Material is required. A null material must not be interpreted as Cardboard; Cardboard is an explicit material value.

### Target kin and first-ownership payload

Profile state may expose separate concepts:

- owned units and each unit's kin
- discovered kin
- kin eligible for applicable ordinary unit grants or recruitment
- Codex ownership

These may currently map through `lineage_unlocks` or other compatibility data. They must remain semantically distinct. Discovery or eligibility is not a substitute for a unit, and a missing projection must not erase durable ownership.

### Compatibility plan

The current unit, shop, reward, and reconstruction payloads may still carry legacy names because those names match current storage. New response objects should prefer:

- `kin_*` for unit identity
- `recipe_*` for reconstruction identity
- `material_*` for permanent dice identity
- `first_discovery` for the one-time ownership transition

Storage renames should occur only after API aliases and client migrations are in place.

## Wrong Machine Reconstruction

Current routes:

- `GET /api/v1/wrong-machine/reconstructions`
- `POST /api/v1/wrong-machine/reconstruct`

### Current request behavior

The current mutation accepts:

```text
lineage_slug
```

The current controller validates feature access and delegates spending and unit creation to the reconstruction service.

### Target request contract

A target reconstruction request must identify:

- a stable recipe, such as `reconstruct_pig_kin`
- a request-level idempotency key

The current `lineage_slug` input may remain as a temporary compatibility alias while only one recipe exists, but recipe identity and idempotency must be explicit before multiple deliberate reconstructions can be safe.

### Target preview contract

Each preview entry should expose:

- recipe key
- output kin
- unit count
- unit-type selection rule
- exact item and Raw Chaos requirements
- owned quantities and missing quantities
- feature and recipe availability
- whether the account has previously discovered the kin
- whether the recipe can currently be completed

Previous kin ownership must not disable a repeatable recipe.

### Target completion contract

A successful reconstruction response should distinguish:

| Field | Meaning |
| --- | --- |
| `produced_unit` | The unit created by this recipe completion. |
| `recipe_key` | The stable recipe completed. |
| `spent` | Exact items and Raw Chaos consumed. |
| `first_discovery` | Whether this unit caused first ownership of its kin. |
| `newly_eligible_kin` | Kin added to applicable ordinary grant pools, otherwise none. |
| `codex_awarded` | Newly awarded kin entry or already-owned state. |
| `preview` | Refreshed recipe state after completion. |

The produced unit is the primary output on every successful deliberate recipe use. First-discovery fields are one-time side effects.

Retrying the same idempotency key returns the original result without spending again, rerolling unit type, or creating another unit. A new deliberate production uses a new key and pays the recipe again.

### Current implementation drift

The current service predates the canonical repeatable recipe contract. It currently:

- blocks later production after a lineage-unlock row exists
- uses legacy Pig Kin costs
- returns no unit for an already-unlocked lineage
- lacks a request-level idempotency key
- performs a fresh random unit-type selection
- presents lineage unlock as the primary transition

These are known implementation gaps, not alternate API requirements.

## Shop and Academy

Current routes:

- `GET /api/v1/shop`
- `POST /api/v1/shop/purchase`
- `GET /api/v1/academy`
- `POST /api/v1/academy/unlock-unit-type`

Current behavior:

- Shop returns starter inventory, daily deals, and feature unlocks.
- Academy returns unit-type unlock catalog entries with backend-authored availability and requirements.
- Purchases and unlocks refresh profile state after success.

Target dice offers should identify explicit size-material pairs or a backend-authored valid generation rule. They must not generate independent rarity or permanent affixes.

## Bounty Board

Current routes:

- `GET /api/v1/bounties`
- `POST /api/v1/bounties/accept`
- `POST /api/v1/bounties/sync`
- `POST /api/v1/bounties/:userBountyId/claim`

Current behavior:

- The board uses a backend-authored definition set.
- Players may accept up to three active or completed-but-unclaimed bounties.
- Progress is derived idempotently from durable gameplay rows.
- Claiming a completed bounty grants backend-authored rewards and returns updated board state.

## Run Flow

Current routes:

- `GET /api/v1/runs/current`
- `POST /api/v1/runs`
- `POST /api/v1/runs/:runId/abandon`
- `POST /api/v1/runs/:runId/exit`
- `POST /api/v1/runs/:runId/nodes/:nodeId/resolve`
- `POST /api/v1/runs/:runId/nodes/:nodeId/rest/open`
- `POST /api/v1/runs/:runId/nodes/:nodeId/rest/finalize`
- `POST /api/v1/runs/:runId/nodes/:nodeId/chaos/generate`
- `POST /api/v1/runs/:runId/nodes/:nodeId/chaos/reroll`
- `POST /api/v1/runs/:runId/nodes/:nodeId/chaos/finalize`

Current behavior:

- Only one active run is supported at a time.
- Region start consumes energy through run creation.
- Current run payloads include region metadata.
- Node resolution is backend-authoritative.
- Hazard and shrine nodes use the shared node-resolution route.
- Chaos nodes persist reel generation, one reroll, and finalization.
- Rest uses explicit open and finalize steps.
- Abandon and exit produce summary-relevant state.

## Battles

Current routes:

- `GET /api/v1/battles/:battleId/log`
- `POST /api/v1/battles/:battleId/claim`

Current behavior:

- Node resolution may already include the battle log used by the frontend.
- Direct log retrieval remains available.
- Claim finalizes rewards and may finalize run summary state.

Target battle logs identify each participating die by size and material and record material triggers and outcomes. Legacy affix trigger data must not remain required after material migration.

## Units and Progression

Current routes:

- `GET /api/v1/units/:unitInstanceId/promotion-options`
- `POST /api/v1/units/:unitInstanceId/promote`
- `PUT /api/v1/units/:unitInstanceId/capstone`
- `PATCH /api/v1/units/:unitInstanceId/name`
- `PUT /api/v1/units/:unitInstanceId/loadout`
- `PUT /api/v1/units/:unitInstanceId/abilities/:abilityId/slots/:slotIndex/dice`
- `DELETE /api/v1/units/:unitInstanceId/abilities/:abilityId/slots/:slotIndex/dice`
- `POST /api/v1/dice/:diceInstanceId/sell`
- `POST /api/v1/dice/:diceInstanceId/salvage`

Current behavior:

- Promotion options are fetched separately from profile.
- Promotion, capstone, rename, loadout, and dice-slot mutations refresh profile afterward.
- Dice sale and salvage block equipped dice and refresh profile state.

Target sale and salvage values derive from size and material. They must not depend on independent rarity, affix slots, or affix premiums.

## Squads and Teams

Current routes:

- `POST /api/v1/teams`
- `POST /api/v1/teams/:teamId/activate`
- `PUT /api/v1/teams/:teamId`
- `DELETE /api/v1/teams/:teamId`

Backend route names remain `teams` for compatibility. Player-facing surfaces use `squads`.

## Codex Boundary

Current Codex discovery is inferred from several profile fields and still includes legacy affix-oriented behavior in some surfaces.

Target dice discovery is material-based. Owning a die with a material may award that material's Codex entry. Permanent affix entries are not part of the target dice model.

A later unified Codex ownership migration may change persistence and payload shape. Until then, API documentation must not imply that inferred fields are equivalent to a durable unified ownership store.

## Debug Surface

Current routes:

- `GET /api/v1/debug/catalog`
- `GET /api/v1/debug/seed-tables`
- `POST /api/v1/debug/grant/currency`
- `POST /api/v1/debug/grant/unit`
- `POST /api/v1/debug/grant/dice`
- `POST /api/v1/debug/grant/region-item`
- `POST /api/v1/debug/units/set-level`
- `POST /api/v1/debug/reset-account`

These routes are non-production testing tools gated by runtime configuration.

A target dice-grant request must create a valid size-material pair. Compatibility-only rarity or affix inputs must not remain required after material migration.

## Documentation Rule

- If endpoint names disagree with `backend/public/index.php`, the router is the source of current route truth and this document must be updated.
- If implemented behavior disagrees with a canonical system contract, record the mismatch as implementation drift rather than rewriting the system contract around legacy behavior.
- Payload migrations should preserve compatibility deliberately and remove aliases in separate cleanup work.
