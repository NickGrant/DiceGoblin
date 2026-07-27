# ISSUES FILE
----
Active issues only. Move completed entries to `agent/ISSUES_ARCHIVE.md`.

## Combat Resolution Correctness

### Remove score-based combat outcome fallback

**Milestone:** Combat Resolution Correctness
**Status:** Open
**Priority:** High

#### Problem
Combat currently calculates an internal player-power versus enemy-power score and uses it as an initial win/loss result when event simulation does not produce a clear terminal state. This allows combat to resolve from an estimate instead of fully resolved events.

#### Acceptance Criteria

- Combat outcome is determined by simulated events, not by a score fallback.
- The score calculation and initial score-based outcome branch are removed from `DeterministicRunNodeResolver`.
- Combat result metadata and logs no longer expose or depend on player/enemy power score fallback behavior.
- Tests cover a combat that previously would have relied on the score fallback and now must resolve through events.
- `documentation/09-active-system-structure/03-combat-resolution.md` is updated after implementation to describe the new event-only outcome rule.

### Remove arbitrary combat round cutoff

**Milestone:** Combat Resolution Correctness
**Status:** Open
**Priority:** High

#### Problem
Combat currently plans a length of 3-5 rounds before event simulation starts. That means a battle can stop without reaching a natural terminal state, which is not the intended combat model.

#### Acceptance Criteria

- Combat simulation continues until one side is defeated or another explicit terminal combat condition is reached.
- There is no ordinary 3-5 round cutoff for combat resolution.
- Any infinite-loop protection is explicit, treated as an engine error or exceptional fail-safe, and covered by tests.
- Existing battle logs still report the round and tick where combat actually ended.
- Tests cover combat that lasts beyond 5 rounds and resolves from events.
- `documentation/09-active-system-structure/03-combat-resolution.md` is updated after implementation to remove the planned-length language.

### Require explicit ability sets for every combatant

**Milestone:** Combat Resolution Correctness
**Status:** Open
**Priority:** High

#### Problem
The resolver currently falls back to `basic_attack_melee` when no active ability can be scheduled. This hides missing or poorly defined unit and enemy ability data instead of forcing the catalog to be correct.

#### Acceptance Criteria

- The automatic `basic_attack_melee` schedule fallback is removed for both player units and enemies.
- Every combatant must resolve an explicit active ability schedule from its unit or enemy definition.
- Missing, empty, or unschedulable ability sets fail validation before combat resolution rather than silently substituting an attack.
- Seed/catalog validation covers player unit types and enemy templates used in runs.
- Tests cover an invalid enemy with no schedulable abilities and assert that combat does not silently proceed.

### Remove automatic tick autofill behavior

**Milestone:** Combat Resolution Correctness
**Status:** Open
**Priority:** High

#### Problem
The active ability scheduler auto-fills remaining round ticks with repeatable filler abilities. This appears to compensate for incomplete ability definitions and makes action timing less authored and less readable.

#### Acceptance Criteria

- The scheduler only schedules explicitly defined abilities according to their authored speed and equip/order rules.
- Repeatable filler abilities are not automatically duplicated to fill unused ticks.
- Units and enemies with sparse schedules retain those intentional gaps rather than receiving hidden extra actions.
- Existing ability definitions are audited and updated where needed so enemies still function after autofill is removed.
- Tests cover a unit or enemy whose schedule leaves unused ticks and assert that no extra filler actions are inserted.

### Apply full dice roll values in combat math

**Milestone:** Combat Resolution Correctness
**Status:** Open
**Priority:** High

#### Problem
Combat dice documentation and code need to be reconciled with the intended rule that the full die roll should be applied. Current resolver behavior calculates a centered modifier from each die roll, which reads as only part of the roll being applied.

#### Acceptance Criteria

- Combat dice math applies the full rolled value according to the intended ability formula.
- Existing centered modifier behavior is removed or explicitly replaced everywhere it affects combat action outcomes.
- Ability outcome text and battle logs describe dice contribution in a player-understandable way.
- Tests cover representative dice rolls, including low rolls, high rolls, and exploding dice.
- `documentation/09-active-system-structure/03-combat-resolution.md` is updated after implementation with the final full-roll rule.

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

### Polish home navigation and command controls

**Milestone:** UAT Feedback Fix Round 1
**Status:** In Progress
**Priority:** Medium

#### Problem
Home and global command controls need the remaining UAT affordance polish so navigation reads clearly and unlocked Raw Chaos has a recognizable tracker.

#### Acceptance Criteria

- Home breadcrumbs do not include an `HQ` link.
- Home utilities do not include the removed formation, map, and unlocks summary cards.
- The Raw Chaos tracker uses a recognizable icon once the Wrong Machine is unlocked.
- The dropdown menu opens with a small slide-down and fade-in animation.

### Refine warband, unit, and squad-edit UX

**Milestone:** UAT Feedback Fix Round 1
**Status:** In Progress
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
**Status:** In Progress
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
