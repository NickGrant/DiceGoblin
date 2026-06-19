# ISSUES FILE
----
Active issues only. Move completed entries to `agent/ISSUES_ARCHIVE.md`.

## Unit Progression Rework

### UPR-005: Add progression rework test coverage and validation

**Milestone:** Unit Progression Rework  
**Status:** Open  
**Priority:** High

#### Problem

The progression rework touches data, combat, promotion, API responses, targeting, run-map behavior, and frontend UX. It needs dedicated regression coverage so later balancing does not break core lineage rules.

#### Acceptance Criteria

- Add backend tests for level 10 max level and level 6 promotion eligibility.
- Add backend tests for capstone selection, persistence, and promotion inheritance.
- Add backend tests that all new abilities are registered and have handlers.
- Add combat tests for active abilities consuming dice and scaling at least one variable component.
- Add combat tests for half-die defensive stack scaling.
- Add combat tests for one-attack stack consumption and clearing.
- Add combat tests for once-per-round reaction limits.
- Add combat tests for target weighting with marked, wounded, debuffed, backline, and preferred targets.
- Add tests for Treasure Sense revealing at most one hidden treasure node per run.
- Add frontend tests or documented QA coverage for capstone selection and promotion preview.
- Run the relevant verification commands from `agent/QUALITY_GATES.md` and report any failures.

#### Current Code References

- `documentation/02-systems-mvp/11-unit-progression-rework.md`
- `agent/QUALITY_GATES.md`
- `backend/tests/Unit/Combat/AbilityHandlerRegistryCoverageTest.php`
- `backend/tests/Integration/BattleNodeResolutionIntegrationTest.php`
- `backend/tests/Integration/GameplayUnitDetailsEndpointTest.php`
