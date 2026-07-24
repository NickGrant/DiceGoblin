# ISSUES FILE
----
Active issues only. Move completed entries to `agent/ISSUES_ARCHIVE.md`.

## Backend Structural Cleanup Pass

### BSC-002: Centralize run lifecycle transitions

**Milestone:** Backend Structural Cleanup Pass  
**Status:** In Progress  
**Priority:** High

#### Problem

Run failure, completion, cleanup, and summary timing are currently split between resolve and claim controllers. Consolidate that orchestration into a single service so defeat, abandon, exit, and claim semantics stay aligned.

#### Acceptance Criteria

- Create a backend service that owns failed, abandoned, and completed run transitions.
- Remove duplicated cleanup and end-run sequencing from multiple controllers.
- Preserve current summary and XP behavior unless explicitly changed.
- Add or update targeted regression coverage for the lifecycle service.

#### Current Code References

- `backend/src/Controllers/RunNodeController.php`
- `backend/src/Controllers/BattleController.php`
- `backend/src/Controllers/ApiController.php`
- `backend/src/Repositories/RunRepository.php`

### BSC-003: Extract shared mutation guard and controller helpers

**Milestone:** Backend Structural Cleanup Pass  
**Status:** Open  
**Priority:** Medium

#### Problem

Active-run mutation checks, auth wrappers, JSON decoding, and transaction envelopes are repeated across controllers. Extract shared helpers and policy services to reduce controller size and inconsistency.

#### Acceptance Criteria

- Create a shared unit mutation guard or equivalent policy service.
- Reduce repeated request/response boilerplate across the most duplicated controllers.
- Keep controller behavior and status codes stable.

#### Current Code References

- `backend/src/Controllers/GameplayController.php`
- `backend/src/Controllers/TeamController.php`
- `backend/src/Controllers/AcademyController.php`
- `backend/src/Controllers/ShopController.php`

### BSC-004: Separate shop and academy domain services from controllers

**Milestone:** Backend Structural Cleanup Pass  
**Status:** Open  
**Priority:** Medium

#### Problem

Shop and academy controllers currently combine catalog assembly, unlock policy, daily-deal generation, and purchase orchestration. Move domain logic into dedicated services so controllers return to thin transport layers.

#### Acceptance Criteria

- Extract dedicated services for catalog and purchase orchestration where it materially reduces controller complexity.
- Keep unlock rules and response envelopes unchanged.
- Preserve daily-deal and feature-unlock behavior with regression coverage where practical.

#### Current Code References

- `backend/src/Controllers/ShopController.php`
- `backend/src/Controllers/AcademyController.php`
