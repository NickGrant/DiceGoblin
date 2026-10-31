# Active Execution Issue

## Milestone 12 - Mystic Cave and Onboarding

### Milestone 12 Package 4 - Wrong Machine introduction and onboarding transition

**Status:** In Progress
**Priority:** High

#### Problem
Packages 1-3 established the canonical Mystic Cave onboarding state model, atomic Basic Goblin starting-Warband provisioning, and an executable ordered Whim dialogue/Codex sequence. The player can now progress authoritatively from `required` through `in_progress` to `ready_to_provision`, then provision the valid starting Warband and become `complete`. Package 4 must ensure the Wrong Machine is introduced in its accepted narrative/mechanical role and that the completed onboarding state has an explicit authoritative transition toward the established Camp/Farm core loop, without granting physical Wrong Machine access or implementing its later Swamp theft/restoration slice.

#### Accepted baseline
- Package 1 is approved through correction evidence `3f7ab2ac9450faa4087108111844d67f2cc4123a` (implementation `4d2e32c68fa3accba7c4f4d93467015806bc17c2`, correction `fb6414d12848ae37b3150a8cf9a35dbabffcec24`).
- Package 2 is approved through evidence `ba0eecb88cc1ac1a0925c8dc49ab0ca836914a02` (implementation `7661b012aeb5eb58a99c751d0d1eaf6157e89fae`).
- Package 3 is approved through evidence `2ef9aeca75280b7279c5f4bea4ae6260bcd7932a` (implementation `978db58e030b00f339574778ad8a7ce6072a31a4`).
- `GET /api/v1/onboarding` is authoritative for onboarding state; unit ownership is the durable completion marker.
- The authored Wrong Machine onboarding dialogue teaches its identity and Raw Chaos reconstruction purpose through a conjured image. Knowledge does not grant possession or access.
- `codex.mystic_cave.wrong_machine` is meaningful retained knowledge and remains location-neutral.
- `capability.wrong_machine_access` is a distinct later capability and must not be granted during Mystic Cave onboarding.
- Package 2 provisioning creates the minimum valid Basic Goblin Warband and active squad atomically; it does not grant kin restoration or Wrong Machine access.
- Farm is the established first normal playable region after onboarding. Mystic Cave has no normal run generator.
- Phaser presentation/routing remains Package 5; Package 4 should establish authoritative transition semantics and contracts, not build the final scene/UI.
- Wrong Machine Swamp provenance, physical acquisition/recovery, Frog Kin restoration, and Swamp gameplay remain later progression/Milestone 13.

#### Purpose
Close the server/content transition between the completed Mystic Cave onboarding lifecycle and the existing Camp/Farm core loop while preserving the Wrong Machine boundary. Confirm or implement the minimum authoritative state/projection needed so a later Phaser router can distinguish ongoing onboarding from a player who should enter normal Camp/Farm play. Keep the Wrong Machine introduction knowledge-only: the player may know what it is and why it matters, but does not yet own, access, or operate it.

#### Canonical Package 4 semantics
1. **Knowledge is not access.** The Wrong Machine introduction may teach name, purpose, and future relevance, but Mystic Cave completion must not grant `capability.wrong_machine_access`, physical possession, reconstruction authority, or any equivalent access state.
2. **Preserve Swamp provenance.** Do not place the physical Wrong Machine in Mystic Cave, invent a Mystic-Cave-to-Swamp relocation, or implement the later theft/recovery sequence. The onboarding content remains location-neutral about physical possession while later canonical Swamp provenance is preserved.
3. **Authoritative transition.** Once provisioning commits and onboarding reads `complete`, the backend/runtime contract must provide enough authoritative information for Package 5 to route the player into the established normal Camp/Farm lifecycle without client-local tutorial flags or inferred completion.
4. **No duplicate completion state.** Prefer the already accepted unit-ownership/onboarding read semantics. Do not add a separate `onboarding_complete`, tutorial-history, story flag, or generic progression marker merely for routing.
5. **Normal core-loop eligibility.** A newly completed onboarding player must satisfy the existing authoritative prerequisites needed to enter Camp and start the Farm flow. Reuse the active squad/Warband and existing Farm availability/run-start rules rather than adding onboarding exceptions.
6. **Returning players remain normal.** Existing players already classified `complete` must continue to route to normal gameplay without knowledge backfill or new transition mutations.
7. **Reconnect-safe.** A reload/reconnect after final dialogue, after provisioning, or after completion must derive the same state from durable backend authority. No transient server/session flag may be required to know whether the player belongs in onboarding or Camp.
8. **Safe projection.** Expose only player-safe transition information needed by the later runtime. Do not expose future Swamp location/mechanics, hidden capability requirements, authored reward/event internals, or private onboarding sequence data.
9. **Reuse accepted APIs where sufficient.** If `GET /api/v1/onboarding` plus existing bootstrap already provide all required transition authority, prove that composition rather than creating a redundant transition endpoint.
10. **No premature Phaser work.** Do not implement the Package 5 onboarding scene/router. Browser contract/runtime helpers may be added only where necessary to make the authoritative transition consumable later.

