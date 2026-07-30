# Documentation Index
----

Status: active
Last Updated: 2026-07-27
Owner: Product + Engineering
Depends On: `README.md`, `documentation/STYLE_GUIDE.md`

## Purpose

- Provide one clear entrypoint for how Dice Goblins works today.
- Point each major question at a current-state canonical document before older subsystem references.
- Keep planning history and superseded design assumptions out of the main gameplay read path.

## Scope

- This index covers the active `documentation/` set.
- Agent workflow, backlog policy, and execution rules live in `AGENTS.md` and `agent/README.md`.
- Future gameplay and narrative planning is directional and does not override current-state contracts until a feature enters implementation.

## Recommended Read Order

1. `documentation/00-overview/00-project-overview.md`
2. `documentation/00-overview/01-core-gameplay-loop.md`
3. `documentation/00-overview/03-world-and-lore.md`
4. `documentation/00-overview/04-story-and-biome-progression.md`
5. `documentation/01-architecture/02-frontend-state-and-scene-contracts.md`
6. `documentation/01-architecture/03-backend-api-contracts.md`
7. `documentation/03-ux/00-ux-and-debug-scope.md`
8. `documentation/00-overview/02-glossary.md`
9. `documentation/01-architecture/04-data-model.md`
10. `documentation/09-active-system-structure/` docs relevant to the implemented system being changed
11. `documentation/02-systems-mvp/` docs relevant to the system being changed
12. `documentation/03-ux/` docs relevant to the player flow being changed
13. `documentation/07-roadmap/00-gameplay-systems-roadmap.md` for future gameplay direction
14. `documentation/05-playability-stability/` for release validation

## Current-State Rule

- If a detailed subsystem doc conflicts with:
  - `00-project-overview.md`
  - `01-core-gameplay-loop.md`
  - `02-frontend-state-and-scene-contracts.md`
  - `03-backend-api-contracts.md`
  - `03-ux/00-ux-and-debug-scope.md`
- treat those current-state docs as the source of truth until the narrower doc is updated.
- `03-world-and-lore.md` is the canonical setting source.
- `04-story-and-biome-progression.md` and roadmap documents describe proposed direction and never override implemented behavior by themselves.

## Canonical Docs By Topic

- What the game is:
  - `documentation/00-overview/00-project-overview.md`
  - `documentation/00-overview/01-core-gameplay-loop.md`
  - `documentation/00-overview/03-world-and-lore.md`
  - `documentation/00-overview/02-glossary.md`
- Story, setting, and biome direction:
  - `documentation/00-overview/03-world-and-lore.md`
  - `documentation/00-overview/04-story-and-biome-progression.md`
- Frontend and API architecture:
  - `documentation/01-architecture/00-tech-stack.md`
  - `documentation/01-architecture/01-authentication-and-sessions.md`
  - `documentation/01-architecture/02-frontend-state-and-scene-contracts.md`
  - `documentation/01-architecture/03-backend-api-contracts.md`
  - `documentation/01-architecture/04-data-model.md`
  - `documentation/01-architecture/08-seed-catalog-ownership.md`
  - `documentation/01-architecture/05-angular-frontend-architecture-plan.md`
  - `documentation/01-architecture/06-angular-component-service-inventory.md`
- Documentation and LLM context architecture:
  - `documentation/01-architecture/09-llm-knowledge-architecture-and-token-efficiency.md`
- Gameplay systems:
  - `documentation/09-active-system-structure/00-index.md`
  - `documentation/09-active-system-structure/01-unit-naming.md`
  - `documentation/09-active-system-structure/02-unit-stat-advancement.md`
  - `documentation/09-active-system-structure/03-combat-resolution.md`
  - `documentation/09-active-system-structure/04-dialogue-flow-determination.md`
  - `documentation/09-active-system-structure/05-run-node-generation.md`
  - `documentation/09-active-system-structure/06-loot-determination.md`
  - `documentation/02-systems-mvp/00-combat-system.md`
  - `documentation/02-systems-mvp/01-dice-system.md`
  - `documentation/02-systems-mvp/02-units-and-progression.md`
  - `documentation/02-systems-mvp/12-academy-and-feature-unlocks.md`
  - `documentation/02-systems-mvp/03-encounter-scope.md`
  - `documentation/02-systems-mvp/04-loot-and-drop-scope.md`
  - `documentation/02-systems-mvp/05-save-and-resume-scope.md`
  - `documentation/02-systems-mvp/06-run-resolution-scope.md`
  - `documentation/02-systems-mvp/07-combat-math-and-modifiers.md`
  - `documentation/02-systems-mvp/08-encounter-reward-surface-rules.md`
  - `documentation/02-systems-mvp/13-wrong-machine-and-kin.md`
  - `documentation/02-systems-mvp/14-balancing-strategy-and-simulation.md`
  - `documentation/02-systems-mvp/15-pattern-based-run-map-generation.md`
- Future gameplay direction:
  - `documentation/00-overview/04-story-and-biome-progression.md`
  - `documentation/07-roadmap/00-gameplay-systems-roadmap.md`
