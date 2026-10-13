# Active Execution Issue

## Milestone 10 - Run Encounter Depth

### Milestone 10 Package 9 - Run-encounter-depth integrated verification/technical closure

**Status:** In Progress
**Priority:** High

#### Problem

The accepted Milestone 10 encounter packages need one integrated production and test-only regression proof, and the canonical test suites still contain permanently skipped prototype cases and brittle authored-catalog snapshots.

#### Accepted baseline

Milestones 1-9 are complete. Milestone 10 Packages 1-8 are approved, with Package 8 approved at `a91fbece6e989c2a468fbafe88b1eb02553a3dfc`.

The accepted slice now includes authoritative exploration-frontier branching, generic non-combat interactions, production Mountains Hazards, test-proven Shrine/Chaos families, run-scoped next-combat modifiers, contextual consumable/multi-step interactions, and Phaser RunScene integration. Farm remains the fixed tutorial and Mountains remains the only production branching/Hazard expansion in Milestone 10; production Shrine/Chaos remains reserved for Swamp/Milestone 13.

#### Purpose

Perform Milestone 10 integrated technical closure. Prove the complete production-composed Farm/Mountains run experience and the reusable test-only encounter foundations remain coherent across backend, storage, API projection, runtime reconciliation, Phaser interaction, retry/reload behavior, and responsive presentation.

This package also owns the repository-wide test-suite rationalization identified during Package 7 review. The goal is a smaller, higher-signal canonical regression suite, not an arbitrary test-count reduction.

#### Integrated verification requirements

Verify the accepted Milestone 10 behavior end-to-end and at owning architectural layers:
- Farm remains exactly Combat -> Loot -> Rest -> Boss -> Exit and completes correctly.
- Mountains retains accepted branching/dead-end topology and Hazard vocabulary without production Shrine/Chaos.
- Multiple available frontier nodes survive resolving a sibling branch.
- Locked/foreign/completed nodes cannot be resolved again; completed battle playback may remain viewable without re-resolving gameplay.
- Rest/Hazard and test-only Shrine/Chaos continue through the shared generic interaction boundary.
- Chaos/Hazard randomness remains server-owned, deterministic under injected test RNG, finalized in receipts, and not rerolled on exact-key replay.
- Run modifiers apply authoritatively to Combat/Boss, consume only under accepted semantics, survive reload where applicable, and expose only narrow safe projection.
- Contextual consumable eligibility is server-owned; inventory consumption and encounter progress are atomic/idempotent.
- Multi-step progress reloads/re-enters safely and exposes only the current authorized step/actions.
- Combat/Boss return reconciles current run/frontier/modifiers.
- Terminal completion/failure leaves no stale actionable run state.
- Hidden effects, weights, tuning, unrevealed content, and server-private authored configuration remain absent from browser projection.
- Compact, Standard, Wide, and portrait-gate behavior remain consistent with canonical client architecture.

#### Test-suite rationalization

Audit the complete discovered backend and frontend suites before deleting anything. Classify tests by durable contract rather than by historical milestone.

Apply these rules:
- Keep deterministic domain/unit tests for algorithms and invariants.
- Keep auth/security/ownership, transaction rollback, idempotency, persistence-boundary, API-envelope/projection-secrecy, and authored-content structural/reference validation tests.
- Keep focused integration/walking-slice tests where they prove behavior spanning real architectural boundaries, but consolidate redundant copies of the same contract across multiple layers.
- Keep exact authored-content assertions only when the exact content shape is itself canonical product behavior. Farm's fixed five-node topology is an explicit example worth protecting.
- For flexible authored content such as catalogs and non-locked regional layouts, prefer semantic invariants over exact counts, full ID inventories, or incidental ordering.
- Remove obsolete prototype tests that are permanently skipped and whose behavior is already covered by accepted vNext tests from completed milestones. Git history is sufficient migration history.
- Retain prototype tests only when they are still useful migration evidence for not-yet-implemented Milestones 11-13; make their transitional status explicit and keep them out of the canonical passing regression expectation where appropriate.
- Replace brittle assertions such as total ability/unit/encounter/item counts or unrelated exact catalog lists with targeted existence/reference/behavior invariants.
- Do not weaken security, transaction, idempotency, secrecy, graph-validity, or deterministic algorithm coverage merely to reduce suite size.
- Do not convert useful behavioral tests into broad snapshots.

Document the resulting disposition sufficiently that future agents know what belongs in the canonical suite and when remaining prototype migration evidence should be removed. Update `documentation/06-testing-release/00-testing-strategy.md` and/or `backend/tests/README.md` if needed so the policy is durable rather than chat-only.

