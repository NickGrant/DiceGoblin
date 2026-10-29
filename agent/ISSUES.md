# Active Execution Issue

## Milestone 12 - Mystic Cave and Onboarding

### Milestone 12 Package 2 - Authoritative fresh-player onboarding/provisioning transaction

**Status:** In Progress
**Priority:** High

#### Problem
Package 1 established the canonical Mystic Cave onboarding state model and authored foundation. A fresh player owns no units; meaningful Codex knowledge tracks ordered onboarding progress; all onboarding knowledge with no unit produces `ready_to_provision`; and owning any unit is the durable completion marker for the current vNext lifecycle. Package 2 must now implement the one server-authoritative provisioning mutation that turns the accepted ready state into a valid Basic Goblin starting roster without introducing duplicate bootstrap/progression authority or allowing retries to duplicate durable assets.

#### Accepted baseline
- Package 1 is approved through correction evidence `3f7ab2ac9450faa4087108111844d67f2cc4123a` (implementation `4d2e32c68fa3accba7c4f4d93467015806bc17c2`, correction `fb6414d12848ae37b3150a8cf9a35dbabffcec24`).
- `GET /api/v1/onboarding` is the authoritative read contract for `required`, `in_progress`, `ready_to_provision`, and `complete`.
- The current step is derived from ordered meaningful Codex knowledge; out-of-order knowledge fails closed.
- Existing returning players with units are complete without onboarding knowledge backfill.
- Any owned unit is the accepted durable onboarding-completion marker for the current vNext unit lifecycle.
- Starting provisioning must be atomic so the completion marker cannot become visible before the required starting roster/equipment/squad state is valid.
- Mystic Cave establishes Basic Goblin via `kin.goblin`; this is not enemy-derived Kin restoration and must not grant a kin-restoration unlock merely to mark onboarding.
- Wrong Machine onboarding is knowledge-only and location-neutral. Wrong Machine access/recovery and Swamp/Frog Kin mechanics remain later progression.
- Backend/MySQL owns durable state, revision, authorization, transactions, and idempotency. Phaser/Angular do not provision assets locally.
- Existing shared unit/die/squad/reward/unlock repositories/services should be reused where their semantics fit; do not revive retired prototype provisioning code.

#### Purpose
Implement the narrow idempotent mutation that provisions the accepted Basic Goblin starting state for an authenticated player who is authoritatively `ready_to_provision`, then returns enough authoritative state for later client routing/reconciliation. This package owns server semantics and browser contract only; it does not build the Phaser onboarding presentation.

#### Required inventory before implementation
Inspect the current canonical source for shared unit creation, die creation/ownership, squad creation/configuration, default equipment/loadout requirements, player revision updates, idempotency receipt conventions, authorization/CSRF conventions, transaction ownership, bootstrap projection, and mutation-response reconciliation. Determine the minimum valid starting roster required by existing Warband/Farm invariants rather than inventing a parallel onboarding-only asset shape. Record the chosen roster and why it is the minimum valid core-loop state.

#### Canonical Package 2 semantics
1. **Eligibility is authoritative.** Provisioning is allowed only when the authenticated player's current onboarding state is `ready_to_provision`. `required`, `in_progress`, and already `complete` must not create assets through a new request.
2. **One atomic transition.** All durable assets required for the accepted starting state must be created inside one owning transaction. No unit may become visible as the completion marker unless the full required roster/equipment/squad state can commit.
3. **Idempotent mutation.** Follow the existing mutation idempotency contract. Exact-key replay must return the stored authoritative result and must not create another unit, die, squad, reward, unlock, or revision increment. A distinct later request after successful provisioning must observe `complete` and must not provision again.
4. **Basic Goblin, not Kin restoration.** Provision only the canonical Basic Goblin starting identity/roster. Do not award Pig/Lizard/Frog-style kin restoration state or route through Wrong Machine reconstruction semantics.
5. **Reuse shared asset creation.** Use existing authoritative unit/die/squad creation/configuration boundaries where semantically valid. If a small shared service extraction is required to avoid duplicating creation invariants, keep it narrow and regression-test existing callers.
6. **Valid core-loop state.** On successful commit, the player must have the minimum valid Warband state required for later Camp/Farm play: canonical starting unit(s), required die/equipment/loadout, and any squad membership/configuration required by current run-start invariants. Do not provision unrelated economy/progression assets unless a current canonical invariant requires them.
7. **Revision/reconciliation.** Successful provisioning advances player revision according to existing mutation semantics exactly once. The response should expose only authoritative player-safe data needed to reconcile the transition; do not leak authored onboarding sequencing, private reward definitions, or server-only IDs/config.
8. **Failure rollback.** Any failure while creating/configuring the starting state must roll back every provisioning write, idempotency receipt, and revision consequence so the player remains recoverably `ready_to_provision`.
9. **Cross-player isolation.** All reads/writes and any supplied asset IDs must remain scoped to the authenticated user. Prefer no client-supplied asset identities when the server can derive canonical starting content itself.
10. **No duplicate authority.** Do not alter registration to provision gameplay assets, create a second bootstrap path, add tutorial flags/history, or create a client-authored completion mechanism.

