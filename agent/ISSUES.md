# Active Execution Issue

## Milestone 10 - Run Encounter Depth

### Milestone 10 Package 9 - Run-encounter-depth integrated verification/technical closure

**Status:** In Progress
**Priority:** High

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
