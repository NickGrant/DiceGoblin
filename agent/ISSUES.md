# ISSUES FILE
----
Active vNext issues only. Prototype/demo issues were intentionally removed from this branch's active context; Git history retains them.

## Milestone 1 - Walking Skeleton

Milestone 1 is intentionally sequential. Complete these packages in order unless the user explicitly reprioritizes them. The prototype reuse/disposition audit is complete and recorded in `documentation/07-development-path/vnext-prototype-code-disposition.md`.

Before replacing a substantive prototype subsystem, inspect its current implementation/tests and apply the map's **Keep / Adapt / Rebuild / Retire** guidance. Do not pull Milestone 2+ gameplay into the skeleton merely because a future contract mentions it.

### Establish the fresh vNext database baseline

**Milestone:** Milestone 1 - Walking Skeleton
**Status:** Open
**Priority:** High

#### Problem
The current migration chain represents the prototype and includes authored catalogs and runtime structures that vNext rejected. Establish a clean database path for authentication and minimum bootstrap player state without throwing away reusable auth/session behavior or prematurely implementing later gameplay domains.

#### Acceptance Criteria
- Consult the prototype disposition map before replacing schema/auth persistence.
- A completely empty MySQL database initializes through a vNext baseline without replaying the prototype migration chain.
- SQL-authored gameplay catalogs such as regions, unit types, dice definitions/affixes, enemies, loot tables, or run-pattern catalogs are not recreated.
- Implement only accepted account/auth persistence plus minimum `user_state` required for the walking skeleton.
- `user_state` supports Teeth, Raw Chaos, current Energy, Energy regeneration timing, and monotonic `player_revision`; derived Energy max is not persisted.
- Account creation/provisioning establishes required player state through an authoritative mutation; read endpoints do not create missing state.
- Preserve applicable password/OAuth/session/CSRF/reset-token safety behavior identified as reusable while conforming storage to the accepted `users` / local credentials / external identities model.
- Existing authentication/session behavior needed to reach authenticated `/game` works against the fresh schema.
- Automated database/backend verification proves initialization from empty DB and account/player-state persistence.
- Unit, dice, squad, run, battle, reward, objective, Codex, inventory, Shop, Academy, Wrong Machine, and starter-gameplay provisioning remain deferred unless strictly required by auth/player state.

### Establish the authored content registry and client projection

**Milestone:** Milestone 1 - Walking Skeleton
**Status:** Blocked
**Priority:** High
**blocked_by:** Establish the fresh vNext database baseline

#### Acceptance Criteria
- Establish canonical Git-tracked JSON location/loading convention; no duplicate MySQL catalog.
- PHP `ContentRegistry` loads/normalizes only minimal real content needed by the skeleton.
- Structural validation catches malformed definitions and duplicate/invalid stable IDs in the initial set.
- Client projection is explicit allowlist/deny-by-default; new server-only fields cannot leak automatically.
- Automated test proves a non-allowlisted field is absent from the client projection.
- Backend and client projection expose the same deterministic content/build revision or manifest hash.
- Validation/projection work in normal local/CI flow; generated projections are build output, not a second authored source.
- Mine reusable build/validation infrastructure from the disposition map without restoring SQL catalog ownership.

### Implement the vNext game bootstrap query

**Milestone:** Milestone 1 - Walking Skeleton
**Status:** Blocked
**Priority:** High
**blocked_by:** Establish the fresh vNext database baseline; Establish the authored content registry and client projection

#### Acceptance Criteria
- `GET /api/v1/game/bootstrap` requires accepted cookie/session authentication.
- Thin controller delegates composition to application/query layer; no controller PDO/gameplay orchestration.
- Return Milestone 1 state: account summary, Teeth, Raw Chaos, current/calculated-max Energy and regen timing, `player_revision`, required session/CSRF metadata, and server content revision.
- Return explicit null/empty later-domain values for unlock/progression summary, active squad, and active run rather than implementing Milestones 2-3 storage early.
- Energy max derives from appropriate authored/domain rule rather than mutable DB state.
- Query is read-only; missing player state is an integrity/provisioning error rather than GET-side creation.
- Cover unauthorized and fresh-account success paths with backend tests.
- Do not reintroduce `/profile`, compatibility translation, or read-side mutation.

### Mount the persistent Phaser runtime at `/game`

**Milestone:** Milestone 1 - Walking Skeleton
**Status:** Blocked
**Priority:** High
**blocked_by:** Implement the vNext game bootstrap query

