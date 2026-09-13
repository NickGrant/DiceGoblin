# Active Execution Issue

`ISSUES.md` contains only the current execution-ready package. Future packages stay summarized in `agent/MILESTONES.md` and the roadmap until promoted. Do not implement them early.

## Milestone 2 - Warband

### Establish Phaser unit detail, rename, loadout, and dice-binding flows

**Status:** Open
**Priority:** High

#### Problem
Packages 1-7 now provide authoritative owned unit/dice persistence and content, complete unit-detail/read APIs, atomic rename/loadout commands, lazy Warband collections, and a functional Phaser squad editor. Players can manage squads but cannot yet open an individual goblin and configure the durable per-unit combat setup. Implement the Phaser-owned unit detail/configuration flow that consumes the already-approved Package 3/5 contracts without restoring Angular Warband pages, per-slot mutations, or profile refresh behavior.

#### Required Context
- `documentation/07-development-path/vnext-phaser-client-architecture.md` — persistent GameScene screen model, lazy authoritative cache, responsive/orientation rules
- `documentation/07-development-path/vnext-api-contract-model.md` — query/mutation authority and `player_revision`
- `documentation/07-development-path/vnext-endpoint-inventory.md` — unit detail, rename, and complete loadout endpoints
- `documentation/02-systems/ability-loadouts-and-dice-binding.md` — per-instance owned abilities, ordered active loadout, exact physical die bindings
- `documentation/02-systems/dice-profiles-and-aspects.md` — profile/material/aspect presentation and size eligibility
- `documentation/02-systems/unit-stat-advancement.md` — level/XP and the fact that final resolved stat formulas are not yet canonical
- approved Package 3 unit-detail and dice collection contracts
- approved Package 5 rename/loadout command semantics
- approved Package 6 GameStore lazy Warband domains/navigation
- approved Package 7 local-draft, native-input, mutation-reconciliation, and GameScene editor patterns

Inspect current `UnitDetailQuery`, `RenameUnitCommand`, `ReplaceUnitLoadoutCommand`, `RuntimeApiClient`, `GameStore`, `WarbandScreen`, and squad-editor patterns before implementation.

Do not restore prototype Angular unit/loadout ownership or prototype per-slot APIs.

#### Architectural Boundary
- Unit detail/configuration is gameplay and remains inside Phaser under the persistent `GameScene`.
- Do not create another Phaser Scene or Angular route/component.
- Extend the concrete GameScene screen/navigation model only for the unit detail/configuration flow.
- Camp, Warband, squad editor, and unit configuration share the existing runtime, startup, API client, GameStore, content registry, canvas, and viewport/orientation infrastructure.
- Do not refetch bootstrap or `game-content.json` to open or save a unit.
- Do not use `/profile`.
- Do not implement promotion/progression transactions in this package.

#### Player Flow
From the Warband Unit Roster, the player must be able to:
- select/open an owned unit;
- view its authoritative individual detail;
- understand its unit type, kin, level/XP, durable owned abilities, equipped active ability order, and exact dice assignments;
- rename it;
- build a local draft of the complete active ability loadout;
- add/remove owned active abilities from the draft subject to the backend contract;
- reorder equipped active abilities;
- assign an exact owned physical die to every required ability slot;
- move a die between slots on the same unit without creating duplicates;
- save the complete loadout atomically;
- return to Warband with authoritative updated state immediately reflected.

Use one coherent unit-detail/configuration screen or a small set of subsurfaces inside the same GameScene screen model. Do not create separate Phaser Scenes for rename, abilities, and dice.

#### Lazy Unit Detail Cache
Add a narrow per-unit detail cache to GameStore.

Requirements:
- unit collection summaries remain the Package 6 roster authority;
- full detail is fetched only when a unit is opened or deliberately refreshed;
- detail cache is keyed by unit instance ID;
- distinguish not-loaded/loading/fresh/stale/error semantics consistent with the existing Warband cache style;
- duplicate concurrent detail requests for the same unit are deduplicated;
- a failure for one unit does not erase the roster, dice, squads, or another valid detail entry;
- `GameStore.clear()` clears unit-detail caches;
- returning to a unit whose detail remains fresh uses cache rather than refetching;
- do not eagerly fetch every unit detail when Warband opens.

