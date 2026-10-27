# Active Execution Issue

## Milestone 11 - Knowledge and Objectives

### Milestone 11 Package 3 - Knowledge-aware dialogue contract

**Status:** In Progress
**Priority:** High

#### Problem

Packages 1-2 established Codex knowledge as a durable unique reward, a player-safe authoritative read/cache contract, and a mounted Phaser Codex presentation. Milestone 11 now needs dialogue to consume that knowledge primitive without creating a parallel story/history state system.

The accepted progression model allows meaningful dialogue to grant Codex knowledge and allows dialogue selection to depend on Codex ownership. Dialogue remains replayable: whether a line was previously viewed is not durable progression state.

#### Accepted baseline

- Milestones 1-10 are complete and passed manual UAT.
- Milestone 11 Package 1 is approved through correction `88ed69f62826977f37d5c5eb365913d9805dcc5f`, with evidence at `eaea0d2a497d2b4a1cf7300232ff7a5d58f47ec7`.
- Milestone 11 Package 2 is approved at `37be6990b480989b83c22645cb5fa71de7291a94` (implementation `890ae102b2deea6929859acd934848ba3a9402e5`).
- Codex ownership is authoritative backend state and Codex grants use the accepted finalized reward pipeline.
- Authored catalogs belong in canonical JSON; backend selection/eligibility is authoritative; browser projections must be player-safe.
- Do not introduce generic story flags, dialogue history, or `has_seen_dialogue` state.

#### Purpose

Establish the smallest reusable knowledge-aware dialogue contract:

1. canonical authored dialogue definitions with stable identity and strict validation;
2. authoritative selection/eligibility using Codex ownership where authored;
3. replayable dialogue interaction without durable viewed/history state;
4. meaningful dialogue completion able to grant Codex knowledge through the existing event/reward pipeline;
5. player-safe API projection and strict client contract/cache/interaction support sufficient to prove the boundary.

This package owns the dialogue contract and lifecycle, not broad narrative content production or onboarding presentation.

#### Required inventory before implementation

Inspect and record reuse/drop decisions for:
- retained prototype dialogue definitions/services/tests, if present;
- canonical authored-content registry/validator patterns;
- Codex ownership repository/query and reward grant pipeline from Package 1;
- generic encounter interaction/action projection patterns from Milestone 10 where structurally useful;
- current API/controller/idempotency conventions for authoritative mutations;
- Phaser/runtime patterns for presenting a narrow interaction if needed for proof.

Preserve useful behavior, not prototype schema/API/UI shape.

#### Canonical design constraints

##### Dialogue semantics

- Dialogue is authored replayable content, not durable progression history.
- Do not persist `has_seen_dialogue`, viewed-line IDs, generic story flags, conversation history, or a catch-all progression record.
- A dialogue definition may have narrow Codex ownership prerequisites when that knowledge meaningfully changes what can be said or selected.
- Eligibility/selection is server authoritative. The client must not infer eligible dialogue from its Codex cache.
- Replaying an eligible dialogue is allowed unless another explicit domain rule applies; viewing it once does not make it ineligible.

##### Authored content

- Define dialogue in canonical authored JSON with stable namespaced IDs and exact validated field shapes.
- Keep the first production/test content narrow. Do not author speculative dialogue trees or broad narrative catalogs.
- Validate all Codex prerequisite and reward/event references.
- Prefer a small explicit dialogue/choice model over a generic scripting language.
- Do not encode hidden routing logic in client constants.

##### Knowledge prerequisites

- Codex prerequisite checks must use authoritative user-scoped ownership.
- Foreign ownership must never satisfy another user's dialogue eligibility.
- Missing/corrupt authored or persisted references fail closed.
- Player-safe reads may expose only the dialogue/options currently authorized for that player; do not reveal hidden prerequisite identities or unavailable private branches unless canonical UX explicitly requires a safe locked presentation.

##### Dialogue completion and Codex rewards

- If meaningful dialogue grants knowledge, completion must resolve the authored event/reward through the accepted finalized reward pipeline.
- Duplicate Codex ownership follows the accepted unique-reward behavior; replaying dialogue must not create duplicate ownership or compensation.
- Mutation retries require an idempotency key and must replay the finalized result rather than reselecting/rerolling/reapplying rewards.
- Do not create a direct Codex grant path from dialogue.
- Player revision changes only when accepted authoritative mutation semantics require it.

##### API/client boundary

- Provide the smallest authoritative dialogue read/interaction contract required to prove selection, replay, prerequisites, and completion.
- Strictly parse browser responses and reject unknown/private fields.
- Do not ship prerequisite Codex IDs, reward definitions/probabilities, hidden branches, or server-private routing configuration unless they are explicitly safe player-facing facts.
- Client state is presentation/cache state only. It must not become the authority for eligibility, completion, or knowledge grants.
- A full narrative/dialogue presentation overhaul is out of scope; functional Phaser integration may be narrow if needed to exercise the contract.

#### Minimum proof

Use narrow authored fixtures or one production-appropriate dialogue set to prove:

1. a player without prerequisite knowledge receives only the dialogue/options authorized for that state;
2. a player with the required Codex ownership receives the knowledge-aware alternative/option;
3. another user's Codex ownership does not affect selection;
4. completing meaningful dialogue can grant a Codex entry through the existing finalized reward pipeline;
5. exact-key retry/replay returns the finalized result without duplicate ownership/revision mutation;
6. replaying the dialogue later remains allowed and a duplicate Codex grant resolves under the accepted unique-reward rule;
7. no viewed/history/story-flag persistence is created;
8. hidden prerequisite/reward/routing configuration does not leak to the browser.

#### Verification requirements

Prove at minimum:
- dialogue authored-definition structural/semantic validation;
- malformed IDs/fields and unknown Codex/event/reward references fail validation;
- authoritative eligibility/selection for owned/unowned Codex states;
- cross-player isolation;
- replayability without history persistence;
- dialogue completion -> event/reward -> Codex ownership transaction path;
- exact-key idempotency and revision stability;
- later legitimate duplicate completion behavior;
- rollback/integrity behavior for corrupt references or failed reward application;
- strict player-safe API/client projection and private-field rejection;
- existing Codex, reward, run, combat, auth/security regressions remain green;
- no objective/M12+ scope leakage.

Run applicable gates in `agent/QUALITY_GATES.md` and full `npm run verify:package` before review.

#### Scope exclusions

Do not add in Package 3:
- objective/bounty definitions or progress counters;
- gameplay-fact objective wiring;
- objective completion rewards;
- generic story flags, dialogue history, or `has_seen_dialogue` state;
- a generic scripting engine;
- broad narrative content production;
- Mystic Cave/onboarding orchestration;
- Swamp/Frog Kin or production Shrine/Chaos expansion;
- broad visual redesign;
- Milestone 12+ work.

#### Completion evidence required

Report:
- implementation SHA;
- prototype/current dialogue inventory and reuse/drop decisions;
- authored dialogue representation and validation rules;
- authoritative selection/prerequisite behavior;
- completion/reward/idempotency/replay semantics;
- API/client projection and secrecy proof;
- cross-player and corrupt-state rejection proof;
- focused backend/frontend results;
- full package-gate results;
- confirmation that objective state/history flags/M12+ were not introduced.

Leave Package 3 **In Progress** for review. Do not promote Package 4.
