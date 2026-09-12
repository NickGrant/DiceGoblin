# Active Execution Issue

`ISSUES.md` contains only the current execution-ready package. Future packages stay summarized in `agent/MILESTONES.md` and the roadmap until promoted. Do not implement them early.

## Milestone 1 - Walking Skeleton

### Verify and close the vNext walking skeleton

**Status:** In Progress
**Priority:** High

#### Problem
Milestone 1 now has all intended implementation slices: fresh vNext persistence, canonical authored content and client projection, authenticated bootstrap, persistent Phaser `/game` runtime, compatibility-gated startup, authoritative Camp, and responsive/orientation behavior. Prove those approved pieces operate as one coherent walking skeleton, remove only live prototype wiring that is now conclusively superseded, and leave Milestone 1 technically complete and ready for manual user UAT. This is a closure/verification package, not a feature package and not the beginning of Warband.

#### Required Context
- `documentation/07-development-path/vnext-game-overhaul.md` — Milestone 1 outcome/exit criteria
- `documentation/07-development-path/vnext-phaser-client-architecture.md` — implemented Angular/Phaser, startup, state-cache, responsive, and orientation boundaries
- `documentation/07-development-path/vnext-prototype-code-disposition.md` — removal/reuse rules; Git history is the archive
- `documentation/07-development-path/vnext-authored-content-model.md` — canonical content/client projection boundary
- `documentation/07-development-path/vnext-storage-model.md` — fresh vNext persistence baseline
- `documentation/07-development-path/vnext-api-contract-model.md` and `vnext-endpoint-inventory.md` — auth/bootstrap contracts
- `agent/QUALITY_GATES.md` and current root `package.json` scripts
- Current backend baseline/auth/bootstrap/content implementation and tests
- Current Angular shell/routes/session initialization and `frontend/src/app/game/` runtime/startup/Camp/responsive implementation

Load other decision docs only if verification reaches their domain.

#### Acceptance Criteria
- Treat the eight previously approved Milestone 1 packages as established architecture. Do not redesign them during closure unless verification exposes a genuine defect.
- Prove the intended real path coherently: authenticated Angular shell -> `/game` -> one persistent Phaser runtime -> generated client-safe content -> authenticated `GET /api/v1/game/bootstrap` -> MySQL-backed player state -> exact content-revision compatibility -> runtime `GameStore` -> authoritative responsive Camp.
- Prove a fresh/empty vNext test database initializes from the current baseline without replaying the prototype migration chain, SQL-authored gameplay catalogs, or read-side provisioning. Verify the expected Milestone 1 tables/contracts and local/external account creation/player-state initialization behavior through existing tests/gates.
- Prove canonical authored JSON validates, the generated browser projection is current, server-private fields do not leak through the client projection, and client/server content revisions agree in successful startup.
- Prove authenticated bootstrap remains read-only and preserves approved semantics including missing-state integrity failure, derived Energy max/rate, deterministic regeneration, and legitimate overcap Energy.
- Prove successful runtime startup performs the required content/bootstrap reads once, hydrates application-lifetime content/store state, and reaches Camp only after exact revision compatibility. Preserve controlled unauthorized, malformed-content/bootstrap, request-failure, and content-mismatch paths.
- Prove Camp renders only authoritative Milestone 1 state (identity, Teeth, Raw Chaos, Energy) and preserves values such as `57 / 50` across resize/orientation changes.
- Prove Compact, Standard `1600 x 900`, and Wide landscape layouts plus touch-first portrait gating using the stable deterministic capture viewports documented in the Phaser architecture. Review captures for clipping, overlap, unsafe-edge placement, stretched assets, status loss, and obvious regressions.
- Prove portrait gating blocks interaction without destroying/recreating `GameRuntime`, startup, `GameStore`, content registry, or active Camp; returning to landscape reflows/resumes without content/bootstrap refetch.
- Reconcile the live Angular routing boundary now that `/game` is proven. Authenticated default gameplay entry must resolve to `/game`, and obsolete Angular gameplay routes must not remain an active parallel game client. Retain public/auth/account/debug/tooling routes that still have a current shell purpose. Do not route Phaser gameplay back into prototype Angular pages.
- Remove the live shell dependency on the prototype gameplay profile for normal vNext authenticated startup. Angular session initialization should establish shell/auth identity without calling obsolete `/profile` gameplay state; Phaser bootstrap remains the gameplay state authority. Preserve login/logout/password-reset/session-expiration behavior needed by the platform shell.
- Do not mass-delete prototype gameplay page/service/test source merely because its live routes/orchestration are retired. Keep source/assets/tests that are still migration evidence or reuse candidates for Milestones 2+ until their owning package extracts/replaces them. Delete only implementation that has no remaining live dependency or future reuse value and whose replacement is proven.
- Inspect guards, command controls, route-audio/orientation wiring, profile synchronization, and other prototype shell composition touched by route retirement. Remove active gameplay-only composition that becomes unreachable/obsolete, but do not turn closure into broad aesthetic or architectural cleanup.
- Ensure `/game` does not trigger prototype gameplay API calls as a side effect of Angular shell startup. The expected network ownership is Angular session/auth where needed, then Phaser client content/bootstrap for gameplay.
- Add or adapt focused integration/regression coverage for the closure changes, especially authenticated default routing, prototype gameplay route retirement, auth-only shell initialization, `/game` ownership, and any stale live dependency removed. Prefer existing test/capture infrastructure; do not build a general E2E framework unless a small addition is necessary to prove the walking slice.
- Where practical with the repository's existing local/Docker tooling, exercise the walking slice against the real PHP/MySQL backend rather than relying exclusively on mocked browser fixtures. If environment limitations prevent a real-browser cross-stack check, report the limitation explicitly and rely on the strongest existing backend integration + frontend runtime/capture evidence rather than claiming an end-to-end result that did not run.
- Run the complete applicable Milestone 1 gates. `npm run verify:full` is the baseline aggregate gate; additionally run fresh DB reset/provision/backend integration paths and deterministic responsive captures as required by `agent/QUALITY_GATES.md`. Use Docker variants where that is the repository-supported way to prove MySQL behavior.
- Review failures rather than blindly changing product behavior to make tests green. New Milestone 1 failures block closure; clearly pre-existing unrelated failures must be reported with evidence.
- Reconcile active documentation only where implementation/closure changed durable behavior. Do not create a completed-roadmap archive, UAT report, legacy-reference tree, or dated closure document merely to preserve history; Git history remains the archive.
- Do not implement Milestone 2 Warband, units/dice/squad persistence, lazy domain queries, gameplay navigation, region/run creation, combat, economy, or later systems.

#### Completion
Run and report the meaningful applicable commands, including `npm run verify:full`, fresh vNext database verification using the repository-supported test/Docker commands, and deterministic Phaser captures for Compact landscape, Standard `1600 x 900`, Wide landscape, and touch-first portrait gating. Verify the resulting authenticated/default route and `/game` network ownership after retiring live prototype gameplay routing/profile coupling.

Leave this package **In Progress** for final architectural review. Do not mark Milestone 1 complete and do not begin Milestone 2 in the same coding-agent pass. After this package is reviewed and approved, planning will mark Milestone 1 technically complete and the resulting build will be handed to the user for manual UAT.
