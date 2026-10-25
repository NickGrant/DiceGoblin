# Active Execution Issue

## Milestone 11 - Knowledge and Objectives

### Milestone 11 Package 1 - Codex ownership and read-model foundation

**Status:** In Progress
**Priority:** High

#### Problem

Milestone 10 is complete and manual UAT passed. Milestone 11 now needs its first durable knowledge primitive before dialogue or objective orchestration can be added.

The accepted progression model defines Codex entries as unique collectible rewards representing meaningful retained knowledge, lore, discoveries, or important information. Current vNext has no canonical authored Codex definition/ownership/read-model slice proving that contract end to end.

#### Accepted baseline

- Milestones 1-10 are complete and passed manual UAT.
- Milestone 10 UAT acceptance is recorded through `d97bcb52c4b834cf72a68d827eac8e196c05adbb`.
- `documentation/07-development-path/vnext-progression-state-model.md` is canonical for Codex/objective progression semantics.
- `documentation/07-development-path/vnext-reward-unlock-model.md` owns reward finalization/application/idempotency semantics.
- Backend/MySQL remains authoritative; authored catalogs live in canonical JSON; client state is a cache/projection of backend authority.
- The canonical test suite is zero-skip. Prototype M11 evidence retained outside canonical discovery may be inspected for behavior but is not architectural authority.

#### Purpose

Implement the smallest reusable Codex foundation:

1. authored Codex entry definitions with stable IDs and validation;
2. durable per-player unique Codex ownership;
3. Codex grant support through the accepted finalized reward pipeline;
4. a player-safe authoritative Codex read contract that distinguishes authored catalog presentation from player ownership without exposing private reward/event configuration;
5. strict ownership/security/idempotency behavior suitable for later dialogue and objective packages.

Do not implement dialogue selection, objective counters, or the final Phaser Codex screen in this package.

#### Required inventory before implementation

Inspect and record reuse/drop decisions for:
- accepted unique unlock reward persistence/application and duplicate behavior;
- current reward definition/finalization/application pipeline;
- authored-content registry/validator/client-projection conventions;
- current API/query/controller/cache patterns for lazy player-owned collections;
- retained prototype Codex implementation/tests, if present;
- existing database naming/ownership/foreign-key conventions.

Do not preserve prototype API/schema/UI shape merely for compatibility.

#### Canonical design constraints

##### Codex semantics

- A Codex entry is a unique collectible reward and durable knowledge record.
- Codex entries must have player-facing meaning; do not use them as arbitrary invisible story flags.
- Duplicate Codex grants resolve to no additional ownership under the accepted unique-reward rule.
- Do not introduce generic region-completion state, story flags, historical-event persistence, `has_seen_dialogue`, or a catch-all progression table.

##### Authored content

- Define Codex entries in canonical authored JSON with stable `codex.*` identity.
- Include only the authored player-facing fields needed by the read/presentation contract plus any narrow server-private classification needed now.
- Validate IDs, exact field shapes, references, bounds, and duplicate identities consistently with existing content validation.
- Do not author speculative dialogue trees or objective catalogs in Package 1.

##### Persistence

- Persist unique ownership in a narrow player/Codex relation following existing ownership-table conventions.
- Enforce uniqueness at the database boundary.
- Ownership must be user-scoped and foreign ownership must never satisfy another player's read/reward state.
- Use the repository/storage boundary; do not embed persistence in controllers/content objects.
- Clean bootstrap/reset must create the canonical schema.

##### Reward integration

- Add Codex as a supported unique reward type through the existing event -> reward definition -> finalized result -> transactional application pipeline.
- Finalized reward results must contain only the stable facts needed to replay/apply safely.
- Exact-key retry/replay cannot create duplicate ownership, reroll, reapply, or increment revision twice.
- A duplicate already-owned Codex reward must follow the accepted duplicate unique-reward behavior rather than throwing or inventing compensation.
- Do not create a direct Codex-grant endpoint or bypass the reward pipeline.

##### Read/API contract

- Provide the smallest authoritative read contract needed for later Codex presentation.
- Prefer established lazy-query/cache patterns rather than adding Codex to bootstrap unless canonical client architecture clearly requires otherwise.
- Return player-safe authored presentation and ownership/discovery state only.
- Do not expose event definitions, reward probabilities, hidden prerequisites, private dialogue routing, or future objective configuration.
- Missing/corrupt authored or persisted references must fail closed/integrity-safe rather than silently fabricating entries.

##### Client scope

- Add strict frontend contract/cache support only as needed to consume the read model and prove secrecy/reconciliation boundaries.
- Do not build the full Phaser Codex screen; that is Package 2.
- Do not add dialogue-specific or objective-specific client state.

#### Minimum proof

Use a narrow test-authored or production-appropriate Codex entry and prove:

1. a finalized event reward can grant an unowned Codex entry transactionally;
2. ownership persists and appears in the authoritative Codex read model after reload/re-query;
3. the same finalized reward replay does not grant again or increment revision again;
4. a later legitimate duplicate roll of the same unique Codex entry resolves according to the accepted duplicate-reward rule without duplicate ownership;
5. another user does not inherit/observe that ownership;
6. malformed/private authored facts do not reach the browser projection.

Do not manufacture a production dialogue/objective merely to trigger the proof. A narrow validated fixture is preferred if no existing production event semantically grants meaningful Codex knowledge yet.

#### Verification requirements

Prove at minimum:
- authored Codex structural/semantic validation, including malformed IDs/fields and duplicate/reference failures;
- clean-schema ownership table constraints and cascade behavior;
- repository ownership/list/read behavior and cross-player isolation;
- reward-definition validation for Codex grants;
- finalized Codex reward application and duplicate unique-reward semantics;
- exact-key replay/idempotency and revision stability;
- transaction rollback on corrupt/missing Codex references;
- authoritative read projection after grant and reload;
- browser contract rejects private/unknown fields;
- existing currency/item/unit/unlock/XP reward paths remain green;
- M1-M10 regression suite remains green;
- no dialogue/objective/M12+ scope leakage.

Run the applicable gates in `agent/QUALITY_GATES.md` and full `npm run verify:package` before review.

#### Scope exclusions

Do not add in Package 1:
- Phaser Codex screen beyond minimal contract/cache plumbing;
- dialogue selection/history/routing;
- generic story flags or `has_seen_dialogue` state;
- objective/bounty definitions or progress counters;
- gameplay-fact objective wiring;
- objective completion rewards;
- Mystic Cave/onboarding;
- Swamp/Frog Kin or production Shrine/Chaos expansion;
- final visual redesign;
- Milestone 12+ work.

#### Completion evidence required

Report:
- implementation SHA;
- prototype/current-system inventory and reuse/drop decisions;
- authored Codex representation and validation rules;
- persistence schema/repository shape and uniqueness/ownership guarantees;
- reward pipeline changes and duplicate semantics;
- API/read projection and browser-secrecy proof;
- idempotency/replay/revision behavior;
- cross-player and corrupt-state rejection proof;
- focused test results;
- full package-gate results;
- confirmation that dialogue/objectives/M12+ were not introduced.

Leave Package 1 **In Progress** for review. Do not promote Package 2.
