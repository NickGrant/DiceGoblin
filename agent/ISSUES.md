# Active Execution Issue

## Milestone 5 - Complete Farm

### Milestone 5 Package 8 - Focused manual UAT

**Status:** In Progress
**Priority:** High

#### Problem

Technical closure is complete. Manually prove the player-facing Farm loop behaves correctly end to end before Milestone 6 is promoted.

Accepted implementation/closure baseline:
- Package 7 technical closure approved at `9825a62f567fca39445674fc7fc71d3f2038c253`.
- Clean-database Farm integration passed.
- Combat playback verification passed.
- Run lifecycle verification passed.
- Standard package verification and Docker backend suite passed.
- No known technical blocker remains.

#### Manual UAT path

Use a normal player account and production UI paths. Do not use direct API calls to advance the run.

Verify:

1. **Camp / run entry**
   - Start from Camp with an active squad.
   - Enter the Farm normally.
   - Confirm the expected five-node path is presented.

2. **Combat**
   - Resolve the first Combat.
   - Watch the persisted battle playback.
   - Continue back to the same run without a duplicate fight or reward.
   - Confirm Loot becomes available.

3. **Loot**
   - Collect Loot once.
   - Confirm the player receives exactly 8 Teeth.
   - Confirm Rest becomes available and Loot cannot be granted again.

4. **Rest**
   - Use Rest.
   - Confirm participating units are restored to full run HP.
   - Confirm Boss becomes available.

5. **Mudking Boss**
   - Fight Mudking and watch the battle result/playback.
   - Confirm each participating unit receives 16 XP.
   - Confirm level changes, if any, are reflected after authoritative reconciliation.
   - Confirm Mountains is reported as unlocked/already owned.
   - Continue back to the run and confirm Exit becomes available.

6. **Exit / terminal reconciliation**
   - Leave the Farm through Exit.
   - Confirm the run completes and returns to Camp only after synchronization.
   - Confirm there is no active-run lock afterward.
   - Confirm the post-Boss XP/levels remain visible.
   - Confirm the Mountains unlock remains durable.
   - Confirm no extra Loot/Boss reward is granted during Exit.

7. **Warband after completion**
   - Open Warband after returning to Camp.
   - Confirm squad/loadout editing is available again.
   - Confirm unit XP/level state agrees with the completed Boss result.

8. **Reload durability**
   - Reload from Camp after completion.
   - Confirm the player remains out of the completed run.
   - Confirm Teeth, XP/levels, and Mountains access remain authoritative and durable.

#### Failure handling

If UAT exposes a defect:
- record the exact step, visible symptom, and whether reload changes the result;
- leave Milestone 5 active;
- create only the focused correction necessary for the failed behavior;
- rerun the affected automated verification before repeating UAT.

Do not add unrelated polish or Milestone 6 functionality while correcting UAT findings.

#### Completion

Milestone 5 closes only after the user reports this focused UAT passed.

Do not promote Milestone 6 until that report is received.
