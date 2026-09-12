# Active Execution Issue

`ISSUES.md` contains only the current execution-ready package. Future packages stay summarized in `agent/MILESTONES.md` and the roadmap until promoted. Do not implement them early.

## Milestone 2 - Warband

### Establish Phaser squad editor and saved-squad lifecycle flows

**Status:** Open
**Priority:** High

#### Problem
The authoritative squad backend and the read-only Phaser Warband are approved. Players can inspect units, dice, saved squads, and the active formation, but cannot yet configure squads through the vNext client. Implement a Phaser-owned squad editor that uses the complete authoritative Package 4 squad commands, keeps edits local until server acceptance, and reconciles GameStore from mutation responses without a global bootstrap/profile refresh.

#### Required Context
- `documentation/07-development-path/vnext-phaser-client-architecture.md` — GameScene screens/navigation, client cache authority, responsive/safe-area rules
- `documentation/07-development-path/vnext-api-contract-model.md` — mutation authority, CSRF, idempotency, `player_revision`
- `documentation/07-development-path/vnext-endpoint-inventory.md` — accepted squad command contracts
- `documentation/02-systems/warband-and-formation.md` — multiple saved squads, nine positions, active-squad semantics
- approved Package 4 squad commands/bootstrap integration
- approved Package 6 `RuntimeApiClient`, Warband domain cache, parsers, navigation, and `WarbandScreen`

Do not restore prototype Team APIs or Angular Warband ownership.

#### Architectural Boundary
- Squad configuration is gameplay and remains inside Phaser under the persistent `GameScene`.
- Do not create a new Phaser Scene or Angular route/component for the editor.
- Extend the existing GameScene screen/navigation model only as needed for the concrete squad editor flow.
- Camp, Warband, and squad editing must share the existing `GameRuntime`, `RuntimeStartup`, `RuntimeApiClient`, `GameStore`, `ClientContentRegistry`, Phaser canvas, and viewport/orientation infrastructure.
- Do not refetch bootstrap or `game-content.json` to commit a squad mutation.
- Do not use `/profile`.

#### Player Flow
From the Warband squad experience, the player must be able to:
- create a new saved squad;
- open an existing saved squad for editing;
- edit its name;
- assign/remove/reposition owned active units across the fixed nine formation positions;
- save the complete configuration;
- activate a non-active squad;
- delete a squad subject to the Package 4 active-squad rules;
- return to Warband without losing authoritative cache state.

Use a concrete GameScene screen/subscreen such as a squad editor rather than embedding mutation orchestration into unrelated Camp/runtime code.

#### Local Draft Rule
Editing is local draft state until PHP accepts the complete command.

Requirements:
- Opening an existing squad clones its authoritative name/formation into an editor draft.
- Creating starts a local unsaved draft; no squad exists server-side until create succeeds.
- Formation/name changes update only the draft.
- Do not optimistically modify `GameStore` committed squads, bootstrap active squad, or `player_revision` while a command is pending.
- On command failure, preserve the draft and authoritative cache unchanged so the player can retry or revise it.
- On success, replace/reconcile committed cache only from the authoritative response.
- Back/Cancel with a dirty draft must not silently discard changes. Provide an explicit discard confirmation flow owned by Phaser.
- Prevent duplicate submissions while a mutation is in flight.

#### Squad Name Entry
Squad names are part of this editor and must support the accepted backend contract: normalized nonblank names up to 128 Unicode characters.

Provide a usable text-entry interaction for both keyboard and touch-first gameplay.

If native browser text input is used to obtain mobile virtual-keyboard behavior, it must be created/positioned/destroyed by the Phaser gameplay screen/runtime, remain scoped to the game host, respect orientation/responsive lifecycle, and not move gameplay ownership into Angular.

Do not use Angular forms or a separate page.

#### Formation Editing
Render the fixed 3x3 / positions `0-8` model explicitly.

Provide a simple touch-friendly placement interaction. Drag-and-drop is not required. A select-unit-then-select-position interaction is acceptable and may be preferable.

