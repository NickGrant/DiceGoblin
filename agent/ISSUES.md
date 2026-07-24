# ISSUES FILE
----
Active issues only. Move completed entries to `agent/ISSUES_ARCHIVE.md`.

## Expanded Combat Stats

### ECS-002: Implement Precision and Resolve combat behavior

**Milestone:** Expanded Combat Stats
**Status:** Open
**Priority:** High

#### Problem

Precision and Resolve now exist in backend and frontend schemas as neutral-readable stats, but combat still treats them as display data. The next implementation pass should make them affect attack reliability, status reliability, and resistance in a deterministic, readable way.

#### Acceptance Criteria

- Add deterministic Precision-based hit and critical behavior for eligible attacks.
- Add Resolve-based resistance behavior for Poison, Bleeding, Sleep, and other supported harmful statuses.
- Preserve current combat determinism and replayability.
- Add battle-log language when Precision or Resolve changes an outcome.
- Keep neutral `5` values close to existing gameplay behavior.
- Add targeted backend combat tests for hit, crit, resisted status, and neutral-stat behavior.

#### Current Code References

- `backend/src/Combat`
- `backend/src/Combat/Engine`
- `backend/src/Services/ProfileDtoMapper.php`
- `backend/tests/Unit/Combat`
- `documentation/02-systems-mvp/00-combat-system.md`

### ECS-003: Author Precision and Resolve balance data

**Milestone:** Expanded Combat Stats
**Status:** Open
**Priority:** High

#### Problem

The expanded stat fields currently default safely, but existing player units and enemy templates need authored Precision and Resolve values before the stats can create meaningful build and encounter differences.

#### Acceptance Criteria

- Add authored Precision and Resolve values to player unit type seed data.
- Add authored Precision and Resolve values to enemy template seed data.
- Keep existing regions playable with conservative tuning.
- Document the initial tuning assumptions and any intentionally neutral entries.
- Add or update regression coverage that verifies seeded stat JSON exposes the expected fields.

#### Current Code References

- `backend/migrations/30_seed_unit_types.sql`
- `backend/migrations/31_seed_enemy_templates.sql`
- `backend/src/Repositories/UnitRepository.php`
- `backend/tests/Integration`
- `documentation/02-systems-mvp/00-combat-system.md`

### ECS-004: Surface expanded stats in player-facing comparisons

**Milestone:** Expanded Combat Stats
**Status:** Open
**Priority:** Medium

#### Problem

Precision and Resolve are visible in unit detail contexts, but players still need consistent comparison language across rewards, recruitment, enemies, and combat summaries once the stats affect outcomes.

#### Acceptance Criteria

- Show Precision and Resolve consistently wherever unit or enemy stat blocks are compared.
- Keep compact/mobile layouts readable.
- Explain misses, critical hits, and resisted outcomes through existing battle summary or log surfaces.
- Add focused frontend coverage for at least one stat-comparison surface.

#### Current Code References

- `frontend/src/app/core/models/api.models.ts`
- `frontend/src/app/pages/unit-details-page`
- `frontend/src/app/pages/run-node-page`
- `frontend/src/app/pages/home-page`
- `documentation/03-ux`
