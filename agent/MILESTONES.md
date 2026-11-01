# Active Milestone

Read this for sequencing/planning or when closing/promoting an execution package. Normal implementation should use `agent/ISSUES.md` instead.

## Milestone 12 - Mystic Cave and Onboarding

**Status:** Complete

The user accepted Package 7 UAT as good enough on 2026-10-31. Package review/promotion remains pending; Milestone 13 has not been activated.

### Related Issues
- Milestone 12 Package 7 - Focused manual UAT before Milestone 13

Milestones 1-11 are complete and passed their required manual UAT.

Milestone 10 closed after Package 10 manual UAT acceptance through `d97bcb52c4b834cf72a68d827eac8e196c05adbb`.

Milestone 11 closed after Package 9 focused manual UAT acceptance on 2026-10-31. The accepted Milestone 11 baseline includes Codex ownership/read/presentation, knowledge-aware replayable dialogue, objective/bounty authored state and authoritative gameplay-fact progress, automatic completion/reward finalization, Phaser Objectives presentation, the Farm Scout and Mudking production dialogue/Codex paths added during UAT, production Farm objectives, dotted-dialogue local routing correction, and the end-of-milestone retirement of obsolete prototype code. Final cleanup verification passed at `03df62b39715e835d31d2642441b0be4186a94f8` with 689 backend tests / 5,605 assertions and 446 frontend tests. The user explicitly accepted renewed Milestone 11 UAT on 2026-10-31.

The major game-wide visual/UI overhaul remains intentionally deferred.

### Outcome

Establish the fresh-player Mystic Cave onboarding slice and introduce The Whim and the Wrong Machine through normal gameplay, while reusing the accepted vNext architecture rather than creating a parallel tutorial system:
- a canonical authored onboarding flow for a fresh player;
- Mystic Cave as the special onboarding biome associated with The Whim and Basic Goblin establishment;
- player-safe, reconnect-safe presentation through the existing Phaser/runtime/API boundaries;
- provisioning/unlock consequences expressed through existing authoritative reward/unlock/player-state systems where semantically valid;
- Wrong Machine introduction without moving its later Swamp theft/restoration gameplay into this milestone;
- explicit transition from onboarding into the established Camp/Farm core loop.

Swamp/Frog Kin and third-region parity remain Milestone 13. Hardening/cutover remains Milestone 14.

### Architectural Direction

- Follow `documentation/07-development-path/vnext-game-overhaul.md` for milestone order and `agent/CONTEXT_ROUTER.md` for narrow canonical authorities.
- Mystic Cave is a special biome: Chaos beings/The Whim establish Basic Goblin rather than an enemy-derived kin.
- Reuse accepted authored-content, dialogue/Codex, reward/unlock, API, storage, client-runtime, responsive, revision, and idempotency boundaries where semantically valid.
- Do not introduce a generic tutorial-history, story-flag, `has_seen_dialogue`, generic progression, or catch-all state layer. Persist only durable state required by the actual onboarding mechanics.
- Backend/MySQL remains authoritative for durable player state, eligibility, provisioning, unlocks/rewards, revision, and idempotency.
- Phaser remains the gameplay client; Angular remains the host/auth/account shell.
- Authored definitions belong in canonical content JSON rather than SQL or client constants.
- Prototype source/tests remain migration evidence only after the Milestone 11 cleanup; do not restore retired prototype architecture to implement onboarding.
- Wrong Machine introduction in Milestone 12 must not contradict its later Swamp provenance/restoration path. Do not implement Swamp/Frog Kin expansion early.
- Final visual polish remains deferred; onboarding must nevertheless be responsive, legible, and usable.

### Package Queue

