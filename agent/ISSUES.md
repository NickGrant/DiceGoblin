# Active Execution Issue

## Milestone 11 - Knowledge and Objectives

### Milestone 11 Package 4 - Objective/bounty authored model + persistence/read contract

**Status:** In Progress
**Priority:** High

#### Problem

Packages 1-3 established durable Codex knowledge, its Phaser presentation, and replayable knowledge-aware dialogue without generic progression/history state. Milestone 11 now needs the objective/bounty domain itself before authoritative gameplay facts can update it.

The accepted progression model requires objectives to own their mutable intermediate progress because those counters are meaningful domain state. Objective progress is not an unlock/reward and must not become a generic player progression or historical-event layer.

#### Accepted baseline

- Milestones 1-10 are complete and passed manual UAT.
- Milestone 11 Package 1 established canonical Codex ownership/read semantics.
- Milestone 11 Package 2 established the mounted Phaser Codex surface.
- Milestone 11 Package 3 is approved through `47c6ace1b75832c528f4151e4988164c31f35fec` (implementation `0e928f31281b5cbb36d317c78701da99dd19188e`).
- Package 3 dialogue is replayable, Codex-aware, reward-pipeline backed, and creates no dialogue history/story flags.
- The Package 3 test-audit cleanup is accepted: test fixtures may consume the complete authored catalog rather than hand-maintained type inventories, while explicit accepted mechanics remain asserted.
- `documentation/07-development-path/vnext-progression-state-model.md` is canonical for objective state semantics.
- Backend/MySQL remains authoritative; authored catalogs live in canonical JSON; client state is a cache/projection of backend authority.

#### Purpose

Establish the smallest durable objective/bounty foundation before gameplay-event wiring:

1. canonical authored objective definitions with stable identity and strict validation;
2. narrow domain-owned per-player objective progress/completion persistence;
3. authoritative objective read projection suitable for later Phaser presentation;
4. explicit lifecycle/state semantics for active and completed objectives;
5. strict ownership, corruption, and reconnect/reload behavior.

Do not wire combat/run/dialogue facts into objective progress in this package. That is Package 5. Do not finalize objective rewards in this package. That is Package 6.

#### Required inventory before implementation

Inspect and record reuse/drop decisions for:
- `documentation/07-development-path/vnext-progression-state-model.md` objective decisions;
- retained prototype bounty/objective implementation/tests in `backend/prototype-test-evidence/` and `frontend/prototype-test-evidence/`;
- current authored-content registry/validator patterns;
- current narrow user-owned persistence/repository conventions;
- current authoritative lazy read/cache conventions used by Codex and other collections;
- accepted revision/idempotency conventions where relevant;
- current schema/bootstrap/reset conventions.

Prototype bounty behavior is migration evidence only. Do not preserve obsolete SQL catalog, API, claim, or Angular UI architecture.

#### Canonical design constraints

##### Objective semantics

- Objectives/bounties are authored gameplay goals with domain-owned mutable progress.
- Intermediate counters belong to the objective domain because the counter itself matters.
- Objective progress/completion is not a generic unlock, reward, story flag, historical-event log, or catch-all player progression record.
- Do not add generic `player_progression`, `progress_flags`, `event_history`, or similar state.
- Package 4 establishes storage/read/lifecycle semantics only; gameplay facts do not update counters yet.

##### Authored content

- Define objectives in canonical authored JSON with stable namespaced IDs and exact validated field shapes.
- Keep the initial authored set narrow and production-appropriate or use narrowly validated fixtures where production content would be premature.
- Requirements must be explicit enough for Package 5 to map authoritative gameplay facts without inventing a generic scripting language.
- Validate IDs, requirement kinds/parameters, bounds, duplicate requirement identities, and all referenced authored IDs.
- If completion reward/event references are represented now, validate them but do not apply/finalize them until Package 6.
- Do not encode objective catalogs or requirement logic in client constants.

##### Persistence and lifecycle

- Persist per-player objective state in narrow objective-owned table(s), following existing user-scoped FK/cascade conventions.
- Enforce one authoritative state record per player/objective where appropriate.
- Store only mutable objective-domain facts needed to resume/reconcile progress and completion.
- Completion must be represented durably and unambiguously; do not infer completed state from client counters.
- Define how an authored objective with no persisted row is projected/initialized without silently fabricating historical progress.
- Foreign-player state must never satisfy or leak into another player's objective projection.
- Missing/corrupt authored references or incoherent persisted counters must fail closed/integrity-safe.
- Clean bootstrap/reset must create the canonical schema.

##### Read/API contract

- Provide the smallest authoritative read contract needed by later Objective presentation.
- Prefer the established lazy-query/cache model rather than adding objectives wholesale to bootstrap unless canonical architecture requires otherwise.
- Project only safe player-facing authored presentation plus authoritative progress/completion facts.
- Do not expose future event-routing internals, reward probabilities/private configuration, server-only requirement wiring, or another player's state.
- The client must not infer completion or eligibility from unrelated local gameplay state.

##### Client scope

- Add strict frontend contract/cache support only as needed to consume and verify the objective read model.
- Do not build the Phaser Objectives surface; that is Package 7.
- Do not add client-side gameplay-event progress mutation.
- Revision/cache invalidation should follow established runtime conventions where objective state changes will eventually require reconciliation.

