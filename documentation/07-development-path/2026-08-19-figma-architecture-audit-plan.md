---
Title: "Figma Refresh and Architecture Audit Plan"
Status: Needs Review
Last Updated: 2026-08-19
Owner: Product + Engineering
Depends On:
  - agent/ISSUES.md
  - documentation/04-ux/page-analysis/figma-update-inventory.md
  - documentation/04-ux/page-analysis/00-index.md
  - documentation/02-systems/README.md
  - documentation/05-technical/03-backend-api-contracts.md
  - documentation/05-technical/04-data-model.md
Category: 07-development-path
Tags:
  - development-path
  - figma
  - architecture
  - db-reset
  - documentation
---

# Figma Refresh and Architecture Audit Plan

## Purpose

Coordinate the next UI refresh, shared-component extraction, DB-reset architecture decisions, backend/API cleanup, and documentation reconciliation work.

This plan is based on parallel read-only audits completed on 2026-08-19. It should be reviewed before implementation work begins.

## Figma Update Queue

The target surfaces are tracked in `documentation/04-ux/page-analysis/figma-update-inventory.md`.

Current queue:

- Start Run / Regions
- Wrong Machine
- Shop: Loot
- Shop: Unlocks
- Academy
- Guide
- Codex

Behavior constraints:

- Shop remains a tabbed interface.
- Academy moves to a tabbed interface.
- Start Run can reuse Dice Inventory-style hover, focus, active, and selected states.
- Guide remains public and scannable before login.
- Codex remains authenticated and must preserve locked placeholders, lore replay, objective ordering, and discovery state.
- Wrong Machine remains demo-critical and must preserve eligibility, missing requirements, idempotent duplicate behavior until the backend contract changes, and successful Pig Kin grant visibility.

## Recommended Workstreams

### Workstream 1: Shared UI Components

Build or extend shared UI before page rewrites.

Recommended components:

- `dg-tab-strip` extension or tab-page shell for Shop, Academy, and possible content sections.
- Guide/Codex navigation rail that supports both anchor links and stateful category buttons.
- Selectable action/object card with hover, focus, selected, disabled, locked, busy, and active-run states.
- Requirement/cost panel for Wrong Machine, Shop unlocks, Academy requirements, and future recipes.
- Price/currency/status chips built on `dgChip`.
- Detail/workspace layout inspired by Dice Inventory master/detail structure.
- Optional filter/segmented-control primitives after the first page pass proves repeated need.

Reuse references:

- Dice Inventory card and selected-state treatment.
- Warband roster grid and compact unit row treatment.
- Existing `TabStripComponent`, `ObjectGridComponent`, `DgPanelComponent`, `DgProgressComponent`, `DgChipDirective`, `UnitBarComponent`, and `UnitThumbnailComponent`.

Avoid direct copy-paste from page-specific SCSS. Extract the behavior and state language into shared primitives instead.

### Workstream 2: Page Implementation

Recommended order:

1. Guide and Codex
   - Establish shared navigation rail.
   - Use the aligned Codex page-analysis doc.
   - Reconcile player-facing dice/codex claims with current runtime or clearly mark target-state material dice content.

2. Shop
   - Keep tabbed interface.
   - Apply shared tab shell, purchase cards, price chips, and requirement states.
   - Keep supplies/loot and unlocks visually distinct but structurally related.

3. Academy
   - Convert to tabbed interface, likely Research and Promotions.
   - Preserve promotion behavior: unit lock warning, capstone-before-promotion rule, selected destination, exactly two secondary units, busy/error states.
   - Reuse Shop unlock/card patterns where possible.

4. Start Run / Regions
   - Apply shared selectable card states.
   - Preserve active-run route continuation and start confirmation.

5. Wrong Machine
   - Use shared requirement/cost/result-preview components.
   - Preserve current runtime contract unless the DB-reset/backend contract has already changed.
   - Recheck against first Pig Kin demo acceptance criteria.

### Workstream 3: Frontend Cleanup

After the page updates:

- Remove obsolete page-local SCSS that was replaced by shared UI.
- Remove unused assets and dead components.
- Consolidate duplicate tab, card, chip, price, and progress styling.
- Add or update component specs for shared UI.
- Run frontend tests and capture desktop/mobile screenshots for target routes.

## DB Reset Architecture Recommendations

The DB reset is the right time to resolve target/current drift instead of carrying compatibility forever.

High-priority reset-time changes:

