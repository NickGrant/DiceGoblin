# Active Execution Issue

## Milestone 12 - Mystic Cave and Onboarding

### Milestone 12 Package 1 - Canonical onboarding flow and authored-content foundation

**Status:** In Progress
**Priority:** High

#### Problem
Milestone 11 is complete and accepted. vNext now needs a canonical fresh-player onboarding foundation for the Mystic Cave before implementation proceeds into provisioning, Whim interactions, Wrong Machine introduction, and Phaser routing. The repository has mature vNext bootstrap, authored-content, dialogue/Codex, reward/unlock, run, Camp, and client-runtime boundaries; Package 1 must determine how onboarding composes those accepted systems rather than inventing a parallel tutorial architecture or reviving retired prototype code.

#### Accepted baseline
- Milestones 1-11 are complete and passed required manual UAT.
- Milestone 11 Package 9 was explicitly accepted by the user on 2026-10-28 after the UAT corrections and end-of-milestone cleanup.
- Final Milestone 11 cleanup verification passed at `03df62b39715e835d31d2642441b0be4186a94f8`: 689 backend tests / 5,605 assertions and 446 frontend tests, plus content validation, front-controller syntax, LLM/context, docs lint, production frontend build, bundle budget, and diff whitespace.
- The obsolete prototype controllers/services/repositories/migrations/gameplay UI and prototype-only tests removed during Milestone 11 remain retired. Do not restore them as onboarding dependencies.
- Backend/MySQL is authoritative for durable player state, eligibility, rewards/unlocks, revision, and idempotency.
- Phaser is the gameplay client; Angular hosts auth/account and `/game`.
- Canonical authored content lives in JSON and is projected to the browser only through explicit player-safe contracts.
- Milestone 11 established reusable replayable dialogue/Codex knowledge and objective systems without generic story/history flags.
- Mystic Cave is a special biome associated with Chaos beings/The Whim and establishes Basic Goblin rather than an enemy-derived kin.
- Wrong Machine is canonically associated with the Swamp for its later theft/restoration gameplay. Milestone 12 may introduce it but must not move the Milestone 13 Swamp/Frog Kin slice forward.
- The major game-wide visual/UI overhaul remains deferred.

#### Purpose
Define and implement the minimum canonical authored-content and durable-state foundation required for the fresh-player Mystic Cave onboarding flow. This package should leave later packages with explicit authored identities, validated references, eligibility/transition semantics, and a clear reuse plan for existing vNext systems. It should not attempt to implement the complete onboarding experience or Phaser presentation.

#### Required inventory before implementation
Inspect the current branch and canonical docs for registration/bootstrap and first authenticated player-state creation; current Camp boot/routing and region/run eligibility; initial units/dice/squad/player-state provisioning; authored region/run/encounter/dialogue/Codex/reward/unlock models and browser-safe projection; Milestone 11 dialogue/Codex contracts; accepted idempotency/revision/cache behavior; current Wrong Machine and Mystic Cave references; relevant lore/product terminology; and responsive/client architecture constraints.

Treat prototype remnants/history only as migration evidence and not authority. Record the inventory and resulting design decisions in package evidence. If a durable product/architecture decision is not already represented in canonical documentation, update the appropriate repository document rather than leaving it only in implementation comments or chat history.

