# ISSUES FILE
----
Active issues only. Move completed entries to `ISSUES_ARCHIVE.md`.

---
title: Align unit promotion and reward drops with tiered unit-type progression
status: in-progress
priority: high
execution: active
ready: yes
milestone: Milestone 31 - First Release UI Polish and Release Prep
description: Update progression so promotions advance units from tier-1 unit types into their tier-2 counterparts instead of only incrementing the instance tier, and ensure current reward generation only grants tier-1 unit drops.

---
title: Make squad facing rules consistent and enforce position impact in combat
status: in-progress
priority: high
execution: active
ready: yes
milestone: Milestone 31 - First Release UI Polish and Release Prep
description: Standardize squad-facing language so front is right and back is left across squad editing and combat, add clear formation indicators in management UI, and make combat resolution honor position-sensitive targeting and damage rules documented for MVP.

---
title: Add The Farm tutorial run option and pig enemy set
status: in-progress
priority: high
execution: active
ready: yes
milestone: Milestone 31 - First Release UI Polish and Release Prep
description: Introduce The Farm as a guided introductory run with a fixed combat -> loot -> rest -> boss -> exit route and a pig-only enemy lineup made up of mudwrestler, mudslinger, and mudking encounters.

---
title: Add between-runs shop with basic stock and daily deal
status: in-progress
priority: high
execution: active
ready: yes
milestone: Milestone 31 - First Release UI Polish and Release Prep
description: Add a shop surface where players can spend soft currency on basic dice and tier-1 units, plus a once-per-day uncommon affixed die deal generated on first open and purchasable until the next day or until sold.

title: Polish first-session presentation across landing home and region select
status: unstarted
priority: high
execution: active
ready: yes
milestone: Milestone 31 - First Release UI Polish and Release Prep
description: Improve the first-release visual impression and clarity of the onboarding/start-run surfaces. Focus on `LandingScene`, `HomeScene`, and `RegionSelectScene` so they better match the visual style guide, use available space intentionally, communicate primary actions more clearly, and feel less like debug-era scaffolding before the first external player wave.

---
title: Improve warband unit and dice management readability for first release
status: unstarted
priority: high
execution: active
ready: yes
milestone: Milestone 31 - First Release UI Polish and Release Prep
description: Rework the management surfaces to be clearer and more presentable for first-release players. Focus on `WarbandManagementScene`, `SquadDetailsScene`, `UnitDetailsScene`, and `DiceInventoryScene` so summary hierarchy, dense data presentation, action discoverability, and affix/equipment readability all feel intentional rather than purely functional.

---
title: Tighten run-map combat-result and run-summary readability
status: unstarted
priority: medium
execution: active
ready: yes
milestone: Milestone 31 - First Release UI Polish and Release Prep
description: Do a readability and layout pass on `MapExplorationScene`, `NodeResolutionScene`, `RestManagementScene`, and `RunEndSummaryScene` to improve moment-to-moment comprehension. Prioritize stronger hierarchy for run condition and outcomes, more polished summary presentation, and better perceived continuity between map, combat result, rest, and end-of-run states.

---
title: Restore deterministic scene screenshot review workflow against current local frontend
status: unstarted
priority: medium
execution: active
ready: yes
milestone: Milestone 31 - First Release UI Polish and Release Prep
description: The local review harness currently times out when running `npm.cmd run capture:scene -- --scene <scene> --base-url http://127.0.0.1:5173/`, which blocks fast screenshot-based UI QA. Repair the scene-ready/capture path against the current Docker/local frontend setup so scene-by-scene visual review remains reproducible during release polish.

---
title: Record first-release manual gate evidence and release checklist
status: unstarted
priority: medium
execution: active
ready: yes
milestone: Milestone 31 - First Release UI Polish and Release Prep
description: Capture the manual release evidence required by `documentation/05-playability-stability/00-release-gate-criteria.md` and turn the final pre-release steps into an explicit checklist. Include fresh-account bootstrap, successful run, failed run, resume continuity, reset-account validation, release configuration checks for disabled debug tooling, and release-note/changelog prep.
