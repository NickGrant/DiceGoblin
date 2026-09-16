# Active Milestone

Read this for sequencing/planning or when closing/promoting an execution package. Normal implementation should use `agent/ISSUES.md` instead.

## Milestone 4 - Combat

**Status:** Active

### Related Issues
- Milestone 4 Package 3 - Battle persistence + playback boundary

Milestone 1 - Walking Skeleton is complete and passed manual user UAT.

Milestone 2 - Warband is complete and passed manual user UAT on 2026-09-13.

Milestone 3 - Enter Farm is complete and passed manual user UAT on 2026-09-15. Integrated technical closure was approved at `c456d983b1afaf36c0dc9e069b3e35cfcdf6957f`; the focused UAT correction package was approved at `038a6ce081fa3b735f627095db372db24b39f79e`.

The major game-wide visual/UI overhaul remains intentionally deferred. Milestone 4 should make combat authoritative, deterministic, persistent, reconnect-safe, and watchable before final presentation fidelity.

### Outcome
Allow a persisted Farm combat node to resolve through authoritative PHP combat, preserve resulting run HP and battle history, and replay the persisted battle in Phaser without client-side simulation:

`RunScene combat node -> authoritative resolve -> persisted battle/result/playback + run HP -> BattleScene playback -> result -> RunScene`

Rewards, XP/progression grants, boss/run completion, Mountains unlock, and the complete Farm terminal flow remain Milestone 5 unless a minimal lifecycle fact is required to make the Milestone 4 combat slice internally coherent.

### Architectural Direction
- PHP remains the sole combat authority; Phaser consumes playback and never simulates authoritative rolls, targets, damage, statuses, deaths, or outcomes.
- Combat receives a complete authoritative input snapshot and returns deterministic result/playback data. It must not own HTTP, PDO, repositories, player wallets, or parent transactions.
- Authored combat content remains canonical Git JSON exposed through `ContentRegistry`; no SQL gameplay catalogs return.
- Existing prototype combat source is evidence to mine, not an architecture to preserve. `DeterministicRunNodeResolver` is not a vNext application or domain boundary.
- Package 1 established the canonical base level-stat rule as `base + growth_per_level * (level - 1)` for HP, Attack, Defense, Precision, and Resolve. Speed is not introduced.
- During a run, `run_unit_state.current_hp` is authoritative. Package 1 initializes new-run participants at their resolved max HP and enforces concrete non-null run HP in the fresh baseline.
- Package 2 established the canonical first-Farm combat content and the infrastructure-free deterministic vNext combat kernel. Its versioned normalized input and semantic result/playback are now the persistence contract to wrap rather than reconstruct after combat.
- Battle persistence must retain the immutable normalized battle facts required to reproduce/explain the finalized battle even if Warband state or authored content later changes. Reconnect must consume persisted battle data rather than reassemble a historical battle from mutable sources.
- Battle storage remains subordinate to the run node. One run node may finalize at most one battle; Package 4 will own the mutation/idempotency transaction that creates it and applies terminal player HP/node state.

### Exit Criteria
- Unit combat stats resolve deterministically from canonical authored type data and unit level without prototype SQL catalogs or a persistent Speed stat.
- New run participants begin with authoritative current HP equal to resolved max HP; idempotent run start and existing M3 behavior remain correct.
- Farm enemy/encounter/ability combat content needed for this slice is canonical authored JSON and semantically validated with deliberate exposure rules.
- The vNext combat engine is deterministic and infrastructure-free, consumes resolved snapshots, and produces authoritative results/playback.
- Target resolution, ordered abilities/timing, dice bindings/profile effects, damage/status behavior, death, and stalemate handling have focused deterministic tests for the implemented Farm slice.
- The fresh baseline contains only battle persistence actually required by the slice; no speculative reward/event-sourcing schema is added.
- Combat-node resolution is authenticated, owned, idempotent/retry-safe, transactionally updates battle/node/run-unit state, and cannot reroll by retry/reload.
- Battle result/playback reads enforce ownership/non-disclosure and allow reconnecting to the same persisted battle.
- `BattleScene` replays server-produced playback without independently deriving gameplay authority.
- Returning from battle reconciles RunScene/GameStore with authoritative persisted state; reload during/after playback resumes safely.
- M1-M3 regressions remain green and responsive/touch behavior remains usable.
- Integrated technical closure passes architectural review, then manual user UAT passes before Milestone 5 is promoted.

### Package Queue
1. ~~Canonical combatant stats + authoritative run HP initialization.~~ Complete and architecturally approved at `dcb78d61672b5b4e739d16c3757f8d7bbc817d07`.
2. ~~Farm combat authored content + deterministic engine adaptation.~~ Complete and architecturally approved at `2c1d9e9f4863da95a2cb0021d4b16d066fb5b7a7`.
3. **Battle persistence + playback boundary.** Current.
4. Authoritative combat-node resolution + persisted run/battle state.
5. Battle/result/playback query and reconnect contracts.
6. Phaser `BattleScene` playback lifecycle.
7. Battle result + authoritative return-to-run reconciliation.
8. Combat integrated verification/closure.

### Sequencing Notes
- Packages 1-2 established the canonical stat/run-HP prerequisite and the deterministic combat input/result/playback contract. Do not reopen those boundaries while adding persistence unless a proven blocker requires it.
- Package 3 introduces only concrete durable battle storage needed by Package 2's immutable normalized input and result/playback. It must preserve enough participant identity metadata for later run-HP reconciliation and BattleScene presentation without asking the combat kernel to understand database or display identity.
- Prefer one finalized `battles` record containing the exact normalized input, a compact participant manifest, and the exact versioned result/playback payload. Do not create a separate `battle_playback` table merely to split a one-to-one JSON payload or duplicate events; add another table only if a concrete current requirement proves it necessary.
- Package 3 repository operations are persistence primitives only and do not own transactions. Package 4 owns the first mutation that resolves a combat node, including transaction/idempotency/state-transition semantics and enforcing the existing one-physical-die/one-slot Warband invariant while assembling the snapshot.
- Package 5 exposes persisted battle/result/playback with ownership/non-disclosure and reconnect contracts; it must not regenerate history from current content/Warband state.
- Packages 6-7 make the persisted result watchable and reconcile Phaser back to authoritative run state; Phaser never becomes combat authority.
- Package 8 is technical closure. Manual UAT follows; do not begin Milestone 5 until it passes.
