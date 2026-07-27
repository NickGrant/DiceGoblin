# ISSUES FILE
----
Active issues only. Move completed entries to `agent/ISSUES_ARCHIVE.md`.

## Critical Path UAT

### Run fresh-account July roadmap UAT

**Milestone:** Critical Path UAT
**Status:** Open
**Priority:** High

#### Problem
The July 25 roadmap implementation is complete at the planned issue-slice level, but the full player path still needs fresh-account UAT evidence before final release hardening.

#### Acceptance Criteria

- A fresh account is played through Farm, Mountains, Swamps, Wrong Machine recovery, Mystic Cave return, and first Pig Kin reconstruction.
- UAT notes capture story comprehension, unlock timing, reward visibility, and any blocking progression failures.
- Repeat-run behavior is checked for first-clear story, stolen pages, and unlock messaging.
- Any player-facing failures are logged as new active issues with severity and reproduction notes.
- If no blockers are found, the issue is archived with the UAT evidence location.

### Verify reward and unlock clarity in UAT

**Milestone:** Critical Path UAT
**Status:** Open
**Priority:** High

#### Problem
Reward and unlock systems were implemented across many slices, so UAT should verify that the combined player-facing summary is understandable rather than only technically present.

#### Acceptance Criteria

- Rewards visibly include teeth, units, dice, generic items, stolen codex pages, feature unlocks, and special catalysts when earned.
- Wrong Machine, Tooth Merchant, Raw Chaos, dice salvage, and Pig Kin reconstruction unlock at the intended moments.
- Players cannot earn or spend Raw Chaos before Wrong Machine recovery.
- Pig Ear and Mudking Crown Fragment rewards appear when earned and are inspectable afterward.
- Any confusing or missing reward presentation is logged as a follow-up issue with screenshots or reproduction notes where practical.

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

### Triage frontend polish findings from UAT

**Milestone:** UAT Polish Backlog
**Status:** Open
**Priority:** Medium

#### Problem
Remaining roadmap risk is expected to come from mobile/desktop polish and copy clarity on the critical path rather than missing backend systems.

#### Acceptance Criteria

- UAT findings for home, run map, run node, rest, summary, inventory, Wrong Machine, Academy, shop, guide, and login are grouped by player-facing severity.
- Remaining visible "splice" terminology is logged unless it is explicitly documenting legacy storage.
- Layout issues include viewport, route, and reproduction details.
- High-severity polish issues are promoted into their own active implementation issues.
- Low-severity or speculative improvements are moved to backlog rather than blocking release hardening.

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
