# ISSUES FILE
----
Active issues only. Move completed entries to `agent/ISSUES_ARCHIVE.md`.

## Backend Structural Cleanup Pass

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

### BSC-005: Evaluate narrow synchronous domain events after service extraction

**Milestone:** Backend Structural Cleanup Pass  
**Status:** Open  
**Priority:** Medium

#### Problem

After the main service boundaries are cleaned up, evaluate whether a small internal event system would simplify post-actions such as run-end follow-up, grants, or analytics without obscuring core game flow.

#### Acceptance Criteria

- Do not introduce a broad event bus before the core service extractions land.
- Document or implement only narrowly scoped synchronous events where they clearly reduce coupling.
- Keep core gameplay mutations understandable and directly traceable.

#### Current Code References

- `backend/src/Controllers`
- `backend/src/Services`
- `backend/src/Repositories`
