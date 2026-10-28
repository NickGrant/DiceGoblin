# Active Execution Issue

## Milestone 11 - Knowledge and Objectives

### Milestone 11 Package 6 - Automatic objective completion + reward finalization

**Status:** In Progress
**Priority:** High

#### Problem

Package 5 connects finalized authoritative combat, region-run, and dialogue facts to objective-owned counters. Counters saturate at their authored targets, but a fully satisfied objective intentionally remains active because automatic completion and reward finalization were deferred to this package.

The accepted progression model requires completed objectives to use the normal event-to-reward system. Completion must therefore become part of the authoritative objective-progress transaction without adding a manual claim endpoint, parallel reward path, or client authority.

#### Accepted baseline

- Milestones 1-10 are complete and passed manual UAT.
- Milestone 11 Packages 1-3 established Codex ownership/presentation and replayable knowledge-aware dialogue.
- Package 4 established validated authored objectives, user-scoped progress/completion persistence, and authoritative lazy read contracts.
- Package 5 is approved through `2903f69555994e3377b3f254f8176d44990024c8` (implementation `151133f6d1e271f31893554a20fdc53d29ee83af`).
- Package 5 progress is emitted only from authoritative finalized combat defeats, successful terminal region runs, and legitimate dialogue completions, inside the owning transaction.
- Counters saturate at `needed`; exact-key gameplay replay does not advance them again; fully saturated objectives may temporarily remain `active` until this package.
- `completion_event_id`, when authored, is already validated against canonical event content but is not yet applied.
- Existing reward finalization/application, unique-reward duplicate behavior, player revision, and idempotency boundaries remain canonical.

#### Purpose

Complete the objective lifecycle by automatically finalizing a satisfied objective and applying its authored completion event/rewards exactly once inside the same authoritative transaction that produces the final qualifying progress.

Package 6 must:

1. detect when all requirements become satisfied;
2. durably transition the objective to completed exactly once;
3. resolve/apply the optional authored `completion_event_id` through the accepted event/reward pipeline;
4. preserve rollback, retry, revision, and duplicate-reward semantics across combat/run/dialogue integration points;
5. expose the resulting completed state through the existing objective read contract without a manual claim flow.

#### Required inventory before implementation

Inspect and record reuse/drop decisions for:
- Package 5 `ObjectiveProgressService` and its owning gameplay transactions;
- `user_objectives.completed_at` and repository completion behavior;
- canonical event -> reward definition -> finalized reward -> application pipeline;
- existing unique reward duplicate semantics for unlocks/Codex and normal stackable/currency rewards;
- idempotency receipts for combat/run/dialogue commands;
- player revision behavior when gameplay, objective progress, completion, and rewards change in one transaction;
- rollback/integrity conventions for reward failures.

Do not introduce a second objective completion authority or a client claim API.

#### Canonical completion semantics

- An objective becomes complete automatically when all of its authored requirements have reached `needed`.
- Completion is durable and represented by the existing objective-owned completion state; do not infer completion only from counters.
- The transition from active to completed occurs at most once per player/objective.
- Once completed, later qualifying gameplay facts do not advance counters, rerun completion, or reroll rewards.
- A qualifying fact may complete multiple objectives in the same authoritative transaction; each objective finalizes independently and exactly once.
- An objective with no `completion_event_id` still transitions to completed normally and produces no reward event.
- If a `completion_event_id` exists, use the existing canonical event/reward pipeline. Do not directly grant currency/items/unlocks/Codex from objective code.
- Unique reward duplicates use the existing accepted duplicate behavior; do not compensate or reroll merely because the player already owns a unique reward.

#### Transaction, rollback, and idempotency

- Completion detection, durable completion marking, reward finalization/application, and the final qualifying gameplay fact must share the owning authoritative transaction.
- If objective completion reward resolution/application fails, the gameplay mutation and objective progress/completion from that attempt must roll back according to the owning command's transaction semantics.
- Exact-key replay of combat/run/dialogue receipts must return the existing finalized result and must not rerun objective completion or rewards.
- A later legitimate gameplay action after completion must not reapply completion rewards.
- Do not add a separate public idempotency key or receipt namespace solely for automatic objective completion unless the existing transaction model demonstrably cannot provide exactly-once semantics; prefer the owning gameplay receipt boundary.
- Cross-player isolation remains mandatory.

#### Reward finalization

- Resolve the objective's authored completion event using the same accepted event/reward-definition semantics used elsewhere.
- Finalized reward facts must be stable within the owning command attempt/replay boundary; retries must not reroll randomized rewards.
- Apply rewards transactionally through existing reward application services/repositories.
- Preserve browser secrecy: objective read responses must not expose completion event IDs, reward definitions, probabilities, hidden prerequisites, or other server-private reward configuration.
- Do not add objective-specific reward shapes to the browser unless an existing mutation response must safely report a finalized player-facing grant; keep any such projection narrow and consistent with established reward receipt conventions.

#### Revision and client reconciliation

- Player revision must represent accepted authoritative state changes without double increments for one logical mutation merely because it changed objective progress, completion, and rewards together.
- Exact replay and post-completion no-op progress must not fabricate revision changes.
- Existing lazy objective/Codex/inventory/unlock caches should become stale through established revision/reconciliation behavior when their authoritative state changes.
- Do not optimistically mark objectives complete or apply rewards in the browser.

#### Read-model transition

- After Package 6, a fully satisfied persisted objective should normally be completed in the same transaction that reaches its final target.
- The Package 5 temporary saturated-but-active state should no longer be produced by normal gameplay after this package.
- Retain fail-closed handling for impossible/corrupt persisted completion states. Decide explicitly whether legacy/interrupted saturated-active state is safely recoverable on the next authoritative progress evaluation or remains an integrity condition; document and test the chosen behavior.

