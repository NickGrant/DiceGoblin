# ISSUES FILE
----
Active issues only. Move completed entries to `agent/ISSUES_ARCHIVE.md`.

## UAT Feedback Fix Round 2

### Repair run event node resolution and visibility

**Milestone:** UAT Feedback Fix Round 2
**Status:** In Progress
**Priority:** High

#### Problem
Rest, shrine, and hazard nodes are unclear or misleading during UAT: rest appears not to fully heal, shrine/hazard nodes add an unnecessary approach step, and non-combat effects are not visible enough in the result UI.

#### Acceptance Criteria

- Rest finalize returns refreshed run unit HP state and the frontend consumes it.
- Shrine nodes resolve directly from the map node screen without a separate approach click.
- Hazard nodes resolve directly from the map node screen without a separate approach click.
- Shrine result screens show a player-readable effect label and detail.
- Hazard result screens show a player-readable effect label and detail.
- Existing run-node and rest-page tests cover the updated behavior.

### Fix chaos reel encounter application

**Milestone:** UAT Feedback Fix Round 2
**Status:** In Progress
**Priority:** High

#### Problem
Chaos reels appear to show cross-biome results, but combat may still fall back to the current biome template and may not visibly apply encounter-shape or rule/reward reels.

#### Acceptance Criteria

- Enemy-family reels select matching encounter families even when the current biome lacks that family.
- Encounter-shape reels have observable combat setup changes or clearly visible fallback copy.
- Rule/reward reels are applied to the finalized battle/reward payload.
- Chaos battle previews and/or logs expose the applied reel effects so UAT can verify them.
- Backend tests cover at least two cross-biome family reel outcomes.

### Surface active run effects

**Milestone:** UAT Feedback Fix Round 2
**Status:** In Progress
**Priority:** High

#### Problem
If shrine, hazard, or chaos outcomes apply persistent run effects, players have no clear place to see which effects are currently active.

#### Acceptance Criteria

- Backend exposes current active run effects in the current-run response or another stable run-state endpoint.
- Run map shows active effect names and concise descriptions near the squad/map controls.
- Run node result screens identify whether an effect is immediate-only or persists after leaving the node.
- Tests cover displaying at least one active shrine/hazard/chaos effect.

### Add post-Wrong-Machine mountain dialogue

**Milestone:** UAT Feedback Fix Round 2
**Status:** In Progress
**Priority:** Medium

#### Problem
The Whim and mountain kobold dialogue do not yet branch after the Wrong Machine is unlocked, leaving important post-recovery story beats missing.

#### Acceptance Criteria

- The Whim has a post-Wrong-Machine dialogue option or branch.
- Mountain kobolds have a post-Wrong-Machine dialogue option or branch.
- Dialogue unlock requirements prevent the new branches from appearing before Wrong Machine recovery.
- Dialogue tests or seed validation cover the new branch availability.

### Reflavor voluntary run return

**Milestone:** UAT Feedback Fix Round 2
**Status:** In Progress
**Priority:** Medium

#### Problem
The voluntary run exit is framed as `Abandon Run` and `Run Abandoned`, which reads like failure even when the player is intentionally returning home.

#### Acceptance Criteria

- Run map action is labeled `Return Home`.
- The run summary title for abandoned status is player-facing as `Returned Home`.
- Service summary state uses the same returned-home copy.
- Tests cover the updated title.

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
**Status:** In Progress
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