Validate a loaded detail against the already-known unit summary when available. Identity/type/kin/level/XP/lifecycle disagreement at the same known client state is an integrity/stale condition, not something to silently merge.

#### Runtime API Client
Extend the framework-neutral runtime API client for:
- `GET /api/v1/units/:unitId`
- `PATCH /api/v1/units/:unitId/name`
- `PUT /api/v1/units/:unitId/loadout`

Mutations use:
- `credentials: include`;
- authoritative bootstrap CSRF token;
- JSON body;
- existing safe error parsing.

Do not add per-ability, reorder, per-die assign, or clear endpoints.

If the current mutation helper is squad-specific, generalize only enough to support these concrete unit commands without weakening existing squad behavior or creating a speculative command framework.

#### Strict Unit Detail Contract
Parse the existing authoritative unit detail exactly rather than casting `unknown`.

Current detail contains:
- `id`
- `display_name`
- `unit_type_id`
- `kin_id`
- `level`
- `xp`
- active lifecycle state
- `promotion_history`
- `owned_ability_ids`
- `ability_loadout`
- `dice_bindings`

Validate:
- canonical positive unit/die IDs;
- bounded nonblank display name;
- projected unit type and kin references;
- valid promotion-history authored type references;
- unique durable owned ability IDs that exist in projected content;
- loadout abilities are unique, active, durably owned, and have contiguous `equip_order` starting at zero;
- every dice binding points to an equipped active ability and a valid slot;
- each required slot for every equipped ability has exactly one binding;
- no physical die appears twice in one detail;
- every bound die exists in the currently fresh owned-dice collection, belongs to the player by virtue of that collection, has a profile/size compatible with current client content, and its dice-summary binding agrees with the unit detail;
- a die summary bound to this unit must agree with the detail's exact ability/slot assignment.

Do not invent placeholders for malformed/missing authored content.

If the dice collection is required for complete detail integrity, ensure it is fresh before the editor becomes ready. Do not fetch bootstrap/profile.

#### Unit Detail Presentation
Present player-readable authored information rather than raw stable IDs.

At minimum show:
- unit display name;
- authored unit type name;
- authored kin name;
- level and XP;
- owned abilities, distinguishing active vs passive;
- currently equipped ordered active abilities;
- exact die assigned to every equipped ability slot;
- useful die presentation from profile/material/aspects/rarity/size.

Promotion history may be shown compactly if it improves comprehension, but do not create promotion controls.

Do **not** invent resolved combat-stat formulas. The canonical stat-advancement document explicitly defers exact resolved stat calculation until progression is reconciled. Authored base/growth information may only be presented if clearly labeled as authored/base information and not represented as a final current combat value. Omitting unresolved calculated stats is preferable to inventing them.

#### Rename Draft and Native Input
Rename is local draft state until the rename command succeeds.

Use the Package 7 native-input lessons:
- scope any HTML input to the game host;
- support keyboard and touch virtual keyboard;
- preserve draft through resize/orientation;
- block/blur native input while a command, confirmation, portrait gate, or integrity-blocking state owns interaction;
- remove the input when the screen is destroyed.

Backend contract is normalized nonblank Unicode name up to 128 characters.

A same-name rename is a legitimate authoritative no-op and may return the same revision.

On rename failure, preserve the local name draft and committed cache.

#### Loadout Draft
The active loadout editor uses an independent local draft until PHP accepts the complete configuration.

The submitted body is exactly:

`{ abilities: [{ ability_id, dice_instance_ids }, ...] }`

Array order is authoritative ability/equip order.

Array order inside `dice_instance_ids` is authoritative slot order.

Rules:
- loadout is a non-empty ordered list;
- only durable per-instance `owned_ability_ids` are candidates;
- only authored `kind: active` abilities may be equipped;
- passive owned abilities are visible/readable but never placed in the scheduled active loadout;
- an active ability appears at most once;
- each equipped ability has exactly its authored `dice_slot_count` physical dice;
- the same physical die appears at most once in the entire draft;
- moving a die within this unit removes its prior draft occurrence;
- a die currently bound to another unit is unavailable and clearly identified, not silently stolen;
- a die currently bound to this unit may be moved because the eventual save is one atomic whole-unit replacement;
- only active owned dice with client-valid profile/size may be selected.