#### Acceptance Criteria
- Phaser is an explicit frontend dependency and vNext game-client source boundary exists separately from Angular gameplay pages.
- Authenticated `/game` renders a dedicated Angular host and mounts exactly one Phaser runtime.
- Angular host owns creation/destruction/route lifecycle only; it does not fetch bootstrap or maintain gameplay state for Phaser.
- Leaving `/game` destroys runtime cleanly; returning creates one runtime without duplicate canvases/listeners.
- Preserve/adapt Angular auth/platform shell and relevant reusable API/orientation/audio/debug infrastructure identified by the disposition map.
- Prototype Angular gameplay pages/services are not copied into the Phaser architecture.
- Cover route/host lifecycle behavior with frontend tests where practical.

### Implement Phaser startup state and the compatibility gate

**Milestone:** Milestone 1 - Walking Skeleton
**Status:** Blocked
**Priority:** High
**blocked_by:** Mount the persistent Phaser runtime at `/game`; Establish the authored content registry and client projection; Implement the vNext game bootstrap query

#### Acceptance Criteria
- Long-lived GameRuntime owns Phaser API client, bootstrap/player cache, client ContentRegistry, runtime configuration, and needed navigation/lifecycle services.
- Adapt existing request semantics where useful, but Phaser's gameplay API client is framework-neutral and not Angular-service-owned.
- Boot/loading loads client-safe projection and calls bootstrap directly through Phaser-owned infrastructure.
- Store `player_revision`; backend state is authoritative cache data.
- Compare client/server content revision before normal gameplay; mismatch blocks entry with clear reload/update flow.
- Auth/bootstrap/content failures render controlled startup error state rather than blank canvas.
- Successful startup reaches persistent `GameScene`; runtime services survive screen changes.
- Do not implement RunScene, BattleScene, Warband loading, or later gameplay beyond minimal interfaces/placeholders.

### Render the minimal authoritative Camp

**Milestone:** Milestone 1 - Walking Skeleton
**Status:** Blocked
**Priority:** High
**blocked_by:** Implement Phaser startup state and the compatibility gate

#### Acceptance Criteria
- `GameScene` opens on Phaser-owned Camp screen/view.
- Render authoritative player identity, Teeth, Raw Chaos, and Energy current/max to prove the full data path.
- Use retained visual direction and appropriate existing assets; this is a minimal game screen, not JSON/debug output or an Angular card page.
- Authored presentation data, where used, comes through client ContentRegistry.
- Do not implement roster, dice inventory, squad editor, Academy, Shop, Wrong Machine, regions/runs, combat, rewards, or onboarding.
- Re-rendering Camp does not recreate/refetch application-level runtime state unnecessarily.
- Provide deterministic fixture/debug state sufficient for visual/responsive verification, adapting existing debug/screenshot concepts where useful.

### Implement responsive landscape host behavior

**Milestone:** Milestone 1 - Walking Skeleton
**Status:** Blocked
**Priority:** High
**blocked_by:** Render the minimal authoritative Camp

#### Acceptance Criteria
- Use `1600 x 900` logical reference composition with proportional physical scaling and no independent object stretching.
- Camp remains usable at representative Compact landscape, reference `1600 x 900`, and Wide landscape viewports.
- Critical UI uses safe edges/anchors/layout regions and respects device safe insets.
- Mobile portrait obscures/blocks gameplay with full-screen rotate-device presentation rather than portrait gameplay layout.
- Portrait -> landscape restores same live runtime/state with resize/reflow, not reboot/re-bootstrap.
- Desktop narrow/portrait behavior is sensible and does not incorrectly rely on mobile APIs.
- Adapt existing viewport-orientation logic/tests where useful, but final ownership is GameHost/GameRuntime.
- Add automated coverage where practical and deterministic visual checks for Compact/reference/Wide.

### Verify and close the walking skeleton

**Milestone:** Milestone 1 - Walking Skeleton
**Status:** Blocked
**Priority:** High
**blocked_by:** Implement responsive landscape host behavior

#### Acceptance Criteria
- Fresh DB path proves: create/authenticate player -> `/game` -> Phaser mount -> compatible client content -> authoritative bootstrap -> Camp.
- Content validation/projection secrecy tests, backend bootstrap tests, frontend host/runtime tests, and applicable builds/checks pass.
- Visually verify Camp at Compact, `1600 x 900`, Wide, plus mobile portrait rotate-device state.
- Explicitly test content-version mismatch blocking gameplay.
- No second gameplay authority in Angular, MySQL-authored catalog, prototype `/profile` dependency, or Milestone 2+ gameplay was introduced.
- Reconcile `vnext-prototype-code-disposition.md` with what actually shipped; remove only prototype paths truly superseded by Milestone 1 and preserve later reuse candidates.
- Update documentation only for architecture that actually shipped; amend an accepted decision if implementation intentionally changes it.
- Mark Milestone 1 complete and make Milestone 2 - Warband the next planning target rather than beginning it implicitly.
