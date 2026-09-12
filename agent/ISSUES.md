# Active Execution Issue

`ISSUES.md` contains only the current execution-ready package. Future packages stay summarized in `agent/MILESTONES.md` and the roadmap until promoted. Do not implement them early.

## Milestone 2 - Warband

### Establish Phaser Warband navigation and lazy read/cache surfaces

**Status:** Open
**Priority:** High

#### Problem
The authoritative Warband backend is now complete through owned reads, squad lifecycle, unit rename, and atomic unit loadout/dice configuration, but the persistent Phaser runtime still exposes only Camp. Establish the first real between-run gameplay navigation and lazy Warband client domains so players can move from Camp into a read-only Warband screen and inspect their owned units, dice, saved squads, and active-squad state without restoring Angular gameplay pages or eagerly loading the entire collection during startup.

#### Required Context
- `documentation/07-development-path/vnext-phaser-client-architecture.md` — persistent runtime, GameScene screen model, Phaser navigation, lazy authoritative cache, responsive layout, visual verification
- `documentation/07-development-path/vnext-api-contract-model.md` — authoritative query/cache boundary and `player_revision`
- `documentation/07-development-path/vnext-endpoint-inventory.md` — approved unit/dice/squad query contracts
- `documentation/07-development-path/vnext-authored-content-model.md` — client projection/stable-ID resolution and browser exposure
- `documentation/02-systems/warband-and-formation.md` — squads and fixed nine-position formation
- `documentation/02-systems/ability-loadouts-and-dice-binding.md` — read-only context only; editing remains Package 8
- current `RuntimeApiClient`, `GameStore`, `ClientContentRegistry`, `GameRuntime`, viewport/orientation infrastructure, `GameScene`, and `CampScreen`
- approved Package 3 read endpoints and Package 4 bootstrap active-squad contract

Inspect retained prototype Angular Warband presentation only for useful information hierarchy or asset references. Do not preserve Angular services/page ownership or page-oriented routing.

#### Architectural Boundary
- Phaser remains the only gameplay client inside `/game`.
- Warband is a screen/view owned by the existing persistent `GameScene`, not a new Phaser Scene and not an Angular route/component.
- Camp and Warband navigation must not recreate `GameRuntime`, `GameStore`, `ClientContentRegistry`, `RuntimeStartup`, API client, Phaser canvas, or bootstrap state.
- Do not introduce Angular gameplay state/services as an intermediary.
- Do not fetch through Angular services. Phaser/runtime infrastructure calls the PHP endpoints directly with the established browser session.
- This package is read/navigation only. Do not invoke any Package 4/5 mutation endpoint from gameplay UI yet.

#### Gameplay Navigation
Add the minimum persistent-runtime navigation model needed for ordinary `GameScene` screens.

For this package it must support at least:
- `camp`
- `warband`

Requirements:
- Camp provides a clear Phaser-owned affordance to open Warband.
- Warband provides a clear Back/Return-to-Camp action.
- Escape/back input on Warband returns to Camp where supported by the current input model.
- Navigation is logical Phaser state, not Angular router navigation and not a browser reload.
- Maintain a small screen-history/navigation abstraction suitable for later GameScene destinations rather than hard-wiring Camp/Warband visibility in unrelated runtime code.
- Do not implement future destinations merely to fill an enum/menu.
- Entering/leaving Warband must not refetch bootstrap or client authored content.

#### Lazy Warband Domains
Extend the framework-neutral `GameStore` with independent authoritative cache domains for:
- unit summaries;
- owned dice;
- saved squads.

Each domain must distinguish states equivalent to:
- not loaded;
- loading;
- fresh;
- stale;
- error.

Use a compact typed representation; do not create a state-management framework for its own sake.

Rules:
- Initial game startup/bootstrap does **not** fetch full units/dice/squads collections.
- First entry to Warband requests only domains not already fresh.
- Returning Camp -> Warband while all three domains remain fresh must use the existing cache and make no duplicate query requests.
- Concurrent/repeated requests for the same loading domain must not fan out duplicate HTTP calls.
- A failed domain enters error state without discarding valid fresh data from unrelated domains.
- Retry can reload an errored/stale domain deliberately.
- `GameStore.clear()` clears the lazy Warband domains as well as bootstrap state.
- Bootstrap `active_squad` remains the initial authoritative active-squad snapshot and is not replaced by an eager `/squads` call during startup.
- Package 7/8 mutation responses will later replace/mark affected cache slices; do not implement speculative mutation reconciliation now.

