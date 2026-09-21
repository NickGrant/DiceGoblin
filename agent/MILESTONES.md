# Active Milestone

Read this for sequencing/planning or when closing/promoting an execution package. Normal implementation should use `agent/ISSUES.md` instead.

## Milestone 6 - Prove Region Generalization

**Status:** Active

### Related Issues
- Milestone 6 Package 4 - Unlock-aware multi-region run start + Camp region selection/resume

Milestone 1 - Walking Skeleton is complete and passed manual user UAT.

Milestone 2 - Warband is complete and passed manual user UAT on 2026-09-13.

Milestone 3 - Enter Farm is complete and passed manual user UAT on 2026-09-15.

Milestone 4 - Combat is complete and passed manual user UAT on 2026-09-17. Integrated technical closure was approved at `16288bff6223cdddee56b5cbf359e607c07dc81e`. Manual UAT exposed one persistent-scene Replay return-state defect; the focused correction at `dcca26823ca035d3d53df77024fb5393e611307f` passed the focused recheck.

Milestone 5 - Complete Farm is complete and passed manual user UAT on 2026-09-19. Integrated technical closure was approved at `9825a62f567fca39445674fc7fc71d3f2038c253`; focused manual UAT completed the full Combat -> Loot -> Rest -> Mudking Boss -> Exit -> Camp path with no issues.

The major game-wide visual/UI overhaul remains intentionally deferred.

### Outcome

Prove that the accepted region/run architecture generalizes beyond the Farm by making the unlocked Mountains and kobolds playable through the same authored-content, run-generation, authoritative-resolution, playback, persistence, and Phaser presentation boundaries.

Milestone 6 is successful when:
- Mountains is authored canonically in vNext rather than read from prototype DB content;
- the existing `unlock.region.mountains` controls availability;
- an unlocked player can select and start Mountains from Camp;
- Mountains generates and persists through the same run architecture as Farm;
- kobold Combat/Boss encounters use the same deterministic combat/playback pipeline;
- reusable node resolution no longer depends on Farm-only event/region/index assumptions;
- completion returns cleanly to Camp through the existing terminal reconciliation path;
- Mountains does not require a parallel API, repository layer, combat service, or Phaser scene.

Swamps, Lizard Kin restoration, Wrong Machine recovery, economy breadth, and final visual polish remain later milestones.

### Architectural Direction

- Generalize by removing concrete Farm assumptions only where Mountains proves they are false; do not build speculative multi-region abstractions.
- JSON in Git remains canonical authored gameplay content.
- Region availability is authoritative server state: the starting region is available by rule; later regions require their stable unlock.
- A playable region must point to validated authored run generation.
- Continue using the same `POST /api/v1/runs`, current-run query, node-resolution endpoint, battle persistence/playback, and terminal reconciliation model.
- Prefer content-driven region/event/reward identities over region-specific handler classes.
- Unique future-region rewards are not invented merely to complete Mountains. Milestone 6 does not need to unlock Swamps.
- Fresh-baseline rules remain in force: update `vnext_baseline.sql` rather than creating a prototype migration chain.

### Package Queue

1. ~~Mountains authored combat foundation + deterministic kobold adaptation.~~ Complete and architecturally approved at `4adff479b4c10af40057f1f93088c0930e5894d8` after focused kobold semantic corrections.
2. ~~Region-neutral Boss reward + terminal Exit resolution contracts.~~ Complete and architecturally approved at `a667ee703da5f868fd65218199ad271553110bc6`; MySQL/Docker proof: Boss 12/111/0 skipped, Exit/Loot/Rest 17/143/0 skipped, full backend 590/2,475/148 skipped.
3. ~~Mountains authored run graph/events/rewards + terminal lifecycle.~~ Complete and architecturally approved at `f06e150e68ef39c7eeab1299a61d00a6dd2f9bea` after authored-content and RunScene region-neutral corrections.
4. **Unlock-aware multi-region run start + Camp region selection/resume.** Current.
5. Mountains integrated verification/closure.
6. Focused manual UAT; Milestone 7 is not promoted until it passes.

### Sequencing Notes

- Package 1 established canonical Shieldbearer, Skirmisher, Sharpshooter, and Chief Engineer content plus retained Mountains encounter compositions through the shared deterministic combat engine; it was approved at `4adff479b4c10af40057f1f93088c0930e5894d8`.
- Package 2 removed the Farm-specific assumptions in reusable Boss reward projection and terminal Exit resolution. It was approved at `a667ee703da5f868fd65218199ad271553110bc6` after MySQL/Docker verification proved the changed Boss/Exit integration suites and full backend gate.
- Package 3 supplied the canonical seven-node Mountains fixed graph and authored Loot/Boss rewards through the generalized Package 2 path. It was approved at `f06e150e68ef39c7eeab1299a61d00a6dd2f9bea`; the Boss grants XP only and does not unlock Swamps.
- Package 4 replaces the current `startingRegionId()`-only start gate with authoritative unlock-aware availability, then gives Camp a content-driven region choice. Active-run resume remains region-agnostic.
- Package 5 proves the full second-region path and checks for remaining Farm-specific architecture leaks.
- Package 6 is manual UAT. Do not begin Milestone 7 economy/inventory work until it passes.
