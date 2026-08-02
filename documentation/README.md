---
Title: "Documentation Index"
Status: Canonical
Last Updated: 2026-08-01
Owner: Product + Engineering
Depends On:
  - AGENTS.md
  - agent/CONTEXT_ROUTER.md
Category: documentation
Tags:
  - documentation
  - index
---

# Documentation Index

## Purpose

- Provide the canonical map for the Dice Goblins documentation library.
- Separate current truth, review material, legacy reference, and planning notes.
- Make each major bucket easy for humans and agents to scan before loading deeper context.

## Status Guidance

- `Canonical` documents are the default source of truth for their stated scope.
- `Needs Review` documents are useful but should be checked against code, active issues, or current design before implementation decisions.
- `Legacy Reference` documents are preserved for historical, migration, or comparison context and do not override canonical docs.
- `Draft` documents are early proposals.
- `Superseded` documents should move to `documentation/archive/` when no active workflow still depends on them.

## Buckets

- `00-overview/`: high-level product overview, core loop, and glossary.
- `01-lore/`: setting, story, narrative tone, and biome progression direction.
- `02-systems/`: current gameplay system behavior plus legacy MVP and multiplayer references.
- `03-content/`: catalog-style docs for units, kin, enemies, biomes, items, affixes, encounters, and codex entries.
- `04-ux/`: player experience, visual design, onboarding, and page-by-page analysis.
- `05-technical/`: architecture, API contracts, data model, frontend state, seed ownership, and schemas.
- `06-testing-release/`: testing strategy, release gates, UAT scripts, validation checklists, and evidence.
- `07-development-path/`: roadmaps, milestone direction, demo planning, expansion thinking, and changelog.
- `08-operations/`: engineering standards, documentation process, LLM context architecture, and maintenance workflows.
- `archive/`: historical or superseded docs removed from the canonical reading path.

## Recommended Read Order

1. `documentation/00-overview/00-project-overview.md`
2. `documentation/00-overview/01-core-gameplay-loop.md`
3. `documentation/01-lore/00-world-and-lore.md`
4. `documentation/00-overview/02-glossary.md`
5. `documentation/02-systems/00-current-system-index.md`
6. `documentation/05-technical/02-frontend-state-and-scene-contracts.md`
7. `documentation/05-technical/03-backend-api-contracts.md`
8. `documentation/05-technical/04-data-model.md`
9. `documentation/04-ux/00-ux-and-debug-scope.md`
10. `documentation/06-testing-release/00-testing-strategy.md`
11. `documentation/07-development-path/2026-07-30-first-pig-kin-demo-roadmap.md`

## Current-State Rule

- If a narrow subsystem document conflicts with a current-state overview, technical contract, or active issue, use the newer/current-state source and update the stale doc during the work.
- `documentation/02-systems/` is the first stop for implemented gameplay behavior.
- `documentation/02-systems/mvp-reference/` is useful background, not default authority.
- `documentation/04-ux/page-analysis/` contains route audits and should be treated as targeted UX context rather than global design law.
- Active issue and milestone state still lives in `agent/ISSUES.md` and `agent/MILESTONES.md`.

## Maintenance Rules

- Every documentation folder should have a `README.md`.
- Every Markdown documentation file should start with YAML frontmatter containing `Title`, `Status`, `Last Updated`, `Owner`, `Depends On`, `Category`, and `Tags`.
- When moving a document, update `agent/CONTEXT_ROUTER.md`, relevant folder READMEs, and any scripts that refer to the old path.
- Prefer moving uncertain material to `Needs Review` or `Legacy Reference` before deleting it.
