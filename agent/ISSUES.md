# ISSUES FILE
----
Active issues only. Move completed entries to `agent/ISSUES_ARCHIVE.md`.

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

#### Progress

- Added Docker balance simulation shortcuts for Mountains and Swamps plus an aggregate Farm/Mountains/Swamps run command for UAT region-pacing evidence.

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

#### Progress

- Added a release-readiness validation command plan that checks tracker/docs hygiene, generated frontend artifact cleanliness, and the heavier Docker/frontend gates needed before final release handoff.

## Shrine Expansion

### Add generated quality-weighted shrine effects

**Milestone:** Shrine Expansion
**Status:** Open
**Priority:** High

#### Problem
Shrines currently behave like simple reward nodes and do not use node quality to produce meaningfully different generated outcomes.

#### Acceptance Criteria

- Shrine nodes generate an effect at encounter time from region, run seed, node id, and quality.
- Shrine node metadata stores quality/rendering context only, not preselected effect slugs.
- Poor, good, and great shrine qualities have different weighted effect pools.
- Generated shrine results persist through battle logs/rewards so repeated claims are idempotent.
- Backend tests cover effect generation and claim-time application.

#### Progress

- Backend shrine catalogue now supports quality-weighted generated effects for teeth, healing, drain-and-heal, next-combat damage metadata, double run teeth, and clearing an available combat node.
- Merged in PR #230.

### Add declineable shrine offer flow

**Milestone:** Shrine Expansion
**Status:** Open
**Priority:** High

#### Problem
Some shrine effects should include costs or negative outcomes, but the current shrine page auto-resolves immediately and gives the player no chance to decline.

#### Acceptance Criteria

- Shrine nodes with costs generate a persisted offer before applying effects.
- The frontend presents accept and decline actions for costly shrines.
- Declining clears or exits the shrine according to the chosen design without applying the positive or negative effect.
- Accepting applies both cost and reward exactly once.
- API and frontend tests cover accept, decline, refresh, and idempotent repeat calls.

### Consume shrine combat modifiers in battle resolution

**Milestone:** Shrine Expansion
**Status:** Open
**Priority:** Medium

#### Problem
The next-combat squad damage shrine can be persisted as run-unit effect metadata, but combat resolution does not yet consume run-unit status effects.

#### Acceptance Criteria

- `squad_damage_next_combat` increases squad damage by the authored multiplier for the next combat-like node.
- The modifier is consumed after one eligible combat.
- Combat logs identify the shrine modifier contribution.
- Tests prove the modifier applies once and then expires.

#### Progress

- Added generic next-combat run stat modifier support for damage plus attack, defense, precision, and resolve.

### Add shrine unit-upgrade reward effect

**Milestone:** Shrine Expansion
**Status:** Open
**Priority:** Medium

#### Problem
The requested “reroll one random unit gained in run to a higher tier” shrine needs reward-history and unit-instance mutation rules that are broader than the first backend shrine effect slice.

#### Acceptance Criteria

- The shrine can select one unit gained during the current run.
- The selected unit is upgraded or rerolled to a higher tier according to authored tier rules.
- Reward preview, claim response, and run summary show the upgraded unit clearly.
- Tests cover no eligible unit, one eligible unit, and multiple eligible units.

### UAT and tune shrine effect weights

**Milestone:** Shrine Expansion
**Status:** Open
**Priority:** Medium

#### Problem
Shrine weights and quality pools need hands-on validation once the new generated effects are available in run maps.

#### Acceptance Criteria

- Farm, Mountains, and Swamps shrine samples are reviewed across poor, good, and great shrine qualities.
- Generated effects feel appropriate to quality and region.
- Costly shrines are clear enough that declining feels intentional rather than like a missed reward.
- Balance changes are captured with evidence packets before tuning.

