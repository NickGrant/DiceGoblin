---
Title: "Documentation Index"
Status: Canonical
Last Updated: 2026-08-23
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
- Separate current truth, target-state contracts, review material, legacy reference, and planning notes.
- Make each major bucket easy for humans and agents to scan before loading deeper context.

## Status Guidance

- `Canonical` documents are the default source of truth for their stated scope.
- A canonical target-state document may define intended behavior that implementation has not fully reached; associated technical docs should identify the drift.
- `Needs Review` documents are useful but should be checked against code, active issues, or current design before implementation decisions.
- `Legacy Reference` documents are preserved for historical, migration, or comparison context and do not override canonical docs.
- `Draft` documents are early proposals.
- `Superseded` documents should move to `documentation/archive/` when no active workflow still depends on them.

## Buckets

- `00-overview/`: high-level product overview, core loop, and glossary.
- `01-lore/`: setting, story, narrative tone, and biome progression direction.
- `02-systems/`: canonical gameplay-system inventory, current behavior and target-state contracts, explicit documentation gaps, plus legacy MVP and multiplayer references.
- `03-content/`: catalog-style documents for units, kin, enemies, biomes, items, dice content, encounters, rewards, and Codex entries.
- `04-ux/`: player experience, visual design, onboarding, and page-by-page analysis.
- `05-technical/`: architecture, API contracts, data model, frontend state, seed ownership, and schemas.
- `06-testing-release/`: testing strategy, release gates, UAT scripts, validation checklists, and evidence.
- `07-development-path/`: roadmaps, milestone direction, approved future content rosters, demo planning, expansion planning, and changelog.
- `08-operations/`: engineering standards, documentation process, LLM context architecture, and maintenance workflows.
- `archive/`: historical or superseded documents removed from the canonical reading path.

## Recommended Read Order

1. `documentation/00-overview/00-project-overview.md`
2. `documentation/00-overview/01-core-gameplay-loop.md`
3. `documentation/01-lore/00-world-and-lore.md`
4. `documentation/00-overview/02-glossary.md`
5. `documentation/02-systems/README.md`
6. The relevant canonical system document under `documentation/02-systems/`, or the evidence linked from the system inventory when canonical coverage is missing.
7. `documentation/05-technical/02-frontend-state-and-scene-contracts.md`
8. `documentation/05-technical/03-backend-api-contracts.md`
9. `documentation/05-technical/04-data-model.md`
10. `documentation/05-technical/09-seed-catalog-ownership.md`
11. The relevant document under `documentation/04-ux/`
12. `documentation/06-testing-release/00-testing-strategy.md`
13. `documentation/07-development-path/2026-07-30-first-pig-kin-demo-roadmap.md`

For current dice work, use `documentation/02-systems/mvp-reference/01-dice-system.md` together with current implementation evidence until a new canonical dice-system/content contract is authored. The active model retains materials and permanent affixes.

For Wrong Machine and kin production, the relevant system document is `documentation/02-systems/kin-reconstruction.md`.

## Current-State Rule

- If a narrow subsystem document conflicts with a current-state overview, technical contract, or active issue, use the newer authoritative source and update the stale document during the work.
- `documentation/02-systems/README.md` is the first stop for gameplay systems and documentation coverage; every meaningful gameplay system should remain visible there even when its canonical contract is missing.
- `documentation/02-systems/` owns gameplay behavior and target-state system rules.
- `documentation/05-technical/` owns current route/schema evidence and target implementation direction; it must identify legacy compatibility rather than silently redefining system behavior.
- Implementation and tests remain the evidence for what currently runs.
- `documentation/02-systems/mvp-reference/` is background unless a system inventory row explicitly uses it as the best available current reference.
- `documentation/04-ux/page-analysis/` contains route audits and should be treated as targeted UX context rather than global design law.
- Active issue and milestone state lives in `agent/ISSUES.md` and `agent/MILESTONES.md`.

## Maintenance Rules

- Every documentation folder should have a `README.md`.
- Every Markdown documentation file should start with YAML frontmatter containing `Title`, `Status`, `Last Updated`, `Owner`, `Depends On`, `Category`, and `Tags`.
- When moving a document, update this index, `agent/CONTEXT_ROUTER.md` when routing changes, relevant folder READMEs, internal references, and scripts that refer to the old path.
- Prefer moving uncertain material to `Needs Review` or `Legacy Reference` before deleting it.
- Do not hide implementation drift by rewriting an approved target-state system contract around legacy runtime behavior.
