# Active Milestone

Read this for sequencing/planning or when closing/promoting an execution package. Normal implementation should use `agent/ISSUES.md` instead.

## Milestone 4 - Combat

**Status:** Active

### Related Issues
- Milestone 4 Package 7 - Battle result + authoritative return-to-run reconciliation

Milestone 1 - Walking Skeleton is complete and passed manual user UAT.

Milestone 2 - Warband is complete and passed manual user UAT on 2026-09-13.

Milestone 3 - Enter Farm is complete and passed manual user UAT on 2026-09-15. Integrated technical closure was approved at `c456d983b1afaf36c0dc9e069b3e35cfcdf6957f`; the focused UAT correction package was approved at `038a6ce081fa3b735f627095db372db24b39f79e`.

The major game-wide visual/UI overhaul remains intentionally deferred. Milestone 4 should make combat authoritative, deterministic, persistent, reconnect-safe, and watchable before final presentation fidelity.

### Outcome
Allow a persisted Farm combat node to resolve through authoritative PHP combat, preserve resulting run HP and battle history, and replay the persisted battle in Phaser without client-side simulation:

`RunScene combat node -> authoritative resolve -> persisted battle/result/playback + run HP -> BattleScene playback -> result -> authoritative reconciliation -> RunScene/Camp`

Rewards, XP/progression grants, boss/run completion, Mountains unlock, and the complete Farm terminal flow remain Milestone 5 unless a minimal lifecycle fact is required to make the Milestone 4 combat slice internally coherent.

### Architectural Direction
- PHP remains the sole combat authority; Phaser consumes playback and never simulates authoritative rolls, targets, damage, statuses, deaths, or outcomes.
- Combat receives a complete authoritative input snapshot and returns deterministic result/playback data. It must not own HTTP, PDO, repositories, player wallets, or parent transactions.
- Authored combat content remains canonical Git JSON exposed through `ContentRegistry`; no SQL gameplay catalogs return.
- Existing prototype combat source is evidence to mine, not an architecture to preserve. `DeterministicRunNodeResolver` is not a vNext application or domain boundary.
- Package 1 established the canonical base level-stat rule as `base + growth_per_level * (level - 1)` for HP, Attack, Defense, Precision, and Resolve. Speed is not introduced.
- During a run, `run_unit_state.current_hp` is authoritative. Package 1 initializes new-run participants at their resolved max HP and enforces concrete non-null run HP in the fresh baseline.
- Package 2 established the canonical first-Farm combat content and the infrastructure-free deterministic vNext combat kernel. Its versioned normalized input and semantic result/playback are the combat contract.
- Package 3 established a single immutable finalized `battles` record per run node containing the exact normalized input, participant identity/presentation manifest, and exact versioned result/playback. Historical playback must never be reconstructed from later Warband/content state.
- Package 4 established the authoritative node-resolution transaction. It serializes through `user_state`, assembles combat from locked run/Warband state, invokes the kernel once, persists battle + terminal player HP + node/run lifecycle + revision + idempotency receipt atomically, and never spends/refunds Energy.
- Package 5 established the retained-battle playback read contract. Playback ownership is battle -> run -> user, remains readable after run failure/abandonment, and is projected only from immutable persisted battle evidence. Active current-run nodes expose only nullable finalized `battle_id` discovery.
- Package 6 established the browser resolution/playback bridge and real persistent `BattleScene`. One logical Fight preserves its idempotency key across ambiguity; Replay never resolves again; a session-scoped identity marker supports reload/terminal-run playback; and Phaser consumes only persisted semantic facts. Battle presentation mirrors front/middle/back formation correctly for opposing sides and uses persisted historical art identity where supported.
- Run loss is a real terminal state. A combat node is resolved once regardless of battle outcome; only victory unlocks direct outgoing nodes. Defeat/stalemate complete the node and terminate the run as `failed`.

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
3. ~~Battle persistence + playback boundary.~~ Complete and architecturally approved at `833bd7dda53ffc1dbe88b085f55e42922280f979`.
4. ~~Authoritative combat-node resolution + persisted run/battle state.~~ Complete and architecturally approved at `8b87ca535c3c9c8dee58516898b28cdc081e4cda`.
5. ~~Battle/result/playback query and reconnect contracts.~~ Complete and architecturally approved at `a447f71c625009c5b54779f7cc28211527553e26`.
6. ~~Phaser `BattleScene` playback lifecycle.~~ Complete and architecturally approved after focused correction at `cb04896d5a01d04694cb15101038c75b90ff24b4` (base implementation `edd7b0a71c99c00d3cad3894679b8418c896cf7a`).
7. **Battle result + authoritative return-to-run reconciliation.** Current.
8. Combat integrated verification/closure.

### Sequencing Notes
- Packages 1-5 establish the complete backend combat authority and immutable read path: canonical stats/content/kernel, atomic node resolution, durable historical battle evidence, and ownership-safe playback reads. Phaser consumes those facts rather than simulating or reconstructing combat.
- Package 4's exact replay is checked after the player lock and before ordinary run/node lifecycle rejection. A different key cannot reroll a completed combat node. Victory unlocks direct persisted graph children only; defeat/stalemate complete the combat node and terminate the run as `failed` without Energy mutation.
- Package 5 fixed the malformed persisted encounter-ID classification identified during Package 4 review: malformed/missing persisted encounter references now fail through the non-disclosing combat integrity boundary without mutation.
- Package 5 playback composition is deliberately content-independent: historical participant identity/presentation comes from the persisted manifest, initial facts from the persisted input, and terminal/event facts from the persisted result. It remains readable after failure/abandonment and does not require an active run.
- For an active run, current-run exposes only nullable finalized `battle_id` on nodes. It does not embed playback or create a server-side pending-playback lifecycle.
- Package 6 owns combat initiation and presentation only. It marks the current-run cache stale after a successful resolution rather than guessing post-combat graph/HP state, and it deliberately retains the presentation marker through playback completion.
- Package 7 must force an authoritative current-run read before leaving the completed battle presentation. That read, through existing GameStore reconciliation, owns the post-battle cache/bootstrap revision and active-run truth. Clear the presentation marker only after successful reconciliation; failures remain retryable without rerunning combat.
- A reconciled active run returns to RunScene. A reconciled null current run returns to Camp. This naturally handles defeat/stalemate, reload after terminal combat, and a newer cross-tab run without inventing client lifecycle state.
- Package 8 is technical closure. Manual UAT follows; do not begin Milestone 5 until it passes.