#### Minimum proof

Prove at minimum:

1. the final qualifying fact automatically marks a satisfied objective completed;
2. an objective without a completion event completes without a reward;
3. an objective with a completion event resolves and applies rewards through the existing pipeline;
4. exact-key replay does not rerun completion, reroll rewards, or increment revision again;
5. later legitimate matching gameplay after completion does not reapply rewards;
6. multiple objectives completed by one fact each finalize once;
7. unique duplicate rewards retain accepted duplicate behavior;
8. reward/application failure rolls back gameplay progress, objective completion, and reward writes from that attempt;
9. another player's gameplay cannot complete or reward the requesting player's objective;
10. authoritative objective re-query reports `completed` after commit and browser projection still hides completion-event/reward internals.

#### Verification requirements

Add focused proof for:
- completion on each relevant gameplay boundary where the final requirement may be satisfied;
- single- and multi-requirement objectives;
- objectives with and without `completion_event_id`;
- multiple objectives completing from one fact;
- exact-key replay at combat/run/dialogue boundaries;
- later legitimate post-completion gameplay;
- deterministic/finalized reward replay where reward definitions can randomize;
- duplicate unique reward behavior;
- reward failure rollback;
- cross-player isolation;
- player revision changed vs unchanged behavior;
- objective read reconciliation after completion;
- strict browser secrecy/private-field rejection;
- existing Codex/dialogue/reward/run/combat/auth regressions remaining green.

Run applicable gates in `agent/QUALITY_GATES.md` and full `npm run verify:package` before review.

#### Scope exclusions

Do not add in Package 6:
- manual objective claim endpoints or UI;
- client-authored progress/completion;
- Phaser Objectives screen (Package 7);
- generic event-history/player-progression state;
- new objective requirement kinds without a demonstrated current need;
- dialogue history/story flags;
- Mystic Cave/onboarding;
- Swamp/Frog Kin or production Shrine/Chaos expansion;
- broad visual redesign;
- Milestone 12+ work.

#### Completion evidence required

Report:
- implementation SHA;
- completion detection/transition design;
- event/reward pipeline reuse and any necessary finalized receipt projection;
- exact-once/idempotency behavior across combat/run/dialogue;
- rollback and duplicate-reward proof;
- revision/cache reconciliation behavior;
- saturated-active recovery/integrity decision;
- cross-player and browser-secrecy proof;
- focused backend/frontend results;
- full package-gate results;
- confirmation that manual claims, client completion authority, Phaser Objectives UI, generic history/progression state, and M12+ were not introduced.

Leave Package 6 **In Progress** for review. Do not promote Package 7.

#### Package 6 implementation evidence (review candidate)

- Implementation commit: `c3e258cb1f4b1a23cd477b915c75fe6c4306a45f`. Package remains **In Progress** for architectural review.
- Inventory/reuse: Package 5's transaction-bound `ObjectiveProgressService`, objective-owned counters and `completed_at`, and combat/Exit/dialogue receipt-first commands remain the only progression authorities. Completion reuses `RewardApplicationService`, the canonical event/reward definition, `ResolvedEventRepository`, `RewardFinalizer`, `RewardContext`, and existing duplicate Codex/unlock behavior. Prototype claim/sync and SQL-authored bounty paths were not reused.
- Transition: gameplay facts still saturate counters first. After each owning gameplay handler finishes its own rewards, the same transaction calls `finalizeSatisfied`: every active objective whose requirements all meet `needed` is marked completed once, then its optional `completion_event_id` is applied with source type `objective_completion` and source ID equal to the stable objective ID. Objectives without an event complete without creating a reward event. Sequential objective events rebuild wallet, unlock, and participating-unit context from current locked authoritative rows, so independent completions and currency/XP/unique grants use fresh state. No objective-specific client grant projection or claim API was added.
- Recovery decision: a saturated `active` row left by Package 5 is safely finalized on the next authoritative progress evaluation inside a gameplay transaction, even when the matching counter itself cannot increase. The read contract still distinguishes this temporary state and rejects a completed marker with unmet requirements.
- Exact-once/rollback: combat, terminal Exit, and dialogue exact-key replay return their stored gameplay receipts before objective logic. Later legitimate matching facts skip completed objectives. Completion reward rows are stable under `(user, event, objective_completion, objective_id)` and randomized results do not reroll. Reward errors roll back the owning combat or dialogue mutation, objective counts/marker, reward writes, and receipt together. Tests also cover separate player ownership, two objectives completed by one fact, granted/already-owned unique unlock outcomes, no-event completion, currency and participating-unit XP rewards, and browser projection secrecy.
- Revision/reconciliation: run-node commands retain one revision increment for their gameplay mutation. Dialogue increments once when new knowledge, objective progress, or recovered completion changes player state; saturated/post-completion no-op and exact replay do not increment. The existing GameStore revision path stales lazy objective and Codex reads, while authoritative bootstrap/current-run requery supplies wallet, unlock, and unit state without locally fabricating rewards. Existing frontend parser rejects completion-event and reward internals.
- Focused verification: objective/combat/Exit/dialogue/reward backend suites **22 tests, 197 assertions**; browser objective contract and GameStore suites **36 tests passed**. Full `npm run verify:package`: **PASS**; backend **795 tests, 5,759 assertions**; frontend **519 tests passed**; content validation, PHP front-controller syntax, LLM/context, docs lint, production frontend build, bundle budget, and diff whitespace all passed. Artifact: `artifacts/verification/package-summary.json`.
- No manual objective claim, client completion authority, Phaser Objectives UI, generic history/progression table, or Milestone 12+ work was introduced.
