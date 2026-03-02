# ISSUES ARCHIVE
----

## Purpose
- Historical record for completed or otherwise inactive issue entries moved from `ISSUES.md`.
- Preserve prior context and resolution notes without bloating active execution context.

---
title: Add documentation archive lane and wire it into context exclusion rules
status: complete
priority: medium
description: |
  [Backlog Curator Follow-up] Introduce `documentation/archive/` and move superseded planning/decision docs out of active paths. Update `LLM_CONTEXT.md` to explicitly exclude the archive lane by default while allowing on-demand loading for historical context.
Resolution: Added `documentation/archive/README.md` and updated `LLM_CONTEXT.md` default exclusions to include `documentation/archive/` and deprecated `documentation/worklist.md`.

---
title: Normalize documentation encoding and style policy
status: complete
priority: medium
description: |
  [Backlog Curator Follow-up] Add explicit documentation standards (UTF-8, punctuation conventions, heading/metadata style, line wrapping) and apply a one-time cleanup pass to eliminate mojibake and formatting drift.
Resolution: Added `documentation/STYLE_GUIDE.md` and normalized high-impact active docs to remove common mojibake and standardize portable punctuation.

---
title: Add documentation changelog for contract and roadmap edits
status: complete
priority: low
description: |
  [Backlog Curator Follow-up] Create `documentation/CHANGELOG.md` to track major documentation deltas (API contracts, data model changes, scope shifts) so context changes are discoverable without scanning many files.
Resolution: Added `documentation/CHANGELOG.md` with initial entries covering recent governance and documentation-structure changes.

---
title: Align backend API contract doc with implemented endpoints and payloads
status: complete
priority: high
description: |
  [Role: Technical Product Manager] `documentation/01-architecture/03-backend-api-contracts.md` is out of sync with current backend implementation in `backend/public/index.php` and response payload shape in `backend/src/Services/ProfileService.php`. Mismatches include logout path (`/api/v1/auth/logout` vs `/auth/logout`), run endpoint naming (`/api/v1/runs/current` vs `/api/v1/runs/active`), profile key naming (`squads` vs `teams`), and team update path/payload (`PUT /api/v1/teams/:teamId` with cell strings vs `/formation` row/col payload). Update the contract doc and examples to prevent implementation drift.
Resolution: Updated API contract docs with implemented/planned endpoint labels, corrected auth/run/team endpoint paths, and aligned profile terminology toward `squads`.

---
title: Document warband management UX flow and constraints in system docs
status: complete
priority: medium
description: |
  [Role: Technical Product Manager] The newly implemented warband management interaction in `frontend/src/scenes/WarbandManagementScene.ts` is not represented in `documentation/03-ux/` and not clearly linked to `documentation/02-systems-mvp/` scope. Add a concise UX/system contract for selection, placement, clearing, save behavior, and error states so product and implementation remain aligned.
Resolution: Added dedicated warband UX/system contract doc and linked it from the main UX scope document and documentation index.

---
title: Migrate open roadmap milestones from deprecated worklist into executable backlog
status: complete
priority: high
description: |
  [Migration] `documentation/worklist.md` was deprecated in favor of `ISSUES.md`. Ensure remaining open roadmap scope is represented as active issue batches for:
  - Milestone 2: server-side battle resolution and rewards/XP completion
  - Milestone 3: run progression, attrition persistence, and run-end handling
  - Milestone 4: encounter flow UI (setup, replay, completion)
  - Milestone 5: unit and dice management depth
  - Milestone 6: playability and stability pass
  Close this migration issue once each milestone has concrete tracked child issues.
Resolution: Introduced `MILESTONES.md` with active milestones and linked issue-title collections for Milestones 2-6, plus `MILESTONES_ARCHIVE.md` for completed milestone history.