1. Add `wrong_machine_recipes`.
2. Add `wrong_machine_reconstruction_requests` with user/idempotency protection.
3. Change Wrong Machine semantics so reconstruction production is repeatable per request, while first kin discovery/Codex unlock remains one-time.
4. Rename or API-mask `splice_variant_*` toward `kin_*`.
5. Make owned unit kin the authority for kin ownership.
6. Migrate dice toward size + material if that target model is still approved.
7. Keep `user_codex_entries` as durable ownership and define target categories now.
8. Move new progression materials fully onto `items`/`user_items`; keep region-item storage compatibility-only.
9. Extract reset-sensitive service constants into data-backed catalogs where tuning/debug visibility matters: Wrong Machine recipes, shop feature unlock metadata, bounties, chaos reel symbols, and possibly objective definitions.

Recommended ownership boundary:

- DB owns durable state, user inventory, units, dice, squads, runs, battles, rewards, Codex ownership, recipes, and inspectable/tunable catalogs.
- Backend owns transactions, validation, combat/reward behavior, handlers, idempotency, profile projections, and side effects.
- Frontend owns rendering, filters, navigation, optimistic disabled states, and route-local modals. It should not own durable eligibility, recipe affordability, or missing-data inference.

## Backend/API Cleanup Recommendations

Recommended sequence:

1. Standardize controller response/domain-error helpers.
2. Move run creation out of `ApiController` into a focused service.
3. Extract shared run/node access helpers for active owned runs, node locks, availability syncing, and edge unlocks.
4. Consolidate reward payload shaping into one materializer/service.
5. Split battle claim internals away from broad run lifecycle responsibilities.
6. Align Wrong Machine payloads with recipe/kin terminology after schema decisions.
7. Establish a mutation response policy: refreshed profile, profile delta, or surface-specific refreshed payload.

Payload conventions to move toward:

- All endpoints use `{ ok: true, data }` or `{ ok: false, error: { code, message, details? } }`.
- API ids are strings.
- Currency responses use consistent `soft`, `hard`, and `raw_chaos` naming.
- Rewards use a single shape across battle, run, dialogue, loot, chaos, and summary surfaces.
- New payloads prefer `kin_*` and `recipe_*`; legacy `lineage_*` and `splice_variant_*` should be compatibility aliases only.

## Documentation Reconciliation

Documentation needs a cleanup pass in parallel with implementation planning.

Recommended categories:

- Outdated: MVP reference, multiplayer planning, dated audits that are still labeled canonical.
- Unsure: page-analysis docs, LLM knowledge architecture plan, catalog open-question sections.
- Current: systems inventory and current system contracts, run-node generation, hazard severity, content source map, dialogue/lore catalog, frontend state contracts.
- Needs implemented: kin reconstruction target, dice material model, backend API/data-model target contracts, Codex discovery target work.

Immediate doc work:

- Keep the dedicated Wrong Machine page-analysis doc aligned as implementation changes.
- Keep the Codex-specific page-analysis filename and indexes aligned as implementation changes.
- Add live-route analysis docs for Run Dialogue and Run Loot later, though they are not in the current Figma queue.
- Keep `documentation/02-systems/README.md` aligned with implemented systems and documentation coverage.
- Maintain the short current-system docs for objectives, consumables, Codex ownership, chaos encounter reels, bounty board, and dice salvage/Raw Chaos.
- Add current-runtime drift sections to combat resolution, loot determination, and kin reconstruction.
- Fix mojibake artifacts in content docs.
- Split metadata meaning so implemented-current and approved-target docs are distinguishable.

## Proposed Implementation Packages

### Package A: Planning and Doc Alignment

- Keep Figma inventory updated.
- Keep Codex and Wrong Machine page-analysis docs aligned with implementation changes.
- Add a concise implementation checklist for target pages.

### Package B: Shared UI Foundation

- Build shared tab/page shell.
- Build shared navigation rail.
- Build shared selectable card and requirement/cost primitives.
- Add focused specs.

### Package C: Guide and Codex

- Apply shared nav rail.
- Update stale content and navigation patterns.
- Verify public/auth route behavior.

### Package D: Shop and Academy

- Update Shop while preserving tabs.
- Convert Academy to tabs.
- Share card, requirement, price, and status patterns.

### Package E: Start Run and Wrong Machine

- Update Start Run states.
- Update Wrong Machine layout after shared primitives stabilize.

### Package F: Backend and DB Reset Design

- Produce schema proposal for recipes, reconstruction requests, kin terminology, dice materials, and data-backed catalogs.
- Decide which changes land before demo versus after demo.

### Package G: Backend/API Cleanup

- Standardize controller helpers.
- Extract run start/access and reward materialization services in risk order.

### Package H: Documentation Reconciliation

- Classify docs.
- Add missing system docs.
- Reconcile current-runtime drift.
- Update context/index docs.

## Open Decisions

- Whether repeatable Wrong Machine production should land before the first Pig Kin demo or remain post-demo.
- Whether dice material migration is in scope for the reset or should be explicitly deferred.
- Whether service-constant catalogs should move to DB now or after demo.
- Whether the page-analysis status model should be changed globally before or after target page implementation.
