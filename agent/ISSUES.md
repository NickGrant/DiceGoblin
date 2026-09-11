# ISSUES FILE
----
Active vNext issues only. Prototype/demo issues were intentionally removed from this branch's active context; Git history retains them.

## Milestone 1 - Walking Skeleton

Milestone 1 is intentionally sequential. Complete these packages in order unless the user explicitly reprioritizes them. Do not pull Milestone 2+ gameplay into the skeleton merely because a future bootstrap field or architecture document mentions it.

### Establish the fresh vNext database baseline

**Milestone:** Milestone 1 - Walking Skeleton
**Status:** Open
**Priority:** High

#### Problem
The current migration chain represents the prototype and includes authored catalogs and runtime structures that vNext explicitly rejected. The first implementation step needs a clean database path that supports authentication and the minimum mutable player state required by the walking skeleton without prematurely implementing later gameplay domains.

#### Acceptance Criteria
- A completely empty MySQL database can be initialized through a vNext migration/baseline path without replaying the prototype migration chain.
- Prototype authored-catalog tables such as regions, unit types, dice definitions/affixes, enemies, loot tables, and similar static content are not recreated in MySQL.
- The baseline includes only account/authentication persistence required by the accepted active authentication flows and the minimum `user_state` persistence required by bootstrap.
- `user_state` supports Teeth, Raw Chaos, current Energy, Energy regeneration timing, and monotonic `player_revision`; values that belong to later domains are not modeled early merely to make the schema look complete.
- Account creation/provisioning establishes required player state through an authoritative mutation path; `GET /api/v1/game/bootstrap` will not need to create missing state as a side effect.
- Existing authentication/session behavior required to reach an authenticated `/game` route continues to work against the fresh schema.
- Automated backend/database verification proves initialization from an empty database and basic account/player-state persistence.
- Unit, dice, squad, run, battle, reward, objective, Codex, inventory, Shop, Academy, and Wrong Machine persistence remains deferred unless a table is strictly required by the authentication/player-state baseline.

### Establish the authored content registry and client projection

**Milestone:** Milestone 1 - Walking Skeleton
**Status:** Blocked
**Priority:** High
**blocked_by:** Establish the fresh vNext database baseline

#### Problem
vNext needs to prove its JSON-authored-content boundary before gameplay systems are rebuilt. The server requires a normalized registry and validation path, while Phaser must receive only explicitly allowlisted public content and a compatible content revision.

#### Acceptance Criteria
- A canonical Git-tracked JSON content location and loading convention exists for vNext.
- The PHP `ContentRegistry` loads and normalizes the minimal real authored content required by the walking skeleton; do not bulk-port prototype catalogs or invent a large speculative catalog.
- Structural validation catches malformed definitions and duplicate/invalid stable IDs relevant to the initial content set.
- A client-projection build/path is deny-by-default: only explicitly allowlisted fields/definitions can enter the browser-facing projection.
- Automated verification proves that a newly added/non-allowlisted server field does not appear in the client projection.
- Backend and client projection expose the same deterministic content/build revision or manifest hash.
- Content validation/projection can run in normal local/CI verification without requiring authored JSON to be duplicated into MySQL.
- Generated artifacts, if any, are treated as build output rather than a second manually maintained source of truth.

### Implement the vNext game bootstrap query

**Milestone:** Milestone 1 - Walking Skeleton
**Status:** Blocked
**Priority:** High
**blocked_by:** Establish the fresh vNext database baseline; Establish the authored content registry and client projection

#### Problem
Phaser needs one authoritative entry query that proves the new HTTP -> application query -> repository/content-registry boundaries and provides enough state to enter Camp without reviving the prototype catch-all profile model.

#### Acceptance Criteria
- `GET /api/v1/game/bootstrap` exists and requires the accepted authenticated cookie/session context.
- The controller is a thin HTTP adapter and delegates bootstrap composition to the application/query layer rather than querying PDO or implementing game rules itself.
- Bootstrap returns the accepted Milestone 1 subset: account summary, Teeth, Raw Chaos, current/calculated-max Energy and regeneration timing, `player_revision`, session/CSRF metadata needed by the client, and server content revision.
- Bootstrap returns explicit empty/null representations for accepted domains not implemented yet, including unlocks/progression summary, active squad, and active run, rather than creating placeholder persistence for Milestones 2-3.
- Calculated values such as Energy maximum come from the appropriate authored/domain rule rather than being duplicated as mutable database state.
- The query is read-only; missing required player state is reported as an integrity/provisioning error rather than silently created by GET.
- Unauthorized behavior and the successful fresh-account bootstrap path are covered by backend tests.
- No `/profile`-style catch-all response, prototype compatibility translation, or gameplay mutation is introduced.

### Mount the persistent Phaser runtime at `/game`

**Milestone:** Milestone 1 - Walking Skeleton
**Status:** Blocked
**Priority:** High
**blocked_by:** Implement the vNext game bootstrap query

#### Problem
The accepted client architecture requires Angular to become a platform host at `/game`, with Phaser owning gameplay after mount. The repository currently has an Angular application but no vNext Phaser runtime root.

#### Acceptance Criteria
- Phaser is an explicit frontend dependency and a vNext game-client source boundary exists separate from Angular page/component gameplay code.
- Authenticated navigation to `/game` renders a dedicated Angular `GameHost`-style surface and mounts exactly one Phaser runtime.
- The Angular host owns runtime creation/destruction and route lifecycle only; it does not fetch bootstrap/gameplay data or maintain gameplay state on Phaser's behalf.
- Leaving `/game` destroys the Phaser runtime cleanly without leaving duplicate canvases/listeners; returning creates one fresh runtime.
- Existing website/auth/account responsibilities remain Angular-owned.
- Prototype Angular gameplay pages/services are not copied into the new runtime and are not treated as the architecture to preserve.
- Route/host lifecycle behavior is covered by frontend tests where practical.

