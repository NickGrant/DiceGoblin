---
Title: "Documentation Classification - 2026-08-20"
Status: Needs Review
Last Updated: 2026-08-20
Owner: Product + Engineering
Depends On:
  - documentation/README.md
  - documentation/02-systems/00-current-system-index.md
Category: 08-operations
Tags:
  - documentation
  - audit
---

# Documentation Classification - 2026-08-20

## Outdated

- `documentation/02-systems/mvp-reference/` is historical and must not override current system docs.
- `documentation/02-systems/multiplayer/` is planning context, not active runtime behavior.
- Dated roadmap and audit docs under `documentation/07-development-path/` and `documentation/08-operations/` should be treated as snapshots unless explicitly promoted.

## Unsure

- Page-analysis docs are useful implementation notes but should be checked against live Angular routes before edits.
- `documentation/05-technical/05-angular-frontend-architecture-plan.md` needs review against the current standalone-component implementation.
- Open-question sections in content catalogs should be resolved or moved into active planning docs.

## Current

- `documentation/02-systems/00-current-system-index.md`
- active system docs under `documentation/02-systems/01-*.md` through `15-*.md`, with explicit drift sections where needed
- `documentation/03-content/00-content-source-map.md`
- `documentation/03-content/10-items-and-consumables.md`
- `documentation/03-content/12-codex-entries.md`
- `documentation/03-content/13-dialogue-and-lore.md`
- `documentation/05-technical/02-frontend-state-and-scene-contracts.md`
- `documentation/05-technical/03-backend-api-contracts.md`
- `documentation/05-technical/04-data-model.md`
- `documentation/05-technical/09-seed-catalog-ownership.md`

## Needs Implemented

- Repeatable Wrong Machine reconstruction request ledger and recipe tables.
- Dice material storage and material-owned salvage classes.
- Backend controller/helper cleanup and reward materializer consolidation.
- DB-authored objective or chaos catalog rows if tuning needs outgrow service constants.

## Maintenance Actions

- Keep `Status` metadata honest: canonical target docs must say when implementation drift remains.
- Add route docs before large page rewrites.
- Prefer small current-system docs over expanding legacy MVP files.
- Fix mojibake or encoding artifacts when touching affected content docs.
