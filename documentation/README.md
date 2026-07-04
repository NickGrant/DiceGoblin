# Documentation Index
----

Status: active  
Last Updated: 2026-07-04  
Owner: Product + Engineering  
Depends On: `README.md`, `documentation/STYLE_GUIDE.md`

## Purpose

- Provide one clear entrypoint for how Dice Goblins works today.
- Point each major question at a current-state canonical document before older subsystem references.
- Keep planning history and superseded design assumptions out of the main gameplay read path.

## Scope

- This index covers the active `documentation/` set.
- Agent workflow, backlog policy, and execution rules live in `AGENTS.md` and `agent/README.md`.

## Recommended Read Order

1. `documentation/00-overview/00-project-overview.md`
2. `documentation/00-overview/01-core-gameplay-loop.md`
3. `documentation/00-overview/03-world-and-lore.md`
4. `documentation/01-architecture/02-frontend-state-and-scene-contracts.md`
5. `documentation/01-architecture/03-backend-api-contracts.md`
6. `documentation/03-ux/00-ux-and-debug-scope.md`
7. `documentation/00-overview/02-glossary.md`
8. `documentation/01-architecture/04-data-model.md`
9. `documentation/02-systems-mvp/` docs relevant to the system being changed
10. `documentation/03-ux/` docs relevant to the player flow being changed
11. `documentation/05-playability-stability/` for release validation

## Current-State Rule

- If a detailed subsystem doc conflicts with:
  - `00-project-overview.md`
  - `01-core-gameplay-loop.md`
  - `02-frontend-state-and-scene-contracts.md`
  - `03-backend-api-contracts.md`
  - `03-ux/00-ux-and-debug-scope.md`
- treat those current-state docs as the source of truth until the narrower doc is updated.

## Canonical Docs By Topic

- What the game is:
  - `documentation/00-overview/00-project-overview.md`
  - `documentation/00-overview/01-core-gameplay-loop.md`
  - `documentation/00-overview/03-world-and-lore.md`
  - `documentation/00-overview/02-glossary.md`
- Frontend and API architecture:
  - `documentation/01-architecture/00-tech-stack.md`
  - `documentation/01-architecture/01-authentication-and-sessions.md`
  - `documentation/01-architecture/02-frontend-state-and-scene-contracts.md`
  - `documentation/01-architecture/03-backend-api-contracts.md`
  - `documentation/01-architecture/04-data-model.md`
  - `documentation/01-architecture/05-angular-frontend-architecture-plan.md`
  - `documentation/01-architecture/06-angular-component-service-inventory.md`
- Gameplay systems:
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
- Player-facing UX:
  - `documentation/03-ux/00-ux-and-debug-scope.md`
  - `documentation/03-ux/01-visual-design-guide.md`
  - `documentation/03-ux/02-warband-management.md`
  - `documentation/03-ux/03-encounter-flow-transition-matrix.md`
  - `documentation/03-ux/04-combat-viewer-readability.md`
  - `documentation/03-ux/07-dice-pool-consumption-and-refresh-cues.md`
  - `documentation/03-ux/08-page-layout-zones.md`
  - `documentation/03-ux/09-first-session-player-journey.md`
- Verification and release:
  - `documentation/ENGINEERING_STANDARDS.md`
  - `documentation/TESTING_STRATEGY.md`
  - `documentation/05-playability-stability/00-release-gate-criteria.md`
  - `documentation/05-playability-stability/01-critical-path-playtest-script.md`
  - `documentation/05-playability-stability/02-first-release-manual-gate-evidence.md`
  - `documentation/05-playability-stability/03-first-release-checklist.md`
  - `documentation/05-playability-stability/04-mobile-viewport-regression-checklist.md`
  - `documentation/CHANGELOG.md`

## Task Entry Points

- Backend/API changes:
  - `documentation/01-architecture/01-authentication-and-sessions.md`
  - `documentation/01-architecture/03-backend-api-contracts.md`
  - `documentation/01-architecture/04-data-model.md`
- Frontend route/state/component changes:
  - `documentation/ENGINEERING_STANDARDS.md`
  - `documentation/01-architecture/02-frontend-state-and-scene-contracts.md`
  - `documentation/01-architecture/05-angular-frontend-architecture-plan.md`
  - `documentation/01-architecture/06-angular-component-service-inventory.md`
  - `documentation/03-ux/`
- Warband, squads, units, and dice:
  - `documentation/02-systems-mvp/01-dice-system.md`
  - `documentation/02-systems-mvp/02-units-and-progression.md`
  - `documentation/02-systems-mvp/12-academy-and-feature-unlocks.md`
  - `documentation/03-ux/02-warband-management.md`
- Runs, encounters, loot, and summaries:
  - `documentation/02-systems-mvp/03-encounter-scope.md`
  - `documentation/02-systems-mvp/04-loot-and-drop-scope.md`
  - `documentation/02-systems-mvp/06-run-resolution-scope.md`
  - `documentation/02-systems-mvp/08-encounter-reward-surface-rules.md`
  - `documentation/03-ux/03-encounter-flow-transition-matrix.md`
- Onboarding and first-session flow:
  - `documentation/03-ux/09-first-session-player-journey.md`

## Reference Data

- `documentation/08-json-schema/unit_types.base_stats_json.json`
- `documentation/08-json-schema/unit_types.ability_set_json.json`

## Related Indexes

- `AGENTS.md`
- `agent/README.md`

## Tooling Notes

- Use screenshot or route-capture tooling only when visual verification is required.
- Keep review checklists and temporary audit notes out of the canonical gameplay path.