Rules:
- each position is `null` or one owned active unit ID;
- the same unit may occupy at most one position in the draft;
- moving an already-placed unit to another position must produce one final occurrence rather than duplicating it;
- removing a unit leaves the position empty;
- an entirely empty squad is valid;
- units may still belong to other saved squads; this editor changes only the selected squad;
- use authored unit/kin presentation from `ClientContentRegistry`, not stable IDs as primary labels.

Do not fetch full unit detail merely to place a unit. Package 6 summaries are sufficient.

#### Squad Mutations
Extend `RuntimeApiClient` for the approved Package 4 endpoints:
- create squad;
- replace complete squad configuration;
- activate squad;
- delete squad.

All mutation requests must:
- send credentials;
- send JSON where applicable;
- send the authoritative bootstrap CSRF token;
- use the accepted complete `{name, formation}` configuration;
- preserve existing runtime API error behavior without exposing server exception text.

Do not add per-slot mutation calls.

#### Create Idempotency
Create must send `Idempotency-Key`.

Client behavior must be safe when the network outcome is unknown:
- generate one opaque valid key for a create submission;
- if that exact pending create is retried because the response was lost/failed ambiguously, reuse the same key;
- once the create receives a definitive authoritative success, abandon that key;
- if the player materially changes the unsaved create draft after a failed attempt, use a new key for the new payload;
- repeated button presses must not generate concurrent creates.

Do not use idempotency keys for commands that do not require them.

#### Active Squad Lifecycle
Represent the accepted Package 4 behavior accurately:
- first created squad becomes active automatically;
- later creates preserve the existing active squad;
- activation is explicit;
- activating the already-active squad is a successful no-op and may return the same revision;
- deleting a non-active squad preserves the active squad;
- deleting the only active/remaining squad is allowed and leaves no active squad;
- deleting the active squad while another saved squad remains is forbidden until another squad is activated.

The UI should make this understandable and should avoid encouraging an obviously invalid active-squad deletion, but the backend remains authority and the client must still handle a server-side conflict safely.

Require an explicit deletion confirmation before issuing DELETE.

#### Authoritative Mutation Response Contract
Add strict client parsing for the existing Package 4 mutation responses rather than casting unknown JSON.

Validate at least:
- returned squad shape where present;
- normalized nine-position formation;
- returned active-squad ID/null;
- returned `player_revision`;
- deleted-squad ID for delete;
- consistency between the returned affected squad and active ID.

Malformed success responses are integrity failures and must not mutate committed GameStore state.

#### GameStore Reconciliation
Add narrow squad-mutation reconciliation methods; do not build a general Redux/event system.

After a valid authoritative response:
- update the cached `player_revision` to the server-returned revision;
- reject/regard as integrity failure an impossible revision regression;
- replace/append/remove the affected squad in a fresh squads cache as appropriate;
- update `isActive` flags from `active_squad_id`;
- keep the squads domain fresh when reconciliation is complete;
- preserve unrelated units/dice cache slices;
- synchronize the cached bootstrap `active_squad` snapshot so Camp/current state does not disagree with the freshly committed squad authority.

The squad editor is entered from a successfully loaded Warband, so fresh unit summaries are available. Use them to rebuild the compact bootstrap active-squad summary from the authoritative active formation when needed.

If an authoritative response cannot be reconciled safely against the current cache, do not invent missing state. Mark the appropriate domain stale/error and require a deliberate domain reload rather than silently fabricating data.

Do not perform a global profile refresh.

#### Revision Semantics
Use the response revision as the latest authoritative player revision.

Expected behavior from the approved backend:
- real create/update/activate/delete mutation advances revision exactly once;
- already-active activation may return the existing revision;
- failed mutation does not change the client revision;
- client must not increment revisions locally.

Do not introduce optimistic locking.

#### Warband Integration
Upgrade the existing Squads/Formation Warband surface with concrete controls to:
- create a squad;
- edit a selected saved squad;
- activate a selected non-active squad;
- delete where valid.

Do not add dead controls for Package 8 unit configuration.

Returning from a successful squad edit should show the authoritative updated squad state immediately from GameStore without refetching all Warband domains.