#### Runtime API Client
Extend the framework-neutral runtime API client for:
- `GET /api/v1/units`
- `GET /api/v1/dice`
- `GET /api/v1/squads`

Preserve:
- `credentials: include`;
- JSON Accept headers;
- existing API base URL behavior;
- unauthorized/network/HTTP/malformed-response distinction.

Do not add `/profile` or any catch-all fetch.

Add strict parsers/contracts for the three response envelopes rather than casting `unknown` API data directly into store state.

#### Unit Summary Client Contract
Accept exactly the current authoritative collection semantics from Package 3:
- `id`
- `display_name`
- `unit_type_id`
- `kin_id`
- `level`
- `xp`
- active lifecycle state

Validate positive instance IDs, non-empty names/IDs, numeric level/XP constraints, and expected lifecycle value.

Resolve static presentation from `ClientContentRegistry` using `unit_type_id` and `kin_id`.

A query referencing projected authored content that the packaged client does not possess is a client-domain integrity/error state, not a placeholder invented by the UI.

Do not fetch unit detail in this package.

#### Dice Summary Client Contract
Accept exactly the current Package 3 dice summary semantics:
- die instance ID;
- size;
- profile ID;
- active lifecycle state;
- authoritative binding summaries.

Binding summaries contain the owning binding's:
- unit ID;
- ability ID;
- slot index.

Resolve material/rarity/aspect/presentation through projected `dice_profile` and related authored definitions rather than expecting those values from PHP.

Validate projected references and die-size compatibility using the safe client content already available.

Do not create an `equipped` boolean as a second authority.

#### Squad Summary Client Contract
Accept the Package 3 collection shape:
- squad ID;
- name;
- `is_active`;
- exactly nine formation entries, each unit ID or `null`.

Validate:
- positive IDs;
- non-empty bounded name;
- nine-position shape;
- no duplicate non-null unit IDs within one squad;
- at most one returned squad marked active;
- if bootstrap has an active squad and the squads domain is loaded, the active identity must agree with bootstrap/current authoritative state at the same known `player_revision`.

Do not silently repair disagreement. Treat unexplained disagreement as stale/integrity state that must be deliberately refreshed/reconciled.

Package 7 will own mutations and active-squad cache reconciliation.

#### Player Revision
Retain the latest authoritative `player_revision` from bootstrap.

These collection GETs do not currently return a separate revision and must not invent one.

Do not implement optimistic locking.

Do not refresh bootstrap merely because Warband was opened.

Keep the store architecture capable of marking lazy domains stale when a future authoritative mutation returns a later revision.

#### Warband Screen
Create a read-only Phaser `WarbandScreen` (or equivalent screen component) owned by `GameScene`.

It must present, at minimum:
- clear Warband identity/title;
- owned-unit roster;
- owned dice inventory summary;
- saved squads summary;
- which squad is active;
- active squad's nine-position formation in an understandable read-only form;
- loading state;
- empty state for a legitimate fresh/no-assets account;
- error/retry state for lazy-domain failures;
- Back/Return to Camp.

The screen should use projected authored names/art keys/descriptions where they improve comprehension rather than displaying raw stable IDs as primary labels.

Instance IDs/stable IDs may be available for debug instrumentation but should not dominate player-facing presentation.

Do not implement:
- squad editing;
- squad create/delete/activate controls;
- unit rename controls;
- ability loadout controls;
- die assignment controls;
- unit-detail API fetch;
- final art-direction overhaul.

A row/card may be made visually selectable only if needed to prove navigation architecture, but do not navigate to an unfinished unit editor or expose dead controls. Package 8 owns unit-detail/configuration flow.

#### Information Architecture
Do not require three separate Phaser Scenes for Units/Dice/Squads.

This package may present the Warband information using tabs, sections, panels, or another compact GameScene-screen composition. Keep the implementation simple and suitable for later Package 7/8 configuration flows.

The architectural requirement is that all three lazy domains are understandable and reachable from the Warband experience without returning to Angular.

Do not overfit the visual structure before the deferred game-wide visual overhaul.

#### Responsive Behavior
Use the established viewport/safe-region infrastructure.

Warband must remain functionally usable in:
- Compact landscape `844 x 390` touch/mobile;
- Standard `1600 x 900`;
- Wide `2560 x 1080`.

