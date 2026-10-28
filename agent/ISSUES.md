# Active Execution Issue

## Milestone 11 - Knowledge and Objectives

### Milestone 11 Package 9 - Focused manual UAT before Milestone 12

**Status:** In Progress
**Priority:** High

#### Problem
Packages 1-8 have implemented and technically verified the Milestone 11 knowledge/objective lifecycle. The remaining gate is focused player-visible manual UAT before the milestone may close and Milestone 12 may begin.

#### Accepted baseline
- Milestones 1-10 are complete and passed their required manual UAT.
- Milestone 11 Packages 1-7 are approved as recorded in `agent/MILESTONES.md`.
- Package 8 is approved through `3834110440ca02ee747860fe7badccae9a398183` (verification implementation `63e59bacf3d5b6248cbed4aa0501a995eef1dbaf`).
- Full Package 8 verification passed with 795 backend tests / 5,769 assertions and 524 frontend tests.
- Package 8 found no production-code or canonical-architecture correction necessary.
- Backend/MySQL remains authoritative for Codex ownership, dialogue eligibility, objective progress/completion, rewards, revision, and idempotency.
- Phaser surfaces are read/presentation clients of authoritative Codex/objective state.
- The major game-wide visual/UI overhaul remains deferred.

#### Purpose
Perform focused manual UAT of the player-visible Milestone 11 behavior. This package is a gate, not an implementation package. Do not add features while executing UAT. Any defect found should be recorded precisely and corrected in a narrow follow-up before acceptance.

#### UAT preparation
Use a clean/test account or otherwise known state that allows the tester to distinguish undiscovered/discovered Codex entries and unstarted/active/completed objectives. Use the normal game client and backend rather than debug-only mutation paths except where a documented test setup is necessary to reach a state.

#### Manual UAT checklist

##### A. Camp surfaces and navigation
1. Open Camp and confirm both **Codex** and **Objectives** destinations are visible and usable alongside existing destinations.
2. Enter Codex, return to Camp, enter Objectives, return to Camp, and repeat several times. Confirm navigation remains responsive with no duplicated input behavior, stale overlays, or obvious lifecycle artifacts.
3. Resize/use representative landscape sizes where practical and confirm both surfaces remain legible and navigable. On portrait touch-first layout, confirm the existing rotate-device gate remains in control.

##### B. Codex knowledge presentation
1. Open Codex before earning the test/available knowledge entry. Confirm the entry is visibly undiscovered and its private body text is not shown.
2. Trigger the legitimate dialogue/gameplay flow that grants the knowledge entry.
3. Reopen or refresh Codex and confirm the entry is now discovered and its body is visible.
4. Reload/reconnect and confirm the discovered state persists.
5. Replay the granting dialogue where available and confirm the Codex does not create a duplicate entry or otherwise visibly duplicate the unique reward.

##### C. Knowledge-aware dialogue
1. Before owning the prerequisite Codex knowledge, open the relevant dialogue and confirm only the non-prerequisite choice(s) are available.
2. After earning the prerequisite knowledge, reopen the dialogue and confirm the knowledge-aware choice becomes available.
3. Confirm dialogue remains replayable; prior viewing alone must not remove it or fabricate a viewed/history state.
4. If practical with a second test account, confirm one account's Codex ownership does not unlock the knowledge-aware choice for the other account.

##### D. Objective presentation and progress
1. Open Objectives in a known initial state and confirm unstarted/active/completed labels and requirement counters look coherent.
2. Perform an authoritative qualifying gameplay action represented by current authored content (combat enemy defeat, successful region run completion, and/or legitimate dialogue completion).
3. Return to Objectives and confirm the appropriate counter advances without any manual claim/progress action.
4. Confirm unrelated objectives/requirements do not visibly advance.
5. Reload/reconnect and confirm progress persists.

##### E. Objective completion and rewards
1. Complete an available objective through legitimate gameplay facts.
2. Confirm the objective automatically becomes completed when its requirements are satisfied; there should be no Claim button or client-side completion action.
3. Confirm any authored completion reward is reflected through its normal authoritative surface (wallet/unlock/Codex/inventory/unit/etc.) rather than reconstructed inside Objectives.
4. Repeat/replay a previously accepted action where the UI permits it and confirm objective completion/rewards are not visibly duplicated.
5. Reload/reconnect and confirm completed status and reward state persist.

##### F. Error/recovery sanity
Where practical without modifying production behavior, exercise a temporary read failure/offline interruption while entering Codex or Objectives. Confirm the surface fails visibly, offers the existing retry path, and recovers after connectivity returns without displaying fabricated/stale state as current.

#### Acceptance criteria
Package 9 passes when the user explicitly confirms the focused UAT is acceptable and no blocking Milestone 11 defect remains. Minor visual polish that belongs to the deferred game-wide visual overhaul may be recorded separately and does not block acceptance unless it prevents usable interaction or comprehension.

If a blocking defect is found:
- leave Package 9 In Progress;
- record the exact reproduction and expected/actual behavior;
- make only the narrow correction required by accepted Milestone 11 semantics;
- rerun affected automated gates and the relevant UAT steps before requesting acceptance again.

#### Scope exclusions
Do not add new gameplay/product mechanics, objective requirement kinds, dialogue history/story flags, manual objective claims, generic progression/history state, Mystic Cave/onboarding, Swamp/Frog Kin, production Shrine/Chaos expansion, broad visual redesign, or other Milestone 12+ work during this UAT package.

#### Completion evidence required
Record the user's explicit UAT acceptance, any defects/corrections and verification SHAs, and the final accepted Milestone 11 baseline. Only after explicit acceptance should `agent/MILESTONES.md` mark Package 9 approved / Milestone 11 complete and activate Milestone 12 planning/execution.

Leave Package 9 **In Progress** until the user explicitly accepts UAT.
