# Active Execution Issue

## Milestone 5 - Complete Farm

### Milestone 5 Package 7 - Complete-Farm integrated verification/closure

**Status:** In Progress
**Priority:** High

#### Goal

Close the Milestone 5 technical slice by proving the complete persisted Farm path works coherently on a fresh vNext database:

`Combat -> Loot -> Rest -> Mudking Boss -> Exit -> Camp`

This is a verification/closure package, not a feature package. Do not add new gameplay behavior unless verification exposes a concrete defect in accepted Packages 1-6.

#### Accepted baseline

Packages 1-6 are architecturally approved. In particular:
- Loot grants the authored deterministic 8 Teeth exactly once.
- Rest fully restores authoritative run HP.
- Boss uses persisted Mudking combat, grants exactly 16 XP per participating unit, and grants/already-owns `unlock.region.mountains`.
- Exit completes the run successfully and same-runtime Camp reconciliation consumes authoritative bootstrap state.
- No claim/reroll/double-grant path exists.
- Mountains gameplay itself remains Milestone 6.

Package 6 implementation at `d46f09a514c7401c3e39bf14c075b0309f62566b` is approved after focused MySQL verification on branch state `5fe5a2400399893841fcf03f55f31a1d5d318346`:
- focused Farm node integration: 11 tests / 107 assertions / 0 skipped;
- Docker backend suite: 576 tests / 2,396 assertions.

#### Verification work

Prove the integrated Farm lifecycle from a clean database, using existing production paths rather than test-only shortcuts where practical.

At minimum:
1. provision/reset the vNext Docker test database from `backend/migrations/vnext_baseline.sql`;
2. run `npm run verify:package`;
3. run `npm run test:backend:docker`;
4. run the applicable integrated run/combat verification already present in the repo;
5. exercise or add a focused integration/regression test that proves the complete successful Farm path through Exit and authoritative terminal/bootstrap state in one coherent flow;
6. verify retry/idempotency invariants remain intact across reward-bearing and terminal nodes;
7. verify current-run/bootstrap state after Exit is terminal and Mountains/XP remain durable;
8. verify frontend build and the accepted RunScene/BattleScene/Camp return path remain green.

If existing tests already provide a required proof, use them rather than creating duplicate coverage.

#### Closure review

Inspect the integrated results for cross-package inconsistencies, including:
- stale active-run locks after successful Exit;
- duplicate Loot/Boss rewards on replay/retry;
- Boss playback/reconnect disagreement;
- participant XP or Mountains missing from post-Exit bootstrap;
- run HP or progression being recomputed by the client;
- stale fresh Warband unit/detail caches after Boss progression;
- Farm-specific shortcuts that would block the accepted Milestone 6 region-generalization direction.

Fix only defects necessary to satisfy accepted Milestone 5 behavior. Do not broaden architecture or refactor unrelated code.

#### Required report

Report:
- exact SHA;
- verification commands actually run;
- compact pass/fail counts, including skipped tests where reported;
- any implementation defect found and corrected;
- whether the full Farm technical slice is ready for manual UAT.

#### Out of scope

- new rewards, economy, inventory, Academy, Wrong Machine, objectives, or onboarding;
- Mountains run generation or gameplay;
- region-selection redesign;
- broad visual/UI overhaul;
- Milestone 6 implementation;
- manual UAT itself.

#### Completion

Leave Package 7 **In Progress** for architectural review. Do not mark Milestone 5 complete and do not promote Milestone 6. Manual UAT is Package 8 and must pass before Milestone 6 promotion.
