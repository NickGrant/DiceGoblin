# Active Milestone

Read this for sequencing/planning or when closing/promoting an execution package. Normal implementation should use `agent/ISSUES.md` instead.

## Milestone 12 - Mystic Cave and Onboarding

**Status:** Active

### Related Issues
- Milestone 12 Package 3 - Mystic Cave interaction/dialogue sequence

Milestones 1-11 are complete and passed their required manual UAT.

Milestone 10 closed after Package 10 manual UAT acceptance through `d97bcb52c4b834cf72a68d827eac8e196c05adbb`.

Milestone 11 closed after Package 9 focused manual UAT acceptance on 2026-10-28. The accepted Milestone 11 baseline includes Codex ownership/read/presentation, knowledge-aware replayable dialogue, objective/bounty authored state and authoritative gameplay-fact progress, automatic completion/reward finalization, Phaser Objectives presentation, the Farm Scout and Mudking production dialogue/Codex paths added during UAT, production Farm objectives, dotted-dialogue local routing correction, and the end-of-milestone retirement of obsolete prototype code. Final cleanup verification passed at `03df62b39715e835d31d2642441b0be4186a94f8` with 689 backend tests / 5,605 assertions and 446 frontend tests. The user explicitly accepted renewed Milestone 11 UAT on 2026-10-28.

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
3. **Mystic Cave interaction/dialogue sequence.** Current. Implement the authored Whim-led onboarding interactions and knowledge/dialogue consequences through existing dialogue/Codex contracts rather than tutorial-specific history.
4. **Wrong Machine introduction and onboarding transition.** Planned. Introduce the Wrong Machine in the accepted narrative/mechanical role and transition the player toward Camp/Farm without implementing the later Swamp theft/restoration slice.
5. **Phaser onboarding presentation and fresh-player routing.** Planned. Mount the responsive Mystic Cave/onboarding flow in the persistent Phaser runtime and reconcile fresh/returning player routing from authoritative state.
6. **Mystic Cave/onboarding integrated verification and technical closure.** Planned. Verify fresh-player bootstrap, replay/reconnect/idempotency, secrecy, cross-player isolation, transition into the established core loop, and regression safety.
7. **Focused manual UAT before Milestone 13.** Planned. Manually verify the complete fresh-player onboarding path before activating Swamp/parity work.

### Sequencing Notes

- Package 1 established the accepted onboarding state model: ordered meaningful Codex knowledge for in-progress state, `ready_to_provision` when all onboarding knowledge is owned but no unit exists, and any owned unit as the durable completion marker for the current vNext lifecycle. Mystic Cave has no normal run generator; Basic Goblin establishment is not kin restoration; Wrong Machine onboarding is knowledge-only and location-neutral, with later Swamp provenance/access preserved.
- Package 2 established the atomic starting Warband transition: one authored Basic Goblin Bruiser with a loaded basic melee attack and compatible d6 in an active one-unit squad. The authenticated, CSRF-protected, idempotent mutation commits unit/die/loadout/squad/revision/receipt together; exact-key replay is stable, distinct-key retry after completion cannot duplicate assets, and rollback leaves the player recoverably `ready_to_provision`.
- Package 3 should reuse the Milestone 11 dialogue/Codex primitives for meaningful Whim interactions and make the Package 1 authored sequence executable through authoritative interaction semantics without adding tutorial history.
- Package 4 owns the Wrong Machine introduction only; the machine's Swamp theft/restoration and Frog Kin consequences remain Milestone 13.
- Package 5 owns player-facing routing/presentation and must not infer authoritative onboarding completion locally.
- Package 6 is the technical closure gate before manual UAT.
- Package 7 is the explicit manual UAT gate. Do not begin Milestone 13 or mark Milestone 12 complete until the user explicitly accepts UAT.