1. **Canonical onboarding flow and authored-content foundation.** Approved through correction evidence `3f7ab2ac9450faa4087108111844d67f2cc4123a` (implementation `4d2e32c68fa3accba7c4f4d93467015806bc17c2`, canonical-content correction `fb6414d12848ae37b3150a8cf9a35dbabffcec24`).
2. **Authoritative fresh-player onboarding/provisioning transaction.** Approved through evidence `ba0eecb88cc1ac1a0925c8dc49ab0ca836914a02` (implementation `7661b012aeb5eb58a99c751d0d1eaf6157e89fae`).
3. **Mystic Cave interaction/dialogue sequence.** Approved through evidence `2ef9aeca75280b7279c5f4bea4ae6260bcd7932a` (implementation `978db58e030b00f339574778ad8a7ce6072a31a4`).
4. **Wrong Machine introduction and onboarding transition.** Approved through evidence `045c5446b7bb6c016c530461e040ab38177706f0` (implementation `1f1be8cffa6492960befb9c680294e464175da49`).
5. **Phaser onboarding presentation and fresh-player routing.** Approved through evidence `fc1c5cff8888a0e5a8b3b717077958dec061f2d0` (implementation `11b27b5cd646733b498b8f9f6f84e9fbe052ef5d`).
6. **Mystic Cave/onboarding integrated verification and technical closure.** Approved through evidence `7c8f2e66df222d1035bba0ae33eb5cbb44220b94` (technical-closure implementation `3f148166a247df80e8839ac48d4b71488f6610de`).
7. **Focused manual UAT before Milestone 13.** User accepted UAT as good enough on 2026-10-31 after correction of the four-unit starting formation and generated unit names. Package review/promotion remains pending; Milestone 13 has not been activated.

### Sequencing Notes

- Package 1 established the accepted onboarding state model: ordered meaningful Codex knowledge for in-progress state, `ready_to_provision` when all onboarding knowledge is owned but no unit exists, and any owned unit as the durable completion marker for the current vNext lifecycle. Mystic Cave has no normal run generator; Basic Goblin establishment is not kin restoration; Wrong Machine onboarding is knowledge-only and location-neutral, with later Swamp provenance/access preserved.
- Package 2 established the atomic starting Warband transition: one authored Basic Goblin Bruiser with a loaded basic melee attack and compatible d6 in an active one-unit squad. The authenticated, CSRF-protected, idempotent mutation commits unit/die/loadout/squad/revision/receipt together; exact-key replay is stable, distinct-key retry after completion cannot duplicate assets, and rollback leaves the player recoverably `ready_to_provision`.
- Package 3 established server-side onboarding dialogue eligibility through the existing dialogue/Codex pipeline. Only the current authored Whim conversation is readable/completable; future and past onboarding dialogues fail closed under new keys, exact-key replay remains stable after advancement, and the final knowledge step ends at `ready_to_provision` without provisioning or Wrong Machine access.
- Package 4 proved the accepted onboarding + bootstrap contracts already provide the complete transition authority: final knowledge yields `ready_to_provision`; provisioning yields `complete`, an active starting squad, and normal Farm run eligibility; reconnect and returning players derive the same state without backfill. Wrong Machine knowledge remains separate from access/reconstruction, and no additional transition endpoint or completion state was needed.
- Package 5 mounted the authoritative onboarding lifecycle in the persistent Phaser runtime. Boot routing now follows server onboarding state; dialogue and provisioning remain separate authoritative mutations with retained idempotency keys and fresh reconciliation; stale responses fail closed; completed/returning players enter the existing Camp/Run/Battle lifecycle; responsive onboarding presentation is verified across Compact, Standard, Wide, and portrait-blocked viewports without client-local tutorial state.
- Package 6 closed technical verification without finding a production architecture defect. The integrated backend lifecycle now begins through real local registration/session/CSRF, proves `required` -> `in_progress` -> `ready_to_provision` -> `complete`, validates the resulting Basic Goblin squad and normal Farm start, and reconfirms secrecy, Wrong Machine separation, retry/reload/stale-response/cross-player coverage, and responsive presentation.
- Package 7 is the explicit manual UAT gate. Do not begin Milestone 13 or mark Milestone 12 complete until the user explicitly accepts UAT.
