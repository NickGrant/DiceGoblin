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

#### Progress

- Added `npm.cmd run sim:shrines:docker` plus region-specific shrine sampler shortcuts for distribution evidence.
- Added pre-claim shrine effect summaries on the node result screen and primitive mix percentages in shrine tuning samples.
- Added `documentation/05-playability-stability/09-shrine-tuning-sample-evidence.md` as the baseline shrine distribution evidence packet for UAT.
- Filtered active run effects to only ongoing next-combat effects and surfaced battle-affecting run modifiers on the battle screen.
- Removed cleared chaos results from ongoing run effects and made connected map paths unlock/render correctly even when an edge travels visually backward.

## Hazard Expansion

### Define generated hazard severity catalog

**Milestone:** Hazard Expansion
**Status:** Open
**Priority:** High

#### Problem
Hazard nodes currently have authored primitive metadata, but most outcomes are still metadata-only pressure. The next hazard pass needs generated effects similar to shrines: severity-weighted, region-aware, persisted when encountered, and broad enough for at least ten initial downside options.

#### Acceptance Criteria

- Hazard nodes use a severity tier for generation and player-facing copy.
- Existing `poor`, `good`, and `great` node quality metadata is either mapped to hazard-facing severity or replaced with an explicit hazard severity contract.
- The exact hazard effect is generated at encounter time from region, severity, seed context, and catalog weights, then persisted for idempotency.
- The initial catalog contains at least ten enabled options across immediate, choice-based, delayed, route, item, currency, HP, and kin-mitigated pressure.
- Hazard definitions do not require duplicating exact generated outcomes in map-node metadata.
- Unit tests cover severity filtering, weighted selection, fallback behavior, and vocabulary validity.

### Implement hazard choice and mitigation flow

**Milestone:** Hazard Expansion
**Status:** Open
**Priority:** High

#### Problem
Some hazards should offer explicit tradeoffs, such as taking damage or paying teeth, and some should be declineable or mitigated by owned kin/progression context. The current auto-resolve flow has no player decision point for hazards.

#### Acceptance Criteria

- Hazard resolution can return a persisted offer with two or more choices when the selected effect requires a decision.
- Frontend hazard result screens present available choices clearly and disable unavailable costs, such as insufficient teeth.
- Claim/apply endpoints persist the selected hazard decision and return the same decision on retry.
- Mitigated hazards can present reduced downside or alternate copy when the player meets the mitigation condition.
- Integration tests cover choice acceptance, unavailable option handling, retry idempotency, and mitigation selection.

### Apply immediate and delayed hazard downsides

**Milestone:** Hazard Expansion
**Status:** Open
**Priority:** High

#### Problem
Hazard result payloads need to change player/run state instead of only clearing the node. Immediate downsides should apply at claim time, while delayed effects such as next-combat defense penalties should be visible and consumed by combat.

#### Acceptance Criteria

- Immediate HP, teeth, item, and route/node-state downsides apply exactly once at hazard claim time.
- Delayed next-combat stat modifiers support attack, defense, precision, resolve, and damage-style multipliers or adders.
- Delayed hazard effects appear in active run effects before combat and are removed or marked consumed after the next eligible fight.
- Hazard effects never grant combat XP unless a later encounter-scope change explicitly allows it.
- Backend and frontend tests cover state application, active effect visibility, and delayed effect consumption.

### Add hazard tuning sampler and evidence packet

**Milestone:** Hazard Expansion
**Status:** Open
**Priority:** Medium

#### Problem
Shrines now have sampling shortcuts and an evidence packet for UAT tuning. Hazards need the same support so severity distribution, downside pressure, and choice frequency can be reviewed before balance passes.

#### Acceptance Criteria

- A Docker-safe hazard sampler reports generated effect counts by region and severity.
- Sampler output includes primitive mix, choice-offer counts, average teeth pressure, average HP pressure, delayed-effect counts, and mitigation counts when applicable.
- Region-specific shortcuts exist for Farm, Mountains, and Swamps.
- A documentation evidence packet records baseline samples and the first tuning notes.
- `docs:lint`, `backlog:validate`, and the relevant backend tests pass.

### UAT and tune hazard severity weights

**Milestone:** Hazard Expansion
**Status:** Open
**Priority:** Medium

#### Problem
Once generated hazards are implemented, severity and downside weights need player-facing validation across several seeds to ensure hazards feel meaningful without turning every branch into punishment.

#### Acceptance Criteria

- Farm, Mountains, and Swamps hazard samples are reviewed across minor, moderate, and severe hazard tiers.
- Immediate, choice-based, delayed, and mitigated hazard outcomes are all seen in UAT or sampler evidence.
- Player-facing copy makes the downside and any available choice understandable before final claim.
- Balance changes are captured with evidence packets before tuning.
- Any blocker found during shrine or hazard UAT is split into a separate high-priority issue with reproduction notes.