### Implement Phaser startup state and the compatibility gate

**Milestone:** Milestone 1 - Walking Skeleton
**Status:** Blocked
**Priority:** High
**blocked_by:** Mount the persistent Phaser runtime at `/game`; Establish the authored content registry and client projection; Implement the vNext game bootstrap query

#### Problem
The persistent Phaser application needs the minimum application-level infrastructure to load public content, obtain authoritative bootstrap state, cache it, verify compatibility, and enter `GameScene` without turning scenes into service containers.

#### Acceptance Criteria
- A long-lived game runtime owns the API client, bootstrap/player-state cache, client content registry, runtime configuration, and navigation/lifecycle services needed by the slice.
- Boot/loading lifecycle loads the client-safe content projection and calls `GET /api/v1/game/bootstrap` directly from Phaser-owned infrastructure.
- The client stores the returned `player_revision` and treats server state as authoritative cache data rather than mutating durable state optimistically.
- Client and server content revisions are compared before normal gameplay begins.
- A revision mismatch blocks entry into normal gameplay and presents a clear reload/update path; incompatible catalogs are never silently used together.
- Authentication/bootstrap/content-load failures produce a controlled startup error state rather than an unhandled blank canvas.
- Successful startup transitions into the persistent `GameScene`; application-level runtime services survive screen changes and are not recreated by the scene.
- The implementation does not introduce `RunScene`, `BattleScene`, Warband data loading, or other later-milestone behavior beyond interfaces/placeholders genuinely needed for the runtime boundary.

### Render the minimal authoritative Camp

**Milestone:** Milestone 1 - Walking Skeleton
**Status:** Blocked
**Priority:** High
**blocked_by:** Implement Phaser startup state and the compatibility gate

#### Problem
The walking skeleton is not complete until the new architecture produces a real gameplay surface. Camp should prove that Phaser can render meaningful game presentation from authoritative bootstrap state without prematurely implementing the Warband or other management systems.

#### Acceptance Criteria
- `GameScene` opens on a Camp screen/view owned entirely by Phaser.
- Camp visibly renders authoritative bootstrap information sufficient to prove the data path, including player identity and current Teeth, Raw Chaos, and Energy/current-max state.
- Presentation uses the retained Dice Goblins visual direction and existing appropriate assets where useful; it is a minimal game screen rather than a diagnostic JSON dump or recreated Angular card page.
- The Camp screen reads public presentation content through the client content registry where authored content is involved.
- No unit roster, dice inventory, squad editor, Academy, Shop, Wrong Machine, region/run flow, combat, reward, or onboarding functionality is implemented in this package.
- Re-entering/re-rendering the Camp does not refetch or recreate application-level runtime services unnecessarily.
- A deterministic fixture/debug path exists for rendering the Camp state without requiring unrelated gameplay progression, sufficient for responsive/visual verification.

### Implement responsive landscape host behavior

**Milestone:** Milestone 1 - Walking Skeleton
**Status:** Blocked
**Priority:** High
**blocked_by:** Render the minimal authoritative Camp

#### Problem
The new Phaser architecture establishes responsive and mobile rules at the runtime level. Those rules need to be proven now so later screens do not bake in desktop-only assumptions.

#### Acceptance Criteria
- Phaser uses `1600 x 900` as the reference composition while mapping logical space to the available physical viewport without independently stretching game objects.
- The Camp remains usable in representative Compact landscape, Standard/reference `1600 x 900`, and Wide landscape viewports.
- Important Camp UI is positioned using safe edges/anchors/layout regions rather than arbitrary per-device coordinates.
- Device/browser safe insets are respected for critical interactive/status UI.
- Mobile portrait blocks/obscures gameplay with a full-screen rotate-device presentation rather than attempting a portrait gameplay layout.
- Rotating portrait -> landscape restores the same live Phaser runtime/state and performs resize/reflow rather than rebooting or re-bootstraping the game.
- Desktop portrait/narrow-window behavior is sensible and does not incorrectly depend on mobile orientation APIs.
- Responsive/orientation behavior has automated coverage where practical plus deterministic visual checks for Compact, reference, and Wide layouts.

### Verify and close the walking skeleton

**Milestone:** Milestone 1 - Walking Skeleton
**Status:** Blocked
**Priority:** High
**blocked_by:** Implement responsive landscape host behavior

#### Problem
Milestone 1 establishes architectural precedents used by every later vertical slice. It should close only after the entire path works from a fresh database and the implementation agrees with the accepted vNext decisions.

#### Acceptance Criteria
- A fresh-database verification proves: create/authenticate a player -> enter `/game` -> mount Phaser -> load compatible client content -> fetch authoritative bootstrap -> reach Camp.
- Content structural/semantic validation and client-projection allowlist/secrecy checks pass.
- Backend bootstrap tests, frontend host/runtime tests, and normal backend/frontend build/test gates pass.
- Camp is visually checked at Compact landscape, `1600 x 900`, and Wide landscape, and portrait mobile shows the rotate-device gate.
- Content-version mismatch is explicitly tested and prevents normal gameplay.
- No implementation added a second gameplay authority in Angular, a MySQL-authored catalog, a prototype `/profile` dependency, or gameplay systems assigned to Milestone 2+.
- Documentation/agent context is updated only for architecture that actually shipped; accepted vNext decisions are amended if implementation required an intentional architectural change.
- On successful completion, mark Milestone 1 complete and make Milestone 2 - Warband the next planning target rather than beginning it implicitly in the same change.