Do not restore the rejected Speed/equipment-budget mechanic.

Do not infer individual ability ownership solely from the unit type's authored `ability_ids`.

#### Touch-Friendly Editing
Use straightforward explicit controls rather than requiring drag-and-drop.

A suitable interaction may include:
- available active ability list;
- equipped ordered ability list;
- add/remove controls;
- move up/down controls;
- select an ability slot then select an available die.

Exact visual composition is implementation-level, but all operations must work on Compact touch landscape as well as desktop.

Do not add dead promotion controls.

#### Command Separation
Rename and loadout are two existing authoritative commands and should remain independent.

Do not fabricate a combined `save unit` endpoint.

UI may provide separate rename and loadout save actions or another clear interaction that still issues the correct independent commands.

Do not optimistically mutate GameStore before either response succeeds.

#### Mutation Response Contract
Both Package 5 mutation endpoints return:
- full authoritative `unit` detail;
- current `player_revision`.

Add strict parsing using the same unit-detail parser/invariants.

Malformed success responses are integrity failures and must not modify committed cache.

Do not increment revision locally.

Reject impossible revision regression.

Same-name rename and identical complete loadout may legitimately return the existing revision.

#### Rename Reconciliation
After an authoritative rename response:
- replace the per-unit cached detail;
- update that unit's compact entry in the fresh unit-summary domain;
- if the unit appears in cached bootstrap `active_squad.units`, update that compact copied display name there as well;
- adopt the returned `player_revision`;
- preserve dice and squads caches;
- preserve static authored content.

Do not refetch bootstrap/profile.

If the response cannot be reconciled safely against current state, mark the affected domain/detail stale/error and deliberately recover instead of inventing state.

#### Loadout Reconciliation
After an authoritative loadout response:
- replace the unit's cached detail;
- adopt returned `player_revision`;
- rebuild this unit's die binding summaries in the fresh dice domain from the authoritative returned exact bindings;
- clear prior dice-summary bindings for this unit that are no longer present;
- preserve bindings belonging to other units;
- reject reconciliation if the response references a die absent from the fresh owned-dice cache or creates an impossible conflict with another unit's binding;
- keep the dice domain fresh only when complete reconciliation succeeds;
- preserve unit summary identity, squads, and bootstrap active formation except for revision.

Do not refetch the entire dice collection solely because a normal accepted loadout changed.

If safe local reconciliation is impossible, mark the affected cache state stale/error and provide deliberate recovery.

#### Navigation and Dirty State
Warband Unit Roster rows become the concrete entry point to unit detail/configuration.

Expected flow:

Camp -> Warband -> Unit Detail/Configuration -> Warband

Requirements:
- same persistent GameScene/runtime;
- no bootstrap/content refetch;
- Back/Escape from a clean unit screen returns normally;
- if rename and/or loadout draft differs from committed state, Back/Cancel requires explicit discard confirmation;
- command-in-flight input is deduplicated/blocked;
- successful rename may keep the screen open so the player can continue configuration;
- successful loadout should present the authoritative committed state immediately.

Do not route through Angular.

#### Failure States
Provide functional handling for:
- detail loading;
- detail not-found/unavailable;
- detail integrity failure;
- rename validation failure;
- rename network/HTTP/malformed response failure;
- loadout locally incomplete/invalid state;
- backend configuration rejection;
- another-unit die conflict returned by backend;
- unauthorized session;
- mutation reconciliation failure.

Failures must preserve committed cache. Mutation failures preserve the applicable local draft so the player can revise/retry.

Do not display raw server exception text.

#### Responsive/Orientation Behavior
Unit detail/configuration must remain usable at:
- Compact `844 x 390` touch/mobile;
- Standard `1600 x 900`;
- Wide `2560 x 1080`.

Long ability/dice collections require Phaser-owned paging/scrolling/section switching as needed.

