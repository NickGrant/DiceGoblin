---
Title: "Figma Update Inventory"
Status: Needs Review
Last Updated: 2026-08-19
Owner: Product + UX
Depends On:
  - documentation/04-ux/page-analysis/00-index.md
  - frontend/src/app/app.routes.ts
  - frontend/src/app/pages/
Category: 04-ux
Tags:
  - ux
  - figma
  - page-analysis
---

# Figma Update Inventory

## Purpose

- Track route surfaces that still need updated Figma-aligned implementation.
- Keep the implementation queue separate from the broader page-analysis notes.
- Record page-specific interaction notes that should survive between design passes.

## Needs Updated

| Surface | Route or component | Existing analysis | Scope | Notes |
| --- | --- | --- | --- | --- |
| Start Run | `/regions` start-run controls | [07-regions.md](./07-regions.md) | Update run-start card/button states. | Can use a hover/active treatment similar to Dice Inventory. |
| Wrong Machine | `/wrong-machine` | [18-wrong-machine.md](./18-wrong-machine.md) | Full page update. | Use shared requirement/cost and result-preview primitives while preserving first-reconstruction behavior. |
| Shop: Loot | `/shop` supplies/loot mode | [12-shop.md](./12-shop.md) | Update shop loot/supplies presentation. | Keep repeatable purchase flow readable and consistent with updated object-card patterns. |
| Shop: Unlocks | `/shop` feature unlock mode | [12-shop.md](./12-shop.md) | Update long-term unlock presentation. | Preserve locked, available, purchased, requirement, and busy states. |
| Academy | `/academy` | [06-academy.md](./06-academy.md) | Update research and promotion flow. | Active demo issue calls for a shop-style academy layout. |
| Guide | `/guide` | [02-guide-public.md](./02-guide-public.md) | Update public guide presentation. | Keep the side-navigation and explanatory content easy to scan before login. |
| Codex | `/codex` | [05-codex.md](./05-codex.md) | Update codex presentation and navigation. | Align codex navigation with the updated guide pattern. |

## Reference Treatments

- Dice Inventory has an implemented Figma shell and can be used as an interaction reference for hover and active states.
- Warband has an implemented Figma roster grid and can be used as a dense roster/grid reference where appropriate.

## Missing Inventory Docs

- Run Dialogue and Run Loot are live routes without dedicated page-analysis files, but they are not currently in this Figma update queue.

## Maintenance Notes

- Move a surface out of `Needs Updated` only after implementation is merged and verified against the relevant route/component.
- Keep this file scoped to Figma/update tracking. Route contents and data inventory still belong in the individual page-analysis docs.
