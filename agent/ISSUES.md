# ISSUES FILE
----
Active issues only. Move completed entries to `agent/ISSUES_ARCHIVE.md`.

## UAT Feedback Fix Round 1

### Refine warband, unit, and squad-edit UX

**Milestone:** UAT Feedback Fix Round 1
**Status:** Open
**Priority:** High

#### Problem
Warband and squad-edit flows need clearer filtering, simpler unit cards, better stat explanations, and stronger drag/drop feedback.

#### Acceptance Criteria

- Warband filters can show units assigned to a squad, units not assigned to a squad, or all units.
- Warband goblin cards no longer show stat blocks directly on the card.
- Unit slot marker appears inline with level on goblin cards.
- Individual unit stat hover states show tooltips explaining what each stat does.
- Squad edit remains easy to drag from when the available-unit list is long.
- Squad edit drop targets are visually obvious while a draggable unit is hovering over them.

### Clean up shop and academy presentation

**Milestone:** UAT Feedback Fix Round 1
**Status:** Open
**Priority:** Medium

#### Problem
Shop and academy screens have duplicate currency indicators and placeholder iconography that make the UI feel rougher than the newer shop-style surfaces.

#### Acceptance Criteria

- Shop second daily deal title does not include `Deal 2:`.
- Shop removes the redundant bottom-screen teeth indicator.
- Academy research wing removes the redundant tooth indicator.
- Academy replaces single-letter placeholders with available icons.
- Academy uses available tier icons where tier information is shown.
- Academy removes the line matching `Tier x role - add future recruit...`.

### Repair guide navigation and combat reference content

**Milestone:** UAT Feedback Fix Round 1
**Status:** Open
**Priority:** High

#### Problem
The guide is currently unreliable for UAT because side navigation links do not work and important reference content is missing or inaccurate.

#### Acceptance Criteria

- Guide sidenav links scroll or route to the intended sections.
- Map glossary includes the missing icon types used by current run maps.
- Starter classes section only shows Bruiser and Marksman.
- Guide includes a section explaining how unit actions are determined.
- Guide includes a section explaining how combat is calculated at a player-understandable level.

## Critical Path UAT

### Continue fresh-account July roadmap UAT

**Milestone:** Critical Path UAT
**Status:** Open
**Priority:** High

#### Problem
The July 25 roadmap implementation is complete at the planned issue-slice level, but the full player path still needs continued fresh-account UAT evidence before final release hardening.

#### Acceptance Criteria

- A fresh account is played through Farm, Mountains, Swamps, Wrong Machine recovery, Mystic Cave return, and first Pig Kin reconstruction.
- UAT notes capture story comprehension, unlock timing, reward visibility, and any blocking progression failures.
- Repeat-run behavior is checked for first-clear story, stolen pages, and unlock messaging.
- Any player-facing failures are logged as new active issues with severity and reproduction notes.
- If no blockers are found, the issue is archived with the UAT evidence location.

### Validate encounter and consumable feel

**Milestone:** Critical Path UAT
**Status:** Open
**Priority:** Medium

#### Problem
Hazard, shrine, chaos, healing-consumable, and energy-consumable systems are implemented, but their combined pacing and variety need hands-on validation across several seeds.

#### Acceptance Criteria

- Multiple Farm, Mountains, and Swamps runs are sampled for hazard, shrine, and chaos variety.
- Healing consumables are checked against rest-node value and attrition pressure.
- Energy consumables are checked against energy caps and intended pacing.
- Encounter copy is checked for readability and result clarity.
- Any balance or content-repeat issues are logged with affected region, run seed when available, and expected tuning direction.

## UAT Polish Backlog

### Confirm release merge and generated-artifact hygiene

**Milestone:** UAT Polish Backlog
**Status:** Open
**Priority:** Medium

#### Problem
The roadmap work moved through many stacked PRs, so release readiness needs a final hygiene pass that confirms `main` includes the intended stack and generated artifacts follow repository policy.

#### Acceptance Criteria

- `main` is synced with `origin/main` before release validation begins.
- The July 25 completion analysis and active tracker agree on remaining work.
- Generated frontend artifacts are either intentionally included or intentionally omitted according to release policy.
- A final validation command set is documented before UAT-confirmed fixes are merged.
- Any merge-order or missing-commit concern is logged as a blocker with exact commit/PR references.
