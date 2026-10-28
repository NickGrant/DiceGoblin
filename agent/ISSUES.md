# Active Execution Issue

## Milestone 11 - Knowledge and Objectives

### Milestone 11 Package 5 - Authoritative gameplay-fact objective progress

**Status:** In Progress
**Priority:** High

#### Problem

Package 4 established canonical objective definitions, narrow user-scoped objective/requirement persistence, and an authoritative player-safe read contract. Objective counters are currently inert: accepted gameplay outcomes do not yet advance them.

The progression model requires objective progress to be derived from authoritative gameplay facts, not client claims or a generic event-history/progression layer.

#### Accepted baseline

- Milestones 1-10 are complete and passed manual UAT.
- Milestone 11 Packages 1-3 established Codex ownership/presentation and replayable knowledge-aware dialogue.
- Milestone 11 Package 4 is approved through `350ec0eead1224d9cd0777cc19d574913ad8a006` (implementation `5f3212fee87af17f3361c162b1ec88fca06a47b6`).
- Package 4 authored requirement kinds are `defeat_enemy_unit_type`, `complete_region_run`, and `complete_dialogue`, each with a validated target and positive needed count.
- `user_objectives` and `user_objective_requirements` are the only objective-owned mutable persistence introduced so far. Missing rows project as unstarted/zero; persisted corruption fails closed.
- Package 4 does not apply objective completion rewards. Package 6 owns automatic completion/reward finalization.
- Backend/MySQL and existing gameplay commands remain authoritative.

#### Purpose

Connect accepted authoritative gameplay outcomes to objective-owned counters without creating a client progress endpoint, generic event log, or parallel gameplay authority.

Package 5 must establish reusable server-side progress application for the three authored requirement kinds and integrate it at the narrow authoritative transaction boundaries that already know those facts.

#### Required inventory before implementation

Inspect and record reuse/drop decisions for:
- Package 4 objective schema/repository/read semantics;
- authoritative combat/battle finalization and enemy defeat facts;
- authoritative successful region-run completion/Exit transaction;
- Package 3 dialogue completion transaction and exact-key replay behavior;
- existing event/reward/idempotency transaction boundaries;
- player revision/cache invalidation conventions;
- relevant prototype objective/bounty progress evidence only where useful.

Do not create a second gameplay-event authority merely to feed objectives.

#### Canonical progress semantics

- Objective progress is derived only from server-authoritative finalized gameplay facts.
- `defeat_enemy_unit_type` increments from authoritative defeated enemy-unit facts, using the authored enemy unit type identity and the actual qualifying defeated count.
- `complete_region_run` increments only from a successful authoritative run completion for the authored region. Failed/abandoned runs do not count.
- `complete_dialogue` increments only from a successfully completed authoritative dialogue choice for the authored dialogue. Dialogue replay is allowed; each distinct legitimate completion may count unless/until the authored target is saturated. Exact-key replay must not count again.
- Counters saturate at authored `needed`; they never exceed the target.
- A qualifying fact may advance every matching objective/requirement for that player. Do not hard-code objective IDs into gameplay commands.
- A first qualifying fact may create the objective state row and requirement counter transactionally. Zero progress remains represented by absence as established in Package 4.
- Completed objectives must not continue accumulating progress. Package 6 owns automatic transition/reward finalization; Package 5 must preserve a clean boundary for that work.

#### Transaction and idempotency requirements

- Progress mutation must occur in the same authoritative transaction/finalization boundary as the gameplay fact wherever required to prevent committed gameplay with lost/double objective progress.
- Exact-key replay of an existing mutation receipt must not advance objective progress again.
- Ambiguous retry/reconnect must resolve through existing authoritative/idempotent semantics rather than client-side compensation.
- Rollback of the authoritative gameplay transaction must roll back objective progress from that attempt.
- Cross-player isolation is mandatory.
- Do not add a public/client endpoint that accepts objective ID, requirement ID, delta, target, or claimed gameplay facts.

#### Completion boundary

- Package 5 advances counters only.
- Do not apply `completion_event_id` or objective rewards yet; that is Package 6.
- Do not invent a manual claim flow.
- If a requirement reaches its target in Package 5, persist the saturated counter while leaving completion/reward transition for Package 6 according to the established sequencing. Ensure the read/integrity contract remains coherent during this temporary package boundary; adjust the Package 4 read semantics narrowly if necessary so target-satisfied-but-not-finalized state is representable without falsely reporting completion.

#### Revision/client reconciliation

- Objective progress is authoritative mutable player state. Use the accepted player revision semantics consistently so an accepted progress change invalidates/reloads the lazy objective cache when appropriate.
- Exact replay or a qualifying fact that produces no counter change must not fabricate another revision change.
- Do not optimistically mutate objective counters in the browser.

#### Minimum proof

Prove at minimum:

1. authoritative enemy defeats advance every matching `defeat_enemy_unit_type` requirement by the correct count;
2. successful authored-region run completion advances matching `complete_region_run` requirements once, while defeat/abandon/nonterminal nodes do not;
3. successful dialogue completion advances matching `complete_dialogue` requirements once per legitimate completion, while exact-key replay does not;
4. nonmatching facts leave objective state unchanged;
5. progress saturates at authored targets;
6. multiple matching objectives/requirements advance independently;
7. another player's gameplay never advances the requesting player's objectives;
8. failed/rolled-back gameplay mutations leave objective progress unchanged;
9. reload/read reflects committed progress through the Package 4 authoritative projection;
10. no objective reward/completion event is finalized yet.

#### Verification requirements

Add focused backend/integration proof for:
- each supported requirement kind;
- first-progress initialization and subsequent increments;
- multi-count enemy defeats where authoritative battle facts allow it;
- saturation and no over-target persistence;
- exact-key replay/idempotency at each integrated mutation boundary;
- rollback/integrity failure behavior;
- cross-player isolation;
- nonmatching facts;
- player revision behavior for changed vs unchanged progress;
- objective read reconciliation after progress;
- existing combat/run/dialogue/reward behavior and receipts remaining stable.

Add frontend tests only if shared revision/cache contract behavior changes. Do not add the Phaser Objectives screen.

Run applicable gates in `agent/QUALITY_GATES.md` and full `npm run verify:package` before review.

#### Scope exclusions

Do not add in Package 5:
- objective completion event/reward finalization;
- manual objective claim APIs;
- client-authored progress mutations;
- generic gameplay/event history persistence;
- generic player progression/flags;
- Phaser Objectives presentation;
- new dialogue history/story flags;
- Mystic Cave/onboarding;
- Swamp/Frog Kin or production Shrine/Chaos expansion;
- Milestone 12+ work.

#### Completion evidence required

Report:
- implementation SHA;
- authoritative gameplay integration points and why each is the correct fact boundary;
- reusable objective progress service/repository behavior;
- counter initialization/saturation/multi-objective semantics;
- combat/run/dialogue idempotency and rollback proof;
- player revision/cache reconciliation behavior;
- focused backend/frontend results;
- full package-gate results;
- confirmation that objective completion rewards, client progress mutation, generic history/progression state, Phaser Objectives UI, and M12+ were not introduced.

Leave Package 5 **In Progress** for review. Do not promote Package 6.