#### Canonical Package 1 semantics
1. **Fresh-player eligibility must be authoritative and minimal.** Determine onboarding eligibility from durable state that has actual gameplay meaning. Do not add `has_seen_tutorial`, `has_seen_dialogue`, generic story flags, generic historical events, or a catch-all progression table merely to route the client.
2. **Persist results, not narrative history.** If onboarding creates/unlocks/provisions something durable, that durable result may serve as the authoritative transition marker when unambiguous. Introduce narrow onboarding-owned state only if existing durable results cannot safely distinguish required states such as not-started vs in-progress vs completed/recoverable.
3. **Mystic Cave is authored content.** Establish stable IDs and validated references for the minimum Mystic Cave/Whim onboarding content needed by subsequent packages. Do not hard-code the sequence in Phaser or controller branches.
4. **Basic Goblin establishment is not Kin restoration.** Mystic Cave establishes the player's Basic Goblin starting capability/roster through appropriate existing provisioning/reward/unit boundaries; do not model it as Pig/Lizard/Frog Kin restoration.
5. **Dialogue should reuse Milestone 11 contracts where semantically valid.** Whim conversations should be authored/replayable interactions and may grant meaningful Codex knowledge if product design calls for it. Do not create tutorial-specific dialogue history.
6. **Wrong Machine introduction must preserve later provenance.** Package 1 may author the identity/reference needed for its onboarding appearance, but must not implement later Swamp theft/restoration mechanics, Frog Kin restoration, or production Swamp expansion.
7. **Transition into the established core loop must be explicit.** Define what authoritative durable condition makes onboarding complete and allows normal Camp/Farm flow. The client must eventually read this state rather than infer completion from local scene progression.
8. **Safe projection.** Server-only onboarding sequencing, reward definitions, eligibility internals, dialogue responses/prerequisites, or other private authored data must not leak through global browser content projection. Add only allowlisted presentation fields actually required by a later client surface.
9. **No duplicate authority.** Do not create a second registration/bootstrap system, reward pipeline, unlock system, run engine, dialogue engine, or client state store for onboarding.

#### Implementation scope
Package 1 should include only the foundation necessary to make subsequent onboarding packages concrete. Expected work may include canonical Mystic Cave/Whim/Wrong Machine authored definitions or narrow extensions to existing authored schemas; validation/reference rules; a narrow read/eligibility contract if required; storage only if inventory proves dedicated in-progress state is necessary; canonical documentation updates; and focused backend/browser contract tests.

Do not implement the complete provisioning mutation, complete dialogue sequence, complete Wrong Machine transition, or Phaser onboarding UI in Package 1 unless an extremely small piece is inseparable from establishing a valid foundation. Those belong to Packages 2-5.

#### Minimum proof
1. A brand-new player can be classified authoritatively as requiring onboarding without client-local inference.
2. A player whose accepted durable state means onboarding is complete is classified as normal Camp/core-loop eligible.
3. Any required intermediate/recoverable state is represented unambiguously if the design needs one.
4. Mystic Cave/Whim onboarding authored definitions validate and use stable typed references.
5. Invalid/missing references fail closed.
6. Basic Goblin onboarding semantics are not represented as enemy-derived Kin restoration.
7. Wrong Machine onboarding references do not introduce Swamp/Frog Kin implementation early.
8. Browser-safe projection/read contracts expose only required presentation/state and do not leak private authored sequencing/reward/dialogue internals.
9. Existing returning-player Camp/Farm behavior remains unchanged.
10. No generic tutorial/history/story-flag architecture or resurrected prototype dependency is introduced.

#### Verification
Run focused tests covering the authored model, eligibility/read behavior, corruption/fail-closed paths, projection secrecy, and regression of existing bootstrap/Camp behavior. Run all applicable `agent/QUALITY_GATES.md` checks and the complete `npm run verify:package` gate before presenting Package 1 for review.

#### Scope exclusions
- complete onboarding provisioning transaction;
- full Whim dialogue/interaction implementation;
- full Wrong Machine onboarding transition;
- Phaser Mystic Cave/onboarding presentation;
- generic tutorial/history/story flags;
- manual objective/progression claims;
- Swamp/Frog Kin implementation or Wrong Machine theft/restoration mechanics;
- production Shrine/Chaos expansion beyond what onboarding concretely requires;
- broad visual redesign;
- Milestone 13+ work.

#### Completion evidence required
Record implementation SHA; inventory of current fresh-player bootstrap/provisioning/routing behavior; chosen authoritative onboarding eligibility/completion state and why it is the narrowest correct model; authored IDs/schema/reference changes; Basic Goblin vs Kin semantics; confirmation that Wrong Machine Swamp provenance remains deferred; safe projection/read behavior; focused tests; full package gates; and confirmation that no generic tutorial/history/story flags, parallel reward/bootstrap/state systems, resurrected prototype dependencies, Phaser onboarding UI, or Milestone 13 work were introduced.

Leave Package 1 **In Progress** for architectural review. Do not promote Package 2 automatically from the coding agent.