- Player-facing UX:
  - `documentation/03-ux/00-ux-and-debug-scope.md`
  - `documentation/03-ux/01-visual-design-guide.md`
  - `documentation/03-ux/02-warband-management.md`
  - `documentation/03-ux/03-encounter-flow-transition-matrix.md`
  - `documentation/03-ux/04-combat-viewer-readability.md`
  - `documentation/03-ux/07-dice-pool-consumption-and-refresh-cues.md`
  - `documentation/03-ux/08-page-layout-zones.md`
  - `documentation/03-ux/09-first-session-player-journey.md`
  - `documentation/06-page-analysis/`
- Verification and release:
  - `documentation/ENGINEERING_STANDARDS.md`
  - `documentation/TESTING_STRATEGY.md`
  - `documentation/05-playability-stability/00-release-gate-criteria.md`
  - `documentation/05-playability-stability/01-critical-path-playtest-script.md`
  - `documentation/05-playability-stability/02-first-release-manual-gate-evidence.md`
  - `documentation/05-playability-stability/03-first-release-checklist.md`
  - `documentation/05-playability-stability/04-mobile-viewport-regression-checklist.md`
  - `documentation/05-playability-stability/06-july-roadmap-uat-balance-checklist.md`
  - `documentation/05-playability-stability/07-release-readiness-validation.md`
  - `documentation/05-playability-stability/08-uat-balance-evidence-template.md`
  - `documentation/CHANGELOG.md`

## Task Entry Points

- Backend/API changes:
  - `documentation/01-architecture/01-authentication-and-sessions.md`
  - `documentation/01-architecture/03-backend-api-contracts.md`
  - `documentation/01-architecture/04-data-model.md`
  - `documentation/01-architecture/08-seed-catalog-ownership.md`
- Frontend route/state/component changes:
  - `documentation/ENGINEERING_STANDARDS.md`
  - `documentation/01-architecture/02-frontend-state-and-scene-contracts.md`
  - `documentation/01-architecture/05-angular-frontend-architecture-plan.md`
  - `documentation/01-architecture/06-angular-component-service-inventory.md`
  - `documentation/03-ux/`
- Documentation, agent context, and knowledge architecture:
  - `documentation/01-architecture/09-llm-knowledge-architecture-and-token-efficiency.md`
  - `agent/LLM_CONTEXT.md`
  - `agent/CONTEXT_ROUTER.md`
  - `documentation/STYLE_GUIDE.md`
- Warband, squads, units, and dice:
  - `documentation/09-active-system-structure/01-unit-naming.md`
  - `documentation/09-active-system-structure/02-unit-stat-advancement.md`
  - `documentation/02-systems-mvp/01-dice-system.md`
  - `documentation/02-systems-mvp/02-units-and-progression.md`
  - `documentation/02-systems-mvp/13-wrong-machine-and-kin.md`
  - `documentation/02-systems-mvp/12-academy-and-feature-unlocks.md`
  - `documentation/03-ux/02-warband-management.md`
- Runs, encounters, loot, and summaries:
  - `documentation/09-active-system-structure/03-combat-resolution.md`
  - `documentation/09-active-system-structure/05-run-node-generation.md`
  - `documentation/09-active-system-structure/06-loot-determination.md`
  - `documentation/02-systems-mvp/03-encounter-scope.md`
  - `documentation/02-systems-mvp/04-loot-and-drop-scope.md`
  - `documentation/02-systems-mvp/06-run-resolution-scope.md`
  - `documentation/02-systems-mvp/08-encounter-reward-surface-rules.md`
  - `documentation/02-systems-mvp/14-balancing-strategy-and-simulation.md`
  - `documentation/02-systems-mvp/15-pattern-based-run-map-generation.md`
  - `documentation/03-ux/03-encounter-flow-transition-matrix.md`
- Onboarding and first-session flow:
  - `documentation/03-ux/09-first-session-player-journey.md`
  - `documentation/02-systems-mvp/03-encounter-scope.md`
- Lore, dialogue, and biome planning:
  - `documentation/09-active-system-structure/04-dialogue-flow-determination.md`
  - `documentation/00-overview/03-world-and-lore.md`
  - `documentation/00-overview/04-story-and-biome-progression.md`
  - `frontend/public/assets/data/dialogue/dialogue-scripts.json`
- Future gameplay planning:
  - `documentation/00-overview/04-story-and-biome-progression.md`
  - `documentation/07-roadmap/00-gameplay-systems-roadmap.md`
  - `documentation/02-systems-mvp/14-balancing-strategy-and-simulation.md`
  - `documentation/02-systems-mvp/15-pattern-based-run-map-generation.md`
- Page-by-page route breakdowns:
  - `documentation/06-page-analysis/00-index.md`

## Reference Data

- `documentation/08-json-schema/unit_types.base_stats_json.json`
- `documentation/08-json-schema/unit_types.ability_set_json.json`

## Related Indexes

- `AGENTS.md`
- `agent/README.md`

## Tooling Notes

- Use screenshot or route-capture tooling only when visual verification is required.
- Keep review checklists and temporary audit notes out of the canonical gameplay path.