#### Required review/inventory
Inspect the current onboarding read, Package 2 provisioning receipt, game bootstrap, available-region projection, active-squad projection, Farm run-start eligibility, Wrong Machine capability/unlock/reconstruction boundaries, and relevant canonical lore/progression docs. Determine whether any production behavior is actually missing or whether Package 4 can primarily close and verify the accepted transition contract. Prefer verification/documentation over new state when existing authority is sufficient.

#### Minimum proof
1. Before final onboarding knowledge, the player remains in an onboarding state and cannot be mistaken for completed solely by client-local state.
2. After final knowledge but before provisioning, the player is `ready_to_provision`, not normal-complete.
3. Successful Package 2 provisioning changes authoritative onboarding to `complete` and existing bootstrap exposes the valid active starting squad/normal gameplay state needed for Camp/Farm.
4. The newly provisioned player can satisfy existing Farm run-start eligibility without an onboarding-specific bypass.
5. Reload/reconnect after completion yields the same `complete` + normal bootstrap state.
6. Existing returning players remain `complete` and normal-play eligible without onboarding knowledge backfill.
7. Wrong Machine Codex knowledge may be owned while `capability.wrong_machine_access` remains absent.
8. Mystic Cave completion cannot invoke Wrong Machine reconstruction or otherwise obtain physical machine functionality.
9. No Swamp/Frog Kin mechanics or physical Wrong Machine placement are introduced.
10. Browser-safe contracts expose no future/private transition or capability internals.
11. No tutorial/history/completion flag or parallel transition state is added.
12. Existing Camp/Farm/bootstrap/run behavior remains regression-safe.

#### Verification
Add or extend focused integration tests for the final-dialogue -> `ready_to_provision` -> provision -> `complete` lifecycle, bootstrap reconciliation after provisioning, reconnect/read-back, returning-player behavior, Farm run-start eligibility, Wrong Machine knowledge-without-access, and rejection/inaccessibility of reconstruction before the later capability is earned. Add strict browser-contract tests only if a transition projection changes. Run all applicable `agent/QUALITY_GATES.md` checks and full `npm run verify:package -- --continue-on-failure`.

#### Scope exclusions
- Phaser Mystic Cave/onboarding scene or routing implementation;
- physical Wrong Machine possession/access/reconstruction during onboarding;
- Wrong Machine theft/recovery or relocation narrative;
- Swamp/Frog Kin implementation;
- new tutorial/history/story/completion flags;
- generic progression state;
- broad visual redesign;
- Milestone 13+ work.

#### Completion evidence required
Record implementation SHA; inventory/conclusion on whether existing onboarding + bootstrap contracts already provide the transition authority; any narrow content/API/runtime changes; end-to-end final knowledge -> provisioning -> complete/bootstrap/Farm evidence; reconnect and returning-player evidence; explicit proof Wrong Machine knowledge does not grant access/reconstruction; confirmation Swamp provenance remains deferred; focused tests; full package gates; and confirmation no duplicate completion/history state or Package 5/M13 work was introduced.

Leave Package 4 **In Progress** for architectural review. Do not promote Package 5 automatically from the coding agent.

#### Package 4 implementation evidence (`1f1be8cffa6492960befb9c680294e464175da49`)

- No production transition endpoint, persisted completion flag, or runtime projection was needed. `GET /api/v1/onboarding` derives `required`, `in_progress`, and `ready_to_provision` from ordered Codex ownership until unit ownership makes it `complete`. Existing bootstrap supplies the active squad, Farm availability, revision, and normal game state; a fresh read after provisioning returns the same authority for reconnect and routing.
- A focused integration test executes both authored Whim dialogues, confirms final knowledge yields `ready_to_provision` with no squad, provisions the starting Warband, and proves the resulting active squad passes the existing Farm run-start rule. It also proves a unit owner with no onboarding Codex knowledge remains `complete` and receives normal bootstrap state without backfill.
- Wrong Machine knowledge and provisioning grant no unlocks. Reconstruction attempts before and after provisioning return `reconstruction_unavailable`; the tested Farm run remains available through normal eligibility. No physical machine placement, Swamp/Frog Kin progression, Package 5 scene/router, or Milestone 13 behavior was added. Existing onboarding/browser contracts remain narrow, with no future step or private transition projection.
- Focused test: 2 tests / 42 assertions. Full `npm run verify:package -- --continue-on-failure` passed: 710 backend tests / 5,781 assertions, 452 frontend tests, content validation, PHP syntax, production frontend build, bundle, LLM/context, docs lint, and diff whitespace. Package 4 remains **In Progress** for architectural review.