#### Minimum proof

Use a narrow objective fixture or production-appropriate authored objective to prove:

1. authored objective definitions validate with stable IDs and explicit requirements;
2. a player with no persisted progress receives the correct authoritative initial projection;
3. persisted intermediate progress survives reload/re-query;
4. completed state is durable and distinct from intermediate progress;
5. another player's progress/completion does not affect the requesting player;
6. malformed or incoherent persisted objective state fails safely;
7. browser projection contains only safe authored/progress facts and rejects private/unknown fields;
8. no combat/run/dialogue client claim can mutate objective progress in Package 4;
9. no objective completion reward is applied yet.

#### Verification requirements

Prove at minimum:
- objective authored-definition structural/semantic validation;
- malformed IDs/fields, duplicate requirements, invalid bounds, and unknown authored references fail validation;
- clean-schema objective persistence constraints and user cascade behavior;
- repository read/write behavior and cross-player isolation;
- initial/unstarted, in-progress, and completed authoritative read projections;
- reload/reconnect persistence;
- corrupt/missing authored reference rejection;
- strict player-safe browser contract and private-field rejection;
- existing Codex/dialogue/reward/run/combat/auth regressions remain green;
- no Package 5 gameplay-fact wiring, Package 6 reward completion, Package 7 Phaser Objective screen, or M12+ scope leakage.

Run applicable gates in `agent/QUALITY_GATES.md` and full `npm run verify:package` before review.

#### Scope exclusions

Do not add in Package 4:
- combat/run/dialogue gameplay-fact objective progress wiring;
- client-authored progress mutation;
- automatic objective completion from gameplay events;
- objective reward finalization/application;
- Phaser Objectives screen;
- generic progression/history/event-log tables;
- dialogue history/story flags;
- Mystic Cave/onboarding;
- Swamp/Frog Kin or production Shrine/Chaos expansion;
- broad visual redesign;
- Milestone 12+ work.

#### Completion evidence required

Report:
- implementation SHA;
- prototype/current objective inventory and reuse/drop decisions;
- authored objective representation and validation rules;
- persistence schema/repository and lifecycle semantics;
- authoritative initial/in-progress/completed read projection;
- ownership/corruption/reload behavior;
- API/client projection and secrecy proof;
- focused backend/frontend results;
- full package-gate results;
- confirmation that gameplay-fact wiring, objective rewards, Phaser Objectives UI, generic progression/history state, and M12+ were not introduced.

Leave Package 4 **In Progress** for review. Do not promote Package 5.

#### Package 4 implementation evidence (review candidate)

- Implementation commit: `5f3212fee87af17f3361c162b1ec88fca06a47b6`. Package remains **In Progress** for architectural review.
- Inventory: the accepted progression model assigns numeric counters to the objective domain. Prototype Bounty Board evidence supplied the useful current/needed/completed concept; its SQL-authored catalog, sync/claim API, and Angular presentation were dropped. Current Codex/content validation, user-scoped repository/FK, lazy GameStore read, revision invalidation, and clean baseline/reset conventions were reused. No objective transaction or idempotency key exists in this read-only package.
- Authored model: the new `objective` definition has a stable namespaced ID, bounded player-facing text, `objective`/`bounty` category, ordered uniquely identified requirements, and optional validated completion-event reference. Supported requirement kinds are enemy-unit defeat, region-run completion, and dialogue completion, each with a typed authored target and positive browser-safe needed count. A narrow test-only JSON fixture exercises all three; no uncompletable production objective was added.
- Persistence: `user_objectives` stores one row per player/objective with a durable nullable `completed_at`; `user_objective_requirements` stores positive counters under a composite parent FK. User deletion and UAT reset remove both. The repository scopes all reads/writes to a user; future gameplay command ownership and reward application remain outside Package 4.
- Lifecycle/read: `GET /api/v1/objectives` projects a missing row as `unstarted` with zero progress without writing state. A row with intermediate counters is `active`; a completed marker is valid only when every requirement reaches its target. Unknown persisted objective/requirement IDs, over-target counts, and premature completion fail closed. Reload and foreign-player isolation are covered by integration tests.
- Browser secrecy: the response contains only ID/title/description/category/status and requirement ID/label/current/needed. The strict frontend parser rejects extra/private fields, duplicated identities, and incoherent counts. Requirement kinds/targets and completion-event references are absent from both the current-read response and static browser content. The cache stays lazy, invalidates on player revision, and discards stale in-flight reads. No progress or reward mutation endpoint was added.
- Focused verification: backend objective/content/baseline suites **13 tests, 130 assertions**; frontend objective contract, API, and GameStore suites **53 tests passed**. Clean `goblin_test` baseline reset succeeded.
- Full `npm run verify:package`: **PASS**; backend **781 tests, 5,643 assertions**; frontend **518 tests passed**; PHP front-controller syntax, content validation, LLM/context, docs lint, production frontend build, bundle budget, and diff whitespace all passed. Verification artifact: `artifacts/verification/package-summary.json`.
- Scope: no gameplay-fact objective wiring, objective reward finalization, Phaser Objectives UI, generic progression/history state, or Milestone 12+ work was introduced.
