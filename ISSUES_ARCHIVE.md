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
