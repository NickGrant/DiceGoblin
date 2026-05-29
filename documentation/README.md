# Documentation Index
----

Status: active  
Last Updated: 2026-05-29  
Owner: Product + Engineering  
Depends On: `README.md`, `documentation/STYLE_GUIDE.md`

## Purpose

- Single entrypoint for game and project documentation under `documentation/`.
- Fast mapping from task type to canonical game-facing docs.
- Identify the current Angular architecture docs and the canonical route/state/component references.

## Scope

- This index is for `documentation/` content only.
- Agent workflow, backlog control, and execution policy live in `AGENTS.md` and `agent/README.md`.

## Canonical Project Sources

- `documentation/01-architecture/00-tech-stack.md`
- `documentation/01-architecture/03-backend-api-contracts.md`
- `documentation/01-architecture/04-data-model.md`
- `documentation/01-architecture/05-angular-frontend-architecture-plan.md`
- `documentation/01-architecture/06-angular-component-service-inventory.md`
- `documentation/03-ux/01-visual-design-guide.md`

## Suggested Read Order

1. `documentation/00-overview/00-project-overview.md`
2. `documentation/00-overview/01-rework-normalization-pass.md`
3. `documentation/01-architecture/00-tech-stack.md`
4. `documentation/01-architecture/05-angular-frontend-architecture-plan.md`
5. `documentation/01-architecture/06-angular-component-service-inventory.md`
6. `documentation/01-architecture/03-backend-api-contracts.md`
7. `documentation/01-architecture/04-data-model.md`
8. `documentation/02-systems-mvp/` (all)
9. `documentation/03-ux/` (all)
10. `documentation/05-playability-stability/` (release validation)
11. `documentation/07-ux-rebuild/` (visual/component design lane)

## Task Entry Points

- Backend/API changes:
  - `documentation/01-architecture/03-backend-api-contracts.md`
  - `documentation/01-architecture/01-authentication-and-sessions.md`
  - `documentation/01-architecture/04-data-model.md`
- Frontend Angular route/state/component changes:
  - `documentation/01-architecture/00-tech-stack.md`
  - `documentation/01-architecture/05-angular-frontend-architecture-plan.md`
  - `documentation/01-architecture/06-angular-component-service-inventory.md`
  - `documentation/03-ux/`
  - `documentation/07-ux-rebuild/`
- Combat/reward/progression changes:
  - `documentation/02-systems-mvp/00-combat-system.md`
  - `documentation/02-systems-mvp/01-dice-system.md`
  - `documentation/02-systems-mvp/02-units-and-progression.md`
  - `documentation/02-systems-mvp/07-combat-math-and-modifiers.md`
  - `documentation/02-systems-mvp/08-encounter-reward-surface-rules.md`
  - `documentation/02-systems-mvp/09-ability-loadout-combat-rework-plan.md`
- Planning/design:
  - `documentation/03-ux/15-ability-loadout-and-unit-naming-plan.md`
  - `documentation/00-overview/01-rework-normalization-pass.md`
- Verification/release:
  - `documentation/TESTING_STRATEGY.md`
  - `documentation/05-playability-stability/00-release-gate-criteria.md`
  - `documentation/05-playability-stability/01-critical-path-playtest-script.md`
  - `documentation/05-playability-stability/02-first-release-manual-gate-evidence.md`
  - `documentation/05-playability-stability/03-first-release-checklist.md`
  - `documentation/CHANGELOG.md`

## Reference Data

- `documentation/08-json-schema/unit_types.base_stats_json.json`
- `documentation/08-json-schema/unit_types.ability_set_json.json`
- `documentation/unit_list.json`
- `documentation/enemy_list.json`

## Related Indexes

- Agent workflow and backlog docs:
  - `AGENTS.md`
  - `agent/README.md`

## Local Automation

- Screenshot capture:
  - `skills/scene-screenshot/SKILL.md`
  - `npm run capture:scene -- --scene <scene>`
  - Run captures serially against the local frontend when using `--base-url`
  - Use route-appropriate capture tooling when the target surface is not Phaser-hosted.
- UX scene review loops:
  - `skills/ux-scene-review/SKILL.md`
  - Use this only when the reviewed surface still depends on scene-style capture workflows.