#### Package 1 implementation evidence (review candidate `4d2e32c68fa3accba7c4f4d93467015806bc17c2`)

- Inventory: local registration and new external identities atomically create `users` plus `user_state` with initial Energy; reads never provision. Fresh accounts have no units, dice, or squad. The existing bootstrap returns account, wallet/Energy/revision, unlocks, available regions, active squad, and active run. Farm is the authored starting playable region, but a run requires an active squad and participating units. Phaser currently enters Run/Camp from bootstrap; Package 1 does not alter that routing. Authored content is validated JSON, and the global browser projection includes only playable regions and other explicit allowlists. Dialogue completion already owns idempotent Codex rewards; Wrong Machine access and reconstruction remain separate unlock/command boundaries.
- Decision: `onboarding.mystic_cave` is a narrow authored ordered flow. Its steps refer to Mystic Cave dialogue encounters and meaningful Codex knowledge. `GET /api/v1/onboarding` reads the first missing knowledge step, returns only its current dialogue ID and visible region name, and fails closed on missing player state, invalid Codex identity, or out-of-order knowledge. All knowledge with no unit means `ready_to_provision`; any owned unit means `complete`, including existing returning players without a knowledge backfill. Current vNext unit ownership is durable, so later atomic Basic Goblin roster provisioning can make completion unambiguous without another flag/table. The flow references `kin.goblin`, not a restoration recipe; its Wrong Machine capability reference grants no access and adds no Swamp mechanics.
- Authored IDs: `region.mystic_cave`, `onboarding.mystic_cave`, two `dialogue.mystic_cave.*`/`encounter.mystic_cave.*` pairs, two meaningful `codex.mystic_cave.*` entries, and their existing event/reward definitions. The region has no run generator and remains out of global client content. Typed references, guaranteed knowledge grants, dialogue prerequisites, Basic Goblin identity, and existing Wrong Machine identity are validated. A strict frontend read contract rejects private fields; no Phaser onboarding UI or provisioning mutation was added.
- Focused verification: onboarding content/read plus existing bootstrap/run regression 47 tests / 360 assertions; onboarding authoring alone 6 tests / 14 assertions; frontend suite 449 tests. Complete `npm run verify:package -- --continue-on-failure` passed in 4m35s: 699 backend tests / 5,645 assertions; 449 frontend tests; PHP front-controller syntax, production content validation, LLM/context, docs lint, frontend production build, bundle budget, and diff whitespace. Production content revision is `894bea2d1627125c9936a2a3f1f873873fbb0205ba2cd04f3ae438c8fbebad5d`. Package 1 remains In Progress for architectural review; Package 2 is not activated.

#### Package 1 canonical-content correction (`fb6414d12848ae37b3150a8cf9a35dbabffcec24`)

- The Wrong Machine dialogue now has The Whim conjure a fleeting image and explain its name and Raw Chaos reconstruction purpose. It explicitly says that knowing the name does not give the player the machine or access to it. The dialogue no longer places a physical device in Mystic Cave or suggests a later relocation. The Codex entry was already location-neutral and remains unchanged. Mystic Cave introduces knowledge only; canonical Swamp provenance and physical access/recovery remain later progression.
- The existing progression-state documentation already distinguishes introduction knowledge from Wrong Machine access and explicitly preserves later Swamp provenance, so it needed no wording change. Authored onboarding flow, completion/read semantics, and all deferred work remain unchanged.
- A fixture-based validation regression proves that replacing the onboarding Codex reward with the existing Wrong Machine access unlock is rejected. Focused onboarding content/read tests passed: 11 tests / 37 assertions. Production authored content validation passed; generated client content revision is `861511b8d7ea710ffea59b904fdb66da5b031cd3f50a16cef946e4375d85abba`.
- Complete `npm run verify:package -- --continue-on-failure` passed in 6m21s: 700 backend tests / 5,647 assertions and 449 frontend tests; LLM/context, docs lint, PHP front-controller syntax, content validation, frontend build, bundle budget, and diff whitespace all passed. Package 1 remains In Progress for architectural re-review; Package 2 is not activated.