#### API shape
Choose the narrow endpoint naming/body consistent with existing vNext mutation conventions. The client should not choose the starting kin, unit type, dice, squad, rewards, or onboarding step. Those are server/canonical-content decisions. Require normal authenticated mutation protections and idempotency key. Return an authoritative receipt/result suitable for later GameStore/runtime integration, including the accepted player revision and enough state or invalidation information to refresh affected collections.

#### Minimum proof
1. A `ready_to_provision` fresh player can provision successfully once.
2. A player still `required` or `in_progress` cannot provision.
3. An already-complete/returning player cannot receive a second starting roster through a new request.
4. Exact-key replay returns the same result without duplicate assets or revision changes.
5. Distinct-key retry after success cannot duplicate assets.
6. Provisioning creates the complete minimum valid Basic Goblin Warband state atomically.
7. Any mid-transaction failure leaves no partial unit/die/squad state and leaves onboarding recoverably `ready_to_provision`.
8. Successful provisioning makes the existing onboarding read return `complete` through durable unit ownership, without a tutorial/completion flag.
9. Basic Goblin provisioning does not grant enemy-derived Kin restoration or Wrong Machine access.
10. Cross-player attempts cannot inspect or mutate another player's provisioning/assets.
11. Browser response/projection contains no private authored onboarding/reward/configuration internals.
12. Existing Warband, run-start, bootstrap, reward/unlock, and returning-player behavior remains intact.

#### Verification
Add focused backend integration tests for eligibility, success, exact-key replay, distinct-key duplicate prevention, rollback, revision behavior, cross-player isolation, resulting onboarding read state, and resulting Warband/run-start invariants. Add strict frontend contract/API tests for the mutation response if a browser client contract is introduced in this package. Run regression tests for every shared creation service/repository touched. Run all applicable `agent/QUALITY_GATES.md` checks and full `npm run verify:package -- --continue-on-failure`.

#### Scope exclusions
- Phaser Mystic Cave/onboarding UI or routing;
- implementing the full Whim interaction sequence beyond what already exists in authored/read foundations;
- changing the Package 1 Codex-based onboarding progression model;
- Wrong Machine physical access, reconstruction, theft/recovery, or Swamp placement mechanics;
- Pig/Lizard/Frog Kin restoration;
- generic tutorial/history/story flags or catch-all progression;
- broad economy/progression grants not required for a valid starting Warband;
- Milestone 13+ work;
- broad visual redesign.

#### Completion evidence required
Record implementation SHA; inventory of reused unit/die/squad/idempotency/revision boundaries; exact minimum starting roster and why it satisfies current core-loop invariants; endpoint/transaction/idempotency semantics; successful and failed transition behavior; revision/reconciliation contract; proof exact-key and distinct-key retries cannot duplicate assets; rollback and cross-player evidence; resulting `GET /api/v1/onboarding` completion behavior; confirmation that no kin-restoration/Wrong-Machine access/tutorial flag/registration provisioning/Phaser UI/M13 work was introduced; focused tests; and full package-gate results.

Leave Package 2 **In Progress** for architectural review. Do not promote Package 3 automatically from the coding agent.
