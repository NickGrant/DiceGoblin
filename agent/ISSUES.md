# Active Execution Issue

## Milestone 11 - Knowledge and Objectives

### Milestone 11 Package 8 - Knowledge/objectives integrated verification and technical closure

**Status:** In Progress
**Priority:** High

#### Problem
Packages 1-7 now implement the intended Milestone 11 knowledge/objective lifecycle: Codex ownership and presentation, knowledge-aware replayable dialogue, objective definitions and persistence, authoritative gameplay-fact progress, automatic completion/rewards, and Phaser Objectives presentation. Before manual UAT, the milestone needs an integrated closure pass across these independently approved packages. This is not a feature package.

#### Accepted baseline
- Milestones 1-10 are complete and passed manual UAT.
- Milestone 11 Packages 1-6 are approved as recorded in `agent/MILESTONES.md`.
- Package 7 is approved through `cdf2886475512cdbe7d479e08bd495d4e276a3b1` (implementation `d3da60c8b8894fc4628e14d74ad7b6fe4ffcc529`).
- Backend/MySQL remains authoritative for Codex ownership, dialogue eligibility, objective progress/completion, rewards, revision, and idempotency.
- Phaser consumes strict player-safe projections through the existing runtime/GameStore boundaries.
- No generic progression/history/story-flag layer is part of the accepted design.
- Package 9 remains the focused manual UAT gate; do not mark Milestone 11 complete here.

#### Purpose
Perform technical closure of the accepted Milestone 11 implementation before manual UAT. Verify the end-to-end Codex/dialogue/objective lifecycle, persistence, retry/reconnect, revision/cache reconciliation, secrecy, reward idempotency, responsive presentation, and regression safety. Close only test/documentation/implementation gaps required by already accepted semantics.

#### Required integrated audit
Inventory P1-P7 against `documentation/07-development-path/vnext-progression-state-model.md`, `vnext-phaser-client-architecture.md`, `vnext-reward-unlock-model.md`, `documentation/06-testing-release/00-testing-strategy.md`, `documentation/04-ux/01-visual-design-guide.md`, and `agent/QUALITY_GATES.md`. Canonical docs remain architectural authority; source/tests are implementation evidence.

#### Integrated lifecycle proof
Prove at minimum:
1. dialogue -> Codex grant -> authoritative Codex read -> Codex-gated dialogue eligibility;
2. combat/run/dialogue -> objective progress -> automatic completion -> canonical reward -> authoritative re-read;
3. exact-key replay does not reroll/reapply knowledge, objective progress, completion, or rewards;
4. randomized completion rewards remain stable and duplicate unique Codex/unlock rewards retain canonical duplicate behavior;
5. multiple matching objectives progress/complete independently from one authoritative fact;
6. failed/rolled-back gameplay or reward application leaves no partial Codex/objective/reward state;
7. cross-player isolation holds for Codex, dialogue eligibility, objective progress/completion, and rewards.

#### Revision, cache, reconnect, and secrecy
Verify accepted mutations update player revision without artificial double increments; revision changes stale the appropriate lazy Codex/objective caches; current caches are reused when revision is unchanged; stale in-flight reads cannot overwrite newer state; read errors remain retryable; and reconnect/reload reconstructs durable state without client history reconstruction.

Audit backend projections, strict browser parsers, GameStore state, and Phaser surfaces so they do not expose or depend on hidden dialogue prerequisite identities, objective requirement kind/target IDs, completion event IDs, reward definitions/probabilities/roll data, generic historical events, or story flags. Preserve fail-closed malformed/private-field rejection.

#### Phaser/responsive closure
Verify repeated Camp -> Codex/Objectives -> Camp lifecycle teardown without duplicate keyboard/pointer/store listeners; loading/error/retry and long-content/list pagination; Compact 844x390, Standard 1600x900, Wide 2560x1080; and portrait 390x844 through the existing rotate-device gate. Do not undertake the deferred global visual redesign.

#### Closure fixes allowed
Narrow fixes are allowed for defects in already accepted Milestone 11 semantics, missing regression coverage, lifecycle cleanup, strict parser gaps, transaction/retry defects, or stale documentation. Do not introduce new product mechanics. If a finding requires a material new architecture/product decision, stop and report it for review.

#### Verification requirements
- Add/consolidate integrated tests only where they materially improve semantic coverage; avoid redundant fixture-heavy count inflation.
- Run focused backend suites spanning Codex, dialogue, objectives, rewards, combat/run integration, idempotency, rollback, and cross-player isolation.
- Run focused frontend suites spanning strict contracts, GameStore revision/cache behavior, Camp navigation, Codex, Objectives, and lifecycle cleanup.
- Run deterministic captures for representative Camp/Codex/Objectives states at canonical viewports where supported.
- Run full `npm run verify:package`; record exact test/assertion counts and evidence policy.
- Review changed files for accidental scope expansion and production entry-point/composition issues.

