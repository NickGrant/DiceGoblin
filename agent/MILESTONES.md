# Active Milestone

Read this for sequencing/planning or when closing/promoting an execution package. Normal implementation should use `agent/ISSUES.md` instead.

## Milestone 4 - Combat

**Status:** Technical closure complete; manual UAT pending

### Related Issues
- Milestone 4 manual combat UAT

Milestone 1 - Walking Skeleton is complete and passed manual user UAT.

Milestone 2 - Warband is complete and passed manual user UAT on 2026-09-13.

Milestone 3 - Enter Farm is complete and passed manual user UAT on 2026-09-15. Integrated technical closure was approved at `c456d983b1afaf36c0dc9e069b3e35cfcdf6957f`; the focused UAT correction package was approved at `038a6ce081fa3b735f627095db372db24b39f79e`.

The major game-wide visual/UI overhaul remains intentionally deferred. Milestone 4 makes combat authoritative, deterministic, persistent, reconnect-safe, and watchable before final presentation fidelity.

### Outcome
Allow a persisted Farm combat node to resolve through authoritative PHP combat, preserve resulting run HP and battle history, and replay the persisted battle in Phaser without client-side simulation:

`RunScene combat node -> authoritative resolve -> persisted battle/result/playback + run HP -> BattleScene playback -> result -> authoritative reconciliation -> RunScene/Camp`

Rewards, XP/progression grants, boss/run completion, Mountains unlock, and the complete Farm terminal flow remain Milestone 5.

### Architectural Direction
- PHP remains the sole combat authority; Phaser consumes playback and never simulates authoritative rolls, targets, damage, statuses, deaths, or outcomes.
- Combat receives a complete authoritative input snapshot and returns deterministic result/playback data. It does not own HTTP, PDO, repositories, player wallets, or parent transactions.
- Authored combat content remains canonical Git JSON exposed through `ContentRegistry`; no SQL gameplay catalogs return.
- Existing prototype combat source is behavioral evidence only. `DeterministicRunNodeResolver` is not a vNext application or domain boundary.
- Package 1 established the canonical base level-stat rule as `base + growth_per_level * (level - 1)` for HP, Attack, Defense, Precision, and Resolve. Speed is not introduced.
- During a run, `run_unit_state.current_hp` is authoritative. New-run participants begin at resolved max HP.
- Package 2 established canonical first-Farm combat content and the infrastructure-free deterministic combat kernel.
- Package 3 established one immutable finalized `battles` record per run node containing exact normalized input, historical participant manifest, and exact versioned result/playback.
- Package 4 established the atomic/idempotent authoritative combat-node transaction and persists terminal run HP without Energy mutation.
- Package 5 established ownership-safe historical playback reads that remain readable after run failure/abandonment.
- Package 6 established the browser Fight/Replay/playback bridge and persistent `BattleScene`; Phaser consumes only persisted semantic facts.
- Package 7 established explicit results and authoritative Continue reconciliation through a fresh current-run read before navigation.
- Run loss is a real terminal state. Only victory unlocks direct outgoing nodes; defeat/stalemate complete Combat and terminate the run as `failed`.

### Exit Criteria
- Unit combat stats resolve deterministically from canonical authored type data and unit level without prototype SQL catalogs or Speed.
- New run participants begin with authoritative max HP; run-start retry behavior remains correct.
- Farm combat content is canonical authored JSON and semantically validated with deliberate exposure rules.
- The combat engine is deterministic and infrastructure-free and produces authoritative versioned playback.
- Targeting, scheduling, dice/aspects/passives, damage/status behavior, death, victory/defeat/stalemate have deterministic coverage.
- Battle persistence contains only storage required by this slice; no reward/event-sourcing/claim schema is added.
- Combat-node resolution is authenticated, owned, idempotent, atomic, and cannot reroll by retry/reload.
- Historical playback enforces ownership/non-disclosure and does not reconstruct from mutable Warband/content state.
- `BattleScene` replays server-produced facts without client combat simulation.
- Returning from battle reconciles GameStore/current-run from authoritative persisted state.
- M1-M3 regressions and responsive/touch behavior remain usable.
- Integrated technical closure passed architectural review at `16288bff6223cdddee56b5cbf359e607c07dc81e` after the required supported closure gates passed.
- **Remaining milestone gate:** manual user UAT must pass before Milestone 5 is promoted.

### Package Queue
1. ~~Canonical combatant stats + authoritative run HP initialization.~~ Approved at `dcb78d61672b5b4e739d16c3757f8d7bbc817d07`.
2. ~~Farm combat authored content + deterministic engine adaptation.~~ Approved at `2c1d9e9f4863da95a2cb0021d4b16d066fb5b7a7`.
3. ~~Battle persistence + playback boundary.~~ Approved at `833bd7dda53ffc1dbe88b085f55e42922280f979`.
4. ~~Authoritative combat-node resolution + persisted run/battle state.~~ Approved at `8b87ca535c3c9c8dee58516898b28cdc081e4cda`.
5. ~~Battle/result/playback query and reconnect contracts.~~ Approved at `a447f71c625009c5b54779f7cc28211527553e26`.
6. ~~Phaser `BattleScene` playback lifecycle.~~ Approved after focused correction at `cb04896d5a01d04694cb15101038c75b90ff24b4`.
7. ~~Battle result + authoritative return-to-run reconciliation.~~ Approved at `45f68eed6a7b6527568d61856e299019091264e5`.
8. ~~Combat integrated verification/closure.~~ Approved at `16288bff6223cdddee56b5cbf359e607c07dc81e`.

### Technical Closure Evidence
Package 8 reported:
- fresh MySQL 8.4.8 baseline reset passed with 19 expected runtime tables and the accepted battle/run-HP constraints;
- deterministic engine tests: 23 tests / 96 assertions;
- focused combat-resolution/playback/current-run MySQL tests: 20 tests / 264 assertions;
- backend Docker suite: 481 tests / 1,931 assertions / 143 fixture-or-environment-gated skips, exit 0;
- focused frontend combat lifecycle: 38 tests;
- full frontend suite: 434 tests;
- production frontend build and bundle check passed; largest bundle 339.09 KiB;
- content validation passed twice with stable revision `fd4b4a0c72970a44fcac55ce2540a0171f6fca7ad6508c3c3f08699e2f436d19`;
- `llm:check`, `docs:lint`, and `git diff --check` passed;
- real-stack victory flow passed with one bodyless resolve POST, two playback GETs across reload, one Continue current-run GET, matched persisted identity, marker clearing, and RunScene destinations before/after final reload;
- deterministic captures passed for available Combat, early/mid playback, victory, defeat, stalemate, Compact, Wide, and portrait gating.

`verify:full` did not complete because host PHP is unavailable on `PATH`; the corresponding supported Docker content/backend gates and frontend constituents passed. No GitHub workflow/status was attached to the closure commit. This is an environment limitation, not a reported gate pass.

### Sequencing Notes
- Technical implementation is closed pending manual UAT. Do not add more Milestone 4 implementation unless UAT identifies a concrete defect.
- Manual UAT should concentrate on player-visible Fight, playback, reload, Replay, result, Continue, responsive/orientation, and return-to-run behavior rather than repeating automated internal checks.
- Final visual/art/animation polish remains deferred and is not by itself a Milestone 4 UAT blocker unless it makes gameplay unclear or unusable.
- Milestone 5 must not begin until the focused manual UAT passes and this milestone is formally closed.