#### Failure/Submission States
Provide clear functional states for:
- saving/creating;
- activating;
- deleting;
- validation failure;
- network/HTTP failure;
- active-delete conflict;
- malformed/integrity response.

Do not translate server errors into a false successful local state.

Unauthorized mutation behavior should remain consistent with current runtime session handling and must not expose server messages.

#### Responsive Behavior
The editor must be usable at:
- Compact `844 x 390` touch/mobile;
- Standard `1600 x 900`;
- Wide `2560 x 1080`.

Requirements:
- 3x3 formation remains understandable and tappable;
- roster selection remains reachable with more units than fit at once via Phaser-owned paging/scrolling;
- Save/Cancel/Activate/Delete controls remain inside safe bounds;
- name editing remains usable in Compact/touch-first mode;
- no browser-page scrolling;
- resize/reflow preserves the local draft and selected squad/unit;
- portrait gate obscures gameplay without destroying draft or cache, and returning to landscape restores the editor.

#### Visual Posture
Do not perform the deferred game-wide visual overhaul.

Match current Warband enough for functional consistency. Prioritize interaction clarity, readable state, active/draft distinction, and responsive safety.

#### Deterministic Capture
Extend deterministic capture/debug support to render a representative populated squad-editor state.

Capture and visually inspect at least:
- Compact squad editor;
- Standard squad editor;
- Wide squad editor.

Use contract-valid deterministic fixture data. Do not create another gameplay model.

#### Tests
Add focused frontend tests proving at minimum:
- squad mutation API methods use direct runtime fetch, credentials, CSRF, and create idempotency key;
- strict mutation-response parsing rejects malformed responses;
- opening existing squad creates a draft without mutating cache;
- local name/formation edits do not alter committed GameStore;
- placement/move/removal preserves one-unit-per-position/one-position-per-unit draft semantics;
- Save issues one complete PUT and updates cache only after success;
- create issues one complete POST and first-create active response reconciles correctly;
- ambiguous create retry reuses the same idempotency key; changed create payload uses a new key;
- duplicate submission is blocked while pending;
- activation updates active flags/bootstrap snapshot/revision from response;
- already-active activation preserves revision correctly;
- delete updates cache correctly for non-active and last-active cases;
- active-delete conflict preserves cache/draft and is presented safely;
- server/network/parse failure preserves committed cache;
- dirty Back/Cancel requires explicit discard;
- GameStore reconciliation preserves units/dice caches;
- no bootstrap, game-content, or `/profile` refetch is used as mutation reconciliation;
- Warband -> squad editor -> Warband remains inside the same GameScene/runtime;
- resize/orientation changes preserve draft/editor state;
- existing Package 6 lazy cache/navigation tests remain green.

Prefer framework-neutral tests for API parsing/reconciliation/draft behavior plus focused Phaser interaction/reflow tests.

#### Real-Stack Verification
Where practical, use the controlled Package 3 fixture against real PHP/MySQL and exercise:

Warband -> edit squad -> save -> activate another squad -> create/delete a squad -> return to Warband

Confirm authoritative results are reflected without bootstrap/profile refetch.

This is useful verification but do not block completion solely on an unavailable optional local environment if the required repository quality gates and deterministic browser tests pass.

#### Explicitly Out of Scope
- Unit-detail fetching/navigation, rename UI, ability-order editing, or dice-binding UI — Package 8.
- New backend squad rules unless a real Package 4 contract defect is found.
- Promotion/progression.
- Production onboarding.
- Shop, Academy, Wrong Machine, Regions, Codex, Objectives.
- RunScene/BattleScene functionality.
- Milestone 3.
- Final visual/UI overhaul.

#### Completion
Run applicable quality gates from `agent/QUALITY_GATES.md`, including focused API/parser/store/editor tests, existing Package 6 navigation/cache regressions, full frontend suite, production frontend build/bundle check, and Compact/Standard/Wide deterministic editor captures with visual inspection.

Run backend tests only if backend code changes because this package should primarily consume already-approved Package 4 contracts.

Leave Package 7 **In Progress** for architectural review. Do not mark it complete, promote Package 8, or begin unit-detail/loadout UI in the same coding-agent change.