Requirements:
- no important controls outside safe bounds;
- no clipped roster/dice/squad content;
- readable labels/resource summaries;
- intentional handling of content that exceeds one viewport, using Phaser-owned paging/scrolling/section switching as appropriate rather than browser-page scrolling;
- portrait remains handled only by the existing runtime orientation gate;
- rotating/resizing must preserve the active logical screen and loaded cache state.

Do not create arbitrary per-resolution coordinates when normal region/anchor layout solves the problem.

#### Visual Posture
Milestone 1 UAT deliberately deferred the major visual/UI overhaul.

For this package:
- match the current Camp/game visual language enough to feel coherent;
- prioritize hierarchy, readability, responsive behavior, and interaction clarity;
- reuse existing assets where suitable;
- do not spend scope attempting final production visual fidelity;
- do not invent a separate finished design system solely for Warband.

The later game-wide visual pass remains authoritative for final polish.

#### Failure Behavior
A Warband domain failure must not destroy the runtime or erase Camp/bootstrap state.

Provide a usable retry path.

Unauthorized responses should use an explicit runtime-auth failure path consistent with existing API/startup behavior; do not render server exception text.

Malformed data or missing projected references should fail the affected domain safely and visibly rather than crashing Phaser.

Do not silently swallow errors and show a false empty collection.

#### Deterministic Capture/Debug Support
Extend the current deterministic scene/screen capture tooling enough to render a populated Warband fixture without requiring manual gameplay.

Add a stable capture alias/state for Warband if that is how the current tooling exposes screens.

The deterministic presentation fixture may be client-side for visual capture, but it must conform to the real approved API/client-content contracts. Do not create a second gameplay content model for screenshots.

Do not expose production player-facing debug controls.

#### Tests
Add focused frontend tests proving at minimum:
- runtime API client calls units/dice/squads directly and uses credentials;
- strict valid parsing for all three collection contracts;
- malformed envelopes/rows/references fail safely;
- Warband domains begin not-loaded after bootstrap;
- first Warband entry lazy-loads required domains;
- returning to Warband while fresh does not refetch;
- concurrent requests deduplicate per domain;
- one domain failure does not erase unrelated fresh domains;
- retry reloads an errored domain;
- `GameStore.clear()` clears Warband cache;
- Camp -> Warband -> Camp navigation stays inside the same `GameScene` and persistent runtime;
- navigation does not refetch bootstrap/client content;
- active screen survives responsive reflow and touch-first portrait gate/resume;
- populated Warband screen resolves authored presentation from ClientContentRegistry;
- empty account state is distinct from loading/error;
- active squad/formation is represented correctly;
- no mutation endpoints are called by Package 6 UI;
- existing Camp/startup/orientation tests remain green.

Prefer framework-neutral unit tests for API/parser/cache behavior plus focused Phaser screen/runtime tests for navigation and reflow.

#### Visual Verification
Generate deterministic Warband captures for:
- Compact `844 x 390` touch/mobile;
- Standard `1600 x 900`;
- Wide `2560 x 1080`.

Visually inspect them for:
- clipping;
- overlap;
- unreadable labels;
- unsafe-edge placement;
- broken long-content handling;
- malformed active-squad formation;
- raw IDs leaking as primary player-facing labels;
- obviously broken scaling.

Do not require a new portrait capture if the existing portrait-gate capture remains unchanged, but run the relevant orientation regression tests.

#### Explicitly Out of Scope
- Squad create/update/activate/delete UI — Package 7.
- Unit detail API consumption, rename UI, loadout/dice editor — Package 8.
- Any new backend mutation or persistence behavior unless a genuine Package 3-5 contract defect is discovered.
- Promotion/progression UI.
- Production starter onboarding.
- Shop, Academy, Wrong Machine, Regions, Codex, Objectives implementation.
- RunScene/BattleScene functionality.
- Milestone 3.
- Final visual/UI overhaul.

#### Completion
Run applicable gates from `agent/QUALITY_GATES.md`, including focused frontend/API/store/runtime tests, the full frontend suite, production frontend build/bundle check, and deterministic Warband captures at Compact/Standard/Wide. Run backend/content gates only if backend/content files are actually changed.

Where practical, exercise the real stack using an authenticated fixture-populated account to prove:

Camp -> Warband -> real `/units`, `/dice`, `/squads` -> rendered read-only state -> Camp

without bootstrap/content refetch or Angular gameplay routing.

Report whether that real-stack browser path actually ran.

Leave Package 6 **In Progress** for architectural review. Do not mark it complete, promote Package 7, or begin editing UI in the same coding-agent change.