#### Verification evidence

Run the applicable quality gates from `agent/QUALITY_GATES.md` after cleanup. Report:
- implementation/closure SHA;
- test inventory before and after rationalization, including executed/skipped totals;
- deleted/consolidated prototype test groups and why accepted vNext coverage replaces them;
- retained future-milestone prototype groups and their owning milestone;
- brittle content assertions replaced with durable invariants;
- focused integrated lifecycle/idempotency/reload/security/secrecy results;
- frontend RunScene/runtime regression results;
- deterministic responsive captures for important Milestone 10 states;
- full `npm run verify:package` result;
- confirmation no production Shrine/Chaos, Swamp/Frog Kin, Milestone 11+, or broad visual redesign was introduced.

#### Scope exclusions

Do not add production Shrine/Chaos to Farm or Mountains, Swamp/Frog Kin, new encounter families, new modifier families, Knowledge/Codex/Objectives, onboarding/Mystic Cave, Milestone 11+ functionality, or broad visual redesign.

Do not promote Package 10 yourself. Leave Package 9 **In Progress** for architectural review.

#### Package 9 implementation evidence (review candidate)

- Audited all discovered backend and frontend test files before rationalization: **134 backend `*Test.php` files / 1,017 tests / 268 skips / 5,233 assertions**, and **90 frontend spec files / 562 executed tests / 0 skips**. The skipped backend cases depended on the retired prototype schema. Canonical suite after rationalization: **85 backend files / 749 executed tests / 0 skips / 5,242 assertions**, and **79 frontend spec files / 492 executed tests / 0 skips**. No previously executed backend test was removed.
- Removed **42** permanently skipped backend files for completed prototype Academy/Shop, team/unit, combat/claim/reward, run/pattern SQL, progression, Wrong Machine, diagnostic, and old API/profile contracts. Accepted vNext command, query, persistence, security, rollback, idempotency, content, and RunScene tests own those behaviors. Removed **10** superseded Angular gameplay/service spec files for completed Regions, Run, Wrong Machine, unit, and authored catalog replacements; Phaser/runtime and current backend suites cover their live contracts.
- Retained **7** prototype integration files outside canonical PHPUnit discovery in `backend/prototype-test-evidence/`: Bounty/Codex/objective and claim facts for Milestone 11; starter provisioning and Mystic Cave introduction for Milestone 12; old Chaos reels and Swamp generation ideas for Milestone 13. Retained the unrouted Angular Codex/Objective spec as Milestone 11 evidence in `frontend/prototype-test-evidence/`; its prototype catalog counts no longer gate current gameplay. Their migration status and removal triggers are documented in the evidence directories and testing strategy.
- Replaced incidental whole-catalog counts and Shop ID inventory assertions with targeted stable-reference, grant/projection, secrecy, and semantic Mountains branch/Hazard/dead-end checks. Kept Farm's exact five-node tutorial graph and Goblin promotion graph assertions because those shapes are accepted product contracts. Current authored JSON and gameplay mechanics were not changed.
- Focused backend validation/progression suites: **83 tests / 519 assertions passed**. Integrated Farm/Mountains, frontier, Rest/Hazard, test-only Shrine/Chaos, Combat/Boss, modifier, contextual-step, retry/reload, ownership, idempotency, persistence, and projection suites: **80 tests / 1,096 assertions passed**. A clean-baseline run exposed a modifier-test assertion comparing different unit instances; the assertion now uses the persisted battle manifest identity, and its focused suite passed **12 tests / 109 assertions**. Focused RunScene/map/contracts/GameStore frontend suites: **95 tests passed**.
- Deterministic RunScene captures passed and were visually inspected: branching, production Hazard, and test-only contextual persisted step in **Compact, Standard, Wide**; modifier and production Rest in Standard; portrait rotate-device gate (**12 captures total**). Test-only contextual fixture content remained outside production Farm/Mountains authored content.
- `npm run verify:package`: **passed** on the settled tree with LLM/context, docs lint, content validation (revision `9139c5f2865d1b1cded64e98fbf41a7549580f623aa2b8662f60bb034fcabcf7`), backend **749 tests / 5,242 assertions / 0 skips**, frontend **492 tests**, frontend production build, bundle check, and diff whitespace check. An initial attempt stopped at the issue schema check because this issue lacked its required `Problem` heading. A clean-baseline attempt exposed the modifier-test fixture mismatch above; both were corrected before the final passing run.
- No production Shrine/Chaos, Swamp/Frog Kin, Milestone 11+ feature, or broad visual redesign was introduced. Package 9 remains **In Progress** for architectural review; Package 10 was not promoted.