Important controls and every active dice slot must remain reachable.

Resize/reflow and touch-first portrait gating must preserve:
- loaded unit detail;
- rename draft;
- loadout draft;
- current ability/die selection;
- dirty state;
- current logical unit screen.

Portrait gate must block native text entry and Phaser input without destroying draft/cache; landscape restoration resumes the same state.

#### Visual Posture
Do not perform the deferred game-wide visual overhaul.

Prioritize:
- clear distinction among unit identity, owned abilities, equipped actions, and dice slots;
- obvious draft/dirty state;
- clear unavailable-die explanation;
- touch-safe controls;
- responsive safety;
- consistency with current Warband/squad editor.

#### Deterministic Capture
Extend deterministic capture/debug support with a representative populated unit-configuration state.

Generate and visually inspect:
- Compact unit configuration;
- Standard unit configuration;
- Wide unit configuration.

Fixture data must conform to the real Package 3/5 contracts and current generated content.

Do not create a second gameplay model.

#### Tests
Add focused frontend tests proving at minimum:
- unit detail is lazy and not fetched during Warband bootstrap/collection loading;
- first unit open fetches detail; fresh reopen uses cache;
- concurrent detail requests deduplicate;
- detail parser rejects malformed IDs, unknown content, unowned/passive loadout entries, non-contiguous order, incomplete/duplicate slots, duplicate dice, dice-summary disagreement, and invalid bound dice;
- opening a unit does not mutate committed data;
- rename edits remain local until success;
- rename uses PATCH + credentials + CSRF and strict response parsing;
- rename no-op revision is accepted;
- rename reconciliation updates detail, roster summary, bootstrap active-squad copy when applicable, and revision without disturbing dice/squads;
- loadout draft add/remove/reorder behavior is deterministic;
- passive abilities cannot be equipped;
- duplicate abilities/dice cannot exist in the draft;
- exact authored slot counts are required before save;
- die currently bound to another unit is unavailable;
- same-unit die movement is allowed in draft;
- complete PUT body preserves ability and slot order;
- no optimistic committed-state mutation occurs while PUT is pending;
- failed loadout preserves committed state and draft;
- successful loadout reconciliation replaces detail and exact dice binding summaries and adopts server revision;
- impossible reconciliation marks state stale/error rather than fabricating state;
- dirty Back requires discard confirmation;
- native rename input is gated during command/confirmation/portrait state;
- Warband -> unit screen -> Warband stays in one GameScene/runtime with no bootstrap/content/profile refetch;
- responsive/orientation changes preserve detail/drafts/selections;
- Package 6/7 navigation, lazy cache, and squad editor regressions remain green.

Prefer framework-neutral parser/cache/draft tests plus focused Phaser screen/navigation/reflow tests.

#### Real-Stack Verification
Where practical, use the controlled Warband fixture against real PHP/MySQL and exercise:

Warband -> unit detail -> rename -> reorder/configure active abilities -> move/assign exact dice -> save -> return Warband

Confirm authoritative unit/dice state is reflected without bootstrap/profile refresh.

This is useful verification but should not block completion solely on unavailable optional local infrastructure if required repository quality gates and deterministic browser tests pass.

#### Explicitly Out of Scope
- Promotion options/transactions and Academy progression — Milestone 8.
- Resolved current-stat formula invention.
- New backend unit/configuration rules unless a genuine Package 3/5 contract defect is discovered.
- Production starter onboarding.
- Shop, Academy, Wrong Machine, Regions, Codex, Objectives.
- RunScene/BattleScene functionality.
- Milestone 3.
- Final visual/UI overhaul.

#### Completion
Run applicable quality gates from `agent/QUALITY_GATES.md`, including focused unit detail/API/parser/cache/draft/editor tests, Package 6/7 frontend regressions, full frontend suite, production frontend build/bundle check, and Compact/Standard/Wide deterministic unit-editor captures with visual inspection.

Run backend/content tests only if backend/content files unexpectedly change.

Leave Package 8 **In Progress** for architectural review. Do not mark it complete, promote Package 9, or begin closure cleanup in the same coding-agent change.