#### Documentation closure
Update canonical documentation only for real stale/gap findings exposed by the accepted implementation. Keep package evidence distinct from architectural authority. Ensure `agent/ISSUES.md`, `agent/QUALITY_GATES.md`, and affected canonical docs are consistent enough for Package 9 UAT and Milestone 12 handoff.

#### Scope exclusions
Do not add new gameplay/product mechanics, objective requirement kinds, dialogue history/story flags, manual objective claims, client-authored Codex/objective mutations, generic progression/history/event persistence, Mystic Cave/onboarding, Swamp/Frog Kin or production Shrine/Chaos expansion, broad visual redesign, or Milestone 12+ work.

#### Completion evidence required
Report audit findings and closure-fix SHAs; integrated backend lifecycle/retry/rollback/cross-player proof; integrated frontend revision/cache/navigation/lifecycle proof; secrecy audit; responsive captures; focused tests; full package gates; documentation/quality-gate updates; and confirmation that no new product mechanics, generic progression/history state, or M12+ work was introduced.

Leave Package 8 **In Progress** for review. Do not activate Package 9 or mark Milestone 11 complete. Package 9 is the explicit focused manual UAT gate.

#### Package 8 technical closure evidence (review candidate)

- Verification implementation: `63e59bacf3d5b6248cbed4aa0501a995eef1dbaf`. This adds no production behavior: the existing combat/run/dialogue integration test now reads committed Codex ownership, Codex-gated dialogue choices, objective completion, and foreign-player isolation through fresh query/repository instances. Codex and Objectives screen tests now repeat mount/exit cycles and verify cache reuse, listener teardown, and no rendering after unsubscription. No closure fix to production code or canonical architecture was needed.
- P1-P7 audit: P1-P3 Codex ownership, replayable dialogue, and strict read projections; P4-P6 persisted objective progress, gameplay-fact advancement, automatic completion, transactional reward application; P7 lazy Phaser presentation and revision-aware GameStore reconciliation. These match the canonical progression, reward, Phaser, testing, and visual contracts. No stale canonical-document or quality-gate command was found; the issue evidence remains separate from architectural authority.
- Integrated backend proof: focused Codex, dialogue, objective read/progress/completion/gameplay-fact, and reward-application suites passed **34 tests / 272 assertions**. They cover combat, successful run exit, dialogue, multiple matching objectives, exact-key replay, stable randomized rolls, duplicate unique grants, rollback, cross-player isolation, and fresh authoritative read-back. The initial direct PHPUnit invocation omitted the required `TEST_DB_*` environment and could not connect; the corrected canonical test-database invocation passed.
- Frontend proof: focused strict Codex/objective contracts, GameStore revision/cache and stale in-flight protections, Camp navigation, and Codex/Objectives screens passed **72 tests**. Repeated surface entry/exit leaves no duplicate Objectives keyboard listener or live Codex/Objectives store subscription. Fresh caches are reused; revision changes require authoritative reload; read errors remain retryable. Private prerequisite, requirement kind/target, completion-event, probability, and reward fields are absent from client projections and screen dependencies; strict parsers reject extra fields.
- Responsive proof: **12/12 deterministic captures passed** for Camp, Codex, and Objectives at 844x390 Compact, 1600x900 Standard, 2560x1080 Wide, and 390x844 portrait. Portrait reported the existing gate active for all three. Representative images were reviewed for clipping and legibility; loading/error/retry and long-content/list pagination remain covered by focused screen tests and accepted Package 7 captures. Files are ignored under `artifacts/screenshots/m11-p8-*`.
- Full `npm run verify:package`: **PASS in 10m 40s** at the implementation SHA; backend **795 tests / 5,769 assertions**, frontend **524 tests**, production build, bundle, content validation, front-controller syntax, context, docs, and diff checks all passed. Production content revision: `708db08721032de44e272a0c5c2782fa0538afd17c19b4f725a9e92f1633fc1b`. The evidence policy retains existing semantic suites and adds only cross-boundary assertions where they prove durable read-back/lifecycle behavior; it does not inflate the test count with duplicate fixtures.
- Production entry-point composition was inspected: Codex, Objectives, and Dialogue routes/controllers are singly registered. Changed files are limited to three test files and this issue evidence. No new mechanics, generic progression/history state, or Milestone 12+ work was introduced. Package 8 remains In Progress for architectural review; Package 9 manual UAT is untouched.
