# Active Milestone

Read this for sequencing/planning or when closing/promoting an execution package. Normal implementation should use `agent/ISSUES.md` instead.

## Milestone 5 - Complete Farm

**Status:** Active

### Related Issues
- Milestone 5 Package 1 - Reward/event authored model + persistence foundation

Milestone 1 - Walking Skeleton is complete and passed manual user UAT.

Milestone 2 - Warband is complete and passed manual user UAT on 2026-09-13.

Milestone 3 - Enter Farm is complete and passed manual user UAT on 2026-09-15.

Milestone 4 - Combat is complete and passed manual user UAT on 2026-09-17. Integrated technical closure was approved at `16288bff6223cdddee56b5cbf359e607c07dc81e`. Manual UAT exposed one persistent-scene Replay return-state defect; the focused correction at `dcca26823ca035d3d53df77024fb5393e611307f` resets ephemeral BattleScene activation state and preserves zero-reroll Replay behavior. The focused recheck passed.

The major game-wide visual/UI overhaul remains intentionally deferred. Milestone 5 completes the Farm gameplay loop before final presentation fidelity.

### Outcome
Complete the currently persisted Farm path as a coherent authoritative run:

`Combat -> Loot -> Rest -> Mudking Boss -> Exit -> successful terminal run`

Milestone 5 also establishes the reusable event/reward path required by that flow:
- reward-bearing gameplay facts resolve once into immutable finalized event results;
- grants apply transactionally with no ordinary claim step;
- participating units can receive XP through the reward model;
- permanent region access is a unique unlock reward;
- Mudking completion awards the Mountains unlock directly rather than creating a separate generic Farm-completed flag;
- Exit is normal node resolution and owns successful run termination after the boss path has been cleared.

Mountains gameplay itself remains Milestone 6. Milestone 5 may expose that Mountains is unlocked, but it does not need to implement a Mountains run.

### Architectural Direction
- Authored events and reward definitions live in Git JSON and use stable IDs.
- An event records a successful gameplay fact. Attempting a UI action does not itself create rewards.
- Reward probability is authored and finalized exactly once. Reconnect/retry/replay never rerolls rewards.
- Finalized reward/event records are operational correctness records, not a general event-sourcing architecture.
- Reward grants are additive ownership/progression: currencies, XP, owned assets/collections, and permanent unlocks. Damage, healing, node state, run lifecycle, and other mutations remain effects/domain transitions.
- Ordinary finalized rewards are applied in the same authoritative transaction as their owning gameplay command. There is no battle/reward claim endpoint.
- Unique rewards such as Mountains access resolve to no additional grant if already owned; they are not rerolled or substituted.
- Permanent region access is represented by `user_unlocks`, not by a second region-completion history table.
- Unit XP remains on `unit_instances.xp`; exact XP/level advancement semantics must be reconciled before the package that applies XP, rather than guessed in the persistence foundation.
- PHP remains authoritative. Phaser presents finalized effects/rewards and reconciles server state; it does not roll rewards or apply progression locally.
- Fresh-baseline rules remain in force: update `vnext_baseline.sql`, do not create a migration chain for current prototype/runtime data.

### Package Queue
1. **Reward/event authored model + persistence foundation.** Current.
2. Deterministic reward finalization + initial Farm grant application (Teeth, unit XP, permanent unlock), including exact XP/level semantics.
3. Farm Loot + Rest authoritative node resolution and RunScene interaction/result flow.
4. Mudking authored boss content + deterministic boss-combat adaptation.
5. Boss-node authoritative resolution + finalized Farm boss rewards/XP + Mountains unlock.
6. Exit-node resolution + successful run termination + authoritative Camp/RunScene/unlock reconciliation.
7. Complete-Farm integrated verification/closure.
8. Focused manual UAT; Milestone 6 is not promoted until it passes.

### Sequencing Notes
- Package 1 establishes only the shared authored/persistence substrate. It must not invent Farm reward amounts, XP curves, Mudking mechanics, or node endpoints.
- Package 2 owns the exact versioned finalized reward-result model and grant semantics after Package 1 has established stable content/storage boundaries. It must deliberately reconcile unit XP/level advancement before applying XP.
- Package 3 uses the reward/effect infrastructure for the existing Loot and Rest nodes. Rest healing is an effect, not a reward.
- Package 4 adapts the existing Mudking behavioral evidence/art into current authored combat content and the deterministic vNext kernel without yet making the boss node mutate a run.
- Package 5 extends authoritative node resolution to Boss and attaches the finalized boss-completion event/rewards transactionally. Boss victory unlocks only its persisted direct child (Exit) while also granting the Mountains unlock through the reward pipeline.
- Package 6 resolves Exit as a normal node, terminates the run successfully, clears active-run locks through authoritative reconciliation, and presents the resulting progression without inventing a claim lifecycle.
- Package 7 is technical closure. Manual UAT follows.
- Do not begin Mountains implementation, economy breadth, Academy, Wrong Machine, objectives, or general visual-overhaul work inside Milestone 5.
