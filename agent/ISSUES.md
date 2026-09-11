# Active Execution Issue

`ISSUES.md` contains only the current execution-ready package. Future packages stay summarized in `agent/MILESTONES.md` and the roadmap until promoted. Do not implement them early.

## Milestone 1 - Walking Skeleton

### Establish Phaser startup state and content compatibility gate

**Status:** Open
**Priority:** High

#### Problem
Turn the approved persistent Phaser runtime into the authoritative vNext game-client startup boundary. Phaser must load the generated client-safe content projection and authenticated game bootstrap directly, establish minimal runtime-owned content/state caches, compare the client/server content revisions, and enter `GameScene` only when startup succeeds with compatible content. This package proves startup correctness without implementing Camp presentation or later gameplay domains.

#### Required Context
- `documentation/07-development-path/vnext-phaser-client-architecture.md` — Persistent Game Runtime, Client State Is a Cache, Client Authored Content Boundary, Content Version Compatibility
- `documentation/07-development-path/vnext-api-contract-model.md` — Game Bootstrap and Player Revision
- `documentation/07-development-path/vnext-endpoint-inventory.md` — `GET /api/v1/game/bootstrap`
- `documentation/07-development-path/vnext-authored-content-model.md` — generated client projection and revision boundary
- Current `frontend/src/app/game/` runtime/scenes/tests, generated `frontend/public/game-content.json`, framework-neutral runtime configuration, and approved bootstrap contract

Load other decision docs only if implementation reaches their domain.

#### Acceptance Criteria
- Keep Angular limited to authenticating/hosting `/game`; Angular services must not fetch bootstrap/content or relay gameplay state into Phaser. `GameRuntime` owns startup and communicates directly with the PHP API/browser content artifact.
- Introduce a small runtime-owned gameplay API client that uses the established deployment API-base configuration and browser cookie/session credentials. Do not make Phaser depend on Angular `ApiHttpService`, Angular DI, profile services, or prototype auth-recovery orchestration. Reuse/extract framework-neutral configuration behavior where appropriate rather than creating a competing deployment-config convention.
- Load the generated client projection from the packaged/public `game-content.json` artifact. Do not load canonical server JSON, duplicate authored content into TypeScript, or maintain a second manual client catalog.
- Establish a `ClientContentRegistry` (or equivalently clear runtime-owned content boundary) that validates/normalizes the generated projection needed by the current client and provides stable-ID lookup over the allowlisted public content. It must retain the projection revision used for compatibility checking; it must not infer or recreate server-only fields.
- Call authenticated `GET /api/v1/game/bootstrap` directly from runtime-owned client infrastructure and validate the Milestone 1 response shape sufficiently to fail startup safely on malformed/incomplete data. Preserve the approved bootstrap contract rather than introducing a parallel client-specific endpoint.
- Establish a minimal runtime-owned `GameStore`/cache for the authoritative bootstrap state: account, player state/Energy, session/CSRF metadata, `player_revision`, progression summary, active squad, active run, server time/content revision as applicable. This store is a cache of server state, not an Angular state service and not local gameplay authority.
- Compare the generated client projection revision with bootstrap `content_revision` before entering normal gameplay. Exact match is required for this package. A mismatch must block transition to `GameScene`; do not silently continue with inconsistent stable IDs/content.
- Model startup state explicitly enough to distinguish at least loading, ready, content-mismatch, and general startup failure. Boot/Loading lifecycle presentation may communicate these states inside Phaser. Do not implement Camp as a loading/error screen.
- On successful startup, the persistent runtime must retain the same API client, content registry, GameStore/cache, and startup state when transitioning from Boot/Loading into `GameScene`; scene transitions must not reconstruct/refetch application-level startup state.
- Do not persist bootstrap data in browser storage in this package. Re-entering `/game` may perform a fresh startup; ordinary scene transitions within the mounted runtime must not rerun startup unless a later explicit refresh/recovery mechanism requires it.
- Handle unauthorized/bootstrap HTTP failure, malformed client projection/bootstrap data, and content mismatch as controlled startup failures without leaking raw internal/server details to gameplay presentation. Do not invent a new authentication mechanism or Angular gameplay-state bridge to recover them.
- Do not implement automatic content-update/version negotiation, service workers, cache busting, retry loops, reconnect/multi-tab synchronization, or production reload strategy beyond a minimal safe user-facing reload/update affordance if needed to make mismatch recoverable. The key requirement is to block incompatible gameplay.
- Preserve the approved persistent-runtime scene boundaries. Successful startup enters the existing `GameScene`; `RunScene`/`BattleScene` remain placeholders. Do not create Camp/Warband/etc. as separate scenes.
- Do not implement Camp UI, gameplay navigation, units/dice/squads/runs, lazy domain queries, mutations, battle playback, asset bundles, final audio migration, responsive layout modes, safe insets, or mobile portrait gating in this package.
- Preserve the approved backend/bootstrap/content contracts unless a genuine blocking defect is discovered. Backend/schema/content-authoring changes are not expected for this package.
- Add focused deterministic frontend coverage for successful startup, direct bootstrap/content requests, cookie credentials/API base behavior, projection/bootstrap shape failure, exact revision match, mismatch blocking, runtime cache hydration, no duplicate/refetched startup across scene transitions, and controlled HTTP/auth failure.
- Ensure the normal frontend test/build/bundle path covers the new runtime client code and generated content contract.

#### Completion
Run applicable frontend/content/context/build gates from `agent/QUALITY_GATES.md`, including frontend tests, content validation/projection freshness, production frontend build, and bundle check. Leave this package active for architectural review; do not promote or begin the Minimal authoritative Camp package in the same change.
