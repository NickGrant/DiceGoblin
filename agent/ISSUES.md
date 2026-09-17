# Active Execution Issue

## Milestone 4 - Combat

### Manual Combat UAT

**Status:** Pending User UAT
**Priority:** High

#### Technical closure
Milestone 4 Package 8 technical closure is complete and architecturally approved at:

`16288bff6223cdddee56b5cbf359e607c07dc81e`

Packages 1-8 are complete. Do not begin Milestone 5 until manual UAT passes.

The automated closure established the authoritative combat slice end to end:

`Camp -> Start Farm -> available Combat -> Fight -> authoritative resolution -> persisted battle/run HP -> BattleScene playback -> reload-safe retained battle -> result -> Continue -> authoritative reconciliation -> RunScene/Camp`

Closure evidence includes the fresh MySQL 8 baseline, deterministic combat tests, real MySQL resolution/playback/current-run coverage, complete backend Docker suite, complete frontend suite/build/bundle gates, stable authored-content validation, deterministic visual captures, and the real browser/PHP/MySQL victory verifier.

`verify:full` remains unavailable as an aggregate because host PHP is not installed on `PATH`; its supported Docker backend/content and frontend constituent gates passed. No GitHub CI status is attached to the closure commit.

#### Manual UAT goal
Confirm the Milestone 4 combat slice is understandable and behaves correctly from the player's perspective. Do not use UAT to demand final production visual polish; art/animation polish remains intentionally deferred unless presentation is confusing or unusable.

#### Primary UAT path
1. Start from a fresh supported test account with the valid Warband UAT/debug fixture.
2. From Camp, start a Farm run.
3. Select the available Combat node and confirm a clear Fight/Enter Combat action appears.
4. Enter combat once. Confirm there is no apparent duplicate submission or double transition.
5. Watch the BattleScene playback. Confirm:
   - player and enemy sides/formation read correctly;
   - Mudwrestler appears in the enemy front and Mudslinger in the enemy back;
   - participant names/HP are readable;
   - acting unit/target/action feedback is understandable;
   - dice/hit/damage/status/death feedback appears coherent;
   - no obvious combat fact changes unexpectedly or appears to be rerolled during playback.
6. Before pressing Continue, reload the browser once after the battle result is visible. Confirm the same retained battle reopens rather than resolving another battle.
7. Let playback finish again. Confirm the persisted Victory result and terminal player HP are understandable and there are no fake XP/Teeth/reward/loot grants.
8. Press Continue. Confirm return to the same Farm RunScene.
9. Confirm Combat is completed, Loot is now available, Rest/Boss/Exit remain locked, and surviving unit HP reflects the battle result.
10. Select the completed Combat node and use Replay/Watch Battle. Confirm it replays the existing battle rather than starting a new fight.
11. Continue after Replay and confirm return to the authoritative Farm run again.
12. Reload after returning to RunScene. Confirm the browser resumes the run rather than returning to the old battle.

#### Interaction / responsive checks
- Check the combat flow at the normal desktop viewport used for UAT.
- Resize/narrow enough to exercise Compact landscape and confirm combat/result controls remain usable without meaningful overlap.
- Exercise a Wide layout if practical and confirm formation/result presentation remains coherent.
- On touch/mobile emulation, rotate to portrait during playback or at the result. Confirm the orientation gate blocks interaction/playback advancement, then return to landscape and confirm presentation resumes safely.
- Confirm actionable Fight/Replay/Continue controls show the expected pointer/action affordance and disabled/submitting controls do not appear clickable.

#### Failure observations
If a transient network/reload problem is easy to simulate manually, verify that the finalized battle is not visibly rerolled and that recovery does not create another fight. This is supplemental; automated coverage already exercises retry/idempotency extensively.

#### Pass criteria
Manual UAT passes when:
- the primary path completes without functional defects;
- combat playback is understandable enough to use despite deferred polish;
- reload before Continue retains the same battle;
- Continue returns to authoritative run state;
- Replay does not reroll combat;
- post-combat HP/node availability is credible and persistent;
- responsive/orientation behavior does not make the flow unusable;
- no blocker is found in the player-facing Milestone 4 scope.

#### If UAT finds a defect
Do not promote Milestone 5. Record the exact observed behavior, reproduction steps, viewport/device context where relevant, and whether reload changes the result. Create only a focused Milestone 4 correction package for genuine blockers, then rerun the affected automated gates and the relevant UAT subset.

#### Explicitly deferred
The following are not Milestone 4 UAT failures by themselves:
- final combat art/animation polish;
- rewards/XP/objectives/Teeth;
- Loot node resolution;
- Rest/Boss/Exit resolution;
- Mudking boss combat;
- Farm completion;
- Mountains unlock;
- broader run-history/replay UI.
