---
Title: "Documentation Content Audit - 2026-08-01"
Status: Canonical
Last Updated: 2026-08-01
Owner: Product + Engineering
Depends On:
  - documentation/README.md
  - documentation/08-operations/02-documentation-style-guide.md
Category: 08-operations
Tags:
  - operations
  - documentation-audit
---

# Documentation Content Audit - 2026-08-01

## Purpose

- Record the first content-location and staleness pass after the documentation bucket migration.
- Capture obvious fixes already applied.
- Identify remaining documents that should be reviewed before they are treated as current authority.

## Applied Fixes

- Moved `documentation/04-ux/10-style-guide.md` to `documentation/08-operations/02-documentation-style-guide.md`.
- Moved historical first-release evidence from the main testing folder to `documentation/06-testing-release/evidence/04-first-release-manual-gate-evidence.md`.
- Renamed the glossary title from `Game Glossary (Milestone 0)` to `Game Glossary`.
- Removed duplicate inline metadata blocks from docs that already had frontmatter.
- Updated folder READMEs affected by the moves.
- Strengthened `npm.cmd run docs:lint` to flag missing frontmatter fences and inline metadata outside frontmatter.

## Status Corrections

- `documentation/01-lore/01-story-and-biome-progression.md`: `Needs Review`
  - The doc intentionally mixes confirmed story direction with planning-level chapter and biome structure.
- `documentation/07-development-path/2026-07-25-roadmap.md`: `Legacy Reference`
  - It is superseded by the July 30 first Pig Kin demo roadmap and retained for historical implementation context.
- `documentation/07-development-path/2026-07-25-completion-analysis.md`: `Legacy Reference`
  - It explicitly points current planning to the later first Pig Kin demo target.
- `documentation/08-operations/01-llm-knowledge-architecture-and-token-efficiency.md`: `Needs Review`
  - It remains useful as an implementation plan, but it proposes future generated catalogs, source records, and decision records that do not currently exist.

## Location Review

- `00-overview/` is correctly scoped to product orientation and glossary.
- `01-lore/` is correctly scoped, but its progression doc should remain directional until chapter plans are reconciled with implemented content.
- `02-systems/` is correctly split between current system summaries and `mvp-reference/` legacy contracts.
- `03-content/` now has source-map and template scaffolding; full catalog docs still need a dedicated content/source review.
- `04-ux/` is now limited to player experience, visual, layout, onboarding, and page-analysis docs.
- `05-technical/` is the right home for architecture and API contracts.
- `06-testing-release/` now separates current gates/checklists from historical evidence.
- `07-development-path/` is correctly scoped to roadmap and changelog material, but older roadmaps should stay legacy/reference.
- `08-operations/` is the right home for engineering standards, documentation process, and LLM/documentation maintenance plans.

## Recommended Next Cleanup

1. Add first real content catalog docs under `03-content/` for current demo content: biomes, enemy families, items, kin, and codex entry categories.
2. Decide whether the LLM knowledge architecture plan should become a roadmap issue set, a lighter operations contract, or a legacy reference.
3. Review existing docs section by section for misplaced subsections, duplicated references, and topic drift.
4. Reconcile content docs with seed SQL, code-owned catalogs, and player-facing copy.

## Follow-Up Pass - Source-Derived Cleanup

After the initial audit, a source-derived cleanup pass refreshed the Angular architecture and component/service inventory from `frontend/src/app`, rewrote the hazard doc around implemented behavior, and added `documentation/03-content/00-content-source-map.md` plus `documentation/03-content/TEMPLATE-catalog-entry.md`.

The next meaningful cleanup step requires reviewing actual document body sections and seed/content sources together to decide where individual subsections, tables, references, and content descriptions belong.
