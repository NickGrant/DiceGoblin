# Active Execution Issue

`ISSUES.md` contains only the current execution-ready package. Future packages stay summarized in `agent/MILESTONES.md` and the roadmap until promoted. Do not implement them early.

## Milestone 1 - Walking Skeleton

### Mount persistent Phaser runtime at `/game`

**Status:** In Progress
**Priority:** High

#### Problem
Establish the accepted Angular/Phaser ownership boundary by adding one authenticated Angular `/game` host that creates a single persistent Phaser runtime for gameplay and destroys it only when the host is left. This package proves runtime ownership and lifecycle without beginning bootstrap/content startup orchestration, Camp, or responsive gameplay behavior.

#### Required Context
- `documentation/07-development-path/vnext-phaser-client-architecture.md` — Angular/Phaser Boundary Rule, Persistent Game Runtime, Scene and Screen Model, Phaser Navigation
- `documentation/07-development-path/vnext-game-overhaul.md` — Milestone 1 walking-skeleton architecture
- `documentation/07-development-path/vnext-prototype-code-disposition.md` — frontend/prototype reuse guidance
- Current Angular routing/root-shell/session implementation and frontend test/build configuration touched by the implementation

Load other decision docs only if implementation reaches their domain.

#### Acceptance Criteria
- Add Phaser as an explicit frontend dependency using the current supported Phaser 3 line unless the repository already establishes a compatible version during implementation. Do not load Phaser from a CDN or global script.
- Add authenticated Angular route `/game` using the existing session/auth guard behavior. Angular remains responsible for entering/leaving the route and hosting the runtime; do not add another authentication path.
- Add one narrow Angular game-host component whose gameplay responsibility is limited to providing the DOM mount point and creating/destroying the Phaser runtime with Angular lifecycle. It must not become a gameplay API/state/navigation orchestrator.
- Mount exactly one Phaser `Game` instance for one live host component. Angular change detection, route events, or ordinary Phaser scene/screen transitions must not create duplicate canvases or duplicate runtime instances.
- Destroy the Phaser instance and runtime-owned resources when the Angular game host is destroyed/left. Re-entering `/game` may create a fresh runtime; ordinary in-game navigation must not.
- Establish an intentional `GameRuntime` (or equivalently clear application-level runtime boundary) that owns the persistent Phaser application lifetime and is the future home for application-level API/cache/content/navigation/assets/audio/input/configuration/responsive concerns. Do not implement those later subsystems speculatively in this package.
- Establish the accepted scene boundaries needed to prove lifecycle: Boot/Loading setup as needed plus `GameScene`, `RunScene`, and `BattleScene`. These may be minimal placeholders in this package. Do not turn Camp/Warband/Shop/etc. into separate Phaser scenes.
- Prove that application-level runtime state/services survive transitions between gameplay scenes rather than being recreated with each scene. Use the smallest deterministic test/instrumentation needed to demonstrate the ownership boundary.
- The `/game` surface must be Phaser-owned presentation. Do not render the prototype Angular gameplay command controls, page chrome, gameplay status/loading cards, or prototype gameplay pages on top of/inside the Phaser host. Angular public/auth/account shell behavior outside `/game` must continue to work.
- Do not delete prototype Angular gameplay pages/routes/services merely because `/game` now exists. They remain migration/reuse evidence until their accepted Phaser replacements are proven by owning packages.
- Do not make Angular services fetch `/api/v1/game/bootstrap` or relay gameplay API state into Phaser. Phaser will communicate directly with PHP once startup/API work is implemented.
- Do not fetch or consume `game-content.json`, compare content revisions, call `/api/v1/game/bootstrap`, establish the real Phaser API client/GameStore/ClientContentRegistry, or implement mismatch/reload behavior in this package. Those belong to the next startup/content-compatibility package.
- Do not implement Camp gameplay/presentation, run flow, battle playback, domain screens, gameplay navigation history, asset bundles, final audio migration, responsive layout modes, safe-inset behavior, or the mobile portrait gate in this package unless a tiny lifecycle stub is strictly necessary to prove runtime mounting.
- Preserve the approved backend/bootstrap/content behavior. This package should not require backend API/schema changes.
- Add focused automated frontend coverage for the Angular host/runtime lifecycle and single-instance behavior, plus the smallest Phaser/runtime tests needed to prove persistent ownership across scene transitions. Keep tests deterministic and avoid requiring real backend state for this package.
- Ensure the normal frontend build/test path includes the new Phaser integration without introducing duplicate framework bundles or relying on browser globals.

#### Completion
Run applicable frontend/context/build gates from `agent/QUALITY_GATES.md`, including frontend tests and production build. Leave this package active for architectural review; do not promote or begin the Phaser startup/content-compatibility package in the same change.
